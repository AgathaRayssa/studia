
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
// FUNÇÃO PARA ESCAPAR TEXTOS
// ==================================================

function escapar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

// ==================================================
// DADOS DO USUÁRIO
// ==================================================

$consulta_usuario = $conexao->prepare(
    "SELECT nome, foto FROM usuarios WHERE id = :id LIMIT 1"
);

$consulta_usuario->execute([":id" => $usuario_id]);
$dados_usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);

$nome_usuario = $dados_usuario["nome"] ?? ($_SESSION["usuario_nome"] ?? "Usuário");
$foto_usuario = $dados_usuario["foto"] ?? "";

$nome_inicial = mb_strtoupper(
    mb_substr($nome_usuario, 0, 1, "UTF-8"),
    "UTF-8"
);

// ==================================================
// PROTEÇÃO CSRF
// ==================================================

if (empty($_SESSION["token_csrf"])) {
    $_SESSION["token_csrf"] = bin2hex(random_bytes(32));
}

$token_csrf = $_SESSION["token_csrf"];

// ==================================================
// MENSAGENS TEMPORÁRIAS
// ==================================================

$mensagem = $_SESSION["mensagem"] ?? "";
$tipo_mensagem = $_SESSION["tipo_mensagem"] ?? "";

unset($_SESSION["mensagem"], $_SESSION["tipo_mensagem"]);

// ==================================================
// REDIRECIONAMENTO COM MENSAGEM
// ==================================================

function redirecionarComMensagem($texto, $tipo)
{
    $_SESSION["mensagem"] = $texto;
    $_SESSION["tipo_mensagem"] = $tipo;

    header("Location: disciplinas.php");
    exit;
}

