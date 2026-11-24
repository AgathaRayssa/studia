
<?php

// ==================================================
// CADASTRO DE USUÁRIOS - STUDIA
// ==================================================

session_start();

require_once "../configuracoes/conexao.php";
require_once "../logs/registro.php";

$mensagem = "";
$tipo_mensagem = "";

$nome = "";
$email = "";

if (empty($_SESSION["token_cadastro"])) {
    $_SESSION["token_cadastro"] = bin2hex(random_bytes(32));
}

// ==================================================
// PROCESSAMENTO DO FORMULÁRIO
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = trim($_POST["nome"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $senha = $_POST["senha"] ?? "";
    $confirmar_senha = $_POST["confirmar_senha"] ?? "";
    $aceitar_termos = $_POST["aceitar_termos"] ?? "";
    $token = $_POST["token_cadastro"] ?? "";

    $erros = [];

    // Proteção CSRF
    if (
        !is_string($token) ||
        !hash_equals($_SESSION["token_cadastro"], $token)
    ) {
        $erros[] = "Sua sessão expirou ou o formulário é inválido. Atualize a página e tente novamente.";
    }

    // Validação do nome
    if ($nome === "") {
        $erros[] = "Informe seu nome.";
    } elseif (mb_strlen($nome) > 100) {
        $erros[] = "O nome deve ter no máximo 100 caracteres.";
    }

    // Validação do e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Informe um e-mail válido.";
    } elseif (mb_strlen($email) > 150) {
        $erros[] = "O e-mail deve ter no máximo 150 caracteres.";
    }

    // Validação da senha
    if (strlen($senha) < 8) {
        $erros[] = "A senha deve ter pelo menos 8 caracteres.";
    }

    if ($senha !== $confirmar_senha) {
        $erros[] = "As senhas informadas não são iguais.";
    }

    // Aceitação obrigatória dos termos
    if ($aceitar_termos !== "1") {
        $erros[] = "Você precisa aceitar os Termos de Uso e a Política de Privacidade.";
    }

    // Verifica se o e-mail já está cadastrado
    if (empty($erros)) {

        $consulta = $conexao->prepare(
            "SELECT id FROM usuarios WHERE email = :email LIMIT 1"
        );

        $consulta->execute([
            ":email" => $email
        ]);

        if ($consulta->fetch(PDO::FETCH_ASSOC)) {
            $erros[] = "Este e-mail já está cadastrado.";
        }
    }

    // ==================================================
    // CADASTRA O USUÁRIO
    // ==================================================

    if (empty($erros)) {

        $senha_criptografada = password_hash(
            $senha,
            PASSWORD_DEFAULT
        );

        try {

            $inserir = $conexao->prepare(
                "INSERT INTO usuarios (nome, email, senha)
                 VALUES (:nome, :email, :senha)"
            );

            $inserir->execute([
                ":nome" => $nome,
                ":email" => $email,
                ":senha" => $senha_criptografada
            ]);

            $novo_usuario_id = $conexao->lastInsertId();

            // Identificação temporária para registrar o log
            $_SESSION["usuario_id"] = $novo_usuario_id;

            registrarLog(
                "Cadastro realizado",
                "Novo usuário cadastrado: " . $nome
            );

            // O cadastro não realiza login automático
            unset($_SESSION["usuario_id"]);

            // Gera um novo token para o próximo envio
            $_SESSION["token_cadastro"] = bin2hex(random_bytes(32));

            $mensagem = "Conta criada com sucesso! Agora você já pode entrar no Studia.";
            $tipo_mensagem = "sucesso";

            $nome = "";
            $email = "";

        } catch (PDOException $erro) {

            registrarLog(
                "Falha no cadastro",
                "Não foi possível cadastrar o e-mail: " . $email
            );

            $mensagem = "Não foi possível criar sua conta. Verifique os dados e tente novamente.";
            $tipo_mensagem = "erro";
        }

    } else {

        registrarLog(
            "Cadastro não realizado",
            "Dados inválidos para o e-mail: " . $email
        );

        $mensagem = implode(" ", $erros);
        $tipo_mensagem = "erro";
    }
}

// ==================================================
// FUNÇÃO DE SEGURANÇA
// ==================================================

