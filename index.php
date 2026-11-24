<?php
// ==================================================
// PÁGINA INICIAL PÚBLICA DO STUDIA
// ==================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Registro de acesso ao sistema
require_once __DIR__ . "/logs/registro.php";

registrarLog(
    "ACESSO_PAGINA_PUBLICA",
    "Acessou a página inicial do Studia"
);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Studia | Organize seus estudos</title>

    <!-- SEO -->
    <meta name="description" content="Organize suas disciplinas, tarefas, materiais e grupos de estudo em um só lugar com o Studia. Simplifique sua rotina acadêmica.">
    <meta name="keywords" content="Studia, organização acadêmica, estudos, disciplinas, tarefas, materiais, grupos de estudo">
    <meta name="author" content="Studia">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#302650">

    <!-- OPEN GRAPH -->
    <meta property="og:title" content="Studia | Organize seus estudos">
    <meta property="og:description" content="Seu espaço para organizar disciplinas, acompanhar tarefas, guardar materiais e planejar sua rotina acadêmica.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Studia">
    <meta property="og:locale" content="pt_BR">

    <!-- TWITTER / X -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Studia | Organize seus estudos">
    <meta name="twitter:description" content="Organize sua rotina acadêmica com disciplinas, tarefas, materiais e grupos de estudo em um só lugar.">

    <!-- FONTE -->
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

        html {
            scroll-behavior: smooth;
        }

        body {
            background: #f1edf9;
            color: #302650;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* CABEÇALHO */

        header {
            width: 100%;
            padding: 22px 8%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            background: #e9e2f6;
            border-bottom: 1px solid #ded4ef;
        }

        .logo {
            color: #302650;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -1.5px;
            flex-shrink: 0;
        }

        .logo span {
            color: #8873c8;
        }

        .navegacao {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 20px;
        }

        .navegacao a {
            color: #716486;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .navegacao a:hover {
            color: #7560c5;
        }

        .navegacao .botao-entrar {
            color: #6551a1;
            padding: 11px 20px;
            border: 1px solid #cfc2e6;
            border-radius: 9px;
            background: #f5f1fc;
        }

        .navegacao .botao-cadastro {
            color: white;
            padding: 12px 21px;
            background: #7560c5;
            border-radius: 9px;
        }

        .navegacao .botao-cadastro:hover {
            background: #5e49ad;
            color: white;
        }

        /* SEÇÃO PRINCIPAL */

        .principal {
            min-height: 620px;
            padding: 80px 8%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 60px;
            overflow: hidden;
            background:
                radial-gradient(circle at 85% 20%, rgba(188, 169, 230, .35), transparent 30%),
                linear-gradient(135deg, #f4f0fb, #e8e0f5);
        }

        .conteudo-principal {
            max-width: 570px;
        }

        .etiqueta {
            display: inline-block;
            padding: 9px 15px;
            margin-bottom: 25px;
            border-radius: 30px;
            background: #ded3f3;
            color: #6551a1;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .conteudo-principal h1 {
            color: #302650;
            font-size: 56px;
            line-height: 1.12;
            letter-spacing: -2px;
            margin-bottom: 23px;
        }

        .conteudo-principal h1 span {
            color: #806bc1;
        }

        .conteudo-principal p {
            color: #777087;
            font-size: 16px;
            line-height: 1.9;
            max-width: 490px;
            margin-bottom: 32px;
        }

        .botoes-principais {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .botao-principal {
            display: inline-block;
            padding: 16px 25px;
            background: linear-gradient(135deg, #6551a1, #8b76ca);
            color: white;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 700;
            transition: .2s;
        }

        .botao-principal:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(101, 81, 161, .2);
        }

        .botao-secundario {
            display: inline-block;
            padding: 15px 24px;
            color: #6551a1;
            border: 1px solid #cfc2e6;
            background: rgba(255,255,255,.35);
            border-radius: 11px;
            font-size: 14px;
            font-weight: 700;
            transition: .2s;
        }

        .botao-secundario:hover {
            background: #e2d8f3;
        }

        /* ILUSTRAÇÃO */

        .ilustracao {
            width: 440px;
            min-height: 390px;
            flex-shrink: 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .circulo {
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: linear-gradient(145deg, #d7c9f0, #bca9e5);
            position: absolute;
        }

        .cartao-estudos {
            position: relative;
            z-index: 2;
            width: 310px;
            padding: 27px;
            background: #fdfbff;
            border: 1px solid #e7def5;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(48, 38, 80, .16);
            transform: rotate(-3deg);
        }

        .cartao-estudos h3 {
            color: #302650;
            font-size: 18px;
            margin-bottom: 7px;
        }

        .cartao-estudos > p {
            color: #9690a5;
            font-size: 12px;
            margin-bottom: 24px;
        }

        .atividade {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 0;
            border-bottom: 1px solid #eee8f6;
        }

        .atividade:last-child {
            border-bottom: none;
        }

        .icone-atividade {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: #e9e0f8;
            color: #7560c5;
            font-size: 17px;
        }

        .atividade strong {
            display: block;
            color: #514b62;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .atividade small {
            color: #aaa5b8;
            font-size: 11px;
        }

        .selo {
            position: absolute;
            z-index: 3;
            right: 0;
            bottom: 35px;
            padding: 15px 20px;
            background: #fdfbff;
            border: 1px solid #e7def5;
            border-radius: 13px;
            box-shadow: 0 10px 30px rgba(48, 38, 80, .12);
            color: #6551a1;
            font-size: 12px;
            font-weight: 700;
        }

        /* SEÇÃO DE RECURSOS */

        .recursos {
            padding: 80px 8%;
            background: #e9e2f5;
            text-align: center;
        }

        .titulo-secao {
            color: #302650;
            font-size: 32px;
            margin-bottom: 12px;
        }

        .subtitulo-secao {
            color: #817890;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 45px;
        }

        .grade-recursos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .recurso {
            padding: 30px 25px;
            background: #f6f2fc;
            border: 1px solid #e2d8f1;
            border-radius: 17px;
            text-align: left;
            transition: .2s;
        }

        .recurso:hover {
            transform: translateY(-5px);
            background: #fcfaff;
            box-shadow: 0 12px 30px rgba(48, 38, 80, .08);
        }

        .icone-recurso {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2d7f4;
            border-radius: 13px;
            color: #7560c5;
            font-size: 21px;
            margin-bottom: 20px;
        }

        .recurso h3 {
            color: #40365e;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .recurso p {
            color: #817a91;
            font-size: 13px;
            line-height: 1.8;
        }

        /* CHAMADA FINAL */

        .chamada {
            margin: 70px 8%;
            padding: 55px 30px;
            border-radius: 22px;
            background: linear-gradient(135deg, #302650, #65518f);
            text-align: center;
            color: white;
            box-shadow: 0 15px 35px rgba(48, 38, 80, .13);
        }

        .chamada h2 {
            font-size: 30px;
            margin-bottom: 13px;
        }

        .chamada p {
            color: #d8d0e9;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.7;
        }

        .chamada a {
            display: inline-block;
            padding: 14px 25px;
            background: #eee8fa;
            color: #6551a1;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .chamada a:hover {
            transform: translateY(-2px);
            background: white;
        }

        /* RODAPÉ */

        footer {
            padding: 30px 8%;
            background: #302650;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        footer p {
            color: #c9c0dc;
            font-size: 12px;
        }

        .links-rodape {
            display: flex;
            gap: 22px;
            flex-wrap: wrap;
        }

        .links-rodape a {
            color: #d5cce7;
            font-size: 12px;
            transition: .2s;
        }

        .links-rodape a:hover {
            color: white;
        }

        /* RESPONSIVIDADE */

        @media (max-width: 1100px) {
            header {
                flex-direction: column;
            }

            .navegacao {
                justify-content: center;
            }
        }

        @media (max-width: 950px) {
            .principal {
                flex-direction: column;
                text-align: center;
                padding-top: 65px;
            }

            .conteudo-principal p {
                margin-left: auto;
                margin-right: auto;
            }

            .botoes-principais {
                justify-content: center;
            }

            .ilustracao {
                width: 100%;
            }

            .grade-recursos {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 650px) {
            header {
                padding: 20px 6%;
                flex-direction: column;
                gap: 20px;
            }

            .navegacao {
                gap: 14px;
                justify-content: center;
            }

            .navegacao a {
                font-size: 12px;
            }

            .principal {
                padding: 55px 6%;
            }

            .conteudo-principal h1 {
                font-size: 39px;
            }

            .ilustracao {
                min-height: 340px;
            }

            .circulo {
                width: 270px;
                height: 270px;
            }

            .cartao-estudos {
                width: 280px;
                padding: 22px;
            }

            .selo {
                right: 0;
                bottom: 10px;
            }

            .recursos {
                padding: 60px 6%;
            }

            .grade-recursos {
                grid-template-columns: 1fr;
            }

            .titulo-secao {
                font-size: 26px;
            }

            .chamada {
                margin: 45px 6%;
                padding: 40px 22px;
            }

            .chamada h2 {
                font-size: 24px;
            }

            footer {
                flex-direction: column;
                text-align: center;
                padding: 25px 6%;
            }

            .links-rodape {
                justify-content: center;
            }
        }
    </style>

</head>

<body>

    <!-- CABEÇALHO -->

    <header>

        <a href="index.php" class="logo" aria-label="Studia - Página inicial">
            studia<span>.</span>
        </a>

        <nav class="navegacao" aria-label="Navegação principal">

            <a href="#recursos">Recursos</a>

            <a href="paginas/disciplinas_publicas.php">
                Disciplinas
            </a>

            <a href="paginas/grupos_publicos.php">
                Grupos de estudo
            </a>

            <a href="paginas/termos.php">
                Termos de Uso
            </a>

            <a href="paginas/contato.php">
                Contato
            </a>

            <a href="paginas/login.php" class="botao-entrar">
                Entrar
            </a>

            <a href="paginas/cadastro.php" class="botao-cadastro">
                Criar conta
            </a>

        </nav>

    </header>

    <!-- APRESENTAÇÃO PRINCIPAL -->

    <main>

        <section class="principal">

            <div class="conteudo-principal">

                <span class="etiqueta">
                    SEU ESPAÇO DE ESTUDOS
                </span>

                <h1>
                    Organize seus estudos.
                    <span>Conquiste seus objetivos.</span>
                </h1>

                <p>
                    O Studia é seu espaço para organizar disciplinas,
                    acompanhar tarefas, guardar materiais e planejar
                    sua rotina acadêmica de maneira simples.
                </p>

                <div class="botoes-principais">

                    <a href="paginas/cadastro.php" class="botao-principal">
                        Começar agora
                    </a>

                    <a href="paginas/login.php" class="botao-secundario">
                        Já tenho uma conta
                    </a>

                </div>

            </div>

            <!-- CARTÃO ILUSTRATIVO -->

            <div class="ilustracao" aria-label="Exemplo visual da organização de estudos no Studia">

                <div class="circulo"></div>

                <div class="cartao-estudos">

                    <h3>Minha rotina</h3>

                    <p>Um espaço para acompanhar seus estudos.</p>

                    <div class="atividade">

                        <div class="icone-atividade">▤</div>

                        <div>
                            <strong>Disciplinas</strong>
                            <small>Organize suas matérias</small>
                        </div>

                    </div>

                    <div class="atividade">

                        <div class="icone-atividade">✓</div>

                        <div>
                            <strong>Tarefas</strong>
                            <small>Acompanhe suas atividades</small>
                        </div>

                    </div>

                    <div class="atividade">

                        <div class="icone-atividade">▣</div>

                        <div>
                            <strong>Materiais</strong>
                            <small>Tenha seus arquivos organizados</small>
                        </div>

                    </div>

                    <div class="atividade">

                        <div class="icone-atividade">◎</div>

                        <div>
                            <strong>Grupos de estudo</strong>
                            <small>Estude em conjunto</small>
                        </div>

                    </div>

                </div>

                <div class="selo">
                    Seu progresso começa aqui.
                </div>

            </div>

        </section>

        <!-- RECURSOS DA PLATAFORMA -->

        <section class="recursos" id="recursos">

            <h2 class="titulo-secao">
                Tudo o que você precisa em um só lugar
            </h2>

            <p class="subtitulo-secao">
                Ferramentas para deixar sua rotina acadêmica mais organizada.
            </p>

            <div class="grade-recursos">

                <article class="recurso">

                    <div class="icone-recurso">▤</div>

                    <h3>Disciplinas</h3>

                    <p>
                        Cadastre e organize suas matérias,
                        mantendo suas informações reunidas.
                    </p>

                </article>

                <article class="recurso">

                    <div class="icone-recurso">✓</div>

                    <h3>Tarefas</h3>

                    <p>
                        Registre atividades, acompanhe prazos
                        e controle o andamento dos seus estudos.
                    </p>

                </article>

                <article class="recurso">

                    <div class="icone-recurso">▣</div>

                    <h3>Materiais</h3>

                    <p>
                        Armazene e organize arquivos e materiais
                        importantes para suas disciplinas.
                    </p>

                </article>

                <article class="recurso">

                    <div class="icone-recurso">◎</div>

                    <h3>Grupos de estudo</h3>

                    <p>
                        Crie grupos e participe de espaços
                        de estudo com outros usuários.
                    </p>

                </article>

                <article class="recurso">

                    <div class="icone-recurso">◉</div>

                    <h3>Perfil pessoal</h3>

                    <p>
                        Gerencie suas informações e personalize
                        sua conta dentro da plataforma.
                    </p>

                </article>

                <article class="recurso">

                    <div class="icone-recurso">✦</div>

                    <h3>Organização acadêmica</h3>

                    <p>
                        Reúna suas atividades e informações
                        em um ambiente pensado para seus estudos.
                    </p>

                </article>

            </div>

        </section>

        <!-- CHAMADA PARA CADASTRO -->

        <section class="chamada">

            <h2>Pronto para organizar sua rotina?</h2>

            <p>
                Crie sua conta gratuitamente e comece a utilizar o Studia.
            </p>

            <a href="paginas/cadastro.php">
                Criar minha conta
            </a>

        </section>

    </main>

    <!-- RODAPÉ -->

    <footer>

        <p>
            © <?php echo date("Y"); ?> Studia. Todos os direitos reservados.
        </p>

        <nav class="links-rodape" aria-label="Links do rodapé">

            <a href="paginas/disciplinas_publicas.php">
                Disciplinas públicas
            </a>

            <a href="paginas/grupos_publicos.php">
                Grupos públicos
            </a>

            <a href="paginas/login.php">
                Entrar
            </a>

            <a href="paginas/cadastro.php">
                Criar conta
            </a>

            <a href="paginas/termos.php">
                Termos e Privacidade
            </a>

            <a href="paginas/contato.php">
                Contato
            </a>

        </nav>

    </footer>

</body>
</html>