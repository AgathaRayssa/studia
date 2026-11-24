<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../configuracoes/conexao.php";
require_once __DIR__ . "/../logs/registro.php";

$usuario_id = (int) $_SESSION["usuario_id"];

// ============================================
// DADOS DO PERFIL
// ============================================

$consulta_perfil = $conexao->prepare("
    SELECT nome, foto
    FROM usuarios
    WHERE id = :id
    LIMIT 1
");

$consulta_perfil->execute([":id" => $usuario_id]);
$perfil = $consulta_perfil->fetch(PDO::FETCH_ASSOC);

$nome_usuario = $perfil["nome"] ?? "Meu perfil";
$foto_usuario = $perfil["foto"] ?? "";

$nome_inicial = mb_strtoupper(
    mb_substr(trim($nome_usuario), 0, 1, "UTF-8"),
    "UTF-8"
);

// ============================================
// TOKEN CSRF
// ============================================

if (empty($_SESSION["token_busca"])) {
    $_SESSION["token_busca"] = bin2hex(random_bytes(32));
}

$token_csrf = $_SESSION["token_busca"];

// ============================================
// FUNÇÃO DE SEGURANÇA
// ============================================

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// ============================================
// PROCESSAMENTO DA BUSCA AJAX
// ============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {

    header("Content-Type: application/json; charset=utf-8");

    try {

        if ($_POST["acao"] !== "buscar") {
            throw new Exception("Ação inválida.");
        }

        $token_recebido = $_POST["token_csrf"] ?? "";

        if (
            !is_string($token_recebido) ||
            !hash_equals($_SESSION["token_busca"], $token_recebido)
        ) {
            http_response_code(403);
            throw new Exception("Token de segurança inválido.");
        }

        $termo = trim($_POST["termo"] ?? "");

        if (
            mb_strlen($termo, "UTF-8") < 2 ||
            mb_strlen($termo, "UTF-8") > 100
        ) {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $termo_busca = "%" . $termo . "%";
        $resultados = [];

        // ========================================
        // DISCIPLINAS
        // ========================================

        $consulta = $conexao->prepare("
            SELECT id, nome, descricao
            FROM disciplinas
            WHERE usuario_id = :usuario_id
            AND (
                nome LIKE :nome
                OR descricao LIKE :descricao
            )
            ORDER BY nome ASC
            LIMIT 20
        ");

        $consulta->execute([
            ":usuario_id" => $usuario_id,
            ":nome" => $termo_busca,
            ":descricao" => $termo_busca
        ]);

        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $resultados[] = [
                "categoria" => "Disciplina",
                "id" => $item["id"],
                "titulo" => $item["nome"],
                "descricao" => $item["descricao"],
                "url" => "detalhes.php?tipo=disciplina&id=" . (int) $item["id"]
            ];
        }

        // ========================================
        // TAREFAS
        // ========================================

        $consulta = $conexao->prepare("
            SELECT t.id, t.titulo, t.descricao
            FROM tarefas t
            INNER JOIN disciplinas d
                ON d.id = t.disciplina_id
            WHERE d.usuario_id = :usuario_id
            AND (
                t.titulo LIKE :titulo
                OR t.descricao LIKE :descricao
            )
            ORDER BY t.data_entrega ASC
            LIMIT 20
        ");

        $consulta->execute([
            ":usuario_id" => $usuario_id,
            ":titulo" => $termo_busca,
            ":descricao" => $termo_busca
        ]);

        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $resultados[] = [
                "categoria" => "Tarefa",
                "id" => $item["id"],
                "titulo" => $item["titulo"],
                "descricao" => $item["descricao"],
                "url" => "detalhes.php?tipo=tarefa&id=" . (int) $item["id"]
            ];
        }

        // ========================================
        // MATERIAIS
        // ========================================

        $consulta = $conexao->prepare("
            SELECT id, titulo, descricao
            FROM materiais
            WHERE usuario_id = :usuario_id
            AND (
                titulo LIKE :titulo
                OR descricao LIKE :descricao
            )
            ORDER BY titulo ASC
            LIMIT 20
        ");

        $consulta->execute([
            ":usuario_id" => $usuario_id,
            ":titulo" => $termo_busca,
            ":descricao" => $termo_busca
        ]);

        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $resultados[] = [
                "categoria" => "Material",
                "id" => $item["id"],
                "titulo" => $item["titulo"],
                "descricao" => $item["descricao"],
                "url" => "detalhes.php?tipo=material&id=" . (int) $item["id"]
            ];
        }

        // ========================================
        // GRUPOS DE ESTUDO
        // ========================================

        $consulta = $conexao->prepare("
            SELECT DISTINCT g.id, g.nome, g.descricao
            FROM grupos_estudo g
            LEFT JOIN usuarios_grupos ug
                ON ug.grupo_id = g.id
                AND ug.usuario_id = :usuario_membro
            WHERE (
                g.criador_id = :usuario_criador
                OR ug.usuario_id = :usuario_participante
            )
            AND (
                g.nome LIKE :nome
                OR g.descricao LIKE :descricao
            )
            ORDER BY g.nome ASC
            LIMIT 20
        ");

        $consulta->execute([
            ":usuario_membro" => $usuario_id,
            ":usuario_criador" => $usuario_id,
            ":usuario_participante" => $usuario_id,
            ":nome" => $termo_busca,
            ":descricao" => $termo_busca
        ]);

        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $resultados[] = [
                "categoria" => "Grupo de estudo",
                "id" => $item["id"],
                "titulo" => $item["nome"],
                "descricao" => $item["descricao"],
                "url" => "detalhes.php?tipo=grupo&id=" . (int) $item["id"]
            ];
        }

        registrarLog(
            "BUSCA_AJAX_REALIZADA",
            "Termo pesquisado: " . $termo
        );

        echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $erro) {

        registrarLog(
            "ERRO_BUSCA_AJAX",
            "Erro: " . $erro->getMessage()
        );

        if (http_response_code() === 200) {
            http_response_code(500);
        }

        echo json_encode([
            "erro" => "Não foi possível realizar a busca."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Buscar | Studia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
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

        /* =====================================
           MENU LATERAL - PADRÃO INICIO.PHP
        ===================================== */

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
            transition: .2s;
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

        /* =====================================
           PERFIL - PADRÃO INICIO.PHP
        ===================================== */

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
            transition: .2s;
        }

        .perfil-info a:hover {
            color: white;
            text-decoration: underline;
        }

        /* =====================================
           CONTEÚDO PRINCIPAL
        ===================================== */

        .main {
            margin-left: 250px;
            padding: 42px 48px;
            min-height: 100vh;
        }

        .conteudo-interno {
            max-width: 1200px;
            margin: 0 auto;
        }

        .cabecalho {
            margin-bottom: 30px;
        }

        .cabecalho h1 {
            color: #302650;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .cabecalho p {
            color: #898398;
            font-size: 14px;
            line-height: 1.5;
        }

        /* =====================================
           PAINEL DE BUSCA
        ===================================== */

        .painel-busca {
            background: white;
            border: 1px solid #ece9f3;
            border-radius: 17px;
            padding: 25px;
            box-shadow: 0 4px 16px rgba(43, 31, 75, .035);
            margin-bottom: 28px;
        }

        .painel-busca label {
            display: block;
            color: #514a61;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 11px;
        }

        .campo-busca {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e6e2ed;
            background: #fcfbfe;
            border-radius: 10px;
            color: #302b3d;
            font-size: 14px;
            outline: none;
            transition: .2s;
        }

        .campo-busca:focus {
            border-color: #8872c2;
            box-shadow: 0 0 0 3px rgba(136, 114, 194, .10);
            background: white;
        }

        .campo-busca::placeholder {
            color: #aaa6b8;
        }

        .dica-busca {
            color: #918b9e;
            font-size: 12px;
            margin-top: 11px;
        }

        /* =====================================
           RESULTADOS
        ===================================== */

        .secao-resultados {
            background: white;
            border: 1px solid #ece9f3;
            border-radius: 17px;
            padding: 25px;
            box-shadow: 0 4px 16px rgba(43, 31, 75, .035);
        }

        .titulo-resultados {
            color: #302650;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        #status {
            color: #918b9e;
            font-size: 13px;
            margin-bottom: 18px;
        }

        #resultados {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .resultado {
            display: block;
            background: #fff;
            border: 1px solid #ece9f3;
            border-radius: 13px;
            padding: 19px;
            color: inherit;
            transition: .2s;
        }

        .resultado:hover {
            border-color: #c9bce8;
            box-shadow: 0 5px 16px rgba(48, 38, 80, .06);
            transform: translateY(-2px);
        }

        .categoria {
            display: inline-block;
            background: #f0edf8;
            color: #625080;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .resultado h3 {
            color: #302650;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 6px;
            overflow-wrap: anywhere;
        }

        .resultado p {
            color: #918b9e;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .estado-vazio {
            border: 1px dashed #d9d2e7;
            border-radius: 15px;
            padding: 35px 20px;
            text-align: center;
            color: #918b9e;
            font-size: 13px;
            line-height: 1.7;
        }

        .estado-vazio strong {
            display: block;
            color: #514a61;
            font-size: 15px;
            margin-bottom: 6px;
        }

        /* =====================================
           BOTÃO FLUTUANTE STUDIA
        ===================================== */

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
            box-shadow: 0 7px 22px rgba(48, 38, 80, .22);
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .botao-studia:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(48, 38, 80, .28);
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

        /* =====================================
           RESPONSIVIDADE - PADRÃO INICIO.PHP
        ===================================== */

        @media(max-width: 1200px) {
            .main {
                padding: 30px;
            }
        }

        @media(max-width: 700px) {
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

            .botao-studia {
                right: 16px;
                bottom: 16px;
                padding: 11px 15px;
            }
        }

        @media(max-width: 480px) {
            .cabecalho h1 {
                font-size: 24px;
            }

            .painel-busca,
            .secao-resultados {
                padding: 19px;
            }
        }
    </style>
</head>

<body>

<!-- MENU LATERAL -->

<aside class="sidebar">

    <div class="logo">studia<span>.</span></div>

    <div class="menu-label">Menu principal</div>

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

        <a href="grupos.php">
            <span class="menu-icon">♧</span>
            <span class="menu-text">Grupos de estudo</span>
        </a>

        <a href="busca.php" class="active">
            <span class="menu-icon">⌕</span>
            <span class="menu-text">Buscar</span>
        </a>

    </nav>

    <div class="perfil-sidebar">

        <div class="perfil-link">

            <a href="perfil.php" class="avatar" title="Acessar meu perfil">

                <?php if (!empty($foto_usuario)): ?>

                    <img
                        src="<?php echo escapar($foto_usuario); ?>"
                        alt="Foto de perfil"
                    >

                <?php else: ?>

                    <?php echo escapar($nome_inicial ?: "U"); ?>

                <?php endif; ?>

            </a>

            <div class="perfil-info">

                <strong>
                    <?php echo escapar($nome_usuario); ?>
                </strong>

                <a href="perfil.php">Meu perfil →</a>

            </div>

        </div>

    </div>

</aside>

<!-- CONTEÚDO PRINCIPAL -->

<main class="main">

    <div class="conteudo-interno">

        <header class="cabecalho">

            <h1>Buscar</h1>

            <p>
                Encontre rapidamente suas disciplinas, tarefas, materiais e grupos de estudo.
            </p>

        </header>

        <!-- CAMPO DE BUSCA -->

        <section class="painel-busca">

            <label for="campoBusca">
                O que você está procurando?
            </label>

            <input
                type="text"
                id="campoBusca"
                class="campo-busca"
                placeholder="Digite o nome de uma disciplina, tarefa, material ou grupo..."
                autocomplete="off"
                maxlength="100"
            >

            <p class="dica-busca">
                Digite pelo menos 2 caracteres para pesquisar seus conteúdos.
            </p>

        </section>

        <!-- RESULTADOS -->

        <section class="secao-resultados">

            <h2 class="titulo-resultados">
                Resultados da busca
            </h2>

            <p id="status" aria-live="polite">
                Digite pelo menos 2 caracteres para começar.
            </p>

            <div id="resultados"></div>

        </section>

    </div>

</main>

<!-- ATALHO FLUTUANTE DO ASSISTENTE -->

<a
    href="assistente.php"
    class="botao-studia"
    aria-label="Abrir assistente studIA"
>
    <span class="botao-studia-icone">✦</span>
    <span>studIA</span>
</a>

<script>
    const campoBusca = document.getElementById("campoBusca");
    const resultados = document.getElementById("resultados");
    const statusBusca = document.getElementById("status");

    const tokenCSRF = <?php echo json_encode($token_csrf); ?>;

    let temporizador = null;
    let controladorBusca = null;

    campoBusca.addEventListener("input", function () {

        clearTimeout(temporizador);

        if (controladorBusca) {
            controladorBusca.abort();
            controladorBusca = null;
        }

        const termo = campoBusca.value.trim();

        resultados.innerHTML = "";

        if (termo.length < 2) {
            statusBusca.textContent =
                "Digite pelo menos 2 caracteres para começar.";
            return;
        }

        statusBusca.textContent = "Buscando...";

        temporizador = setTimeout(function () {
            realizarBusca(termo);
        }, 350);

    });

    async function realizarBusca(termo) {

        if (controladorBusca) {
            controladorBusca.abort();
        }

        controladorBusca = new AbortController();

        const dados = new FormData();

        dados.append("acao", "buscar");
        dados.append("termo", termo);
        dados.append("token_csrf", tokenCSRF);

        try {

            const resposta = await fetch("busca.php", {
                method: "POST",
                body: dados,
                signal: controladorBusca.signal
            });

            const dadosRecebidos = await resposta.json();

            if (!resposta.ok || dadosRecebidos.erro) {
                throw new Error(
                    dadosRecebidos.erro || "Erro ao realizar a busca."
                );
            }

            // Evita exibir resultados de uma pesquisa anterior.
            if (campoBusca.value.trim() !== termo) {
                return;
            }

            resultados.innerHTML = "";

            if (!Array.isArray(dadosRecebidos) || dadosRecebidos.length === 0) {

                statusBusca.textContent =
                    "Nenhum resultado encontrado.";

                const vazio = document.createElement("div");
                vazio.className = "estado-vazio";

                const titulo = document.createElement("strong");
                titulo.textContent = "Nenhum resultado encontrado.";

                const descricao = document.createElement("span");
                descricao.textContent =
                    "Tente pesquisar usando outras palavras.";

                vazio.appendChild(titulo);
                vazio.appendChild(descricao);

                resultados.appendChild(vazio);

                return;
            }

            statusBusca.textContent =
                dadosRecebidos.length + " resultado(s) encontrado(s).";

            dadosRecebidos.forEach(function (item) {

                const link = document.createElement("a");
                link.className = "resultado";
                link.href = item.url;

                const categoria = document.createElement("span");
                categoria.className = "categoria";
                categoria.textContent = item.categoria;

                const titulo = document.createElement("h3");
                titulo.textContent = item.titulo;

                const descricao = document.createElement("p");
                descricao.textContent =
                    item.descricao || "Nenhuma descrição disponível.";

                link.appendChild(categoria);
                link.appendChild(titulo);
                link.appendChild(descricao);

                resultados.appendChild(link);

            });

        } catch (erro) {

            if (erro.name === "AbortError") {
                return;
            }

            statusBusca.textContent =
                "Não foi possível realizar a busca. Tente novamente.";

            console.error("Erro na busca:", erro);

        }
    }
</script>

</body>
</html>