<?php
session_start();

require_once __DIR__ . "/../configuracoes/conexao.php";
require_once __DIR__ . "/../logs/registro.php";

// ============================================
// VERIFICAÇÃO DE LOGIN
// ============================================

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$usuario_id = (int) $_SESSION["usuario_id"];

// ============================================
// DADOS DO USUÁRIO
// ============================================

$consulta_usuario = $conexao->prepare(
    "SELECT nome, foto FROM usuarios WHERE id = :id LIMIT 1"
);

$consulta_usuario->execute(["id" => $usuario_id]);
$perfil = $consulta_usuario->fetch(PDO::FETCH_ASSOC);

if (!$perfil) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$nome_usuario = $perfil["nome"] ?? "Estudante";
$foto_usuario = $perfil["foto"] ?? "";

$nome_inicial = mb_strtoupper(
    mb_substr($nome_usuario, 0, 1, "UTF-8"),
    "UTF-8"
);

// ============================================
// MENSAGENS E TOKEN
// ============================================

$mensagem = $_SESSION["mensagem"] ?? "";
$tipo_mensagem = $_SESSION["tipo_mensagem"] ?? "";

unset($_SESSION["mensagem"], $_SESSION["tipo_mensagem"]);

if (!isset($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["token"];

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function mensagemGrupo($texto, $tipo = "sucesso") {
    $_SESSION["mensagem"] = $texto;
    $_SESSION["tipo_mensagem"] = $tipo;

    header("Location: grupos.php");
    exit;
}

// ============================================
// AÇÕES DOS FORMULÁRIOS
// ============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($token, $_POST["token"] ?? "")) {

        registrarLog(
            "Falha de segurança",
            "Token CSRF inválido na página de grupos."
        );

        mensagemGrupo(
            "Sessão inválida. Atualize a página.",
            "erro"
        );
    }

    $acao = $_POST["acao"] ?? "";

    try {

        // ------------------------------------
        // CRIAR GRUPO
        // ------------------------------------

        if ($acao === "criar") {

            $nome = trim($_POST["nome"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");

            if ($nome === "") {
                mensagemGrupo("Informe o nome do grupo.", "erro");
            }

            if (mb_strlen($nome, "UTF-8") > 100) {
                mensagemGrupo(
                    "O nome deve ter até 100 caracteres.",
                    "erro"
                );
            }

            // Gera um código de convite único.
            do {

                $codigo = strtoupper(bin2hex(random_bytes(4)));

                $verificar = $conexao->prepare(
                    "SELECT id
                     FROM grupos_estudo
                     WHERE codigo_convite = :codigo"
                );

                $verificar->execute([
                    ":codigo" => $codigo
                ]);

            } while ($verificar->fetch());

            $conexao->beginTransaction();

            $inserir = $conexao->prepare(
                "INSERT INTO grupos_estudo
                    (criador_id, nome, descricao, codigo_convite)
                 VALUES
                    (:criador_id, :nome, :descricao, :codigo)"
            );

            $inserir->execute([
                ":criador_id" => $usuario_id,
                ":nome" => $nome,
                ":descricao" => $descricao,
                ":codigo" => $codigo
            ]);

            $grupo_id = (int) $conexao->lastInsertId();

            // O criador também é cadastrado como participante.
            $membro = $conexao->prepare(
                "INSERT INTO usuarios_grupos
                    (usuario_id, grupo_id)
                 VALUES
                    (:usuario_id, :grupo_id)"
            );

            $membro->execute([
                ":usuario_id" => $usuario_id,
                ":grupo_id" => $grupo_id
            ]);

            $conexao->commit();

            registrarLog(
                "Grupo criado",
                "Grupo ID: " . $grupo_id .
                " | Nome: " . $nome .
                " | Código de convite: " . $codigo
            );

            mensagemGrupo(
                "Grupo criado com sucesso! Compartilhe o código de convite."
            );
        }

        // ------------------------------------
        // ENTRAR EM UM GRUPO
        // ------------------------------------

        elseif ($acao === "entrar") {

            $codigo = strtoupper(trim($_POST["codigo"] ?? ""));

            if ($codigo === "") {
                mensagemGrupo("Informe o código de convite.", "erro");
            }

            $consulta = $conexao->prepare(
                "SELECT id, nome
                 FROM grupos_estudo
                 WHERE codigo_convite = :codigo"
            );

            $consulta->execute([
                ":codigo" => $codigo
            ]);

            $grupo = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$grupo) {

                registrarLog(
                    "Falha ao entrar em grupo",
                    "Código de convite não encontrado."
                );

                mensagemGrupo(
                    "Nenhum grupo foi encontrado com esse código.",
                    "erro"
                );
            }

            $grupo_id = (int) $grupo["id"];

            $verificar = $conexao->prepare(
                "SELECT grupo_id
                 FROM usuarios_grupos
                 WHERE usuario_id = :usuario_id
                 AND grupo_id = :grupo_id"
            );

            $verificar->execute([
                ":usuario_id" => $usuario_id,
                ":grupo_id" => $grupo_id
            ]);

            if ($verificar->fetch()) {

                registrarLog(
                    "Tentativa de entrar em grupo existente",
                    "Grupo ID: " . $grupo_id
                );

                mensagemGrupo(
                    "Você já participa desse grupo.",
                    "erro"
                );
            }

            $entrar = $conexao->prepare(
                "INSERT INTO usuarios_grupos
                    (usuario_id, grupo_id)
                 VALUES
                    (:usuario_id, :grupo_id)"
            );

            $entrar->execute([
                ":usuario_id" => $usuario_id,
                ":grupo_id" => $grupo_id
            ]);

            registrarLog(
                "Entrada em grupo",
                "Grupo ID: " . $grupo_id .
                " | Nome: " . $grupo["nome"]
            );

            mensagemGrupo("Você entrou no grupo com sucesso!");
        }

        // ------------------------------------
        // SAIR DE UM GRUPO
        // ------------------------------------

        elseif ($acao === "sair") {

            $grupo_id = (int) ($_POST["grupo_id"] ?? 0);

            $consulta = $conexao->prepare(
                "SELECT g.criador_id, g.nome
                 FROM grupos_estudo g
                 INNER JOIN usuarios_grupos ug
                    ON ug.grupo_id = g.id
                 WHERE g.id = :grupo_id
                 AND ug.usuario_id = :usuario_id"
            );

            $consulta->execute([
                ":grupo_id" => $grupo_id,
                ":usuario_id" => $usuario_id
            ]);

            $grupo = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$grupo) {

                registrarLog(
                    "Falha ao sair de grupo",
                    "Grupo ID: " . $grupo_id .
                    " não encontrado para o usuário."
                );

                mensagemGrupo(
                    "Você não participa desse grupo.",
                    "erro"
                );
            }

            if ((int) $grupo["criador_id"] === $usuario_id) {

                mensagemGrupo(
                    "O criador não pode sair do próprio grupo. Exclua o grupo se desejar encerrá-lo.",
                    "erro"
                );
            }

            $sair = $conexao->prepare(
                "DELETE FROM usuarios_grupos
                 WHERE usuario_id = :usuario_id
                 AND grupo_id = :grupo_id"
            );

            $sair->execute([
                ":usuario_id" => $usuario_id,
                ":grupo_id" => $grupo_id
            ]);

            registrarLog(
                "Saída de grupo",
                "Grupo ID: " . $grupo_id .
                " | Nome: " . $grupo["nome"]
            );

            mensagemGrupo("Você saiu do grupo.");
        }

        // ------------------------------------
        // EXCLUIR GRUPO
        // ------------------------------------

        elseif ($acao === "excluir") {

            $grupo_id = (int) ($_POST["grupo_id"] ?? 0);

            $consulta_grupo = $conexao->prepare(
                "SELECT nome
                 FROM grupos_estudo
                 WHERE id = :grupo_id
                 AND criador_id = :usuario_id"
            );

            $consulta_grupo->execute([
                ":grupo_id" => $grupo_id,
                ":usuario_id" => $usuario_id
            ]);

            $grupo = $consulta_grupo->fetch(PDO::FETCH_ASSOC);

            if (!$grupo) {

                registrarLog(
                    "Falha ao excluir grupo",
                    "Grupo ID: " . $grupo_id .
                    " | Permissão negada ou grupo inexistente."
                );

                mensagemGrupo(
                    "Você não tem permissão para excluir esse grupo.",
                    "erro"
                );
            }

            $excluir = $conexao->prepare(
                "DELETE FROM grupos_estudo
                 WHERE id = :grupo_id
                 AND criador_id = :usuario_id"
            );

            $excluir->execute([
                ":grupo_id" => $grupo_id,
                ":usuario_id" => $usuario_id
            ]);

            registrarLog(
                "Grupo excluído",
                "Grupo ID: " . $grupo_id .
                " | Nome: " . $grupo["nome"]
            );

            mensagemGrupo("Grupo excluído com sucesso.");
        }

        else {
            mensagemGrupo("Ação inválida.", "erro");
        }

    } catch (Throwable $erro) {

        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        registrarLog(
            "Erro ao processar grupo",
            "Ação: " . $acao .
            " | Erro: " . get_class($erro)
        );

        mensagemGrupo(
            "Ocorreu um erro ao processar a solicitação.",
            "erro"
        );
    }
}