function escapar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Criar conta | Studia</title>

    <meta name="description" content="Crie sua conta no Studia e organize suas disciplinas, tarefas, materiais e grupos de estudo em um só lugar.">

    <meta name="plagiarism" content="Conteúdo original do projeto acadêmico Studia.">

    <meta property="og:title" content="Criar conta | Studia">
    <meta property="og:description" content="Organize seus estudos e acompanhe sua rotina acadêmica com o Studia.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Studia">

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
            padding: 35px;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(45, 34, 75, .08);
        }

        .painel-lateral {
            background: linear-gradient(145deg, #302650, #51417c);
            color: white;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .painel-lateral::before,
        .painel-lateral::after {
            content: "";
            position: absolute;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 50%;
        }

        .painel-lateral::before {
            width: 380px;
            height: 380px;
            right: -170px;
            top: -100px;
        }

        .painel-lateral::after {
            width: 300px;
            height: 300px;
            left: -160px;
            bottom: -100px;
        }

        .logo {
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -1.5px;
            position: relative;
            z-index: 1;
            color: white;
            text-decoration: none;
        }

        .logo span {
            color: #c5b6ff;
        }

        .texto-lateral {
            position: relative;
            z-index: 1;
            max-width: 390px;
        }

        .texto-lateral h1 {
            font-size: 36px;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .texto-lateral p {
            color: #d3cce4;
            font-size: 15px;
            line-height: 1.8;
        }

        .destaque {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 17px;
            padding: 22px;
            backdrop-filter: blur(10px);
        }

        .destaque strong {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .destaque p {
            color: #d3cce4;
            font-size: 12px;
            line-height: 1.6;
        }

        .painel-formulario {
            padding: 55px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .cabecalho-formulario {
            margin-bottom: 28px;
        }

        .cabecalho-formulario h2 {
            color: #302650;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .cabecalho-formulario p {
            color: #9690a5;
            font-size: 13px;
        }

        .campo {
            margin-bottom: 17px;
        }

        .campo label {
            display: block;
            color: #514b62;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .campo input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #e7e3ef;
            border-radius: 11px;
            outline: none;
            background: #fcfbfe;
            color: #302b40;
            font-size: 14px;
            transition: .2s;
        }

        .campo input:focus {
            border-color: #8873c8;
            box-shadow: 0 0 0 3px rgba(136,115,200,.12);
        }

        .aceite-termos {
            margin: 5px 0 22px;
        }

        .aceite-termos label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #777185;
            font-size: 12px;
            line-height: 1.6;
            cursor: pointer;
        }

        .aceite-termos input {
            margin-top: 3px;
            accent-color: #6551a1;
            flex-shrink: 0;
        }

        .aceite-termos a {
            color: #6551a1;
            font-weight: 600;
            text-decoration: none;
        }

        .aceite-termos a:hover {
            text-decoration: underline;
        }

        .botao {
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

        .botao:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(101,81,161,.22);
        }

        .mensagem {
            padding: 13px 15px;
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

        .login {
            text-align: center;
            color: #9690a5;
            font-size: 13px;
            margin-top: 23px;
        }

        .login a {
            color: #6551a1;
            font-weight: 600;
            text-decoration: none;
        }

        .login a:hover {
            text-decoration: underline;
        }

        .voltar-inicio {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
        }

        .voltar-inicio a {
            color: #817890;
            text-decoration: none;
        }

        .voltar-inicio a:hover {
            color: #6551a1;
            text-decoration: underline;
        }

        @media (max-width: 850px) {

            body {
                padding: 20px;
            }

            .container {
                grid-template-columns: 1fr;
                max-width: 520px;
            }

            .painel-lateral {
                min-height: 260px;
                padding: 30px;
            }

            .texto-lateral {
                margin: 35px 0;
            }

            .texto-lateral h1 {
                font-size: 27px;
            }

            .destaque {
                display: none;
            }

            .painel-formulario {
                padding: 35px 30px;
            }
        }

        @media (max-width: 450px) {

            body {
                padding: 10px;
            }

            .painel-formulario {
                padding: 30px 22px;
            }

            .painel-lateral {
                padding: 25px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <section class="painel-lateral">

        <a href="../index.php" class="logo">
            studia<span>.</span>
        </a>

        <div class="texto-lateral">

            <h1>Seu futuro começa com uma boa organização.</h1>

            <p>
                Organize sua rotina acadêmica, acompanhe suas tarefas
                e tenha tudo o que precisa para estudar em um só lugar.
            </p>

        </div>

        <div class="destaque">

            <strong>Um espaço feito para sua evolução.</strong>

            <p>
                Planeje seus estudos, acompanhe seu progresso
                e transforme seus objetivos em conquistas.
            </p>

        </div>

    </section>

    <main class="painel-formulario">

        <div class="cabecalho-formulario">

            <h2>Crie sua conta</h2>

            <p>Preencha seus dados para começar a utilizar o Studia.</p>

        </div>

        <?php if ($mensagem !== ""): ?>

            <div
                class="mensagem <?= escapar($tipo_mensagem) ?>"
                role="alert"
                aria-live="polite"
            >
                <?= escapar($mensagem) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="cadastro.php">

            <input
                type="hidden"
                name="token_cadastro"
                value="<?= escapar($_SESSION["token_cadastro"]) ?>"
            >

            <div class="campo">

                <label for="nome">Nome completo</label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    placeholder="Digite seu nome"
                    maxlength="100"
                    value="<?= escapar($nome) ?>"
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
                    placeholder="seuemail@exemplo.com"
                    maxlength="150"
                    value="<?= escapar($email) ?>"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="campo">

                <label for="senha">Senha</label>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="Crie uma senha"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>

            <div class="campo">

                <label for="confirmar_senha">Confirmar senha</label>

                <input
                    type="password"
                    id="confirmar_senha"
                    name="confirmar_senha"
                    placeholder="Digite sua senha novamente"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>

            <div class="aceite-termos">

                <label>

                    <input
                        type="checkbox"
                        name="aceitar_termos"
                        value="1"
                        required
                    >

                    <span>
                        Li e aceito os
                        <a href="termos.php" target="_blank" rel="noopener noreferrer">
                            Termos de Uso e a Política de Privacidade
                        </a>.
                    </span>

                </label>

            </div>

            <button type="submit" class="botao">
                Criar minha conta
            </button>

        </form>

        <div class="login">

            Já possui uma conta?

            <a href="login.php">Entrar</a>

        </div>

        <div class="voltar-inicio">

            <a href="../index.php">
                Voltar para a página inicial
            </a>

        </div>

    </main>

</div>

</body>
</html>
