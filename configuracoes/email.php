
<?php

// =============================================
// CONFIGURAÇÕES DO E-MAIL - STUDIA
// =============================================

$segredos = require __DIR__ . "/segredos.php";

define("SMTP_SERVIDOR", "smtp-relay.brevo.com");
define("SMTP_PORTA", 587);
define("SMTP_USUARIO", "c4e661001@smtp-brevo.com");
define("SMTP_SENHA", $segredos["smtp_senha"]);

define("EMAIL_REMETENTE", "contatogrupostudia@gmail.com");
define("NOME_REMETENTE", "Studia");


function enviarEmailStudia($destinatario, $assunto, $mensagemHtml)
{
    $socket = @fsockopen(
        SMTP_SERVIDOR,
        SMTP_PORTA,
        $erroNumero,
        $erroTexto,
        20
    );

    if (!$socket) {
        error_log("Studia SMTP: falha na conexão: " . $erroTexto);
        return false;
    }

    stream_set_timeout($socket, 20);

    $lerResposta = function () use ($socket) {
        $resposta = "";

        do {
            $linha = fgets($socket, 515);

            if ($linha === false) {
                break;
            }

            $resposta .= $linha;

        } while (isset($linha[3]) && $linha[3] === "-");

        return $resposta;
    };

    $comando = function ($texto, $codigosEsperados) use ($socket, $lerResposta) {
        fwrite($socket, $texto . "\r\n");

        $resposta = $lerResposta();
        $codigo = substr($resposta, 0, 3);

        if (!in_array($codigo, $codigosEsperados, true)) {
            throw new Exception("Resposta SMTP inesperada: " . $resposta);
        }

        return $resposta;
    };

    try {
        $resposta = $lerResposta();

        if (substr($resposta, 0, 3) !== "220") {
            throw new Exception("Servidor SMTP não respondeu corretamente.");
        }

        $comando("EHLO localhost", ["250"]);
        $comando("STARTTLS", ["220"]);

        $tls = stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if ($tls !== true) {
            throw new Exception("Não foi possível iniciar o TLS.");
        }

        $comando("EHLO localhost", ["250"]);

        $comando("AUTH LOGIN", ["334"]);
        $comando(base64_encode(SMTP_USUARIO), ["334"]);
        $comando(base64_encode(SMTP_SENHA), ["235"]);

        $comando("MAIL FROM:<" . EMAIL_REMETENTE . ">", ["250"]);
        $comando("RCPT TO:<" . $destinatario . ">", ["250", "251"]);
        $comando("DATA", ["354"]);

        $assuntoCodificado = "=?UTF-8?B?" .
            base64_encode($assunto) .
            "?=";

        $cabecalhos = [
            "From: " . NOME_REMETENTE . " <" . EMAIL_REMETENTE . ">",
            "To: <" . $destinatario . ">",
            "Subject: " . $assuntoCodificado,
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit"
        ];

        $corpo = implode("\r\n", $cabecalhos) .
            "\r\n\r\n" .
            $mensagemHtml;

        $corpo = preg_replace('/(?m)^\./', '..', $corpo);

        fwrite($socket, $corpo . "\r\n.\r\n");

        $resposta = $lerResposta();

        if (substr($resposta, 0, 3) !== "250") {
            throw new Exception("O servidor não aceitou o e-mail.");
        }

        $comando("QUIT", ["221"]);

        fclose($socket);

        return true;

    } catch (Throwable $erro) {
        error_log("Studia SMTP: " . $erro->getMessage());

        if (is_resource($socket)) {
            fclose($socket);
        }

        return false;
    }
}