// ============================================
// CONSULTAR GRUPOS DO USUÁRIO
// ============================================

$consulta = $conexao->prepare(
    "SELECT
        g.id,
        g.nome,
        g.descricao,
        g.codigo_convite,
        g.criador_id,
        g.data_criacao,
        u.nome AS nome_criador,

        (SELECT COUNT(*)
         FROM usuarios_grupos ug2
         WHERE ug2.grupo_id = g.id) AS total_membros,

        (SELECT GROUP_CONCAT(
            u2.nome ORDER BY u2.nome SEPARATOR ', '
         )
         FROM usuarios_grupos ug3
         INNER JOIN usuarios u2
            ON u2.id = ug3.usuario_id
         WHERE ug3.grupo_id = g.id) AS nomes_membros

     FROM grupos_estudo g

     INNER JOIN usuarios u
        ON u.id = g.criador_id

     INNER JOIN usuarios_grupos ug
        ON ug.grupo_id = g.id

     WHERE ug.usuario_id = :usuario_id

     ORDER BY g.data_criacao DESC"
);

$consulta->execute([
    ":usuario_id" => $usuario_id
]);

$grupos = $consulta->fetchAll(PDO::FETCH_ASSOC);

registrarLog(
    "VISUALIZACAO_GRUPOS",
    "Página de grupos de estudo acessada."
);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Grupos de estudo | Studia</title>

    <meta
        name="description"
        content="Crie grupos de estudo, convide outros estudantes e organize seus estudos em conjunto com o Studia."
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
        textarea,
        select {
            font: inherit;
        }

        /* ==================================================
           BARRA LATERAL
           PADRÃO ORIGINAL DO inicio.php
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
           PADRÃO ORIGINAL DO inicio.php
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
            margin-bottom: 32px;
        }

        .cabecalho h1 {
            font-size: 27px;
            font-weight: 800;
            color: #302650;
        }

        .cabecalho p {
            color: #89829d;
            font-size: 14px;
            margin-top: 5px;
            line-height: 1.6;
        }

        .botao-voltar {
            color: #7660a8;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .botao-voltar:hover {
            color: #302650;
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
            color: #a63c3c;
        }

        /* ==================================================
           PAINEL DE FORMULÁRIOS
        ================================================== */

        .painel {
            background: #ffffff;
            border: 1px solid #eeeaf5;
            border-radius: 19px;
            padding: 27px;
            box-shadow: 0 5px 25px rgba(39, 29, 68, .045);
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
            line-height: 1.6;
            margin-bottom: 23px;
        }

        .formularios {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-bloco {
            background: #fcfbfe;
            border: 1px solid #eeeaf4;
            border-radius: 15px;
            padding: 21px;
            min-width: 0;
        }

        .form-bloco h3 {
            font-size: 15px;
            color: #302650;
            margin-bottom: 7px;
        }

        .form-bloco p {
            font-size: 12px;
            color: #938da1;
            line-height: 1.6;
            margin-bottom: 17px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .campo label {
            font-size: 13px;
            font-weight: 700;
            color: #514a63;
        }

        .campo input,
        .campo textarea {
            width: 100%;
            border: 1px solid #e6e2ed;
            background: #ffffff;
            border-radius: 10px;
            padding: 12px 13px;
            color: #39334a;
            font-size: 13px;
            outline: none;
        }

        .campo input:focus,
        .campo textarea:focus {
            border-color: #8972c5;
            box-shadow: 0 0 0 3px rgba(137, 114, 197, .10);
        }

        .campo textarea {
            min-height: 90px;
            resize: vertical;
        }

        .botao {
            border: none;
            background: linear-gradient(135deg, #66528f, #8872b5);
            color: #ffffff;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .botao:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 82, 143, .2);
        }

        /* ==================================================
           LISTA DE GRUPOS
        ================================================== */

        .lista-titulo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .lista-titulo h2 {
            font-size: 20px;
            color: #302650;
        }

        .contador {
            color: #8c849b;
            font-size: 12px;
            white-space: nowrap;
        }

        .grupos-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fill,
                minmax(min(100%, 270px), 1fr)
            );
            gap: 18px;
            align-items: stretch;
        }

        .grupo-card {
            background: #ffffff;
            border: 1px solid #e7def5;
            border-radius: 16px;
            padding: 23px;
            box-shadow: 0 4px 16px rgba(39, 29, 68, .035);
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100%;
        }

        .grupo-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .grupo-etiqueta {
            background: #f0ebfa;
            color: #705a9c;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .grupo-card h3 {
            font-size: 17px;
            color: #302650;
            margin-bottom: 9px;
            overflow-wrap: anywhere;
        }

        .descricao {
            color: #827b90;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 17px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .informacao {
            border-top: 1px solid #f0edf4;
            padding-top: 13px;
            margin-top: auto;
            color: #898398;
            font-size: 12px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .informacao strong {
            color: #625875;
        }

        .convite {
            background: #f8f6fc;
            border: 1px dashed #cfc3e6;
            padding: 12px;
            border-radius: 10px;
            margin-top: 16px;
        }

        .convite small {
            display: block;
            color: #928aa2;
            font-size: 10px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .codigo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            color: #5f4b89;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .copiar {
            background: none;
            border: none;
            color: #705a9c;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .copiar:hover {
            color: #302650;
        }

        .acoes {
            display: flex;
            justify-content: flex-end;
            margin-top: 17px;
            padding-top: 14px;
            border-top: 1px solid #f0edf4;
        }

        .excluir,
        .sair {
            background: none;
            border: none;
            color: #c35c68;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .excluir:hover,
        .sair:hover {
            color: #9f3443;
        }

        .vazio {
            background: #ffffff;
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
           PADRÃO ORIGINAL DO inicio.php
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
           RESPONSIVIDADE
           PADRÃO ORIGINAL DO inicio.php
        ================================================== */

        @media (max-width: 1200px) {

            .formularios {
                grid-template-columns: 1fr;
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

            .botao-studia {
                right: 16px;
                bottom: 16px;
                padding: 11px 15px;
            }

        }

        @media (max-width: 520px) {

            .main {
                padding: 20px 12px 85px;
            }

            .painel {
                padding: 19px;
            }

            .form-bloco {
                padding: 17px;
            }

            .lista-titulo {
                align-items: flex-start;
            }

        }

        @media (max-width: 380px) {

            .painel {
                padding: 17px;
            }

            .grupo-card {
                padding: 18px;
            }

        }

    </style>

</head>

<body>

<!-- ==================================================
     BARRA LATERAL
     COPIADA DO inicio.php
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

        <a href="materiais.php">
            <span class="menu-icon">▧</span>
            <span class="menu-text">Materiais</span>
        </a>

        <a href="grupos.php" class="active">
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

            <h1>Grupos de estudo</h1>

            <p>
                Estude em conjunto, compartilhe ideias e aprenda com outras pessoas.
            </p>

        </div>

        <a href="inicio.php" class="botao-voltar">
            Voltar ao início
        </a>

    </header>

    <?php if ($mensagem): ?>

        <div class="mensagem <?= escapar($tipo_mensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>

    <?php endif; ?>

    <!-- =================================
         CRIAR E ENTRAR EM GRUPOS
    ================================== -->

    <section class="painel">

        <h2>Conecte-se com outros estudantes</h2>

        <p class="subtitulo">
            Crie seu próprio grupo ou participe de um grupo usando um código de convite.
        </p>

        <div class="formularios">

            <!-- CRIAR GRUPO -->

            <div class="form-bloco">

                <h3>Criar um grupo</h3>

                <p>
                    Crie um espaço de estudo e convide outras pessoas para participar.
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= escapar($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="acao"
                        value="criar"
                    >

                    <div class="campo">

                        <label for="nome">Nome do grupo</label>

                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            maxlength="100"
                            placeholder="Ex.: Grupo de matemática"
                            required
                        >

                    </div>

                    <div class="campo">

                        <label for="descricao">Descrição</label>

                        <textarea
                            id="descricao"
                            name="descricao"
                            placeholder="Qual é o objetivo desse grupo?"
                        ></textarea>

                    </div>

                    <button type="submit" class="botao">
                        + Criar grupo
                    </button>

                </form>

            </div>

            <!-- ENTRAR EM GRUPO -->

            <div class="form-bloco">

                <h3>Entrar em um grupo</h3>

                <p>
                    Recebeu um código de convite? Informe-o abaixo para participar.
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= escapar($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="acao"
                        value="entrar"
                    >

                    <div class="campo">

                        <label for="codigo">Código de convite</label>

                        <input
                            type="text"
                            id="codigo"
                            name="codigo"
                            maxlength="20"
                            placeholder="Ex.: A1B2C3D4"
                            required
                        >

                    </div>

                    <button type="submit" class="botao">
                        Entrar no grupo
                    </button>

                </form>

            </div>

        </div>

    </section>

    <!-- =================================
         MEUS GRUPOS
    ================================== -->

    <section>

        <div class="lista-titulo">

            <h2>Meus grupos</h2>

            <span class="contador">
                <?= count($grupos) ?> grupo(s)
            </span>

        </div>

        <?php if (empty($grupos)): ?>

            <div class="vazio">

                <h3>Você ainda não participa de nenhum grupo.</h3>

                <p>
                    Crie um grupo ou utilize um código de convite para começar.
                </p>

            </div>

        <?php else: ?>

            <div class="grupos-grid">

                <?php foreach ($grupos as $grupo): ?>

                    <article class="grupo-card">

                        <div class="grupo-topo">

                            <span class="grupo-etiqueta">
                                <?= (int) $grupo["criador_id"] === $usuario_id
                                    ? "Criador"
                                    : "Participante" ?>
                            </span>

                        </div>

                        <h3>
                            <?= escapar($grupo["nome"]) ?>
                        </h3>

                        <p class="descricao">
                            <?= escapar(
                                $grupo["descricao"] ?: "Grupo sem descrição."
                            ) ?>
                        </p>

                        <div class="informacao">

                            <strong>Criado por:</strong>
                            <?= escapar($grupo["nome_criador"]) ?>

                            <br>

                            <strong>Participantes:</strong>
                            <?= (int) $grupo["total_membros"] ?>

                            <br>

                            <strong>Membros:</strong>
                            <?= escapar(
                                $grupo["nomes_membros"] ?: "Nenhum membro"
                            ) ?>

                        </div>

                        <?php if ((int) $grupo["criador_id"] === $usuario_id): ?>

                            <div class="convite">

                                <small>Código de convite</small>

                                <div class="codigo">

                                    <span>
                                        <?= escapar($grupo["codigo_convite"]) ?>
                                    </span>

                                    <button
                                        type="button"
                                        class="copiar"
                                        data-codigo="<?= escapar($grupo["codigo_convite"]) ?>"
                                    >
                                        Copiar
                                    </button>

                                </div>

                            </div>

                        <?php endif; ?>

                        <div class="acoes">

                            <?php if ((int) $grupo["criador_id"] === $usuario_id): ?>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Deseja excluir este grupo? Todos os participantes serão removidos.');"
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
                                        name="grupo_id"
                                        value="<?= (int) $grupo["id"] ?>"
                                    >

                                    <button type="submit" class="excluir">
                                        Excluir grupo
                                    </button>

                                </form>

                            <?php else: ?>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Deseja sair deste grupo?');"
                                >

                                    <input
                                        type="hidden"
                                        name="token"
                                        value="<?= escapar($token) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="sair"
                                    >

                                    <input
                                        type="hidden"
                                        name="grupo_id"
                                        value="<?= (int) $grupo["id"] ?>"
                                    >

                                    <button type="submit" class="sair">
                                        Sair do grupo
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<!-- ==================================================
     BOTÃO FLUTUANTE DO STUDIA
================================================== -->

<a href="assistente.php" class="botao-studia">

    <span class="botao-studia-icone">✦</span>

    <span>Conversar com o studIA</span>

</a>

<!-- ==================================================
     COPIAR CÓDIGO DE CONVITE
================================================== -->

<script>
document.querySelectorAll(".copiar").forEach(function(botao) {

    botao.addEventListener("click", function() {

        const codigo = this.dataset.codigo;

        if (navigator.clipboard && window.isSecureContext) {

            navigator.clipboard.writeText(codigo)
                .then(function() {
                    alert("Código de convite copiado!");
                })
                .catch(function() {
                    alert("Não foi possível copiar o código.");
                });

        } else {

            const campoTemporario = document.createElement("textarea");

            campoTemporario.value = codigo;
            campoTemporario.style.position = "fixed";
            campoTemporario.style.opacity = "0";

            document.body.appendChild(campoTemporario);

            campoTemporario.select();

            try {
                document.execCommand("copy");
                alert("Código de convite copiado!");
            } catch (erro) {
                alert("Não foi possível copiar o código.");
            }

            document.body.removeChild(campoTemporario);

        }

    });

});
</script>

</body>
</html>