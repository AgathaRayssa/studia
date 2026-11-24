
<?php

session_start();

require_once __DIR__ . "/../configuracoes/conexao.php";
require_once __DIR__ . "/../logs/registro.php";

registrarLog(
    "VISUALIZACAO_DISCIPLINAS_PUBLICAS",
    "Visitou a listagem pública de disciplinas."
);

$disciplinas = [];
$erro = "";

try {

    $consulta = $conexao->prepare("
        SELECT
            id,
            nome,
            descricao,
            cor
        FROM disciplinas
        ORDER BY nome ASC
    ");

    $consulta->execute();
    $disciplinas = $consulta->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    registrarLog(
        "ERRO_DISCIPLINAS_PUBLICAS",
        "Falha ao consultar disciplinas públicas."
    );

    $erro = "Não foi possível carregar as disciplinas neste momento.";
}

// Proteção contra HTML inserido nos dados.
function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Garante que a cor cadastrada seja um hexadecimal válido.
function corValida($cor)
{
    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $cor)) {
        return $cor;
    }

    return "#8873c8";
}

// Endereço atual da página para compartilhamento.
$endereco = (
    (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    ? "https://"
    : "http://"
) . ($_SERVER["HTTP_HOST"] ?? "localhost")
    . ($_SERVER["REQUEST_URI"] ?? "/");

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Disciplinas | Studia</title>

    <meta
        name="description"
        content="Conheça as disciplinas disponíveis na plataforma Studia e explore conteúdos para organizar sua aprendizagem."
    >

    <meta
        name="plagiarism"
        content="Conteúdo original desenvolvido para a plataforma Studia."
    >

    <meta property="og:title" content="Disciplinas | Studia">

    <meta
        property="og:description"
        content="Explore disciplinas e descubra novas oportunidades de aprendizagem."
    >

    <meta property="og:type" content="website">

    <meta
        property="og:url"
        content="<?php echo escapar($endereco); ?>"
    >

    <meta property="og:site_name" content="Studia">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f1edf9;
            color: #302650;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* CABEÇALHO */

        header {
            width: 100%;
            padding: 22px 8%;
            background: #ffffff;
            box-shadow: 0 3px 18px rgba(48, 38, 80, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            color: #302650;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -1px;
            text-decoration: none;
        }

        .logo span {
            color: #8873c8;
        }

        .acoes-topo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .voltar,
        .entrar {
            padding: 11px 17px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            transition: 0.2s;
        }

        .voltar {
            color: #6551a1;
            border: 1px solid #e7def5;
        }

        .voltar:hover {
            background: #eee9fa;
        }

        .entrar {
            color: #ffffff;
            background: linear-gradient(120deg, #6551a1, #8873c8);
            box-shadow: 0 4px 12px rgba(101, 81, 161, 0.15);
        }

        .entrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(101, 81, 161, 0.2);
        }

        /* CONTEÚDO PRINCIPAL */

        main {
            width: min(1100px, 90%);
            margin: 0 auto;
            padding: 60px 0 75px;
            flex: 1;
        }

        .introducao {
            text-align: center;
            margin-bottom: 40px;
        }

        .etiqueta {
            display: inline-block;
            background: #e9e2f6;
            color: #6551a1;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }

        .introducao h1 {
            color: #302650;
            font-size: 36px;
            font-weight: 750;
            letter-spacing: -1px;
            margin-bottom: 12px;
        }

        .introducao p {
            color: #777185;
            font-size: 15px;
            line-height: 1.8;
            max-width: 650px;
            margin: 0 auto;
        }

        /* CONTADOR */

        .contador {
            color: #777185;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 22px;
        }

        .contador strong {
            color: #6551a1;
        }

        /* GRADE DE DISCIPLINAS */

        .grade {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr));
            gap: 25px;
            align-items: stretch;
            width: 100%;
        }

        .disciplina {
            background: #ffffff;
            border: 1px solid #e7def5;
            border-radius: 17px;
            padding: 27px;
            box-shadow: 0 8px 25px rgba(48, 32, 92, 0.045);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            height: 100%;
        }

        .disciplina:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(48, 32, 92, 0.09);
        }

        .faixa {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }

        .icone {
            width: 49px;
            height: 49px;
            background: #eee9fa;
            color: #7560bd;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 23px;
            margin: 8px 0 20px;
            flex-shrink: 0;
        }

        .disciplina h2 {
            color: #302650;
            font-size: 19px;
            font-weight: 750;
            margin-bottom: 11px;
            overflow-wrap: anywhere;
        }

        .descricao {
            color: #777185;
            font-size: 14px;
            line-height: 1.8;
            overflow-wrap: anywhere;
            flex: 1;
        }

        /* ESTADOS VAZIOS E ERROS */

        .vazio,
        .erro {
            background: #ffffff;
            border: 1px solid #e7def5;
            border-radius: 17px;
            padding: 48px 25px;
            text-align: center;
            color: #777185;
            line-height: 1.8;
            box-shadow: 0 8px 25px rgba(48, 32, 92, 0.045);
        }

        .vazio-icone {
            width: 55px;
            height: 55px;
            background: #eee9fa;
            color: #7560bd;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 24px;
            margin: 0 auto 17px;
        }

        .vazio strong {
            display: block;
            color: #302650;
            font-size: 18px;
            margin-bottom: 8px;
        }

        /* RODAPÉ */

        footer {
            background: #ffffff;
            border-top: 1px solid #e7def5;
            padding: 24px 15px;
            text-align: center;
            color: #9690a5;
            font-size: 12px;
        }

        footer strong {
            color: #6551a1;
            font-weight: 700;
        }

        /* RESPONSIVIDADE */

        @media (max-width: 650px) {

            header {
                padding: 18px 5%;
            }

            .logo {
                font-size: 25px;
            }

            .acoes-topo {
                gap: 7px;
            }

            .voltar,
            .entrar {
                padding: 9px 11px;
                font-size: 11px;
            }

            main {
                width: 90%;
                padding: 42px 0 55px;
            }

            .introducao h1 {
                font-size: 29px;
            }

            .introducao p {
                font-size: 14px;
            }

            .grade {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .disciplina {
                padding: 23px;
            }
        }

        @media (max-width: 380px) {

            header {
                align-items: flex-start;
                gap: 10px;
            }

            .logo {
                font-size: 23px;
            }

            .acoes-topo {
                flex-direction: column;
                align-items: flex-end;
            }
        }

    </style>

</head>

<body>

<header>

    <a href="../index.php" class="logo">
        studia<span>.</span>
    </a>

    <div class="acoes-topo">

        <a href="../index.php" class="voltar">
            Voltar ao início
        </a>

        <a href="login.php" class="entrar">
            Acessar plataforma
        </a>

    </div>

</header>

<main>

    <section class="introducao">

        <span class="etiqueta">EXPLORE E APRENDA</span>

        <h1>Disciplinas</h1>

        <p>
            Conheça as disciplinas cadastradas na plataforma Studia
            e descubra novas oportunidades de aprendizagem.
        </p>

    </section>

    <?php if ($erro !== ""): ?>

        <div class="erro">
            <?php echo escapar($erro); ?>
        </div>

    <?php elseif (count($disciplinas) > 0): ?>

        <p class="contador">

            <strong><?php echo count($disciplinas); ?></strong>

            <?php
            echo count($disciplinas) === 1
                ? "disciplina encontrada"
                : "disciplinas encontradas";
            ?>

        </p>

        <section class="grade">

            <?php foreach ($disciplinas as $disciplina): ?>

                <article class="disciplina">

                    <div
                        class="faixa"
                        style="background-color: <?php echo escapar(corValida($disciplina["cor"])); ?>;"
                    ></div>

                    <div class="icone">✦</div>

                    <h2>
                        <?php echo escapar($disciplina["nome"]); ?>
                    </h2>

                    <p class="descricao">

                        <?php

                        echo !empty($disciplina["descricao"])
                            ? escapar($disciplina["descricao"])
                            : "Esta disciplina ainda não possui uma descrição.";

                        ?>

                    </p>

                </article>

            <?php endforeach; ?>

        </section>

    <?php else: ?>

        <div class="vazio">

            <div class="vazio-icone">✦</div>

            <strong>Nenhuma disciplina encontrada</strong>

            Ainda não existem disciplinas cadastradas na plataforma.

        </div>

    <?php endif; ?>

</main>

<footer>

    <strong>Studia</strong> © <?php echo date("Y"); ?> — Organização acadêmica mais simples.

</footer>

</body>
</html>
