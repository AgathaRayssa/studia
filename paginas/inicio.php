
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
// API DO PAINEL
// ==================================================

if (isset($_GET["api"]) && $_GET["api"] === "dashboard") {

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate");

    try {

        // Dados do usuário.
        $consulta = $conexao->prepare(
            "SELECT nome FROM usuarios WHERE id = :id LIMIT 1"
        );

        $consulta->execute(["id" => $usuario_id]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        // Total de disciplinas.
        $consulta = $conexao->prepare(
            "SELECT COUNT(*) FROM disciplinas WHERE usuario_id = :id"
        );

        $consulta->execute(["id" => $usuario_id]);
        $disciplinas = (int) $consulta->fetchColumn();

        // Total de tarefas.
        $consulta = $conexao->prepare(
            "SELECT COUNT(*)
             FROM tarefas t
             INNER JOIN disciplinas d ON d.id = t.disciplina_id
             WHERE d.usuario_id = :id"
        );

        $consulta->execute(["id" => $usuario_id]);
        $tarefas = (int) $consulta->fetchColumn();

        // Total de materiais.
        $consulta = $conexao->prepare(
            "SELECT COUNT(*) FROM materiais WHERE usuario_id = :id"
        );

        $consulta->execute(["id" => $usuario_id]);
        $materiais = (int) $consulta->fetchColumn();

        // Total de grupos dos quais participa ou que criou.
        $consulta = $conexao->prepare(
            "SELECT COUNT(DISTINCT g.id)
             FROM grupos_estudo g
             LEFT JOIN usuarios_grupos ug ON ug.grupo_id = g.id
             WHERE ug.usuario_id = :id OR g.criador_id = :criador"
        );

        $consulta->execute([
            "id" => $usuario_id,
            "criador" => $usuario_id
        ]);

        $grupos = (int) $consulta->fetchColumn();

        // Próximas tarefas.
        $consulta = $conexao->prepare(
            "SELECT
                t.id,
                t.titulo,
                t.data_entrega,
                t.status,
                d.nome AS disciplina
             FROM tarefas t
             INNER JOIN disciplinas d ON d.id = t.disciplina_id
             WHERE d.usuario_id = :id
             AND t.data_entrega >= CURDATE()
             ORDER BY t.data_entrega ASC
             LIMIT 8"
        );

        $consulta->execute(["id" => $usuario_id]);
        $proximas = $consulta->fetchAll(PDO::FETCH_ASSOC);

        registrarLog(
            "CONSULTA_API_PAINEL",
            "Dashboard carregado."
        );

        echo json_encode([
            "sucesso" => true,
            "usuario" => $usuario["nome"] ?? "Estudante",
            "totais" => [
                "disciplinas" => $disciplinas,
                "tarefas" => $tarefas,
                "materiais" => $materiais,
                "grupos" => $grupos
            ],
            "proximas_tarefas" => $proximas
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $erro) {

        http_response_code(500);

        registrarLog(
            "ERRO_API_PAINEL",
            $erro->getMessage()
        );

        echo json_encode([
            "sucesso" => false,
            "erro" => "Não foi possível carregar os dados do painel."
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

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

registrarLog(
    "ACESSO_PAINEL_INICIAL",
    "Página inicial acessada."
);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Início | Studia</title>

    <meta
        name="description"
        content="Organize sua rotina acadêmica com o Studia."
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

        .main {
            margin-left: 250px;
            padding: 42px 48px;
            min-height: 100vh;
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
        }

        .topo p {
            color: #89829d;
            font-size: 14px;
            margin-top: 5px;
        }

        .data-atual {
            color: #827995;
            background: white;
            padding: 11px 17px;
            border-radius: 12px;
            font-size: 13px;
        }

        /* ==================================================
           BOAS-VINDAS
        ================================================== */

        .boas-vindas {
            background: linear-gradient(115deg, #493b70, #7660a8);
            border-radius: 20px;
            padding: 30px 34px;
            color: white;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .boas-vindas h2 {
            font-size: 23px;
            margin-bottom: 8px;
        }

        .boas-vindas p {
            color: #e5def5;
            font-size: 14px;
            max-width: 570px;
            line-height: 1.6;
        }

        .boas-vindas::after {
            content: "✦";
            position: absolute;
            right: 40px;
            top: 5px;
            font-size: 100px;
            color: #ffffff18;
            pointer-events: none;
        }

        /* ==================================================
           TÍTULOS DE SEÇÃO
        ================================================== */

        .secao-titulo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 30px 0 16px;
        }

        .secao-titulo h2 {
            font-size: 17px;
            font-weight: 800;
        }

        /* ==================================================
           CARDS DE RESUMO
        ================================================== */

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            background: white;
            padding: 21px;
            border-radius: 16px;
            box-shadow: 0 4px 18px #30265008;
            border: 1px solid #eeeaf5;
            min-width: 0;
        }

        .card-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 17px;
        }

        .card-icone {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #f0ebfa;
            font-size: 18px;
        }

        .card-numero {
            font-size: 28px;
            font-weight: 800;
        }

        .card-nome {
            color: #8a839b;
            font-size: 13px;
            font-weight: 600;
        }

        /* ==================================================
           CONTEÚDO INFERIOR
        ================================================== */

        .conteudo-inferior {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 18px;
            margin-top: 28px;
        }

        .painel {
            background: white;
            border: 1px solid #eeeaf5;
            border-radius: 17px;
            padding: 23px;
            box-shadow: 0 4px 18px #30265008;
            min-width: 0;
        }

        .painel h3 {
            font-size: 16px;
            margin-bottom: 18px;
        }

        .tarefa {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 14px 0;
            border-bottom: 1px solid #f0edf5;
        }

        .tarefa:last-child {
            border-bottom: none;
        }

        .tarefa strong {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .tarefa small {
            color: #958da5;
            font-size: 11px;
        }

        .prazo {
            color: #7660a8;
            background: #f0ebfa;
            border-radius: 8px;
            padding: 7px 9px;
            font-size: 11px;
            white-space: nowrap;
        }

        .vazio {
            color: #958da5;
            font-size: 13px;
            line-height: 1.6;
            padding: 8px 0;
        }

        /* ==================================================
           ATALHOS
        ================================================== */

        .atalhos {
            display: flex;
            flex-direction: column;
            gap: 11px;
        }

        .atalho {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px;
            border-radius: 12px;
            background: #f8f6fc;
            transition: 0.2s;
        }

        .atalho:hover {
            background: #eee8f9;
        }

        .atalho-icone {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #eae3f8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .atalho strong {
            font-size: 12px;
            display: block;
        }

        .atalho small {
            color: #938ba2;
            font-size: 10px;
        }

        /* ==================================================
           PÁGINAS INTERNAS DO REACT
        ================================================== */

        .pagina-extra {
            background: white;
            border-radius: 17px;
            padding: 28px;
            border: 1px solid #eeeaf5;
        }

        .pagina-extra h2 {
            margin-bottom: 10px;
        }

        .pagina-extra p {
            color: #89829d;
            font-size: 14px;
            line-height: 1.7;
        }

        .botao-voltar {
            display: inline-block;
            margin-top: 20px;
            background: #493b70;
            color: white;
            border-radius: 10px;
            padding: 11px 17px;
            font-size: 13px;
        }

        .erro-carregamento {
            background: #fff0f0;
            border: 1px solid #f1cccc;
            color: #a33b3b;
            border-radius: 12px;
            padding: 18px;
            font-size: 14px;
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
           RESPONSIVIDADE
        ================================================== */

        @media (max-width: 1200px) {

            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .conteudo-inferior {
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

            .topo {
                align-items: flex-start;
                flex-direction: column;
            }

            .topo h1 {
                font-size: 24px;
            }

            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .card {
                padding: 15px;
            }

            .card-numero {
                font-size: 24px;
            }

            .boas-vindas {
                padding: 24px;
            }

            .boas-vindas h2 {
                font-size: 20px;
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

            .cards {
                grid-template-columns: 1fr;
            }

            .painel {
                padding: 17px;
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

        <a href="inicio.php" class="active">
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
    src="<?php echo htmlspecialchars($foto_usuario, ENT_QUOTES, "UTF-8"); ?>"
    alt="Foto de perfil"
>

                <?php else: ?>

                    <?php echo htmlspecialchars($nome_inicial, ENT_QUOTES, "UTF-8"); ?>

                <?php endif; ?>

            </a>

            <div class="perfil-info">

                <strong>
                    <?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, "UTF-8"); ?>
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

    <div id="app">

        <p>Carregando seu painel...</p>

    </div>

</main>

<!-- ==================================================
     ACESSO FLUTUANTE AO ASSISTENTE
================================================== -->

<a href="assistente.php" class="botao-studia">

    <span class="botao-studia-icone">✦</span>

    <span>Conversar com o studIA</span>

</a>

<!-- ==================================================
     REACT E REACT ROUTER COMPILADOS LOCALMENTE
================================================== -->

<script src="../assets/js/inicio.js"></script>

</body>
</html>
