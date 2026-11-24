
<?php

// ==================================================
// ENCERRAMENTO DE SESSÃO - STUDIA
// ==================================================

session_start();

require_once "../logs/registro.php";

// Guarda o ID do usuário antes de encerrar a sessão
$usuario_id = $_SESSION["usuario_id"] ?? null;
$nome_usuario = $_SESSION["usuario_nome"] ?? "Usuário não identificado";

// Registra a saída antes de destruir a sessão
if ($usuario_id !== null) {

    registrarLog(
        "Logout realizado",
        "O usuário " . $nome_usuario . " encerrou sua sessão."
    );

} else {

    registrarLog(
        "Tentativa de logout sem sessão",
        "Foi acessada a página de saída sem usuário autenticado."
    );
}

// Limpa os dados da sessão
$_SESSION = [];

// Remove o cookie da sessão, se estiver sendo utilizado
if (ini_get("session.use_cookies")) {

    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header("Location: login.php");
exit;

