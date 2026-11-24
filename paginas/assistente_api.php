
<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

function responder($sucesso, $mensagem, $status = 200)
{
    http_response_code($status);

    echo json_encode([
        "sucesso" => $sucesso,
        "mensagem" => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Verifica se o usuário está conectado.
if (!isset($_SESSION["usuario_id"])) {
    responder(false, "Você precisa entrar na sua conta.", 401);
}

// Aceita somente POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método não permitido. Utilize a página do assistente.", 405);
}

// Carrega o sistema de logs.
$arquivo_log = __DIR__ . "/../logs/registro.php";

if (file_exists($arquivo_log)) {
    require_once $arquivo_log;
}

function registrarAcao($acao, $detalhes = "")
{
    if (function_exists("registrarLog")) {
        registrarLog($acao, $detalhes);
    }
}

// Lê a pergunta enviada pelo JavaScript.
$dados = json_decode(file_get_contents("php://input"), true);

if (!is_array($dados)) {
    responder(false, "Não foi possível interpretar a pergunta.", 400);
}

$pergunta = trim($dados["pergunta"] ?? "");

if ($pergunta === "") {
    responder(false, "Digite uma pergunta.", 400);
}

if (mb_strlen($pergunta, "UTF-8") > 3000) {
    responder(false, "A pergunta deve ter no máximo 3000 caracteres.", 400);
}

// Carrega a chave do Gemini.
$arquivo_segredos = __DIR__ . "/../configuracoes/segredos.php";

if (!file_exists($arquivo_segredos)) {
    responder(false, "Arquivo de configuração da IA não encontrado.", 500);
}

$segredos = require $arquivo_segredos;
$chave = $segredos["gemini_api_key"] ?? "";

if (empty($chave)) {
    responder(false, "A chave do Gemini não foi configurada.", 500);
}

if (!function_exists("curl_init")) {
    responder(false, "A extensão cURL do PHP não está habilitada.", 500);
}

// Instruções do studIA.
$instrucoes = "Você é o studIA, assistente educacional da plataforma Studia.

Ajude estudantes a compreender conteúdos, criar resumos, organizar estudos,
elaborar planos de aprendizagem e esclarecer dúvidas.

Responda sempre em português do Brasil, com linguagem clara, didática,
acolhedora e organizada.

Utilize títulos, listas e negrito quando ajudarem na compreensão.
Explique assuntos difíceis com exemplos simples.
Não invente fontes ou informações.
Se não souber algo, informe com honestidade.

Pergunta do estudante:

" . $pergunta;

// Monta a solicitação para o Gemini.
$corpo = [
    "contents" => [
        [
            "parts" => [
                [
                    "text" => $instrucoes
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 1200
    ]
];

// Função responsável pela comunicação com o Gemini.
function consultarGemini($modelo, $chave, $corpo)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/"
        . $modelo . ":generateContent";

    $ultimo_status = 0;
    $ultima_resposta = "";

    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "x-goog-api-key: " . $chave
            ],
            CURLOPT_POSTFIELDS => json_encode($corpo),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60
        ]);

        $resposta = curl_exec($curl);
        $erro = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($resposta === false) {
            $ultima_resposta = $erro;
            $ultimo_status = 0;
        } else {
            $ultima_resposta = $resposta;
            $ultimo_status = $status;

            if ($status >= 200 && $status < 300) {
                return [
                    "sucesso" => true,
                    "dados" => json_decode($resposta, true),
                    "status" => $status
                ];
            }
        }

        // Repete somente quando o erro pode ser temporário.
        if (!in_array($ultimo_status, [0, 429, 500, 502, 503, 504])) {
            break;
        }

        if ($tentativa < 2) {
            sleep(2);
        }
    }

    return [
        "sucesso" => false,
        "erro" => $ultima_resposta,
        "status" => $ultimo_status
    ];
}

// Primeira tentativa: modelo principal.
$resultado = consultarGemini(
    "gemini-3.5-flash-lite",
    $chave,
    $corpo
);

// Segunda opção: modelo alternativo.
if (
    !$resultado["sucesso"] &&
    in_array($resultado["status"], [0, 429, 500, 502, 503, 504])
) {

    registrarAcao(
        "LLM_FALLBACK",
        "Tentativa de utilização do modelo alternativo."
    );

    $resultado = consultarGemini(
        "gemini-3.8-flash",
        $chave,
        $corpo
    );
}

// Trata falhas da API.
if (!$resultado["sucesso"]) {

    registrarAcao(
        "LLM_ERRO",
        "Erro na API Gemini. HTTP: " . $resultado["status"]
    );

    if (in_array($resultado["status"], [401, 403])) {
        responder(
            false,
            "Não foi possível autenticar o acesso à inteligência artificial. Verifique a chave e as permissões.",
            502
        );
    }

    if ($resultado["status"] === 429) {
        responder(
            false,
            "O Gemini está recebendo muitas solicitações. Aguarde um pouco e tente novamente.",
            503
        );
    }

    responder(
        false,
        "O studIA está temporariamente indisponível. Tente novamente em alguns instantes.",
        503
    );
}

// Extrai o texto gerado.
$partes = $resultado["dados"]["candidates"][0]["content"]["parts"] ?? [];

$texto = "";

foreach ($partes as $parte) {
    if (isset($parte["text"])) {
        $texto .= $parte["text"];
    }
}

$texto = trim($texto);

if ($texto === "") {
    registrarAcao("LLM_ERRO", "Resposta vazia recebida do Gemini.");

    responder(
        false,
        "O assistente não conseguiu preparar uma resposta. Tente novamente.",
        502
    );
}

// Registra o sucesso.
registrarAcao(
    "LLM_SUCESSO",
    "Usuário utilizou o assistente studIA."
);

// Devolve a resposta para a página.
responder(true, $texto);
