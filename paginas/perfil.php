<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../configuracoes/conexao.php";
require_once __DIR__ . "/../logs/registro.php";

$id_usuario = (int) $_SESSION["usuario_id"];
$mensagem = "";
$tipo_mensagem = "";
$erros = [];

// Busca os dados atuais do usuário
$consulta = $conexao->prepare(
    "SELECT id, nome, email, senha, foto
     FROM usuarios
     WHERE id = :id"
);
$consulta->execute([":id" => $id_usuario]);
$usuario = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    registrarLog("Falha ao acessar perfil", "Usuário não encontrado no banco de dados.");
    session_destroy();
    header("Location: login.php");
    exit;
}

// Cria o token de segurança do formulário
if (empty($_SESSION["token_perfil"])) {
    $_SESSION["token_perfil"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Confere o token de segurança
    if (
        !isset($_POST["token"]) ||
        !hash_equals($_SESSION["token_perfil"], $_POST["token"])
    ) {
        $erros[] = "Não foi possível validar o formulário. Atualize a página e tente novamente.";

        registrarLog(
            "Falha de segurança",
            "Token CSRF inválido na tentativa de atualização do perfil."
        );
    }

    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");

    $senha_atual = $_POST["senha_atual"] ?? "";
    $nova_senha = $_POST["nova_senha"] ?? "";
    $confirmar_senha = $_POST["confirmar_senha"] ?? "";

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
    } else {
        $verifica_email = $conexao->prepare(
            "SELECT id FROM usuarios
             WHERE email = :email AND id != :id"
        );

        $verifica_email->execute([
            ":email" => $email,
            ":id" => $id_usuario
        ]);

        if ($verifica_email->fetch()) {
            $erros[] = "Esse e-mail já está sendo utilizado por outra conta.";
        }
    }

    // A troca de senha é opcional
    $alterar_senha = (
        $senha_atual !== "" ||
        $nova_senha !== "" ||
        $confirmar_senha !== ""
    );

    if ($alterar_senha) {
        if (!password_verify($senha_atual, $usuario["senha"])) {
            $erros[] = "A senha atual está incorreta.";
        }

        if (strlen($nova_senha) < 8) {
            $erros[] = "A nova senha deve ter pelo menos 8 caracteres.";
        }

        if ($nova_senha !== $confirmar_senha) {
            $erros[] = "A confirmação da nova senha não corresponde.";
        }
    }

    // Verifica se foi enviada uma foto
    $novo_arquivo = null;
    $caminho_novo = null;

    if (
        isset($_FILES["foto"]) &&
        $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
            $erros[] = "Ocorreu um erro ao enviar a foto.";
        } elseif ($_FILES["foto"]["size"] > 3 * 1024 * 1024) {
            $erros[] = "A foto deve ter no máximo 3 MB.";
        } else {
            $arquivo_temporario = $_FILES["foto"]["tmp_name"];

            $informacoes = finfo_open(FILEINFO_MIME_TYPE);
            $tipo_arquivo = finfo_file($informacoes, $arquivo_temporario);
            finfo_close($informacoes);

            $tipos_permitidos = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (!isset($tipos_permitidos[$tipo_arquivo])) {
                $erros[] = "Formato inválido. Envie uma imagem JPG, PNG ou WEBP.";
            } elseif (getimagesize($arquivo_temporario) === false) {
                $erros[] = "O arquivo enviado não é uma imagem válida.";
            } else {
                $novo_arquivo = bin2hex(random_bytes(16))
                    . "." . $tipos_permitidos[$tipo_arquivo];

                $pasta = __DIR__ . "/../envios/perfis/";

                if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
                    $erros[] = "Não foi possível preparar a pasta para salvar a foto.";
                } else {
                    $caminho_novo = $pasta . $novo_arquivo;
                }
            }
        }
    }

    // Salva as alterações se não houver erros
    if (empty($erros)) {
        try {
            if ($caminho_novo !== null) {
                if (!move_uploaded_file(
                    $_FILES["foto"]["tmp_name"],
                    $caminho_novo
                )) {
                    throw new Exception("Não foi possível salvar a foto.");
                }

                $novo_caminho_banco = "../envios/perfis/" . $novo_arquivo;
            } else {
                $novo_caminho_banco = $usuario["foto"];
            }

            $nova_senha_banco = $alterar_senha
                ? password_hash($nova_senha, PASSWORD_DEFAULT)
                : $usuario["senha"];

            $atualizar = $conexao->prepare(
                "UPDATE usuarios
                 SET nome = :nome,
                     email = :email,
                     senha = :senha,
                     foto = :foto
                 WHERE id = :id"
            );

            $atualizar->execute([
                ":nome" => $nome,
                ":email" => $email,
                ":senha" => $nova_senha_banco,
                ":foto" => $novo_caminho_banco,
                ":id" => $id_usuario
            ]);

            // Atualiza os dados da sessão
            $_SESSION["usuario_nome"] = $nome;
            $_SESSION["usuario_email"] = $email;

            // Remove a foto antiga depois de salvar a nova
            if (
                $caminho_novo !== null &&
                !empty($usuario["foto"]) &&
                strpos($usuario["foto"], "../envios/perfis/") === 0
            ) {
                $foto_antiga = basename($usuario["foto"]);
                $caminho_antigo = __DIR__ . "/../envios/perfis/" . $foto_antiga;

                if (is_file($caminho_antigo)) {
                    unlink($caminho_antigo);
                }
            }

            // Atualiza os dados exibidos
            $consulta->execute([":id" => $id_usuario]);
            $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

            $mensagem = "Seus dados foram atualizados com sucesso!";
            $tipo_mensagem = "sucesso";

            // Registra as alterações realizadas
            $detalhes = "Nome: " . $nome . " | E-mail: " . $email;

            if ($alterar_senha) {
                $detalhes .= " | Senha alterada";
            }

            if ($caminho_novo !== null) {
                $detalhes .= " | Foto de perfil atualizada";
            }

            registrarLog("Perfil atualizado", $detalhes);

            // Gera um novo token
            $_SESSION["token_perfil"] = bin2hex(random_bytes(32));

        } catch (Exception $erro) {
            if ($caminho_novo !== null && is_file($caminho_novo)) {
                unlink($caminho_novo);
            }

            registrarLog(
                "Erro ao atualizar perfil",
                "Falha durante a atualização. Tipo: " . get_class($erro)
            );

            $erros[] = "Não foi possível atualizar seu perfil. Tente novamente.";
        }
    }

    if (!empty($erros)) {
        $mensagem = implode(" ", $erros);
        $tipo_mensagem = "erro";

        registrarLog(
            "Falha ao atualizar perfil",
            implode(" | ", $erros)
        );
    }
}

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$nome_usuario = $usuario["nome"];
$foto_usuario = $usuario["foto"];
$nome_inicial = mb_strtoupper(mb_substr($nome_usuario, 0, 1, "UTF-8"), "UTF-8");
$iniciais = $nome_inicial;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gerencie suas informações pessoais, foto de perfil e senha na plataforma Studia.">
    <title>Meu perfil | Studia</title>

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

        /* MENU LATERAL PADRÃO DO INÍCIO */

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

        /* SAIR DA CONTA ABAIXO DO PERFIL */

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

        .main {
            margin-left: 250px;
            padding: 42px 48px;
            min-height: 100vh;
        }

        /* CONTEÚDO DO PERFIL */

        .topo {
            margin-bottom: 30px;
        }

        .topo h1 {
            font-size: 30px;
            color: #302650;
            margin-bottom: 8px;
        }

        .topo p {
            color: #888297;
            font-size: 14px;
        }

        .cartao {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(45, 34, 75, .05);
            max-width: 850px;
        }

        .apresentacao {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eeeaf4;
            margin-bottom: 28px;
        }

        .avatar {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            object-fit: cover;
            background: #e9e2ff;
            color: #51417c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .apresentacao h2 {
            color: #302650;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .apresentacao p {
            color: #9690a5;
            font-size: 13px;
        }

        .secao-titulo {
            font-size: 17px;
            color: #302650;
            margin-bottom: 20px;
        }

        .campo {
            margin-bottom: 20px;
        }

        .campo label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #514b62;
            margin-bottom: 8px;
        }

        .campo input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #e7e3ef;
            border-radius: 11px;
            outline: none;
            font-size: 14px;
            background: #fcfbfe;
            color: #302b40;
            transition: .2s;
        }

        .campo input:focus {
            border-color: #8873c8;
            box-shadow: 0 0 0 3px rgba(136,115,200,.12);
        }

        .ajuda {
            font-size: 12px;
            color: #9993a8;
            margin-top: 7px;
        }

        .divisor {
            border: none;
            border-top: 1px solid #eeeaf4;
            margin: 30px 0;
        }

        .botao {
            border: none;
            background: linear-gradient(135deg, #6551a1, #8b76ca);
            color: white;
            padding: 14px 25px;
            border-radius: 11px;
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
            max-width: 850px;
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
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
            display: inline-block;
            color: #7565a5;
            text-decoration: none;
            font-size: 13px;
            margin-top: 25px;
        }

        .voltar:hover {
            text-decoration: underline;
        }

        /* BOTÃO FLUTUANTE DO STUDIA */

        .botao-studia {
            position: fixed;
            right: 28px;
            bottom: 25px;
            z-index: 90;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 19px;
            background: linear-gradient(120deg,#493b70,#8873c8);
            color: white;
            border-radius: 30px;
            box-shadow: 0 7px 22px rgba(48,38,80,.22);
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .botao-studia:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(48,38,80,.28);
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

            .botao-studia {
                right: 16px;
                bottom: 16px;
                padding: 11px 15px;
            }
        }

        @media(max-width:500px) {
            .cartao {
                padding: 20px;
            }

            .apresentacao {
                align-items: flex-start;
            }

            .topo h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

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
                    <img src="<?= escapar($foto_usuario) ?>" alt="Foto de perfil">
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

<main class="main">

    <div class="topo">
        <h1>Meu perfil</h1>
        <p>Gerencie suas informações pessoais e os dados da sua conta.</p>
    </div>

    <?php if ($mensagem !== ""): ?>
        <div class="mensagem <?= escapar($tipo_mensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <form class="cartao" method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="token"
            value="<?= escapar($_SESSION["token_perfil"]) ?>"
        >

        <div class="apresentacao">

            <?php if (!empty($usuario["foto"])): ?>
                <img
                    src="<?= escapar($usuario["foto"]) ?>"
                    alt="Foto de perfil"
                    class="avatar"
                >
            <?php else: ?>
                <div class="avatar">
                    <?= escapar($iniciais) ?>
                </div>
            <?php endif; ?>

            <div>
                <h2><?= escapar($usuario["nome"]) ?></h2>
                <p><?= escapar($usuario["email"]) ?></p>
            </div>

        </div>

        <h3 class="secao-titulo">Informações pessoais</h3>

        <div class="campo">
            <label for="nome">Nome completo</label>
            <input
                type="text"
                id="nome"
                name="nome"
                maxlength="100"
                value="<?= escapar($usuario["nome"]) ?>"
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
                value="<?= escapar($usuario["email"]) ?>"
                required
            >
        </div>

        <div class="campo">
            <label for="foto">Foto de perfil</label>
            <input
                type="file"
                id="foto"
                name="foto"
                accept=".jpg,.jpeg,.png,.webp"
            >
            <p class="ajuda">
                Formatos aceitos: JPG, PNG e WEBP. Tamanho máximo: 3 MB.
            </p>
        </div>

        <hr class="divisor">

        <h3 class="secao-titulo">Alterar senha</h3>

        <div class="campo">
            <label for="senha_atual">Senha atual</label>
            <input
                type="password"
                id="senha_atual"
                name="senha_atual"
                autocomplete="current-password"
                placeholder="Digite sua senha atual"
            >
        </div>

        <div class="campo">
            <label for="nova_senha">Nova senha</label>
            <input
                type="password"
                id="nova_senha"
                name="nova_senha"
                autocomplete="new-password"
                placeholder="Digite a nova senha"
            >
            <p class="ajuda">A nova senha deve ter pelo menos 8 caracteres.</p>
        </div>

        <div class="campo">
            <label for="confirmar_senha">Confirmar nova senha</label>
            <input
                type="password"
                id="confirmar_senha"
                name="confirmar_senha"
                autocomplete="new-password"
                placeholder="Digite novamente a nova senha"
            >
        </div>

        <button type="submit" class="botao">
            Salvar alterações
        </button>

    </form>

    <a href="inicio.php" class="voltar">← Voltar ao início</a>

</main>

<a href="assistente.php" class="botao-studia">
    <span class="botao-studia-icone">✦</span>
    Conversar com o studIA
</a>

</body>
</html>