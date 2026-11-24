
<?php

// ==================================================
// LOGIN DE USUÁRIOS - STUDIA
// ==================================================

session_start();

require_once "../configuracoes/conexao.php";
require_once "../logs/registro.php";

$mensagem = "";
$tipo_mensagem = "";

$email = "";

// Gera o token de proteção do formulário
if (empty($_SESSION["token_login"])) {
    $_SESSION["token_login"] = bin2hex(random_bytes(32));
}

// ==================================================
// PROCESSAMENTO DO LOGIN
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = strtolower(trim($_POST["email"] ?? ""));
    $senha = $_POST["senha"] ?? "";
    $token = $_POST["token_login"] ?? "";

    // Proteção CSRF
    if (
        !is_string($token) ||
        !hash_equals($_SESSION["token_login"], $token)
    ) {

        $mensagem = "Sua sessão expirou ou o formulário é inválido. Atualize a página e tente novamente.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Login não realizado",
            "Token de segurança inválido."
        );

    } elseif ($email === "" || $senha === "") {

        $mensagem = "Preencha seu e-mail e sua senha.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Login não realizado",
            "Campos de e-mail ou senha vazios."
        );

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = "Informe um endereço de e-mail válido.";
        $tipo_mensagem = "erro";

        registrarLog(
            "Login não realizado",
            "Formato de e-mail inválido."
        );

    } else {

        try {

            // Busca o usuário pelo e-mail
            $consulta = $conexao->prepare(
                "SELECT id, nome, email, senha, foto
                 FROM usuarios
                 WHERE email = :email
                 LIMIT 1"
            );

            $consulta->execute([
                ":email" => $email
            ]);

            $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

            // Confere se o usuário existe e se a senha está correta
            if (
                $usuario &&
                password_verify($senha, $usuario["senha"])
            ) {

                // Atualiza o hash caso o algoritmo precise evoluir
                if (password_needs_rehash($usuario["senha"], PASSWORD_DEFAULT)) {

                    $novo_hash = password_hash(
                        $senha,
                        PASSWORD_DEFAULT
                    );

                    $atualizar_senha = $conexao->prepare(
                        "UPDATE usuarios SET senha = :senha WHERE id = :id"
                    );

                    $atualizar_senha->execute([
                        ":senha" => $novo_hash,
                        ":id" => $usuario["id"]
                    ]);
                }

                // Cria um novo identificador de sessão
                session_regenerate_id(true);

                // Dados do usuário na sessão
                $_SESSION["usuario_id"] = $usuario["id"];
                $_SESSION["usuario_nome"] = $usuario["nome"];
                $_SESSION["usuario_email"] = $usuario["email"];
                $_SESSION["usuario_foto"] = $usuario["foto"] ?? null;

                // Remove o token utilizado
                unset($_SESSION["token_login"]);

                // Registra o login realizado
                registrarLog(
                    "Login realizado",
                    "Usuário: " . $usuario["nome"]
                );

                // Redireciona para o painel interno
                header("Location: inicio.php");
                exit;

            } else {

                // Registra a tentativa inválida sem revelar a existência da conta
                registrarLog(
                    "Falha no login",
                    "Tentativa de acesso com e-mail: " . $email
                );

                $mensagem = "E-mail ou senha incorretos.";
                $tipo_mensagem = "erro";
            }

        } catch (PDOException $erro) {

            registrarLog(
                "Erro no processamento do login",
                "Falha ao consultar os dados de acesso."
            );

            $mensagem = "Não foi possível realizar o login. Tente novamente.";
            $tipo_mensagem = "erro";
        }
    }

    // Gera um novo token para o próximo envio
    if (isset($_SESSION["usuario_id"])) {
        // O usuário autenticado será redirecionado.
    } else {
        $_SESSION["token_login"] = bin2hex(random_bytes(32));
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

    <title>Entrar | Studia</title>

    <meta name="description" content="Entre na sua conta Studia para organizar disciplinas, tarefas, materiais e acompanhar seu progresso acadêmico.">

    <meta name="plagiarism" content="Conteúdo original do projeto acadêmico Studia.">

    <meta property="og:title" content="Entrar | Studia">
    <meta property="og:description" content="Acesse seu espaço de organização acadêmica no Studia.">
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
            background: #f6f5fa;
            color: #25223a;
        }

        .pagina {
            min-height: 100vh;
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
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 70px rgba(39, 29, 77, 0.09);
        }

        .painel {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, #302650, #51417c);
            color: white;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .logo {
            font-size: 29px;
            font-weight: 750;
            letter-spacing: -1.5px;
            color: white;
            text-decoration: none;
            position: relative;
            z-index: 2;
        }

        .logo span {
            color: #c4b5fd;
        }

        .conteudo-painel {
            position: relative;
            z-index: 2;
            margin: 50px 0;
        }

        .etiqueta {
            display: inline-block;
            color: #c8baff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 22px;
        }

        .conteudo-painel h1 {
            font-size: 43px;
            line-height: 1.15;
            letter-spacing: -1.8px;
            margin-bottom: 20px;
        }

        .conteudo-painel p {
            color: #ded8ef;
            font-size: 15px;
            line-height: 1.8;
            max-width: 340px;
        }

        .cartao-decorativo {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.17);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 14px;
            max-width: 290px;
        }

        .cartao-titulo {
            font-size: 12px;
            color: #d8cff3;
            margin-bottom: 12px;
        }

        .cartao-linha {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            margin-top: 10px;
        }

        .bolinha {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #bcaaff;
        }

        .decoracao {
            position: absolute;
            width: 320px;
            height: 320px;
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 50%;
            right: -150px;
            top: 150px;
        }

        .decoracao::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 50%;
            top: 50px;
            left: 50px;
        }

        .rodape-painel {
            position: relative;
            z-index: 2;
            color: #c4badb;
            font-size: 12px;
        }

        .area-formulario {
            padding: 65px 58px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .area-formulario h2 {
            font-size: 29px;
            letter-spacing: -1px;
            margin-bottom: 9px;
        }

        .subtitulo {
            color: #89869a;
            font-size: 14px;
            margin-bottom: 35px;
        }

        .grupo-campo {
            margin-bottom: 21px;
        }

        .grupo-campo label {
            display: block;
            font-size: 13px;
            font-weight: 650;
            margin-bottom: 9px;
            color: #403c54;
        }

        .grupo-campo input {
            width: 100%;
            height: 49px;
            padding: 0 15px;
            border: 1px solid #e7e4ef;
            border-radius: 9px;
            background: #fcfbfe;
            font-size: 14px;
            color: #302b43;
            outline: none;
            transition: all 0.2s ease;
        }

        .grupo-campo input:focus {
            border-color: #8872d6;
            background: white;
            box-shadow: 0 0 0 3px rgba(136, 114, 214, 0.12);
        }

        .grupo-campo input::placeholder {
            color: #aaa6b6;
        }

        .esqueci-senha {
            display: block;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 18px;
            color: #7560c5;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .esqueci-senha:hover {
            text-decoration: underline;
        }

        .botao {
            width: 100%;
            height: 50px;
            margin-top: 8px;
            border: none;
            border-radius: 9px;
            background: #7560c5;
            color: white;
            font-size: 14px;
            font-weight: 650;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .botao:hover {
            background: #5e49ad;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(94, 73, 173, 0.2);
        }

        .mensagem {
            padding: 13px;
            border-radius: 9px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .mensagem.erro {
            background: #fff0f0;
            color: #b93838;
            border: 1px solid #f5d0d0;
        }

        .links {
            margin-top: 25px;
            text-align: center;
            font-size: 13px;
            color: #89869a;
        }

        .links a {
            color: #7560c5;
            font-weight: 650;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .aviso {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: #9995a6;
            line-height: 1.6;
        }

        .voltar-inicio {
            margin-top: 15px;
            text-align: center;
            font-size: 12px;
        }

        .voltar-inicio a {
            color: #89869a;
            text-decoration: none;
        }

        .voltar-inicio a:hover {
            color: #7560c5;
            text-decoration: underline;
        }

        @media (max-width: 850px) {

            .container {
                max-width: 550px;
                grid-template-columns: 1fr;
            }

            .painel {
                min-height: 390px;
                padding: 35px;
            }

            .conteudo-painel {
                margin: 35px 0;
            }

            .conteudo-painel h1 {
                font-size: 34px;
            }

            .cartao-decorativo {
                display: none;
            }

            .area-formulario {
                padding: 45px 35px;
            }
        }

        @media (max-width: 480px) {

            .pagina {
                padding: 15px;
            }

            .painel {
                min-height: 330px;
                padding: 27px;
            }

            .area-formulario {
                padding: 35px 25px;
            }

            .area-formulario h2 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

    <main class="pagina">

        <div class="container">

            <section class="painel">

                <div class="decoracao"></div>

                <a href="../index.php" class="logo">
                    studia<span>.</span>
                </a>

                <div class="conteudo-painel">

                    <span class="etiqueta">
                        SEU ESPAÇO DE ESTUDOS
                    </span>

                    <h1>
                        Seu próximo
                        objetivo começa
                        aqui.
                    </h1>

                    <p>
                        Retome sua rotina, acompanhe seu progresso
                        e continue construindo seu caminho acadêmico
                        com o Studia.
                    </p>

                </div>

                <div class="cartao-decorativo">

                    <div class="cartao-titulo">
                        SUA ROTINA, SEU PROGRESSO
                    </div>

                    <div class="cartao-linha">
                        <span class="bolinha"></span>
                        Organize suas disciplinas
                    </div>

                    <div class="cartao-linha">
                        <span class="bolinha"></span>
                        Acompanhe suas atividades
                    </div>

                    <div class="cartao-linha">
                        <span class="bolinha"></span>
                        Conquiste seus objetivos
                    </div>

                </div>

                <div class="rodape-painel">
                    Um passo de cada vez.
                </div>

            </section>

            <section class="area-formulario">

                <h2>Bem-vindo de volta!</h2>

                <p class="subtitulo">
                    Entre na sua conta para continuar.
                </p>

                <?php if ($mensagem !== ""): ?>

                    <div
                        class="mensagem <?= escapar($tipo_mensagem) ?>"
                        role="alert"
                        aria-live="polite"
                    >
                        <?= escapar($mensagem) ?>
                    </div>

                <?php endif; ?>

                <form action="login.php" method="POST">

                    <input
                        type="hidden"
                        name="token_login"
                        value="<?= escapar($_SESSION["token_login"]) ?>"
                    >

                    <div class="grupo-campo">

                        <label for="email">E-mail</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="seuemail@exemplo.com"
                            value="<?= escapar($email) ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >

                    </div>

                    <div class="grupo-campo">

                        <label for="senha">Senha</label>

                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="Digite sua senha"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <a href="recuperar_senha.php" class="esqueci-senha">
                        Esqueci minha senha
                    </a>

                    <button type="submit" class="botao">
                        Entrar na minha conta
                    </button>

                </form>

                <div class="links">

                    Ainda não tem uma conta?

                    <a href="cadastro.php">Criar conta</a>

                </div>

                <p class="aviso">
                    Seus estudos estão esperando por você.
                </p>

                <div class="voltar-inicio">

                    <a href="../index.php">
                        Voltar para a página inicial
                    </a>

                </div>

            </section>

        </div>

    </main>

</body>

</html>
