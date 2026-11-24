<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../configuracoes/conexao.php";

$id_usuario = (int) $_SESSION["usuario_id"];

// Busca os dados do usuário para a barra lateral
$consulta_usuario = $conexao->prepare(
    "SELECT nome, foto FROM usuarios WHERE id = :id"
);
$consulta_usuario->execute([":id" => $id_usuario]);
$dados_usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);

if (!$dados_usuario) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$nome_usuario = $dados_usuario["nome"];
$foto_usuario = $dados_usuario["foto"];
$nome_inicial = mb_strtoupper(
    mb_substr($nome_usuario, 0, 1, "UTF-8"),
    "UTF-8"
);

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#302650">
    <meta name="description" content="Converse com o studIA, seu assistente inteligente de estudos da plataforma Studia.">

    <title>studIA | Assistente de estudos</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
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

        /* MENU LATERAL PADRÃO DO STUDIA */

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

        .avatar-sidebar {
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

        .avatar-sidebar img {
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

        .sair-sidebar {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            margin-top: 12px;
            border-radius: 12px;
            color: #d8d1e9;
            font-size: 14px;
            transition: .2s;
        }

        .sair-sidebar:hover {
            background: #493b70;
            color: white;
        }

        /* ÁREA PRINCIPAL */

        .main {
            margin-left: 250px;
            padding: 35px 48px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topo-pagina {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .topo-pagina h1 {
            font-size: 27px;
            font-weight: 800;
            color: #302650;
            letter-spacing: -.7px;
            margin-bottom: 5px;
        }

        .topo-pagina p {
            font-size: 13px;
            color: #89839c;
        }

        .voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border: 1px solid #e5e0ef;
            border-radius: 11px;
            background: white;
            color: #65558f;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
            white-space: nowrap;
        }

        .voltar:hover {
            background: #f0ecfa;
            border-color: #d4c9ef;
        }

        /* INTRODUÇÃO */

        .introducao {
            text-align: center;
            margin: 5px 0 25px;
        }

        .icone {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            background: linear-gradient(135deg, #8873c8, #493b70);
            color: white;
            display: grid;
            place-items: center;
            font-size: 27px;
            margin: 0 auto 14px;
            box-shadow: 0 8px 22px rgba(73,59,112,.18);
        }

        .introducao h2 {
            font-size: 25px;
            letter-spacing: -.7px;
            color: #302650;
            margin-bottom: 8px;
        }

        .introducao p {
            color: #858197;
            font-size: 14px;
            line-height: 1.7;
            max-width: 590px;
            margin: 0 auto;
        }

        /* PAINEL DE CONVERSA */

        .painel {
            background: white;
            border: 1px solid #ebe7f3;
            border-radius: 19px;
            box-shadow: 0 10px 35px rgba(48,38,80,.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            flex: 1;
            min-height: 530px;
        }

        .painel-topo {
            padding: 17px 22px;
            border-bottom: 1px solid #f0edf6;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
        }

        .status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #48bd83;
            box-shadow: 0 0 0 4px #e8f8ef;
            flex-shrink: 0;
        }

        .painel-topo strong {
            font-size: 14px;
            color: #302650;
        }

        .painel-topo small {
            display: block;
            color: #9994a9;
            margin-top: 3px;
            font-size: 12px;
        }

        #conversa {
            flex: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            overflow-y: auto;
            min-height: 300px;
            max-height: 510px;
            scroll-behavior: smooth;
        }

        .mensagem {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            max-width: 88%;
        }

        .mensagem.usuario {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .avatar {
            width: 35px;
            height: 35px;
            flex-shrink: 0;
            border-radius: 12px;
            background: #eeeaf8;
            color: #65558f;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 12px;
        }

        .usuario .avatar {
            background: #e9e3f7;
            color: #493b70;
        }

        .balao {
            background: #f6f5fa;
            color: #39334b;
            border-radius: 5px 15px 15px 15px;
            padding: 14px 17px;
            font-size: 14px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .usuario .balao {
            background: #493b70;
            color: white;
            border-radius: 15px 5px 15px 15px;
        }

        .balao p {
            margin: 5px 0 10px;
        }

        .balao h1,
        .balao h2,
        .balao h3 {
            font-size: 16px;
            color: #302650;
            margin: 12px 0 7px;
        }

        .usuario .balao h1,
        .usuario .balao h2,
        .usuario .balao h3 {
            color: white;
        }

        .balao ul,
        .balao ol {
            padding-left: 22px;
            margin: 7px 0 10px;
        }

        .balao li {
            margin: 4px 0;
        }

        .balao strong {
            font-weight: 750;
        }

        .balao code {
            background: #eae6f3;
            color: #493b70;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 12px;
        }

        .sugestoes {
            padding: 0 25px 17px;
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .sugestao {
            border: 1px solid #e7e2f4;
            background: white;
            color: #65558f;
            border-radius: 30px;
            padding: 9px 13px;
            font-size: 12px;
            cursor: pointer;
            transition: .2s;
        }

        .sugestao:hover {
            background: #f3f0fa;
            border-color: #c9bdf5;
            transform: translateY(-1px);
        }

        /* CAMPO DE ENVIO */

        .entrada-area {
            border-top: 1px solid #f0edf6;
            padding: 17px 20px;
            background: #fff;
        }

        .entrada {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            background: #f7f6fa;
            border: 1px solid #e9e6f0;
            border-radius: 15px;
            padding: 9px 10px 9px 15px;
            transition: .2s;
        }

        .entrada:focus-within {
            border-color: #8873c8;
            box-shadow: 0 0 0 3px rgba(136,115,200,.10);
        }

        textarea {
            flex: 1;
            border: none;
            outline: none;
            resize: none;
            background: transparent;
            font-family: inherit;
            font-size: 14px;
            color: #302650;
            min-height: 38px;
            max-height: 120px;
            padding: 9px 0;
        }

        textarea::placeholder {
            color: #aaa6b6;
        }

        #enviar {
            border: none;
            background: #493b70;
            color: white;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 19px;
            transition: .2s;
            flex-shrink: 0;
        }

        #enviar:hover {
            background: #65558f;
            transform: translateY(-1px);
        }

        #enviar:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }

        .aviso {
            text-align: center;
            font-size: 11px;
            color: #aaa6b6;
            margin-top: 10px;
        }

        .digitando {
            color: #8d879e;
            font-size: 13px;
            padding: 8px 0;
        }

        .erro {
            color: #b94b5b;
        }

        /* RESPONSIVIDADE */

        @media(max-width:1200px) {
            .main {
                padding: 30px;
            }
        }

        @media(max-width:700px) {
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

            .sair-sidebar {
                justify-content: center;
                padding: 13px 5px;
            }

            .sair-sidebar .menu-text {
                display: none;
            }

            .main {
                margin-left: 75px;
                padding: 25px 16px;
            }

            .topo-pagina {
                align-items: flex-start;
            }

            .topo-pagina h1 {
                font-size: 23px;
            }

            .voltar {
                padding: 9px 11px;
                font-size: 12px;
            }

            .introducao h2 {
                font-size: 22px;
            }

            .painel {
                min-height: 530px;
            }

            #conversa {
                padding: 18px 14px;
            }

            .mensagem {
                max-width: 96%;
            }

            .sugestoes {
                padding: 0 14px 15px;
            }

            .entrada-area {
                padding: 14px;
            }
        }

        @media(max-width:420px) {
            .topo-pagina {
                flex-direction: column;
                gap: 12px;
            }

            .voltar {
                align-self: flex-start;
            }

            .sugestao {
                font-size: 11px;
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

        <a href="busca.php">
            <span class="menu-icon">⌕</span>
            <span class="menu-text">Buscar</span>
        </a>

    </nav>

    <div class="perfil-sidebar">

        <div class="perfil-link">

            <a href="perfil.php" class="avatar-sidebar" title="Acessar meu perfil">

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
                <strong><?= escapar($nome_usuario) ?></strong>
                <a href="perfil.php">Meu perfil →</a>
            </div>

        </div>

    </div>

    <a href="sair.php" class="sair-sidebar">
        <span class="menu-icon">↪</span>
        <span class="menu-text">Sair da conta</span>
    </a>

</aside>

<!-- CONTEÚDO PRINCIPAL -->

<main class="main">

    <div class="topo-pagina">

        <div>
            <h1>studIA</h1>
            <p>Seu espaço inteligente de aprendizagem.</p>
        </div>

        <a class="voltar" href="inicio.php">
            ← Voltar ao painel
        </a>

    </div>

    <section class="introducao">

        <div class="icone">✦</div>

        <h2>Olá! Eu sou o studIA.</h2>

        <p>
            Seu assistente de estudos está aqui para ajudar você a aprender,
            organizar ideias e tirar dúvidas.
        </p>

    </section>

    <section class="painel">

        <div class="painel-topo">
            <span class="status"></span>

            <div>
                <strong>Assistente de estudos</strong>
                <small>Inteligência artificial do Studia</small>
            </div>
        </div>

        <div id="conversa" aria-live="polite">

            <div class="mensagem">
                <div class="avatar">IA</div>

                <div class="balao">
                    Olá! Eu sou o studIA. Como posso ajudar você hoje?
                    Pode me pedir explicações, resumos, exemplos ou ajuda
                    para organizar seus estudos.
                </div>
            </div>

        </div>

        <div class="sugestoes" id="sugestoes">

            <button
                class="sugestao"
                type="button"
                data-pergunta="Explique o que é inteligência artificial de forma simples."
            >
                Explique um assunto
            </button>

            <button
                class="sugestao"
                type="button"
                data-pergunta="Me ajude a criar um plano de estudos para esta semana."
            >
                Criar plano de estudos
            </button>

            <button
                class="sugestao"
                type="button"
                data-pergunta="Me dê dicas para memorizar melhor o conteúdo das aulas."
            >
                Dicas de aprendizagem
            </button>

        </div>

        <div class="entrada-area">

            <form id="formulario">

                <div class="entrada">

                    <textarea
                        id="pergunta"
                        placeholder="Escreva sua pergunta..."
                        rows="1"
                        maxlength="3000"
                        required
                    ></textarea>

                    <button
                        id="enviar"
                        type="submit"
                        title="Enviar pergunta"
                    >
                        ↑
                    </button>

                </div>

            </form>

            <p class="aviso">
                O studIA pode cometer erros. Confira informações importantes.
            </p>

        </div>

    </section>

</main>

<script>
const formulario = document.getElementById("formulario");
const campoPergunta = document.getElementById("pergunta");
const conversa = document.getElementById("conversa");
const botaoEnviar = document.getElementById("enviar");
const sugestoes = document.getElementById("sugestoes");

function escaparHTML(texto) {
    return texto.replace(/[&<>"']/g, function(caractere) {
        const mapa = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        };

        return mapa[caractere];
    });
}

function formatarTexto(texto) {
    let seguro = escaparHTML(texto);

    seguro = seguro.replace(/^### (.*)$/gm, "<h3>$1</h3>");
    seguro = seguro.replace(/^## (.*)$/gm, "<h2>$1</h2>");
    seguro = seguro.replace(/^# (.*)$/gm, "<h1>$1</h1>");
    seguro = seguro.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
    seguro = seguro.replace(/\*(.*?)\*/g, "<em>$1</em>");
    seguro = seguro.replace(/`([^`]+)`/g, "<code>$1</code>");

    const linhas = seguro.split("\n");
    let resultado = "";
    let listaAberta = false;

    linhas.forEach(function(linha) {
        const item = linha.match(/^\s*[-*]\s+(.+)$/);

        if (item) {
            if (!listaAberta) {
                resultado += "<ul>";
                listaAberta = true;
            }

            resultado += "<li>" + item[1] + "</li>";

        } else {
            if (listaAberta) {
                resultado += "</ul>";
                listaAberta = false;
            }

            if (linha.trim() !== "") {
                if (/^<h[1-3]>/.test(linha)) {
                    resultado += linha;
                } else {
                    resultado += "<p>" + linha + "</p>";
                }
            }
        }
    });

    if (listaAberta) {
        resultado += "</ul>";
    }

    return resultado;
}

function adicionarMensagem(texto, tipo, formatar = false) {
    const mensagem = document.createElement("div");
    mensagem.className = "mensagem " + tipo;

    const avatar = document.createElement("div");
    avatar.className = "avatar";
    avatar.textContent = tipo === "usuario" ? "EU" : "IA";

    const balao = document.createElement("div");
    balao.className = "balao";

    if (formatar) {
        balao.innerHTML = formatarTexto(texto);
    } else {
        balao.textContent = texto;
    }

    mensagem.appendChild(avatar);
    mensagem.appendChild(balao);

    conversa.appendChild(mensagem);
    conversa.scrollTop = conversa.scrollHeight;

    return mensagem;
}

async function enviarPergunta(pergunta) {
    pergunta = pergunta.trim();

    if (!pergunta || botaoEnviar.disabled) {
        return;
    }

    adicionarMensagem(pergunta, "usuario");

    campoPergunta.value = "";
    campoPergunta.style.height = "38px";

    sugestoes.style.display = "none";
    botaoEnviar.disabled = true;

    const carregando = document.createElement("div");
    carregando.className = "digitando";
    carregando.textContent = "studIA está preparando uma resposta...";

    conversa.appendChild(carregando);
    conversa.scrollTop = conversa.scrollHeight;

    try {
        const resposta = await fetch("assistente_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                pergunta: pergunta
            })
        });

        const dados = await resposta.json();

        carregando.remove();

        if (!resposta.ok || !dados.sucesso) {
            adicionarMensagem(
                dados.mensagem || "Não foi possível obter uma resposta. Tente novamente.",
                "assistente"
            );

            return;
        }

        adicionarMensagem(dados.mensagem, "assistente", true);

    } catch (erro) {
        carregando.remove();

        adicionarMensagem(
            "Não foi possível conectar ao assistente. Verifique sua conexão e tente novamente.",
            "assistente"
        );

    } finally {
        botaoEnviar.disabled = false;
        campoPergunta.focus();
    }
}

formulario.addEventListener("submit", function(evento) {
    evento.preventDefault();
    enviarPergunta(campoPergunta.value);
});

campoPergunta.addEventListener("input", function() {
    this.style.height = "38px";
    this.style.height = Math.min(this.scrollHeight, 120) + "px";
});

campoPergunta.addEventListener("keydown", function(evento) {
    if (evento.key === "Enter" && !evento.shiftKey) {
        evento.preventDefault();
        formulario.requestSubmit();
    }
});

document.querySelectorAll(".sugestao").forEach(function(botao) {
    botao.addEventListener("click", function() {
        enviarPergunta(this.dataset.pergunta);
    });
});
</script>

</body>
</html>