// ==================================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token_enviado = $_POST["token_csrf"] ?? "";

    if (
        !is_string($token_enviado) ||
        !hash_equals($token_csrf, $token_enviado)
    ) {
        registrarLog(
            "FALHA_SEGURANCA",
            "Token CSRF inválido na página de disciplinas."
        );

        redirecionarComMensagem(
            "Não foi possível validar a solicitação. Atualize a página e tente novamente.",
            "erro"
        );
    }

    $acao = $_POST["acao"] ?? "";

    // ==================================================
    // CADASTRAR DISCIPLINA
    // ==================================================

    if ($acao === "cadastrar") {

        $nome = trim($_POST["nome"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");
        $cor = $_POST["cor"] ?? "#6C63FF";

        if ($nome === "") {
            redirecionarComMensagem(
                "Informe o nome da disciplina.",
                "erro"
            );
        }

        if (mb_strlen($nome, "UTF-8") > 100) {
            redirecionarComMensagem(
                "O nome da disciplina deve ter no máximo 100 caracteres.",
                "erro"
            );
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) {
            $cor = "#6C63FF";
        }

        try {

            $consulta = $conexao->prepare(
                "INSERT INTO disciplinas
                (usuario_id, nome, descricao, cor)
                VALUES (:usuario_id, :nome, :descricao, :cor)"
            );

            $consulta->execute([
                ":usuario_id" => $usuario_id,
                ":nome" => $nome,
                ":descricao" => $descricao,
                ":cor" => $cor
            ]);

            registrarLog(
                "DISCIPLINA_CADASTRADA",
                "Disciplina: " . $nome
            );

            redirecionarComMensagem(
                "Disciplina cadastrada com sucesso!",
                "sucesso"
            );

        } catch (PDOException $erro) {

            registrarLog(
                "ERRO_CADASTRAR_DISCIPLINA",
                "Não foi possível cadastrar a disciplina."
            );

            redirecionarComMensagem(
                "Não foi possível cadastrar a disciplina. Tente novamente.",
                "erro"
            );
        }
    }

    // ==================================================
    // EDITAR DISCIPLINA
    // ==================================================

    if ($acao === "editar") {

        $id = filter_var(
            $_POST["id"] ?? "",
            FILTER_VALIDATE_INT
        );

        $nome = trim($_POST["nome"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");
        $cor = $_POST["cor"] ?? "#6C63FF";

        if (!$id || $nome === "") {
            redirecionarComMensagem(
                "Verifique os dados da disciplina.",
                "erro"
            );
        }

        if (mb_strlen($nome, "UTF-8") > 100) {
            redirecionarComMensagem(
                "O nome da disciplina deve ter no máximo 100 caracteres.",
                "erro"
            );
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) {
            $cor = "#6C63FF";
        }

        try {

            $consulta = $conexao->prepare(
                "UPDATE disciplinas
                 SET nome = :nome,
                     descricao = :descricao,
                     cor = :cor
                 WHERE id = :id
                 AND usuario_id = :usuario_id"
            );

            $consulta->execute([
                ":nome" => $nome,
                ":descricao" => $descricao,
                ":cor" => $cor,
                ":id" => $id,
                ":usuario_id" => $usuario_id
            ]);

            if ($consulta->rowCount() > 0) {

                registrarLog(
                    "DISCIPLINA_EDITADA",
                    "ID: " . $id . " | Novo nome: " . $nome
                );

                redirecionarComMensagem(
                    "Disciplina atualizada com sucesso!",
                    "sucesso"
                );
            }

            // Verificar se a disciplina existe mesmo sem alterações
            $verificar = $conexao->prepare(
                "SELECT id FROM disciplinas
                 WHERE id = :id
                 AND usuario_id = :usuario_id"
            );

            $verificar->execute([
                ":id" => $id,
                ":usuario_id" => $usuario_id
            ]);

            if ($verificar->fetch()) {

                registrarLog(
                    "DISCIPLINA_SEM_ALTERACOES",
                    "ID: " . $id
                );

                redirecionarComMensagem(
                    "Nenhuma alteração foi realizada.",
                    "sucesso"
                );
            }

            registrarLog(
                "FALHA_EDITAR_DISCIPLINA",
                "Disciplina não encontrada. ID: " . $id
            );

            redirecionarComMensagem(
                "Disciplina não encontrada.",
                "erro"
            );

        } catch (PDOException $erro) {

            registrarLog(
                "ERRO_EDITAR_DISCIPLINA",
                "ID: " . $id
            );

            redirecionarComMensagem(
                "Não foi possível atualizar a disciplina.",
                "erro"
            );
        }
    }

    // ==================================================
    // EXCLUIR DISCIPLINA
    // ==================================================

    if ($acao === "excluir") {

        $id = filter_var(
            $_POST["id"] ?? "",
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            redirecionarComMensagem(
                "Disciplina inválida.",
                "erro"
            );
        }

        // Verificar se a disciplina pertence ao usuário
        $buscar = $conexao->prepare(
            "SELECT nome FROM disciplinas
             WHERE id = :id
             AND usuario_id = :usuario_id"
        );

        $buscar->execute([
            ":id" => $id,
            ":usuario_id" => $usuario_id
        ]);

        $disciplina = $buscar->fetch(PDO::FETCH_ASSOC);

        if (!$disciplina) {

            registrarLog(
                "FALHA_EXCLUIR_DISCIPLINA",
                "Disciplina não encontrada. ID: " . $id
            );

            redirecionarComMensagem(
                "Disciplina não encontrada.",
                "erro"
            );
        }

        // Verificar se existem tarefas vinculadas
        $verificar_tarefas = $conexao->prepare(
            "SELECT COUNT(*)
             FROM tarefas
             WHERE disciplina_id = :id"
        );

        $verificar_tarefas->execute([":id" => $id]);

        $total_tarefas = (int) $verificar_tarefas->fetchColumn();

        if ($total_tarefas > 0) {

            registrarLog(
                "EXCLUSAO_DISCIPLINA_BLOQUEADA",
                "ID: " . $id . " | Existem tarefas vinculadas."
            );

            redirecionarComMensagem(
                "Esta disciplina possui tarefas vinculadas. Exclua ou reorganize essas tarefas antes de removê-la.",
                "erro"
            );
        }

        try {

            $consulta = $conexao->prepare(
                "DELETE FROM disciplinas
                 WHERE id = :id
                 AND usuario_id = :usuario_id"
            );

            $consulta->execute([
                ":id" => $id,
                ":usuario_id" => $usuario_id
            ]);

            if ($consulta->rowCount() > 0) {

                registrarLog(
                    "DISCIPLINA_EXCLUIDA",
                    "ID: " . $id . " | Nome: " . $disciplina["nome"]
                );

                redirecionarComMensagem(
                    "Disciplina excluída com sucesso!",
                    "sucesso"
                );
            }

            redirecionarComMensagem(
                "Não foi possível encontrar a disciplina.",
                "erro"
            );

        } catch (PDOException $erro) {

            registrarLog(
                "ERRO_EXCLUIR_DISCIPLINA",
                "ID: " . $id
            );

            redirecionarComMensagem(
                "Não foi possível excluir esta disciplina.",
                "erro"
            );
        }
    }

    // ==================================================
    // AÇÃO INVÁLIDA
    // ==================================================

    registrarLog(
        "ACAO_INVALIDA_DISCIPLINAS",
        "Ação recebida: " . (string) $acao
    );

    redirecionarComMensagem(
        "Solicitação inválida.",
        "erro"
    );
}

// ==================================================
// BUSCAR DISCIPLINAS
// ==================================================

$consulta = $conexao->prepare(
    "SELECT id, nome, descricao, cor
     FROM disciplinas
     WHERE usuario_id = :usuario_id
     ORDER BY nome ASC"
);

$consulta->execute([
    ":usuario_id" => $usuario_id
]);

$disciplinas = $consulta->fetchAll(PDO::FETCH_ASSOC);

$total_disciplinas = count($disciplinas);

registrarLog(
    "ACESSO_DISCIPLINAS",
    "Página de disciplinas acessada."
);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Disciplinas | Studia</title>

    <meta
        name="description"
        content="Organize suas disciplinas e matérias acadêmicas no Studia."
    >

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
        textarea {
            font: inherit;
        }

        /* ==================================================
           BARRA LATERAL - PADRÃO DO INICIO.PHP
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
           PERFIL NA BARRA LATERAL
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

        .conteudo {
            margin-left: 250px;
            padding: 42px 48px;
            min-height: 100vh;
            min-width: 0;
        }

        .topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .topo h1 {
            font-size: 27px;
            font-weight: 800;
            color: #302650;
        }

        .topo p {
            color: #89829d;
            font-size: 14px;
            margin-top: 5px;
        }

        .contador {
            color: #827995;
            background: white;
            padding: 11px 17px;
            border-radius: 12px;
            font-size: 13px;
            border: 1px solid #eeeaf5;
            white-space: nowrap;
        }

        .contador strong {
            color: #493b70;
            font-size: 19px;
            margin-right: 5px;
        }

        /* ==================================================
           PAINEL
        ================================================== */

        .painel {
            display: grid;
            grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
            gap: 25px;
            align-items: start;
        }

        .cartao {
            background: white;
            border: 1px solid #eeeaf5;
            border-radius: 17px;
            padding: 25px;
            box-shadow: 0 4px 18px #30265008;
            min-width: 0;
        }

        .cartao h2 {
            font-size: 18px;
            color: #302650;
            margin-bottom: 8px;
        }

        .subtitulo {
            font-size: 13px;
            color: #89829d;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        /* ==================================================
           FORMULÁRIOS
        ================================================== */

        .campo {
            margin-bottom: 17px;
        }

        .campo label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #514a61;
        }

        .campo input,
        .campo textarea {
            width: 100%;
            border: 1px solid #e6e2ed;
            background: #fcfbfe;
            border-radius: 10px;
            padding: 12px 13px;
            font-size: 13px;
            outline: none;
            color: #302b3d;
            transition: 0.2s;
        }

        .campo input:focus,
        .campo textarea:focus {
            border-color: #8872c2;
            box-shadow: 0 0 0 3px rgba(136,114,194,.10);
        }

        .campo textarea {
            resize: vertical;
            min-height: 90px;
        }

        .campo input[type="color"] {
            height: 43px;
            padding: 5px;
            cursor: pointer;
        }

        .botao {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 14px;
            background: linear-gradient(120deg, #493b70, #7660a8);
            color: white;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(73,54,111,.2);
        }

        /* ==================================================
           MENSAGENS
        ================================================== */

        .mensagem {
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .sucesso {
            background: #e9f8ef;
            color: #287344;
        }

        .erro {
            background: #fff0f0;
            color: #b33c3c;
        }

        /* ==================================================
           LISTA DE DISCIPLINAS
        ================================================== */

        .lista-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .lista-topo h2 {
            font-size: 19px;
            color: #302650;
        }

        .lista-topo span {
            color: #89829d;
            font-size: 12px;
        }

        .disciplinas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 210px), 1fr));
            gap: 16px;
        }

        .disciplina {
            background: white;
            border: 1px solid #eeeaf5;
            border-radius: 15px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: 0.2s;
            min-width: 0;
        }

        .disciplina:hover {
            box-shadow: 0 8px 22px rgba(43,31,75,.07);
            transform: translateY(-2px);
        }

        .faixa {
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
        }

        .disciplina h3 {
            font-size: 16px;
            margin-bottom: 9px;
            padding-left: 5px;
            overflow-wrap: anywhere;
            color: #302650;
        }

        .disciplina > p {
            color: #89829d;
            font-size: 12px;
            line-height: 1.6;
            padding-left: 5px;
            margin-bottom: 18px;
            overflow-wrap: anywhere;
            min-height: 38px;
        }

        /* ==================================================
           AÇÕES DOS CARDS
        ================================================== */

        .acoes {
            display: flex;
            gap: 8px;
            padding-left: 5px;
            flex-wrap: wrap;
        }

        .acoes form {
            margin: 0;
        }

        .editar,
        .excluir {
            border: none;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .editar {
            background: #eee9fa;
            color: #5b4787;
        }

        .editar:hover {
            background: #e1d9f5;
        }

        .excluir {
            background: #fff0f0;
            color: #bd5151;
        }

        .excluir:hover {
            background: #ffe0e0;
        }

        /* ==================================================
           EDIÇÃO
        ================================================== */

        .form-edicao {
            border-top: 1px solid #eeeaf5;
            margin-top: 17px;
            padding-top: 17px;
        }

        .form-edicao summary {
            color: #65518f;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .form-edicao[open] summary {
            margin-bottom: 18px;
        }

        .form-edicao .campo {
            margin-bottom: 12px;
        }

        .form-edicao .campo input,
        .form-edicao .campo textarea {
            font-size: 12px;
            padding: 10px;
        }

        .form-edicao .campo textarea {
            min-height: 65px;
        }

        .botao-editar {
            width: 100%;
            border: none;
            border-radius: 9px;
            padding: 11px;
            background: #493b70;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao-editar:hover {
            background: #65518f;
        }

        /* ==================================================
           ESTADO VAZIO
        ================================================== */

        .vazio {
            background: white;
            border: 1px dashed #d9d2e7;
            border-radius: 15px;
            padding: 35px 20px;
            text-align: center;
            color: #89829d;
            font-size: 13px;
            line-height: 1.7;
        }

        .vazio h3 {
            color: #51417c;
            font-size: 16px;
            margin-bottom: 8px;
        }

        /* ==================================================
           VOLTAR
        ================================================== */

        .voltar {
            display: inline-block;
            margin-top: 25px;
            color: #7660a8;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .voltar:hover {
            color: #302650;
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
           RESPONSIVIDADE - PADRÃO DO INICIO.PHP
        ================================================== */

        @media (max-width: 1200px) {

            .conteudo {
                padding: 30px;
            }

            .painel {
                grid-template-columns: minmax(250px, 310px) minmax(0, 1fr);
                gap: 18px;
            }

        }

        @media (max-width: 900px) {

            .painel {
                grid-template-columns: 1fr;
            }

            .disciplinas {
                grid-template-columns: repeat(auto-fill, minmax(min(100%, 220px), 1fr));
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

            .conteudo {
                margin-left: 75px;
                padding: 25px 16px;
            }

            .topo {
                align-items: flex-start;
                flex-direction: column;
            }

            .topo h1 {
                font-size: 24px;
            }

            .contador {
                width: 100%;
            }

            .lista-topo {
                align-items: flex-start;
                flex-direction: column;
            }

            .botao-studia {
                right: 16px;
                bottom: 16px;
                padding: 11px 15px;
            }

        }

        @media (max-width: 380px) {

            .conteudo {
                padding: 20px 12px;
            }

            .cartao {
                padding: 17px;
            }

            .disciplinas {
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

        <a href="disciplinas.php" class="active">
            <span class="menu-icon">▤</span>
            <span class="menu-text">Disciplinas</span>
        </a>

        <a href="tarefas.php">
            <span class="menu-icon">✓</span>
            <span class="menu-text">Tarefas</span>
        </a>

        <a href="materiais.php">
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
                        src="<?php echo escapar($foto_usuario); ?>"
                        alt="Foto de perfil"
                    >

                <?php else: ?>

                    <?php echo escapar($nome_inicial); ?>

                <?php endif; ?>

            </a>

            <div class="perfil-info">

                <strong>
                    <?php echo escapar($nome_usuario); ?>
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

<main class="conteudo">

    <header class="topo">

        <div>

            <h1>Minhas disciplinas</h1>

            <p>
                Organize suas matérias e mantenha seus estudos em ordem.
            </p>

        </div>

        <div class="contador">

            <strong><?php echo $total_disciplinas; ?></strong>

            disciplinas cadastradas

        </div>

    </header>

    <section class="painel">

        <!-- ======================================
             FORMULÁRIO DE CADASTRO
        ======================================= -->

        <div class="cartao">

            <h2>Nova disciplina</h2>

            <p class="subtitulo">
                Adicione uma matéria para começar a organizar seus estudos.
            </p>

            <?php if ($mensagem !== ""): ?>

                <div class="mensagem <?php echo $tipo_mensagem === "sucesso" ? "sucesso" : "erro"; ?>">
                    <?php echo escapar($mensagem); ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="acao"
                    value="cadastrar"
                >

                <input
                    type="hidden"
                    name="token_csrf"
                    value="<?php echo escapar($token_csrf); ?>"
                >

                <div class="campo">

                    <label for="nome">
                        Nome da disciplina
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="100"
                        placeholder="Ex.: Banco de Dados"
                        required
                    >

                </div>

                <div class="campo">

                    <label for="descricao">
                        Descrição (opcional)
                    </label>

                    <textarea
                        id="descricao"
                        name="descricao"
                        placeholder="Adicione uma observação sobre a matéria..."
                    ></textarea>

                </div>

                <div class="campo">

                    <label for="cor">
                        Cor da disciplina
                    </label>

                    <input
                        type="color"
                        id="cor"
                        name="cor"
                        value="#6C63FF"
                    >

                </div>

                <button type="submit" class="botao">
                    + Cadastrar disciplina
                </button>

            </form>

        </div>

        <!-- ======================================
             LISTA DE DISCIPLINAS
        ======================================= -->

        <div>

            <div class="lista-topo">

                <h2>Disciplinas cadastradas</h2>

                <span>Suas matérias</span>

            </div>

            <?php if ($total_disciplinas > 0): ?>

                <div class="disciplinas">

                    <?php foreach ($disciplinas as $disciplina): ?>

                        <?php
                        $cor_disciplina = $disciplina["cor"] ?? "#8873c8";

                        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor_disciplina)) {
                            $cor_disciplina = "#8873c8";
                        }
                        ?>

                        <article class="disciplina">

                            <div
                                class="faixa"
                                style="background-color: <?php echo escapar($cor_disciplina); ?>;"
                            ></div>

                            <h3>
                                <?php echo escapar($disciplina["nome"]); ?>
                            </h3>

                            <p>
                                <?php
                                echo escapar(
                                    $disciplina["descricao"] ?: "Nenhuma descrição adicionada."
                                );
                                ?>
                            </p>

                            <div class="acoes">

                                <!-- EDITAR -->

                                <button
                                    type="button"
                                    class="editar"
                                    onclick="document.getElementById('edicao-<?php echo (int) $disciplina['id']; ?>').open = true;"
                                >
                                    Editar
                                </button>

                                <!-- EXCLUIR -->

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Tem certeza que deseja excluir esta disciplina?');"
                                >

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="excluir"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int) $disciplina["id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="token_csrf"
                                        value="<?php echo escapar($token_csrf); ?>"
                                    >

                                    <button type="submit" class="excluir">
                                        Excluir
                                    </button>

                                </form>

                            </div>

                            <!-- FORMULÁRIO DE EDIÇÃO -->

                            <details
                                class="form-edicao"
                                id="edicao-<?php echo (int) $disciplina["id"]; ?>"
                            >

                                <summary>
                                    Editar informações
                                </summary>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="editar"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int) $disciplina["id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="token_csrf"
                                        value="<?php echo escapar($token_csrf); ?>"
                                    >

                                    <div class="campo">

                                        <label>
                                            Nome
                                        </label>

                                        <input
                                            type="text"
                                            name="nome"
                                            maxlength="100"
                                            value="<?php echo escapar($disciplina["nome"]); ?>"
                                            required
                                        >

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Descrição
                                        </label>

                                        <textarea name="descricao"><?php echo escapar($disciplina["descricao"] ?? ""); ?></textarea>

                                    </div>

                                    <div class="campo">

                                        <label>
                                            Cor
                                        </label>

                                        <input
                                            type="color"
                                            name="cor"
                                            value="<?php echo escapar($cor_disciplina); ?>"
                                        >

                                    </div>

                                    <button
                                        type="submit"
                                        class="botao-editar"
                                    >
                                        Salvar alterações
                                    </button>

                                </form>

                            </details>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="vazio">

                    <h3>
                        Nenhuma disciplina por aqui ainda.
                    </h3>

                    <p>
                        Cadastre sua primeira matéria usando o formulário ao lado.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <a href="inicio.php" class="voltar">
        ← Voltar ao painel inicial
    </a>

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
