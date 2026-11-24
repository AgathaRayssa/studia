
<?php

session_start();

// ==================================================
// VERIFICAÇÃO DE LOGIN
// ==================================================

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../configuracoes/conexao.php";
require_once __DIR__ . "/../logs/registro.php";

$usuario_id = (int) $_SESSION["usuario_id"];

// ==================================================
// DADOS DO PERFIL
// ==================================================

$consulta = $conexao->prepare(
    "SELECT nome, foto FROM usuarios WHERE id = :id LIMIT 1"
);

$consulta->execute(["id" => $usuario_id]);
$perfil = $consulta->fetch(PDO::FETCH_ASSOC);

$nome_usuario = $perfil["nome"] ?? "Estudante";
$foto_usuario = $perfil["foto"] ?? "";

$nome_inicial = mb_strtoupper(
    mb_substr($nome_usuario, 0, 1, "UTF-8"),
    "UTF-8"
);

// ==================================================
// MENSAGENS E TOKEN CSRF
// ==================================================

$mensagem = $_SESSION["mensagem"] ?? "";
$tipo_mensagem = $_SESSION["tipo_mensagem"] ?? "";

unset($_SESSION["mensagem"], $_SESSION["tipo_mensagem"]);

if (!isset($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["token"];

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function redirecionarComMensagem($mensagem, $tipo = "sucesso")
{
    $_SESSION["mensagem"] = $mensagem;
    $_SESSION["tipo_mensagem"] = $tipo;

    header("Location: materiais.php");
    exit;
}

/**
 * Confere se todas as tarefas selecionadas pertencem ao usuário.
 */
function tarefasDoUsuario($conexao, $usuario_id, $ids)
{
    if (!is_array($ids)) {
        return false;
    }

    $ids_validos = [];

    foreach ($ids as $id) {
        $id_validado = filter_var($id, FILTER_VALIDATE_INT);

        if ($id_validado === false || $id_validado <= 0) {
            return false;
        }

        $ids_validos[] = (int) $id_validado;
    }

    $ids_validos = array_values(array_unique($ids_validos));

    if (empty($ids_validos)) {
        return [];
    }

    $marcadores = implode(",", array_fill(0, count($ids_validos), "?"));

    $sql = "
        SELECT t.id
        FROM tarefas t
        INNER JOIN disciplinas d ON d.id = t.disciplina_id
        WHERE d.usuario_id = ?
        AND t.id IN ($marcadores)
    ";

    $consulta = $conexao->prepare($sql);
    $consulta->execute(array_merge([$usuario_id], $ids_validos));

    $tarefas_encontradas = array_map(
        "intval",
        $consulta->fetchAll(PDO::FETCH_COLUMN)
    );

    sort($tarefas_encontradas);

    $ids_validos_ordenados = $ids_validos;
    sort($ids_validos_ordenados);

    if ($tarefas_encontradas !== $ids_validos_ordenados) {
        return false;
    }

    return $tarefas_encontradas;
}

/**
 * Salva as associações entre materiais e tarefas.
 * A coluna utilizada é tarefa_id.
 */
function salvarAssociacoes($conexao, $material_id, $tarefas)
{
    if (empty($tarefas)) {
        return;
    }

    $inserir = $conexao->prepare(
        "INSERT INTO tarefas_materiais (tarefa_id, material_id)
         VALUES (:tarefa_id, :material_id)"
    );

    foreach ($tarefas as $tarefa_id) {
        $inserir->execute([
            ":tarefa_id" => $tarefa_id,
            ":material_id" => $material_id
        ]);
    }
}

/**
 * Remove somente arquivos enviados para a pasta de materiais.
 */
function removerArquivoMaterial($caminho)
{
    if (
        !$caminho ||
        strpos($caminho, "../envios/materiais/") !== 0
    ) {
        return;
    }

    $nome = basename($caminho);
    $arquivo = __DIR__ . "/../envios/materiais/" . $nome;

    if (is_file($arquivo)) {
        unlink($arquivo);
    }
}

// ==================================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($token, $_POST["token"] ?? "")) {
        registrarLog(
            "FALHA_SEGURANCA",
            "Token CSRF inválido na página de materiais."
        );

        redirecionarComMensagem(
            "Sua sessão expirou ou é inválida. Atualize a página e tente novamente.",
            "erro"
        );
    }

    $acao = $_POST["acao"] ?? "";
    $novo_arquivo_enviado = null;

    try {

        // ==========================================
        // CADASTRAR OU EDITAR MATERIAL
        // ==========================================

        if ($acao === "cadastrar" || $acao === "editar") {

            $titulo = trim($_POST["titulo"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $tipo = $_POST["tipo"] ?? "outro";
            $endereco = trim($_POST["endereco"] ?? "");
            $tarefas_selecionadas = $_POST["tarefas"] ?? [];

            $tipos_permitidos = [
                "pdf",
                "link",
                "resumo",
                "anotacao",
                "outro"
            ];

            if ($titulo === "") {
                redirecionarComMensagem(
                    "Informe o título do material.",
                    "erro"
                );
            }

            if (mb_strlen($titulo, "UTF-8") > 150) {
                redirecionarComMensagem(
                    "O título deve ter no máximo 150 caracteres.",
                    "erro"
                );
            }

            if (!in_array($tipo, $tipos_permitidos, true)) {
                redirecionarComMensagem(
                    "Tipo de material inválido.",
                    "erro"
                );
            }

            if (!is_array($tarefas_selecionadas)) {
                redirecionarComMensagem(
                    "A seleção de tarefas é inválida.",
                    "erro"
                );
            }

            $tarefas_validas = tarefasDoUsuario(
                $conexao,
                $usuario_id,
                $tarefas_selecionadas
            );

            if ($tarefas_validas === false) {
                redirecionarComMensagem(
                    "Uma ou mais tarefas selecionadas são inválidas.",
                    "erro"
                );
            }

            $material_id = 0;
            $material_antigo = null;
            $arquivo_antigo = null;
            $arquivo = null;

            // ======================================
            // BUSCAR MATERIAL EXISTENTE NA EDIÇÃO
            // ======================================

            if ($acao === "editar") {

                $material_id = filter_var(
                    $_POST["material_id"] ?? null,
                    FILTER_VALIDATE_INT
                );

                if ($material_id === false || $material_id <= 0) {
                    redirecionarComMensagem(
                        "Material inválido.",
                        "erro"
                    );
                }

                $consulta = $conexao->prepare(
                    "SELECT titulo, tipo, arquivo
                     FROM materiais
                     WHERE id = :id
                     AND usuario_id = :usuario_id
                     LIMIT 1"
                );

                $consulta->execute([
                    ":id" => $material_id,
                    ":usuario_id" => $usuario_id
                ]);

                $material_antigo = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$material_antigo) {
                    registrarLog(
                        "FALHA_EDITAR_MATERIAL",
                        "Material ID: " . $material_id . " não encontrado."
                    );

                    redirecionarComMensagem(
                        "Material não encontrado.",
                        "erro"
                    );
                }

                $arquivo_antigo = $material_antigo["arquivo"];

                // Mantém o PDF antigo somente se continuar sendo PDF.
                if (
                    $tipo === "pdf" &&
                    $material_antigo["tipo"] === "pdf"
                ) {
                    $arquivo = $arquivo_antigo;
                }
            }

            // ======================================
            // VALIDAR LINK
            // ======================================

            if ($tipo === "link") {

                $esquema = strtolower(
                    parse_url($endereco, PHP_URL_SCHEME) ?? ""
                );

                if (
                    !filter_var($endereco, FILTER_VALIDATE_URL) ||
                    !in_array($esquema, ["http", "https"], true)
                ) {
                    redirecionarComMensagem(
                        "Informe um endereço válido, iniciado por http:// ou https://.",
                        "erro"
                    );
                }

                $arquivo = $endereco;
            }

            // ======================================
            // ENVIO DE PDF
            // ======================================

            if (
                $tipo === "pdf" &&
                isset($_FILES["arquivo"]) &&
                $_FILES["arquivo"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {

                $upload = $_FILES["arquivo"];

                if ($upload["error"] !== UPLOAD_ERR_OK) {
                    redirecionarComMensagem(
                        "Não foi possível enviar o arquivo.",
                        "erro"
                    );
                }

                if ($upload["size"] > 5 * 1024 * 1024) {
                    redirecionarComMensagem(
                        "O PDF deve ter no máximo 5 MB.",
                        "erro"
                    );
                }

                $extensao = strtolower(
                    pathinfo($upload["name"], PATHINFO_EXTENSION)
                );

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($upload["tmp_name"]);

                if (
                    $extensao !== "pdf" ||
                    $mime !== "application/pdf"
                ) {
                    redirecionarComMensagem(
                        "Envie somente arquivos PDF válidos.",
                        "erro"
                    );
                }

                $pasta = __DIR__ . "/../envios/materiais/";

                if (!is_dir($pasta)) {
                    if (!mkdir($pasta, 0755, true) && !is_dir($pasta)) {
                        redirecionarComMensagem(
                            "Não foi possível preparar a pasta de arquivos.",
                            "erro"
                        );
                    }
                }

                $nome_arquivo = bin2hex(random_bytes(16)) . ".pdf";
                $destino = $pasta . $nome_arquivo;

                if (!move_uploaded_file($upload["tmp_name"], $destino)) {
                    redirecionarComMensagem(
                        "Erro ao salvar o PDF.",
                        "erro"
                    );
                }

                $arquivo = "../envios/materiais/" . $nome_arquivo;
                $novo_arquivo_enviado = $arquivo;
            }

            // Outros tipos não utilizam arquivo.
            if ($tipo !== "pdf" && $tipo !== "link") {
                $arquivo = null;
            }

            // ======================================
            // ATUALIZAR MATERIAL
            // ======================================

            if ($acao === "editar") {

                $conexao->beginTransaction();

                $atualizar = $conexao->prepare(
                    "UPDATE materiais
                     SET titulo = :titulo,
                         descricao = :descricao,
                         tipo = :tipo,
                         arquivo = :arquivo
                     WHERE id = :id
                     AND usuario_id = :usuario_id"
                );

                $atualizar->execute([
                    ":titulo" => $titulo,
                    ":descricao" => $descricao,
                    ":tipo" => $tipo,
                    ":arquivo" => $arquivo,
                    ":id" => $material_id,
                    ":usuario_id" => $usuario_id
                ]);

                $excluir_associacoes = $conexao->prepare(
                    "DELETE FROM tarefas_materiais
                     WHERE material_id = :material_id"
                );

                $excluir_associacoes->execute([
                    ":material_id" => $material_id
                ]);

                salvarAssociacoes(
                    $conexao,
                    $material_id,
                    $tarefas_validas
                );

                $conexao->commit();

                if (
                    $arquivo_antigo &&
                    $arquivo_antigo !== $arquivo
                ) {
                    removerArquivoMaterial($arquivo_antigo);
                }

                $novo_arquivo_enviado = null;

                registrarLog(
                    "MATERIAL_EDITADO",
                    "Material ID: " . $material_id .
                    " | Título anterior: " . $material_antigo["titulo"] .
                    " | Novo título: " . $titulo
                );

                redirecionarComMensagem(
                    "Material atualizado com sucesso!"
                );
            }

            // ======================================
            // CADASTRAR NOVO MATERIAL
            // ======================================

            if ($acao === "cadastrar") {

                $conexao->beginTransaction();

                $inserir = $conexao->prepare(
                    "INSERT INTO materiais
                     (usuario_id, titulo, descricao, tipo, arquivo)
                     VALUES
                     (:usuario_id, :titulo, :descricao, :tipo, :arquivo)"
                );

                $inserir->execute([
                    ":usuario_id" => $usuario_id,
                    ":titulo" => $titulo,
                    ":descricao" => $descricao,
                    ":tipo" => $tipo,
                    ":arquivo" => $arquivo
                ]);

                $novo_material_id = (int) $conexao->lastInsertId();

                salvarAssociacoes(
                    $conexao,
                    $novo_material_id,
                    $tarefas_validas
                );

                $conexao->commit();

                $novo_arquivo_enviado = null;

                registrarLog(
                    "MATERIAL_CADASTRADO",
                    "Material ID: " . $novo_material_id .
                    " | Título: " . $titulo .
                    " | Tipo: " . $tipo
                );

                redirecionarComMensagem(
                    "Material cadastrado com sucesso!"
                );
            }
        }

        // ==========================================
        // EXCLUIR MATERIAL
        // ==========================================

        elseif ($acao === "excluir") {

            $material_id = filter_var(
                $_POST["material_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($material_id === false || $material_id <= 0) {
                redirecionarComMensagem(
                    "Material inválido.",
                    "erro"
                );
            }

            $consulta = $conexao->prepare(
                "SELECT titulo, arquivo
                 FROM materiais
                 WHERE id = :id
                 AND usuario_id = :usuario_id
                 LIMIT 1"
            );

            $consulta->execute([
                ":id" => $material_id,
                ":usuario_id" => $usuario_id
            ]);

            $material = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                registrarLog(
                    "FALHA_EXCLUIR_MATERIAL",
                    "Material ID: " . $material_id . " não encontrado."
                );

                redirecionarComMensagem(
                    "Material não encontrado.",
                    "erro"
                );
            }

            $conexao->beginTransaction();

            $excluir_associacoes = $conexao->prepare(
                "DELETE FROM tarefas_materiais
                 WHERE material_id = :material_id"
            );

            $excluir_associacoes->execute([
                ":material_id" => $material_id
            ]);

            $excluir = $conexao->prepare(
                "DELETE FROM materiais
                 WHERE id = :id
                 AND usuario_id = :usuario_id"
            );

            $excluir->execute([
                ":id" => $material_id,
                ":usuario_id" => $usuario_id
            ]);

            $conexao->commit();

            removerArquivoMaterial($material["arquivo"]);

            registrarLog(
                "MATERIAL_EXCLUIDO",
                "Material ID: " . $material_id .
                " | Título: " . $material["titulo"]
            );

            redirecionarComMensagem(
                "Material excluído com sucesso!"
            );
        }

        else {
            redirecionarComMensagem(
                "Ação inválida.",
                "erro"
            );
        }

    } catch (Throwable $erro) {

        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        if ($novo_arquivo_enviado) {
            removerArquivoMaterial($novo_arquivo_enviado);
        }

        registrarLog(
            "ERRO_PROCESSAR_MATERIAL",
            "Ação: " . $acao .
            " | Erro: " . get_class($erro) .
            " | Mensagem: " . $erro->getMessage()
        );

        redirecionarComMensagem(
            "Ocorreu um erro ao processar o material. Verifique os dados e tente novamente.",
            "erro"
        );
    }
}

// ==================================================
// CONSULTAR TAREFAS DO USUÁRIO
// ==================================================

$consulta_tarefas = $conexao->prepare(
    "SELECT
        t.id,
        t.titulo,
        d.nome AS disciplina
     FROM tarefas t
     INNER JOIN disciplinas d ON d.id = t.disciplina_id
     WHERE d.usuario_id = :usuario_id
     ORDER BY t.data_entrega ASC, t.titulo ASC"
);

$consulta_tarefas->execute([
    ":usuario_id" => $usuario_id
]);

$tarefas_disponiveis = $consulta_tarefas->fetchAll(PDO::FETCH_ASSOC);

// ==================================================
// CONSULTAR MATERIAIS CADASTRADOS
// ==================================================

$consulta_materiais = $conexao->prepare(
    "SELECT
        m.*,
        GROUP_CONCAT(
            DISTINCT t.titulo
            ORDER BY t.titulo
            SEPARATOR ', '
        ) AS tarefas_relacionadas
     FROM materiais m
     LEFT JOIN tarefas_materiais tm
        ON tm.material_id = m.id
     LEFT JOIN tarefas t
        ON t.id = tm.tarefa_id
     LEFT JOIN disciplinas d
        ON d.id = t.disciplina_id
        AND d.usuario_id = m.usuario_id
     WHERE m.usuario_id = :usuario_id
     GROUP BY m.id
     ORDER BY m.data_criacao DESC"
);

$consulta_materiais->execute([
    ":usuario_id" => $usuario_id
]);

$materiais = $consulta_materiais->fetchAll(PDO::FETCH_ASSOC);

// ==================================================
// CONSULTAR ASSOCIAÇÕES PARA EDIÇÃO
// ==================================================

$associacoes = [];

$consulta_associacoes = $conexao->prepare(
    "SELECT
        tm.material_id,
        tm.tarefa_id
     FROM tarefas_materiais tm
     INNER JOIN materiais m
        ON m.id = tm.material_id
     INNER JOIN tarefas t
        ON t.id = tm.tarefa_id
     INNER JOIN disciplinas d
        ON d.id = t.disciplina_id
     WHERE m.usuario_id = :usuario_id
     AND d.usuario_id = :usuario_id"
);

$consulta_associacoes->execute([
    ":usuario_id" => $usuario_id
]);

foreach ($consulta_associacoes->fetchAll(PDO::FETCH_ASSOC) as $associacao) {
    $associacoes[$associacao["material_id"]][] = (int) $associacao["tarefa_id"];
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Materiais de estudo | Studia</title>

    <meta
        name="description"
        content="Organize seus materiais de estudo no Studia. Cadastre PDFs, links, resumos e anotações e relacione conteúdos às suas tarefas."
    >

    <meta name="plagiarism" content="Conteúdo original do projeto Studia.">

    <meta property="og:title" content="Materiais de estudo | Studia">
    <meta property="og:description" content="Organize seus PDFs, links, resumos e anotações em um só lugar.">
    <meta property="og:type" content="website">

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        /* ==================================================
           CONFIGURAÇÕES GERAIS
        ================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "DM Sans", sans-serif;
        }

        body {
            background: #f5f3fa;
            color: #302650;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        /* ==================================================
           BARRA LATERAL - IGUAL AO INICIO.PHP
        ================================================== */

        .sidebar {
            width: 250px;
            height: 100vh;
            background: #302650;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            padding: 30px 18px 20px;
            color: white;
            z-index: 100;
            overflow-y: auto;
        }

        .logo {
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -1px;
            padding: 0 14px;
            margin-bottom: 42px;
            color: white;
        }

        .logo span {
            color: #c9b7ff;
        }

        .menu-label {
            color: #a99fc4;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 14px;
            margin-bottom: 13px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            border-radius: 12px;
            color: #d8d1e9;
            font-size: 14px;
            transition: 0.2s;
        }

        .menu a:hover,
        .menu a.active {
            background: #493b70;
            color: white;
        }

        .menu-icon {
            width: 21px;
            text-align: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .menu-text {
            white-space: nowrap;
        }

        /* ==================================================
           PERFIL NA BARRA LATERAL - IGUAL AO INICIO.PHP
        ================================================== */

        .perfil-sidebar {
            margin-top: auto;
            border-top: 1px solid #51456e;
            padding: 19px 7px 0;
        }

        .perfil-link {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .avatar {
            width: 39px;
            height: 39px;
            border-radius: 50%;
            background: #c9b7ff;
            color: #302650;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            overflow: hidden;
            flex-shrink: 0;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .perfil-info {
            min-width: 0;
            flex: 1;
        }

        .perfil-info strong {
            display: block;
            font-size: 13px;
            color: white;
            max-width: 145px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .perfil-info a {
            display: inline-block;
            color: #c9b7ff;
            font-size: 11px;
            margin-top: 4px;
            transition: 0.2s;
        }

        .perfil-info a:hover {
            color: white;
            text-decoration: underline;
        }

        /* ==================================================
           CONTEÚDO PRINCIPAL
        ================================================== */

        .main {
            margin-left: 250px;
            padding: 42px 48px;
            min-height: 100vh;
        }

        .cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .cabecalho h1 {
            font-size: 27px;
            font-weight: 800;
        }

        .cabecalho p {
            color: #89829d;
            font-size: 14px;
            margin-top: 5px;
        }

        .botao-voltar {
            color: #827995;
            background: white;
            padding: 11px 17px;
            border-radius: 12px;
            font-size: 13px;
            white-space: nowrap;
            transition: 0.2s;
        }

        .botao-voltar:hover {
            color: #493b70;
            background: #eeeaf7;
        }

        /* ==================================================
           MENSAGENS
        ================================================== */

        .mensagem {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .mensagem.sucesso {
            background: #e4f7ed;
            color: #23764b;
        }

        .mensagem.erro {
            background: #fde9e9;
            color: #a63b3b;
        }

        /* ==================================================
           PAINÉIS E FORMULÁRIOS
        ================================================== */

        .painel {
            background: white;
            padding: 27px;
            border-radius: 16px;
            box-shadow: 0 4px 18px #30265008;
            border: 1px solid #eeeaf5;
            margin-bottom: 32px;
        }

        .painel h2 {
            font-size: 19px;
            color: #302650;
            margin-bottom: 7px;
        }

        .subtitulo {
            color: #938da1;
            font-size: 13px;
            margin-bottom: 23px;
        }

        .formulario-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .campo.completo {
            grid-column: 1 / -1;
        }

        .campo label {
            font-size: 13px;
            font-weight: 700;
            color: #514a63;
        }

        .campo input,
        .campo select,
        .campo textarea {
            width: 100%;
            border: 1px solid #e6e2ed;
            background: #fcfbfe;
            border-radius: 10px;
            padding: 12px 13px;
            color: #39334a;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .campo input:focus,
        .campo select:focus,
        .campo textarea:focus {
            border-color: #8972c5;
            box-shadow: 0 0 0 3px rgba(137, 114, 197, .10);
        }

        .campo textarea {
            min-height: 90px;
            resize: vertical;
        }

        .ajuda {
            color: #9992a6;
            font-size: 11px;
            line-height: 1.5;
        }

        .selecao-tarefas {
            border: 1px solid #e6e2ed;
            border-radius: 11px;
            padding: 13px;
            max-height: 180px;
            overflow-y: auto;
            background: #fcfbfe;
        }

        .tarefa-opcao {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 3px;
            font-size: 12px;
            color: #5c566a;
            cursor: pointer;
        }

        .tarefa-opcao input {
            width: auto;
            accent-color: #7560a8;
        }

        .botao {
            border: none;
            background: linear-gradient(135deg, #493b70, #7660a8);
            color: white;
            border-radius: 10px;
            padding: 13px 21px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(73, 59, 112, .2);
        }

        /* ==================================================
           LISTAGEM DE MATERIAIS
        ================================================== */

        .lista-titulo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .lista-titulo h2 {
            font-size: 17px;
            font-weight: 800;
            color: #302650;
        }

        .contador {
            color: #8c849b;
            font-size: 12px;
        }

        .materiais-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
            align-items: stretch;
        }

        .material-card {
            background: white;
            padding: 21px;
            border-radius: 16px;
            box-shadow: 0 4px 18px #30265008;
            border: 1px solid #eeeaf5;
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100%;
        }

        .material-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .tipo {
            display: inline-block;
            background: #f0ebfa;
            color: #705a9c;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .material-card h3 {
            font-size: 16px;
            color: #302650;
            margin-bottom: 9px;
            overflow-wrap: anywhere;
        }

        .descricao {
            color: #827b90;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            margin-bottom: 15px;
        }

        .relacionadas {
            border-top: 1px solid #f0edf5;
            padding-top: 12px;
            color: #8c849b;
            font-size: 11px;
            line-height: 1.6;
            margin-top: auto;
            margin-bottom: 15px;
            overflow-wrap: anywhere;
        }

        .relacionadas strong {
            color: #625a73;
        }

        .abrir-arquivo {
            display: inline-block;
            color: #705a9c;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            margin-bottom: 15px;
        }

        .abrir-arquivo:hover {
            color: #302650;
            text-decoration: underline;
        }

        /* ==================================================
           AÇÕES DOS CARDS
        ================================================== */

        .acoes {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-top: 1px solid #f0edf5;
            padding-top: 14px;
        }

        .acoes details {
            flex: 1;
            min-width: 0;
        }

        .editar {
            color: #705a9c;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .excluir {
            background: none;
            border: none;
            color: #c35c68;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .excluir:hover {
            color: #a52f40;
        }

        .form-edicao {
            margin-top: 17px;
            padding-top: 17px;
            border-top: 1px solid #eeeaf3;
        }

        .form-edicao .campo {
            margin-bottom: 13px;
        }

        .form-edicao .botao {
            width: 100%;
        }

        /* ==================================================
           ESTADO VAZIO
        ================================================== */

        .vazio {
            background: white;
            border: 1px solid #eeeaf5;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            color: #8e879d;
            font-size: 13px;
            line-height: 1.7;
        }

        .vazio h3 {
            color: #51417c;
            font-size: 16px;
            margin-bottom: 8px;
        }

        /* ==================================================
           BOTÃO FLUTUANTE DO STUDIA
        ================================================== */

        .botao-studia {
            position: fixed;
            right: 28px;
            bottom: 25px;
            z-index: 90;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 19px;
            background: linear-gradient(120deg, #493b70, #8873c8);
            color: white;
            border-radius: 30px;
            box-shadow: 0 7px 22px rgba(48, 38, 80, 0.22);
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
        }

        .botao-studia:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(48, 38, 80, 0.28);
        }

        .botao-studia-icone {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #ffffff25;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        /* ==================================================
           RESPONSIVIDADE - IGUAL AO INICIO.PHP
        ================================================== */

        @media (max-width: 1200px) {

            .materiais-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main {
                padding: 30px;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 75px;
                padding: 25px 9px;
            }

            .logo {
                font-size: 20px;
                padding: 0;
                text-align: center;
                margin-bottom: 35px;
            }

            .logo span,
            .menu-label,
            .menu-text,
            .perfil-info {
                display: none;
            }

            .menu a {
                justify-content: center;
                padding: 14px 5px;
            }

            .menu-icon {
                font-size: 19px;
            }

            .perfil-sidebar {
                padding: 18px 0 0;
                display: flex;
                justify-content: center;
            }

            .perfil-link {
                justify-content: center;
            }

            .main {
                margin-left: 75px;
                padding: 25px 16px;
            }

            .cabecalho {
                align-items: flex-start;
                flex-direction: column;
            }

            .cabecalho h1 {
                font-size: 24px;
            }

            .materiais-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .material-card {
                padding: 15px;
            }

            .painel {
                padding: 20px;
            }

            .formulario-grid {
                grid-template-columns: 1fr;
            }

            .campo.completo {
                grid-column: auto;
            }

            .lista-titulo {
                align-items: flex-start;
                flex-direction: column;
                gap: 6px;
            }

            .botao-studia {
                right: 16px;
                bottom: 16px;
                padding: 11px 15px;
            }

        }

        @media (max-width: 380px) {

            .main {
                padding: 20px 12px;
            }

            .materiais-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<!-- ==================================================
     BARRA LATERAL
================================================== -->

<aside class="sidebar">

    <div class="logo">
        studia<span>.</span>
    </div>

    <div class="menu-label">
        Menu principal
    </div>

    <nav class="menu">

        <a href="inicio.php">
            <span class="menu-icon">⌂</span>
            <span class="menu-text">Início</span>
        </a>

        <a href="disciplinas.php">
            <span class="menu-icon">▤</span>
            <span class="menu-text">Disciplinas</span>
        </a>

        <a href="tarefas.php">
            <span class="menu-icon">✓</span>
            <span class="menu-text">Tarefas</span>
        </a>

        <a href="materiais.php" class="active">
            <span class="menu-icon">▧</span>
            <span class="menu-text">Materiais</span>
        </a>

        <a href="grupos.php">
            <span class="menu-icon">♧</span>
            <span class="menu-text">Grupos de estudo</span>
        </a>

        <a href="busca.php">
            <span class="menu-icon">⌕</span>
            <span class="menu-text">Buscar</span>
        </a>

    </nav>

    <!-- PERFIL DO USUÁRIO -->

    <div class="perfil-sidebar">

        <div class="perfil-link">

            <a href="perfil.php" class="avatar" title="Acessar meu perfil">

                <?php if (!empty($foto_usuario)): ?>

                    <img
                        src="<?= escapar($foto_usuario) ?>"
                        alt="Foto de perfil"
                    >

                <?php else: ?>

                    <?= escapar($nome_inicial) ?>

                <?php endif; ?>

            </a>

            <div class="perfil-info">

                <strong>
                    <?= escapar($nome_usuario) ?>
                </strong>

                <a href="perfil.php">
                    Meu perfil →
                </a>

            </div>

        </div>

    </div>

</aside>

<!-- ==================================================
     CONTEÚDO PRINCIPAL
================================================== -->

<main class="main">

    <header class="cabecalho">

        <div>

            <h1>Materiais de estudo</h1>

            <p>
                Organize seus PDFs, links, resumos e anotações.
            </p>

        </div>

        <a href="inicio.php" class="botao-voltar">
            Voltar ao início
        </a>

    </header>

    <!-- MENSAGENS DO SISTEMA -->

    <?php if ($mensagem): ?>

        <div class="mensagem <?= escapar($tipo_mensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>

    <?php endif; ?>

    <!-- ==================================================
         FORMULÁRIO DE CADASTRO
    ================================================== -->

    <section class="painel">

        <h2>Adicionar material</h2>

        <p class="subtitulo">
            Cadastre um novo conteúdo e associe-o às suas tarefas, se desejar.
        </p>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="token"
                value="<?= escapar($token) ?>"
            >

            <input
                type="hidden"
                name="acao"
                value="cadastrar"
            >

            <div class="formulario-grid">

                <div class="campo">

                    <label for="titulo">
                        Título do material
                    </label>

                    <input
                        type="text"
                        id="titulo"
                        name="titulo"
                        maxlength="150"
                        placeholder="Ex.: Resumo de matemática"
                        required
                    >

                </div>

                <div class="campo">

                    <label for="tipo">
                        Tipo de material
                    </label>

                    <select name="tipo" id="tipo" required>
                        <option value="pdf">PDF</option>
                        <option value="link">Link</option>
                        <option value="resumo">Resumo</option>
                        <option value="anotacao">Anotação</option>
                        <option value="outro">Outro</option>
                    </select>

                </div>

                <div class="campo completo">

                    <label for="descricao">
                        Descrição ou conteúdo
                    </label>

                    <textarea
                        id="descricao"
                        name="descricao"
                        placeholder="Escreva uma descrição para identificar seu material..."
                    ></textarea>

                </div>

                <div class="campo">

                    <label for="arquivo">
                        Arquivo PDF
                    </label>

                    <input
                        type="file"
                        id="arquivo"
                        name="arquivo"
                        accept=".pdf,application/pdf"
                    >

                    <span class="ajuda">
                        Somente PDF. Tamanho máximo: 5 MB.
                    </span>

                </div>

                <div class="campo">

                    <label for="endereco">
                        Endereço do link
                    </label>

                    <input
                        type="url"
                        id="endereco"
                        name="endereco"
                        placeholder="https://exemplo.com"
                    >

                    <span class="ajuda">
                        Preencha quando o tipo escolhido for Link.
                    </span>

                </div>

                <div class="campo completo">

                    <label>
                        Relacionar com tarefas
                    </label>

                    <div class="selecao-tarefas">

                        <?php if (empty($tarefas_disponiveis)): ?>

                            <span class="ajuda">
                                Você ainda não possui tarefas cadastradas.
                            </span>

                        <?php else: ?>

                            <?php foreach ($tarefas_disponiveis as $tarefa): ?>

                                <label class="tarefa-opcao">

                                    <input
                                        type="checkbox"
                                        name="tarefas[]"
                                        value="<?= (int) $tarefa["id"] ?>"
                                    >

                                    <?= escapar($tarefa["titulo"]) ?>
                                    — <?= escapar($tarefa["disciplina"]) ?>

                                </label>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                    <span class="ajuda">
                        Você pode selecionar mais de uma tarefa.
                    </span>

                </div>

                <div class="campo completo">

                    <button type="submit" class="botao">
                        + Cadastrar material
                    </button>

                </div>

            </div>

        </form>

    </section>

    <!-- ==================================================
         LISTAGEM DE MATERIAIS
    ================================================== -->

    <section>

        <div class="lista-titulo">

            <h2>Meus materiais</h2>

            <span class="contador">
                <?= count($materiais) ?> material(is) cadastrado(s)
            </span>

        </div>

        <?php if (empty($materiais)): ?>

            <div class="vazio">

                <h3>Nenhum material cadastrado ainda.</h3>

                <p>
                    Adicione seu primeiro material usando o formulário acima.
                </p>

            </div>

        <?php else: ?>

            <div class="materiais-grid">

                <?php foreach ($materiais as $material): ?>

                    <article class="material-card">

                        <div class="material-topo">

                            <span class="tipo">
                                <?= escapar($material["tipo"]) ?>
                            </span>

                        </div>

                        <h3>
                            <?= escapar($material["titulo"]) ?>
                        </h3>

                        <p class="descricao">
                            <?= escapar(
                                $material["descricao"] ?: "Sem descrição."
                            ) ?>
                        </p>

                        <?php if (!empty($material["arquivo"])): ?>

                            <?php if ($material["tipo"] === "link"): ?>

                                <a
                                    class="abrir-arquivo"
                                    href="<?= escapar($material["arquivo"]) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir link ↗
                                </a>

                            <?php elseif ($material["tipo"] === "pdf"): ?>

                                <a
                                    class="abrir-arquivo"
                                    href="<?= escapar($material["arquivo"]) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir PDF ↗
                                </a>

                            <?php endif; ?>

                        <?php endif; ?>

                        <div class="relacionadas">

                            <strong>Tarefas relacionadas:</strong><br>

                            <?= escapar(
                                $material["tarefas_relacionadas"]
                                ?: "Nenhuma tarefa relacionada"
                            ) ?>

                        </div>

                        <!-- AÇÕES DO MATERIAL -->

                        <div class="acoes">

                            <details>

                                <summary class="editar">
                                    Editar material
                                </summary>

                                <form
                                    method="POST"
                                    enctype="multipart/form-data"
                                    class="form-edicao"
                                >

                                    <input
                                        type="hidden"
                                        name="token"
                                        value="<?= escapar($token) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="editar"
                                    >

                                    <input
                                        type="hidden"
                                        name="material_id"
                                        value="<?= (int) $material["id"] ?>"
                                    >

                                    <div class="campo">

                                        <label>
                                            Título
                                        </label>

                                        <input
                                            type="text"
                                            name="titulo"
                                            maxlength="150"
                                            value="<?= escapar($material["titulo"]) ?>"
                                            required
                                        >

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Tipo
                                        </label>

                                        <select name="tipo" required>

                                            <?php foreach (
                                                ["pdf", "link", "resumo", "anotacao", "outro"]
                                                as $tipo_opcao
                                            ): ?>

                                                <option
                                                    value="<?= escapar($tipo_opcao) ?>"
                                                    <?= $material["tipo"] === $tipo_opcao ? "selected" : "" ?>
                                                >
                                                    <?= escapar(ucfirst($tipo_opcao)) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Descrição
                                        </label>

                                        <textarea name="descricao"><?= escapar($material["descricao"]) ?></textarea>

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Novo PDF (opcional)
                                        </label>

                                        <input
                                            type="file"
                                            name="arquivo"
                                            accept=".pdf,application/pdf"
                                        >

                                        <span class="ajuda">
                                            Se não enviar outro PDF, o arquivo atual será mantido quando o tipo continuar como PDF.
                                        </span>

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Endereço do link
                                        </label>

                                        <input
                                            type="url"
                                            name="endereco"
                                            placeholder="https://exemplo.com"
                                            value="<?= $material["tipo"] === "link"
                                                ? escapar($material["arquivo"])
                                                : "" ?>"
                                        >

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Tarefas relacionadas
                                        </label>

                                        <div class="selecao-tarefas">

                                            <?php foreach ($tarefas_disponiveis as $tarefa): ?>

                                                <label class="tarefa-opcao">

                                                    <input
                                                        type="checkbox"
                                                        name="tarefas[]"
                                                        value="<?= (int) $tarefa["id"] ?>"
                                                        <?= in_array(
                                                            (int) $tarefa["id"],
                                                            $associacoes[$material["id"]] ?? [],
                                                            true
                                                        ) ? "checked" : "" ?>
                                                    >

                                                    <?= escapar($tarefa["titulo"]) ?>
                                                    — <?= escapar($tarefa["disciplina"]) ?>

                                                </label>

                                            <?php endforeach; ?>

                                            <?php if (empty($tarefas_disponiveis)): ?>

                                                <span class="ajuda">
                                                    Você ainda não possui tarefas cadastradas.
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                    <button type="submit" class="botao">
                                        Salvar alterações
                                    </button>

                                </form>

                            </details>

                            <form
                                method="POST"
                                onsubmit="return confirm('Tem certeza que deseja excluir este material?');"
                            >

                                <input
                                    type="hidden"
                                    name="token"
                                    value="<?= escapar($token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="excluir"
                                >

                                <input
                                    type="hidden"
                                    name="material_id"
                                    value="<?= (int) $material["id"] ?>"
                                >

                                <button type="submit" class="excluir">
                                    Excluir
                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<!-- ==================================================
     ACESSO FLUTUANTE AO ASSISTENTE
================================================== -->

<a href="assistente.php" class="botao-studia">

    <span class="botao-studia-icone">✦</span>

    <span>Conversar com o studIA</span>

</a>

</body>
</html>
