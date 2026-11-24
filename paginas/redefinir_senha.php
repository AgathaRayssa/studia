
<?php
session_start();

require_once "../configuracoes/conexao.php";
require_once "../logs/registro.php";

$mensagem = "";
$tipo_mensagem = "";
$token = $_GET["token"] ?? $_POST["token"] ?? "";

$solicitacao = null;

// Verifica se o token possui o formato esperado
if (preg_match('/^[a-f0-9]{64}$/', $token)) {

    $token_hash = hash("sha256", $token);

    $consulta = $conexao->prepare(
        "SELECT r.id, r.usuario_id, r.expiracao, r.utilizado
         FROM recuperacao_senha r
         WHERE r.token = :token
         LIMIT 1"
    );

    $consulta->execute([":token" => $token_hash]);
    $solicitacao = $consulta->fetch(PDO::FETCH_ASSOC);
}

// Confere se a solicitação existe, não foi utilizada e não expirou
$token_valido = (
    $solicitacao &&
    !$solicitacao["utilizado"] &&
    strtotime($solicitacao["expiracao"]) >= time()
);

if (!$token_valido) {
    $mensagem = "Este link é inválido, já foi utilizado ou expirou. Solicite uma nova recuperação de senha.";
    $tipo_mensagem = "erro";

    registrarLog(
        "Link de recuperação inválido",
        "Tentativa de acessar redefinição com token inexistente, utilizado ou expirado."
    );
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $token_valido) {

    $senha = $_POST["senha"] ?? "";
    $confirmar_senha = $_POST["confirmar_senha"] ?? "";

    if (strlen($senha) < 8) {
        $mensagem = "A senha deve ter pelo menos 8 caracteres.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha na redefinição de senha",
            "A nova senha não atingiu o mínimo de 8 caracteres."
        );

    } elseif ($senha !== $confirmar_senha) {
        $mensagem = "As senhas informadas não são iguais.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha na redefinição de senha",
            "A confirmação da nova senha não corresponde."
        );

    } else {

        try {
            $conexao->beginTransaction();

            // Bloqueia a solicitação enquanto ela é validada
            $bloquear = $conexao->prepare(
                "SELECT id, usuario_id, expiracao, utilizado
                 FROM recuperacao_senha
                 WHERE id = :id
                 FOR UPDATE"
            );

            $bloquear->execute([":id" => $solicitacao["id"]]);
            $solicitacao_atual = $bloquear->fetch(PDO::FETCH_ASSOC);

            $ainda_valido = (
                $solicitacao_atual &&
                !$solicitacao_atual["utilizado"] &&
                strtotime($solicitacao_atual["expiracao"]) >= time()
            );

            if (!$ainda_valido) {
                throw new Exception("O link não é mais válido.");
            }

            // Criptografa a nova senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            // Atualiza a senha do usuário
            $atualizar = $conexao->prepare(
                "UPDATE usuarios
                 SET senha = :senha
                 WHERE id = :id"
            );

            $atualizar->execute([
                ":senha" => $senha_hash,
                ":id" => $solicitacao_atual["usuario_id"]
            ]);

            // Marca o token como utilizado
            $utilizar = $conexao->prepare(
                "UPDATE recuperacao_senha
                 SET utilizado = 1
                 WHERE id = :id"
            );

            $utilizar->execute([
                ":id" => $solicitacao_atual["id"]
            ]);

            // Invalida outras solicitações anteriores
            $outras = $conexao->prepare(
                "UPDATE recuperacao_senha
                 SET utilizado = 1
                 WHERE usuario_id = :usuario_id
                 AND id != :id"
            );

            $outras->execute([
                ":usuario_id" => $solicitacao_atual["usuario_id"],
                ":id" => $solicitacao_atual["id"]
            ]);

            $conexao->commit();

            registrarLog(
                "Senha redefinida",
                "A senha da conta do usuário ID " . $solicitacao_atual["usuario_id"] . " foi redefinida com sucesso."
            );

            $mensagem = "Sua senha foi alterada com sucesso! Agora você pode entrar na sua conta.";
            $tipo_mensagem = "sucesso";
            $token_valido = false;

        } catch (Exception $erro) {

            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }

            registrarLog(
                "Erro ao redefinir senha",
                "Falha durante a redefinição. Tipo: " . get_class($erro)
            );

            $mensagem = "Não foi possível alterar sua senha. Solicite um novo link de recuperação.";
            $tipo_mensagem = "erro";
            $token_valido = false;
        }
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
    <title>Redefinir senha | Studia</title>

    <!-- INÍCIO DO CSS -->
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

        .campo {
            margin-bottom: 20px;
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
        }

        input:focus {
            border-color: #8873c8;
            box-shadow: 0 0 0 3px rgba(136,115,200,.12);
        }

        .ajuda {
            font-size: 12px;
            color: #9993a8;
            margin-top: 7px;
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
    <!-- FIM DO CSS -->
</head>

<body>

    <main class="cartao">

        <div class="logo">studia<span>.</span></div>

        <div class="icone">⌑</div>

        <h1>Crie uma nova senha</h1>

        <p class="descricao">
            Escolha uma nova senha para recuperar o acesso à sua conta.
        </p>

        <?php if ($mensagem !== ""): ?>

            <div class="mensagem <?= escapar($tipo_mensagem) ?>">
                <?= escapar($mensagem) ?>
            </div>

        <?php endif; ?>

        <?php if ($token_valido): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="token"
                    value="<?= escapar($token) ?>"
                >

                <div class="campo">
                    <label for="senha">Nova senha</label>

                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Digite sua nova senha"
                        required
                    >

                    <p class="ajuda">
                        Utilize pelo menos 8 caracteres.
                    </p>
                </div>

                <div class="campo">
                    <label for="confirmar_senha">Confirmar nova senha</label>

                    <input
                        type="password"
                        id="confirmar_senha"
                        name="confirmar_senha"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Digite novamente sua senha"
                        required
                    >
                </div>

                <button type="submit">
                    Redefinir senha
                </button>

            </form>

        <?php else: ?>

            <a href="recuperar_senha.php" class="voltar">
                Solicitar outro link de recuperação
            </a>

        <?php endif; ?>

        <a href="login.php" class="voltar">
            ← Voltar para o login
        </a>

    </main>

</body>
</html>
