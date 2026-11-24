
<?php
session_start();

require_once "../configuracoes/conexao.php";
require_once "../configuracoes/email.php";
require_once "../logs/registro.php";

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = "Informe um endereço de e-mail válido.";
        $tipo_mensagem = "erro";

        registrarLog(
            "E-mail inválido na recuperação de senha",
            "Foi informado um endereço de e-mail inválido."
        );

    } else {

        $consulta = $conexao->prepare(
            "SELECT id FROM usuarios WHERE email = :email"
        );

        $consulta->execute([":email" => $email]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {

            // Invalida solicitações anteriores
            $invalidar = $conexao->prepare(
                "UPDATE recuperacao_senha
                 SET utilizado = 1
                 WHERE usuario_id = :id AND utilizado = 0"
            );

            $invalidar->execute([":id" => $usuario["id"]]);

            // Gera um token seguro
            $token = bin2hex(random_bytes(32));
            $token_hash = hash("sha256", $token);

            // Validade de 30 minutos
            $expiracao = date("Y-m-d H:i:s", time() + 1800);

            // Salva somente o hash no banco
            $inserir = $conexao->prepare(
                "INSERT INTO recuperacao_senha
                 (usuario_id, token, expiracao, utilizado)
                 VALUES (:usuario_id, :token, :expiracao, 0)"
            );

            $inserir->execute([
                ":usuario_id" => $usuario["id"],
                ":token" => $token_hash,
                ":expiracao" => $expiracao
            ]);

            // Monta o endereço da página de redefinição
            $protocolo = (
                !empty($_SERVER["HTTPS"]) &&
                $_SERVER["HTTPS"] !== "off"
            ) ? "https" : "http";

            $diretorio = rtrim(
                str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])),
                "/"
            );

            $link_recuperacao = $protocolo .
                "://" . $_SERVER["HTTP_HOST"] .
                $diretorio .
                "/redefinir_senha.php?token=" .
                urlencode($token);

            // Conteúdo do e-mail
            $emailHtml = '
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Recuperação de senha</title>
            </head>
            <body style="margin:0;padding:30px 15px;background:#f6f5fa;font-family:Arial,sans-serif;color:#302650;">

                <div style="max-width:520px;margin:0 auto;background:#ffffff;padding:35px;border-radius:18px;">

                    <h1 style="margin:0 0 25px;font-size:30px;color:#302650;">
                        studia<span style="color:#8873c8;">.</span>
                    </h1>

                    <h2 style="font-size:23px;margin-bottom:15px;">
                        Recuperação de senha
                    </h2>

                    <p style="font-size:15px;line-height:1.7;color:#696477;">
                        Recebemos uma solicitação para redefinir a senha da sua conta no Studia.
                    </p>

                    <p style="font-size:15px;line-height:1.7;color:#696477;">
                        Clique no botão abaixo para criar uma nova senha:
                    </p>

                    <div style="text-align:center;margin:30px 0;">
                        <a href="' . htmlspecialchars($link_recuperacao, ENT_QUOTES, "UTF-8") . '"
                           style="display:inline-block;padding:15px 28px;background:#705bb0;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:bold;">
                            Redefinir minha senha
                        </a>
                    </div>

                    <p style="font-size:13px;line-height:1.6;color:#898398;">
                        Este link é válido por 30 minutos e só pode ser utilizado uma vez.
                    </p>

                    <p style="font-size:13px;line-height:1.6;color:#898398;">
                        Se você não solicitou essa alteração, basta ignorar este e-mail.
                    </p>

                    <hr style="border:none;border-top:1px solid #eeeaf5;margin:25px 0;">

                    <p style="font-size:12px;color:#aaa3b8;text-align:center;">
                        Studia — Sua organização acadêmica em um só lugar.
                    </p>

                </div>
            </body>
            </html>
            ';

            // Envia pelo SMTP do Brevo
            $enviado = enviarEmailStudia(
                $email,
                "Recuperação de senha | Studia",
                $emailHtml
            );

            if (!$enviado) {
                // Invalida o token caso o envio falhe
                $cancelar = $conexao->prepare(
                    "UPDATE recuperacao_senha
                     SET utilizado = 1
                     WHERE token = :token"
                );

                $cancelar->execute([
                    ":token" => $token_hash
                ]);

                registrarLog(
                    "Falha no envio de recuperação de senha",
                    "O SMTP não conseguiu enviar o e-mail de recuperação."
                );

                error_log(
                    "Studia: não foi possível enviar o e-mail de recuperação."
                );

            } else {

                registrarLog(
                    "E-mail de recuperação enviado",
                    "Solicitação de recuperação enviada para uma conta cadastrada."
                );
            }

        } else {

            registrarLog(
                "Recuperação solicitada para e-mail não cadastrado",
                "Foi solicitada recuperação para um endereço sem conta correspondente."
            );
        }

        // Não revela se o endereço está cadastrado
        $mensagem = "Se esse e-mail estiver cadastrado, você receberá uma mensagem com as instruções para recuperar sua senha. Verifique também a caixa de spam.";
        $tipo_mensagem = "sucesso";
    }
}

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha | Studia</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            min-height: 100vh;
            background: #f6f5fa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .cartao {
            width: 100%;
            max-width: 460px;
            background: white;
            padding: 45px;
            border-radius: 22px;
            box-shadow: 0 12px 45px rgba(45, 34, 75, .08);
        }

        .logo {
            font-size: 31px;
            font-weight: 800;
            color: #302650;
            margin-bottom: 35px;
            letter-spacing: -1px;
        }

        .logo span {
            color: #8873c8;
        }

        .icone {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            background: #eee9ff;
            color: #6551a1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            margin-bottom: 22px;
        }

        h1 {
            color: #302650;
            font-size: 25px;
            margin-bottom: 10px;
        }

        .descricao {
            color: #898398;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #514b62;
            margin-bottom: 9px;
        }

        input {
            width: 100%;
            padding: 15px;
            border: 1px solid #e7e3ef;
            border-radius: 11px;
            outline: none;
            font-size: 14px;
            background: #fcfbfe;
            margin-bottom: 20px;
        }

        input:focus {
            border-color: #8873c8;
            box-shadow: 0 0 0 3px rgba(136,115,200,.12);
        }

        button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(135deg, #6551a1, #8b76ca);
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(101,81,161,.22);
        }

        .mensagem {
            padding: 14px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
            overflow-wrap: anywhere;
        }

        .mensagem.sucesso {
            background: #e8f7ee;
            color: #247447;
        }

        .mensagem.erro {
            background: #fff0f0;
            color: #b33b3b;
        }

        .voltar {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #7565a5;
            text-decoration: none;
            font-size: 13px;
        }

        .voltar:hover {
            text-decoration: underline;
        }

        @media (max-width: 500px) {
            .cartao {
                padding: 30px 23px;
            }
        }
    </style>
</head>

<body>

    <main class="cartao">

        <div class="logo">studia<span>.</span></div>

        <div class="icone">↻</div>

        <h1>Esqueceu sua senha?</h1>

        <p class="descricao">
            Não se preocupe! Informe o e-mail associado à sua conta
            para iniciar a recuperação da sua senha.
        </p>

        <?php if ($mensagem !== ""): ?>

            <div class="mensagem <?= escapar($tipo_mensagem) ?>">
                <?= escapar($mensagem) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <label for="email">E-mail cadastrado</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="seuemail@exemplo.com"
                maxlength="150"
                required
            >

            <button type="submit">
                Solicitar recuperação
            </button>

        </form>

        <a href="login.php" class="voltar">
            ← Voltar para o login
        </a>

    </main>

</body>
</html>
