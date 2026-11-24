
<?php

// ==================================================
// SISTEMA DE REGISTRO DE LOGS - STUDIA
// ==================================================

function registrarLog($acao, $detalhes = "")
{
    // Identificação do usuário
    $usuario_id = $_SESSION["usuario_id"] ?? "Não identificado";

    // Data e horário
    $data = date("Y-m-d H:i:s");

    // Página acessada, sem parâmetros sensíveis da URL
    $pagina = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);

    // Endereço IP
    $ip = $_SERVER["REMOTE_ADDR"] ?? "Desconhecido";

    // Informações do registro
    $registro = [
        "data" => $data,
        "usuario_id" => $usuario_id,
        "acao" => $acao,
        "detalhes" => $detalhes,
        "pagina" => $pagina,
        "ip" => $ip
    ];

    // Arquivo onde os registros serão armazenados
    $arquivo = __DIR__ . "/sistema.log";

    // Salva um registro por linha
    file_put_contents(
        $arquivo,
        json_encode($registro, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
