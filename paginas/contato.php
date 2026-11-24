
<?php
session_start();

require_once "../configuracoes/email.php";
require_once "../logs/registro.php";

$destinatario = "contatogrupostudia@gmail.com";

$mensagem = "";
$tipo_mensagem = "";

$nome = "";
$email = "";
$assunto = "";
$texto = "";

if (empty($_SESSION["token_contato"])) {
    $_SESSION["token_contato"] = bin2hex(random_bytes(32));
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function receberCampoContato($campo)
{
    $valor = $_POST[$campo] ?? "";

    return is_string($valor) ? trim($valor) : "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = receberCampoContato("nome");
    $email = strtolower(receberCampoContato("email"));
    $assunto = receberCampoContato("assunto");
    $texto = receberCampoContato("mensagem");
    $token = receberCampoContato("token");

    if (
        !is_string($_SESSION["token_contato"]) ||
        !hash_equals($_SESSION["token_contato"], $token)
    ) {

        $mensagem = "Sessão inválida. Atualize a página e tente novamente.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha de segurança no contato",
            "Token CSRF inválido."
        );

    } elseif (
        $nome === "" ||
        $email === "" ||
        $assunto === "" ||
        $texto === ""
    ) {

        $mensagem = "Preencha todos os campos.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha no formulário de contato",
            "Campos obrigatórios vazios."
        );

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = "Informe um e-mail válido.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha no formulário de contato",
            "E-mail inválido."
        );

    } elseif (
        strlen($nome) > 100 ||
        strlen($email) > 150 ||
        strlen($assunto) > 150 ||
        strlen($texto) > 5000
    ) {

        $mensagem = "Um dos campos ultrapassou o limite permitido.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha no formulário de contato",
            "Limite de caracteres ultrapassado."
        );

    } elseif (
        preg_match('/[\r\n]/', $assunto) ||
        preg_match('/[\r\n]/', $nome)
    ) {

        $mensagem = "Não utilize quebras de linha no nome ou no assunto.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha no formulário de contato",
            "Conteúdo inválido no nome ou assunto."
        );

    } elseif (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {

        $mensagem = "O destinatário do e-mail não está configurado corretamente.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha no envio de contato",
            "Endereço destinatário inválido."
        );

    } else {

        $nome_html = escapar($nome);
        $email_html = escapar($email);
        $assunto_html = escapar($assunto);
        $texto_html = nl2br(escapar($texto));

        $emailHtml = '
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>Nova mensagem de contato</title>
        </head>

        <body style="margin:0;padding:30px 15px;background:#f6f5fa;font-family:Arial,sans-serif;color:#302650;">

            <div style="max-width:600px;margin:0 auto;background:#ffffff;padding:35px;border-radius:18px;">

                <h1 style="font-size:28px;color:#302650;">
                    studia<span style="color:#8873c8;">.</span>
                </h1>

                <h2>Nova mensagem de contato</h2>

                <p>Uma nova mensagem foi enviada pelo formulário de contato do Studia.</p>

                <div style="background:#f6f5fa;padding:20px;border-radius:12px;margin:25px 0;">

                    <p><strong>Nome:</strong> ' . $nome_html . '</p>
                    <p><strong>E-mail:</strong> ' . $email_html . '</p>
                    <p><strong>Assunto:</strong> ' . $assunto_html . '</p>

                    <hr style="border:none;border-top:1px solid #ddd8e9;margin:20px 0;">

                    <p><strong>Mensagem:</strong></p>
                    <p style="line-height:1.7;">' . $texto_html . '</p>

                </div>

                <p style="font-size:12px;color:#aaa3b8;text-align:center;">
                    Studia — Sua organização acadêmica em um só lugar.
                </p>

            </div>

        </body>
        </html>
        ';

        $enviado = enviarEmailStudia(
            $destinatario,
            "Contato pelo Studia: " . $assunto,
            $emailHtml
        );

        if ($enviado) {

            $mensagem = "Mensagem enviada com sucesso!";
            $tipo_mensagem = "sucesso";

            registrarLog(
                "Mensagem de contato enviada",
                "Assunto: " . $assunto . " | Remetente: " . $email
            );

            $nome = "";
            $email = "";
            $assunto = "";
            $texto = "";

            $_SESSION["token_contato"] = bin2hex(random_bytes(32));

        } else {

            $mensagem = "Não foi possível enviar a mensagem. Tente novamente mais tarde.";
            $tipo_mensagem = "erro";

            registrarLog(
                "Falha no envio de contato",
                "Falha no SMTP do Brevo. Assunto: " . $assunto
            );
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contato | Studia</title>

    <meta
        name="description"
        content="Entre em contato com o Studia para enviar dúvidas, sugestões ou relatar problemas na plataforma."
    >

    <meta
        name="plagiarism"
        content="Conteúdo original desenvolvido para o projeto acadêmico Studia."
    >

    <meta property="og:title" content="Contato | Studia">
    <meta
        property="og:description"
        content="Entre em contato com o Studia. Envie dúvidas, sugestões ou relate problemas."
    >
    <meta property="og:type" content="website">
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
        }

        /* CABEÇALHO */

        header {
            width: 100%;
            padding: 22px 8%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 18px rgba(48, 38, 80, 0.05);
        }

        .logo {
            color: #302650;
            font-size: 29px;
            font-weight: 800;
            text-decoration: none;
            letter-spacing: -1px;
        }

        .logo span {
            color: #8873c8;
        }

        .voltar {
            color: #6551a1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 11px 18px;
            border: 1px solid #e7def5;
            border-radius: 9px;
            transition: 0.2s;
        }

        .voltar:hover {
            background: #eee9fa;
        }

        /* CONTEÚDO */

        main {
            width: 100%;
            padding: 65px 8% 85px;
        }

        .introducao {
            max-width: 700px;
            margin: 0 auto 35px;
            text-align: center;
        }

        .etiqueta {
            display: inline-block;
            background: #e9e2f6;
            color: #6551a1;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 17px;
            letter-spacing: 0.5px;
        }

        .introducao h1 {
            font-size: 36px;
            font-weight: 750;
            letter-spacing: -1px;
            margin-bottom: 12px;
            color: #302650;
        }

        .introducao p {
            color: #777185;
            font-size: 15px;
            line-height: 1.7;
        }

        /* CARTÃO PRINCIPAL */

        .container {
            width: 100%;
            max-width: 950px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
            background: #ffffff;
            border: 1px solid #e7def5;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(76, 57, 125, 0.08);
        }

        /* LADO INFORMATIVO */

        .informacoes {
            padding: 45px 35px;
            background: linear-gradient(145deg, #e9e2f6, #f5f1fb);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .icone-contato {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            background: #ffffff;
            color: #7560c5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(76, 57, 125, 0.08);
        }

        .informacoes h2 {
            font-size: 23px;
            font-weight: 750;
            margin-bottom: 14px;
            color: #302650;
        }

        .informacoes p {
            color: #777185;
            font-size: 14px;
            line-height: 1.8;
        }

        .detalhe {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #dcd4ef;
            color: #6551a1;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 600;
        }

        /* FORMULÁRIO */

        .formulario {
            padding: 45px;
        }

        .formulario h2 {
            font-size: 23px;
            font-weight: 750;
            margin-bottom: 8px;
            color: #302650;
        }

        .subtitulo {
            color: #9690a5;
            font-size: 13px;
            margin-bottom: 28px;
        }

        .campo {
            margin-bottom: 19px;
        }

        .campo label {
            display: block;
            font-size: 13px;
            font-weight: 650;
            color: #51417c;
            margin-bottom: 8px;
        }

        .campo input,
        .campo textarea {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #e7e3ef;
            border-radius: 11px;
            background: #fcfbfe;
            color: #302650;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        .campo input::placeholder,
        .campo textarea::placeholder {
            color: #aaa4b8;
        }

        .campo input:focus,
        .campo textarea:focus {
            border-color: #8873c8;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(136, 115, 200, 0.10);
        }

        .campo textarea {
            resize: vertical;
            min-height: 140px;
        }

        /* BOTÃO */

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(120deg, #6551a1, #8b76ca);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 5px 13px rgba(101, 81, 161, 0.18);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(101, 81, 161, 0.23);
        }

        /* AVISOS */

        .aviso {
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }

        .sucesso {
            background: #e5f7ec;
            color: #236b3d;
            border: 1px solid #c8ecd4;
        }

        .erro {
            background: #fdeaea;
            color: #a12c2c;
            border: 1px solid #f5cece;
        }

        /* RESPONSIVIDADE */

        @media (max-width: 800px) {
            main {
                padding: 45px 5% 65px;
            }

            .container {
                grid-template-columns: 1fr;
            }

            .informacoes {
                padding: 30px;
            }

            .formulario {
                padding: 35px 30px;
            }

            .introducao h1 {
                font-size: 30px;
            }
        }

        @media (max-width: 500px) {
            header {
                padding: 18px 5%;
            }

            .logo {
                font-size: 25px;
            }

            .voltar {
                padding: 9px 12px;
                font-size: 12px;
            }

            .introducao h1 {
                font-size: 27px;
            }

            .formulario {
                padding: 28px 22px;
            }

            .informacoes {
                padding: 28px 22px;
            }
        }
    </style>
</head>

<body>

<header>
    <a href="../index.php" class="logo">studia<span>.</span></a>

    <a href="../index.php" class="voltar">
        Voltar ao início
    </a>
</header>

<main>

    <section class="introducao">
        <span class="etiqueta">FALE COM A GENTE</span>

        <h1>Vamos conversar?</h1>

        <p>
            Estamos aqui para ouvir você. Envie suas dúvidas,
            sugestões ou conte se encontrou algum problema no Studia.
        </p>
    </section>

    <div class="container">

        <section class="informacoes">

            <div class="icone-contato">✉</div>

            <h2>Estamos por aqui!</h2>

            <p>
                Sua opinião é importante para melhorar o Studia.
                Preencha o formulário ao lado e envie sua mensagem.
            </p>

            <div class="detalhe">
                Studia — Sua organização acadêmica em um só lugar.
            </div>

        </section>

        <section class="formulario">

            <h2>Entre em contato</h2>

            <p class="subtitulo">
                Preencha os campos abaixo para enviar sua mensagem.
            </p>

            <?php if ($mensagem !== ""): ?>
                <div class="aviso <?php echo escapar($tipo_mensagem); ?>">
                    <?php echo escapar($mensagem); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo escapar($_SESSION["token_contato"]); ?>"
                >

                <div class="campo">
                    <label for="nome">Nome</label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="100"
                        placeholder="Digite seu nome"
                        value="<?php echo escapar($nome); ?>"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="email">E-mail</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        placeholder="seuemail@exemplo.com"
                        value="<?php echo escapar($email); ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="assunto">Assunto</label>

                    <input
                        type="text"
                        id="assunto"
                        name="assunto"
                        maxlength="150"
                        placeholder="Sobre o que deseja falar?"
                        value="<?php echo escapar($assunto); ?>"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="mensagem">Mensagem</label>

                    <textarea
                        id="mensagem"
                        name="mensagem"
                        maxlength="5000"
                        placeholder="Escreva sua mensagem aqui..."
                        required
                    ><?php echo escapar($texto); ?></textarea>
                </div>

                <button type="submit">
                    Enviar mensagem
                </button>

            </form>

        </section>

    </div>

</main>

</body>
</html>
