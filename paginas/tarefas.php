<?php

session_start();

// ==================================================
// VERIFICAÇÃO DE LOGIN
// ==================================================

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../configuracoes/conexao.php";
require_once __DIR__ . "/../logs/registro.php";

$usuario_id = (int) $_SESSION["usuario_id"];

// ==================================================
// DADOS DO PERFIL
// ==================================================

$consulta_perfil = $conexao->prepare(
    "SELECT nome, foto FROM usuarios WHERE id = :id LIMIT 1"
);

$consulta_perfil->execute([":id" => $usuario_id]);
$perfil_sidebar = $consulta_perfil->fetch(PDO::FETCH_ASSOC);

$nome_usuario = $perfil_sidebar["nome"] ?? "Meu perfil";
$foto_usuario = $perfil_sidebar["foto"] ?? "";

$nome_inicial = mb_strtoupper(
    mb_substr(trim($nome_usuario), 0, 1, "UTF-8"),
    "UTF-8"
);

// ==================================================
// PROTEÇÃO CSRF
// ==================================================

if (empty($_SESSION["token_csrf"])) {
    $_SESSION["token_csrf"] = bin2hex(random_bytes(32));
}

$token_csrf = $_SESSION["token_csrf"];

// ==================================================
// MENSAGENS
// ==================================================

$mensagem = $_SESSION["mensagem"] ?? "";
$tipo_mensagem = $_SESSION["tipo_mensagem"] ?? "";

unset($_SESSION["mensagem"], $_SESSION["tipo_mensagem"]);

function redirecionarComMensagem($texto, $tipo)
{
    $_SESSION["mensagem"] = $texto;
    $_SESSION["tipo_mensagem"] = $tipo;

    header("Location: tarefas.php");
    exit;
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// ==================================================
// VERIFICAÇÕES DE PROPRIEDADE
// ==================================================

function disciplinaPertenceAoUsuario($conexao, $disciplina_id, $usuario_id)
{
    $consulta = $conexao->prepare(
        "SELECT id
         FROM disciplinas
         WHERE id = :id
         AND usuario_id = :usuario_id
         LIMIT 1"
    );

    $consulta->execute([
        ":id" => $disciplina_id,
        ":usuario_id" => $usuario_id
    ]);

    return $consulta->fetch(PDO::FETCH_ASSOC) !== false;
}

function buscarTarefaDoUsuario($conexao, $id, $usuario_id)
{
    $consulta = $conexao->prepare(
        "SELECT t.id, t.titulo, t.status, t.disciplina_id
         FROM tarefas t
         INNER JOIN disciplinas d ON d.id = t.disciplina_id
         WHERE t.id = :id
         AND d.usuario_id = :usuario_id
         LIMIT 1"
    );

    $consulta->execute([
        ":id" => $id,
        ":usuario_id" => $usuario_id
    ]);

    return $consulta->fetch(PDO::FETCH_ASSOC);
}

// ==================================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token_enviado = $_POST["token_csrf"] ?? "";

    if (
        !is_string($token_enviado) ||
        !hash_equals($token_csrf, $token_enviado)
    ) {
        registrarLog(
            "FALHA_SEGURANCA",
            "Token CSRF inválido na página de tarefas."
        );

        redirecionarComMensagem(
            "Não foi possível validar a solicitação. Tente novamente.",
            "erro"
        );
    }

    $acao = $_POST["acao"] ?? "";

    // ==============================================
    // CADASTRAR TAREFA
    // ==============================================

    if ($acao === "cadastrar") {

        $titulo = trim($_POST["titulo"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");

        $disciplina_id = filter_var(
            $_POST["disciplina_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        $tipo = $_POST["tipo"] ?? "atividade";
        $data_entrega = trim($_POST["data_entrega"] ?? "");

        $tipos_validos = [
            "trabalho",
            "prova",
            "atividade",
            "outro"
        ];

        if (
            $titulo === "" ||
            mb_strlen($titulo, "UTF-8") > 150 ||
            !$disciplina_id ||
            !in_array($tipo, $tipos_validos, true)
        ) {
            redirecionarComMensagem(
                "Preencha corretamente os campos obrigatórios. O título deve ter até 150 caracteres.",
                "erro"
            );
        }

        if (!disciplinaPertenceAoUsuario($conexao, $disciplina_id, $usuario_id)) {
            redirecionarComMensagem(
                "A disciplina selecionada é inválida.",
                "erro"
            );
        }

        if ($data_entrega !== "") {

            $data = DateTime::createFromFormat("Y-m-d", $data_entrega);

            if (!$data || $data->format("Y-m-d") !== $data_entrega) {
                redirecionarComMensagem(
                    "Informe uma data de entrega válida.",
                    "erro"
                );
            }

        } else {
            $data_entrega = null;
        }

        $consulta = $conexao->prepare(
            "INSERT INTO tarefas
            (disciplina_id, titulo, descricao, tipo, data_entrega)
            VALUES
            (:disciplina_id, :titulo, :descricao, :tipo, :data_entrega)"
        );

        $consulta->execute([
            ":disciplina_id" => $disciplina_id,
            ":titulo" => $titulo,
            ":descricao" => $descricao,
            ":tipo" => $tipo,
            ":data_entrega" => $data_entrega
        ]);

        $nova_tarefa_id = $conexao->lastInsertId();

        registrarLog(
            "TAREFA_CADASTRADA",
            "ID: " . $nova_tarefa_id . " | Título: " . $titulo
        );

        redirecionarComMensagem(
            "Tarefa cadastrada com sucesso!",
            "sucesso"
        );
    }

    // ==============================================
    // EDITAR TAREFA
    // ==============================================

    if ($acao === "editar") {

        $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
        $titulo = trim($_POST["titulo"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");

        $disciplina_id = filter_var(
            $_POST["disciplina_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        $tipo = $_POST["tipo"] ?? "";
        $data_entrega = trim($_POST["data_entrega"] ?? "");

        $tipos_validos = [
            "trabalho",
            "prova",
            "atividade",
            "outro"
        ];

        if (
            !$id ||
            $titulo === "" ||
            mb_strlen($titulo, "UTF-8") > 150 ||
            !$disciplina_id ||
            !in_array($tipo, $tipos_validos, true)
        ) {
            redirecionarComMensagem(
                "Verifique os dados informados para editar a tarefa.",
                "erro"
            );
        }

        if (!disciplinaPertenceAoUsuario($conexao, $disciplina_id, $usuario_id)) {
            redirecionarComMensagem(
                "A disciplina selecionada é inválida.",
                "erro"
            );
        }

        if ($data_entrega !== "") {

            $data = DateTime::createFromFormat("Y-m-d", $data_entrega);

            if (!$data || $data->format("Y-m-d") !== $data_entrega) {
                redirecionarComMensagem(
                    "Informe uma data de entrega válida.",
                    "erro"
                );
            }

        } else {
            $data_entrega = null;
        }

        $tarefa_anterior = buscarTarefaDoUsuario(
            $conexao,
            $id,
            $usuario_id
        );

        if (!$tarefa_anterior) {

            registrarLog(
                "FALHA_EDICAO_TAREFA",
                "Tarefa não encontrada. ID: " . $id
            );

            redirecionarComMensagem("Tarefa não encontrada.", "erro");
        }

        $consulta = $conexao->prepare(
            "UPDATE tarefas
             SET disciplina_id = :disciplina_id,
                 titulo = :titulo,
                 descricao = :descricao,
                 tipo = :tipo,
                 data_entrega = :data_entrega
             WHERE id = :id
             AND disciplina_id IN (
                 SELECT id FROM disciplinas WHERE usuario_id = :usuario_id
             )"
        );

        $consulta->execute([
            ":disciplina_id" => $disciplina_id,
            ":titulo" => $titulo,
            ":descricao" => $descricao,
            ":tipo" => $tipo,
            ":data_entrega" => $data_entrega,
            ":id" => $id,
            ":usuario_id" => $usuario_id
        ]);

        if ($consulta->rowCount() > 0) {

            registrarLog(
                "TAREFA_EDITADA",
                "ID: " . $id .
                " | Título anterior: " . $tarefa_anterior["titulo"] .
                " | Novo título: " . $titulo
            );

            redirecionarComMensagem(
                "Tarefa atualizada com sucesso!",
                "sucesso"
            );
        }

        registrarLog(
            "EDICAO_TAREFA_SEM_ALTERACOES",
            "ID: " . $id . " | Título: " . $titulo
        );

        redirecionarComMensagem(
            "Nenhuma alteração foi realizada.",
            "sucesso"
        );
    }

    // ==============================================
    // ALTERAR STATUS
    // ==============================================

    if ($acao === "status") {

        $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
        $status = $_POST["status"] ?? "";

        $status_validos = [
            "pendente",
            "em andamento",
            "concluida"
        ];

        if (!$id || !in_array($status, $status_validos, true)) {
            redirecionarComMensagem("Status inválido.", "erro");
        }

        $tarefa_anterior = buscarTarefaDoUsuario(
            $conexao,
            $id,
            $usuario_id
        );

        if (!$tarefa_anterior) {

            registrarLog(
                "FALHA_ALTERACAO_STATUS",
                "Tarefa não encontrada. ID: " . $id
            );

            redirecionarComMensagem("Tarefa não encontrada.", "erro");
        }

        $consulta = $conexao->prepare(
            "UPDATE tarefas
             SET status = :status
             WHERE id = :id
             AND disciplina_id IN (
                 SELECT id FROM disciplinas WHERE usuario_id = :usuario_id
             )"
        );

        $consulta->execute([
            ":status" => $status,
            ":id" => $id,
            ":usuario_id" => $usuario_id
        ]);

        if ($tarefa_anterior["status"] !== $status) {

            registrarLog(
                "STATUS_TAREFA_ALTERADO",
                "ID: " . $id .
                " | Tarefa: " . $tarefa_anterior["titulo"] .
                " | Status anterior: " . $tarefa_anterior["status"] .
                " | Novo status: " . $status
            );
        }

        redirecionarComMensagem(
            "Status da tarefa atualizado!",
            "sucesso"
        );
    }

    // ==============================================
    // EXCLUIR TAREFA
    // ==============================================

    if ($acao === "excluir") {

        $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);

        if (!$id) {
            redirecionarComMensagem("Tarefa inválida.", "erro");
        }

        $tarefa_excluida = buscarTarefaDoUsuario(
            $conexao,
            $id,
            $usuario_id
        );

        if (!$tarefa_excluida) {

            registrarLog(
                "FALHA_EXCLUSAO_TAREFA",
                "Tarefa não encontrada. ID: " . $id
            );

            redirecionarComMensagem("Tarefa não encontrada.", "erro");
        }

        try {

            $conexao->beginTransaction();

            $consulta_vinculos = $conexao->prepare(
                "DELETE FROM tarefas_materiais WHERE tarefa_id = :tarefa_id"
            );

            $consulta_vinculos->execute([
                ":tarefa_id" => $id
            ]);

            $consulta = $conexao->prepare(
                "DELETE FROM tarefas
                 WHERE id = :id
                 AND disciplina_id IN (
                     SELECT id FROM disciplinas WHERE usuario_id = :usuario_id
                 )"
            );

            $consulta->execute([
                ":id" => $id,
                ":usuario_id" => $usuario_id
            ]);

            if ($consulta->rowCount() > 0) {

                $conexao->commit();

                registrarLog(
                    "TAREFA_EXCLUIDA",
                    "ID: " . $id .
                    " | Título: " . $tarefa_excluida["titulo"]
                );

                redirecionarComMensagem(
                    "Tarefa excluída com sucesso!",
                    "sucesso"
                );
            }

            $conexao->rollBack();

            redirecionarComMensagem(
                "Não foi possível excluir a tarefa.",
                "erro"
            );

        } catch (PDOException $erro) {

            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }

            registrarLog(
                "ERRO_EXCLUSAO_TAREFA",
                "ID: " . $id . " | Erro: " . $erro->getMessage()
            );

            redirecionarComMensagem(
                "Ocorreu um erro ao excluir a tarefa. Tente novamente.",
                "erro"
            );
        }
    }

    registrarLog(
        "ACAO_INVALIDA_TAREFAS",
        "Ação recebida: " . $acao
    );

    redirecionarComMensagem("Ação não reconhecida.", "erro");
}

// ==================================================
// BUSCAR DISCIPLINAS
// ==================================================

$consulta = $conexao->prepare(
    "SELECT id, nome, cor
     FROM disciplinas
     WHERE usuario_id = :usuario_id
     ORDER BY nome ASC"
);

$consulta->execute([":usuario_id" => $usuario_id]);
$disciplinas = $consulta->fetchAll(PDO::FETCH_ASSOC);

// ==================================================
// BUSCAR TAREFAS
// ==================================================

$consulta = $conexao->prepare(
    "SELECT
        t.*,
        d.nome AS nome_disciplina,
        d.cor AS cor_disciplina
     FROM tarefas t
     INNER JOIN disciplinas d ON d.id = t.disciplina_id
     WHERE d.usuario_id = :usuario_id
     ORDER BY
        CASE
            WHEN t.status = 'pendente' THEN 1
            WHEN t.status = 'em andamento' THEN 2
            ELSE 3
        END,
        t.data_entrega ASC"
);

$consulta->execute([":usuario_id" => $usuario_id]);
$tarefas = $consulta->fetchAll(PDO::FETCH_ASSOC);

// ==================================================
// CONTADORES
// ==================================================

$total_tarefas = count($tarefas);
$total_pendentes = 0;
$total_andamento = 0;
$total_concluidas = 0;

foreach ($tarefas as $tarefa) {

    if ($tarefa["status"] === "pendente") {
        $total_pendentes++;

    } elseif ($tarefa["status"] === "em andamento") {
        $total_andamento++;

    } elseif ($tarefa["status"] === "concluida") {
        $total_concluidas++;
    }
}

// ==================================================
// FORMATAÇÃO DE DATA
// ==================================================

function formatarData($data)
{
    if (empty($data)) {
        return "Sem prazo definido";
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        return "Data não definida";
    }

    return date("d/m/Y", $timestamp);
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Minhas tarefas | Studia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        /* ==========================================
           PADRÃO GLOBAL - INICIO.PHP
        ========================================== */

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

        /* ==========================================
           SIDEBAR - PADRÃO EXATO DO INICIO.PHP
        ========================================== */

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

        /* ==========================================
           PERFIL - PADRÃO EXATO DO INICIO.PHP
        ========================================== */

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

        .avatar {
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

        .avatar img {
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

        /* ==========================================
           CONTEÚDO PRINCIPAL
        ========================================== */

        .main {
            margin-left: 250px;
            padding: 42px 48px;
            min-height: 100vh;
        }

        .topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .topo h1 {
            color: #302650;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .topo p {
            color: #898398;
            font-size: 14px;
        }

        .botao-nova,
        .botao-salvar {
            background: #65518f;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 13px 19px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .botao-nova:hover,
        .botao-salvar:hover {
            background: #493b70;
            transform: translateY(-1px);
        }

        /* ==========================================
           RESUMO
        ========================================== */

        .resumo {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .resumo-card {
            background: white;
            border: 1px solid #ece9f3;
            border-radius: 15px;
            padding: 19px;
        }

        .resumo-card span {
            display: block;
            color: #918b9e;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .resumo-card strong {
            color: #493875;
            font-size: 25px;
            font-weight: 800;
        }

        /* ==========================================
           CARTÕES E FORMULÁRIOS
        ========================================== */

        .cartao {
            background: white;
            border: 1px solid #ece9f3;
            border-radius: 17px;
            padding: 25px;
            box-shadow: 0 4px 16px rgba(43, 31, 75, .035);
            margin-bottom: 23px;
        }

        .cartao h2 {
            color: #302650;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitulo {
            color: #918b9e;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 22px;
        }

        .campo {
            margin-bottom: 16px;
        }

        .campo label {
            display: block;
            color: #514a61;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .campo input,
        .campo textarea,
        .campo select {
            width: 100%;
            border: 1px solid #e6e2ed;
            background: #fcfbfe;
            border-radius: 10px;
            padding: 12px 13px;
            color: #302b3d;
            font-size: 13px;
            outline: none;
        }

        .campo input:focus,
        .campo textarea:focus,
        .campo select:focus {
            border-color: #8872c2;
            box-shadow: 0 0 0 3px rgba(136, 114, 194, .10);
        }

        .campo textarea {
            resize: vertical;
            min-height: 80px;
        }

        .linha-campos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* ==========================================
           MENSAGENS
        ========================================== */

        .mensagem {
            padding: 13px 15px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .sucesso {
            background: #e9f8ef;
            color: #287344;
        }

        .erro {
            background: #fff0f0;
            color: #b33c3c;
        }

        /* ==========================================
           LISTA DE TAREFAS
        ========================================== */

        .lista-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .lista-topo h2 {
            font-size: 19px;
        }

        .lista-topo span {
            color: #9690a2;
            font-size: 12px;
        }

        .lista-tarefas {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .tarefa {
            border: 1px solid #ece9f3;
            border-radius: 13px;
            padding: 19px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .tarefa-info {
            flex: 1;
            min-width: 0;
        }

        .tarefa h3 {
            color: #302650;
            font-size: 15px;
            margin: 8px 0;
            overflow-wrap: anywhere;
        }

        .tarefa p {
            color: #918b9e;
            font-size: 12px;
            line-height: 1.5;
            overflow-wrap: anywhere;
            margin-top: 4px;
        }

        .etiqueta {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .etiqueta-disciplina {
            background: #f0edf8;
            color: #625080;
        }

        .status-pendente {
            background: #fff3df;
            color: #a36b19;
        }

        .status-andamento {
            background: #e8f1ff;
            color: #416eaa;
        }

        .status-concluida {
            background: #e5f7ec;
            color: #348052;
        }

        .tipo {
            color: #8b8499;
            font-size: 11px;
            margin-left: 5px;
        }

        .tarefa-acoes {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .tarefa-acoes button,
        .tarefa-acoes select {
            border: none;
            border-radius: 8px;
            padding: 9px 11px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .botao-status {
            background: #eee9fa;
            color: #5b4787;
        }

        .botao-excluir {
            background: #fff0f0;
            color: #bd5151;
        }

        /* ==========================================
           EDIÇÃO
        ========================================== */

        .edicao {
            width: 100%;
            margin-top: 15px;
            border-top: 1px solid #eeeaf4;
            padding-top: 15px;
        }

        .edicao summary {
            color: #65518f;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .edicao[open] summary {
            margin-bottom: 18px;
        }

        .edicao .campo {
            margin-bottom: 12px;
        }

        .edicao .campo input,
        .edicao .campo select,
        .edicao .campo textarea {
            padding: 10px;
            font-size: 12px;
        }

        /* ==========================================
           ESTADO VAZIO
        ========================================== */

        .vazio {
            border: 1px dashed #d9d2e7;
            border-radius: 15px;
            padding: 35px 20px;
            text-align: center;
            color: #918b9e;
            font-size: 13px;
            line-height: 1.7;
        }

        .vazio h3 {
            color: #514a61;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .voltar {
            display: inline-block;
            margin-top: 5px;
            color: #65518f;
            font-size: 13px;
            font-weight: 600;
        }

        .voltar:hover {
            text-decoration: underline;
        }

        /* ==========================================
           BOTÃO FLUTUANTE STUDIA - INICIO.PHP
        ========================================== */

        .botao-studia {
            position: fixed;
            right: 28px;
            bottom: 25px;
            z-index: 90;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 19px;
            background: linear-gradient(120deg, #493b70, #8873c8);
            color: white;
            border-radius: 30px;
            box-shadow: 0 7px 22px rgba(48, 38, 80, .22);
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .botao-studia:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(48, 38, 80, .28);
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

        /* ==========================================
           RESPONSIVIDADE - INICIO.PHP
        ========================================== */

        @media (max-width: 1200px) {
            .main {
                padding: 30px;
            }

            .resumo {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 700px) {

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

            .main {
                margin-left: 75px;
                padding: 25px 16px;
            }

            .botao-studia {
                right: 16px;
                bottom: 16px;
                padding: 11px 15px;
            }

            .topo {
                align-items: flex-start;
                flex-direction: column;
            }

            .topo h1 {
                font-size: 24px;
            }

            .linha-campos {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .tarefa {
                align-items: flex-start;
                flex-direction: column;
            }

            .tarefa-acoes {
                width: 100%;
            }
        }

        @media (max-width: 450px) {

            .main {
                padding: 22px 12px 85px;
            }

            .resumo {
                gap: 9px;
            }

            .resumo-card {
                padding: 14px;
            }

            .resumo-card strong {
                font-size: 21px;
            }

            .cartao {
                padding: 17px;
            }

            .topo h1 {
                font-size: 22px;
            }
        }

    </style>

</head>

<body>

    <!-- ==========================================
         SIDEBAR
    ========================================== -->

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

            <a href="tarefas.php" class="active">
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

        <!-- PERFIL -->

        <div class="perfil-sidebar">

            <div class="perfil-link">

                <a href="perfil.php" class="avatar" title="Acessar meu perfil">

                    <?php if (!empty($foto_usuario)): ?>

                        <img
                            src="<?php echo escapar($foto_usuario); ?>"
                            alt="Foto de perfil"
                        >

                    <?php else: ?>

                        <?php echo escapar($nome_inicial); ?>

                    <?php endif; ?>

                </a>

                <div class="perfil-info">

                    <strong>
                        <?php echo escapar($nome_usuario); ?>
                    </strong>

                    <a href="perfil.php">Meu perfil →</a>

                </div>

            </div>

        </div>

    </aside>

    <!-- ==========================================
         CONTEÚDO PRINCIPAL
    ========================================== -->

    <main class="main">

        <header class="topo">

            <div>

                <h1>Minhas tarefas</h1>

                <p>
                    Acompanhe trabalhos, provas e atividades das suas disciplinas.
                </p>

            </div>

            <button
                type="button"
                class="botao-nova"
                onclick="document.getElementById('formulario-nova').scrollIntoView({behavior:'smooth'});"
            >
                + Nova tarefa
            </button>

        </header>

        <!-- RESUMO -->

        <section class="resumo">

            <div class="resumo-card">
                <span>Total de tarefas</span>
                <strong><?php echo $total_tarefas; ?></strong>
            </div>

            <div class="resumo-card">
                <span>Pendentes</span>
                <strong><?php echo $total_pendentes; ?></strong>
            </div>

            <div class="resumo-card">
                <span>Em andamento</span>
                <strong><?php echo $total_andamento; ?></strong>
            </div>

            <div class="resumo-card">
                <span>Concluídas</span>
                <strong><?php echo $total_concluidas; ?></strong>
            </div>

        </section>

        <!-- MENSAGENS -->

        <?php if ($mensagem !== ""): ?>

            <div class="mensagem <?php echo escapar($tipo_mensagem); ?>">
                <?php echo escapar($mensagem); ?>
            </div>

        <?php endif; ?>

        <!-- FORMULÁRIO DE CADASTRO -->

        <section class="cartao" id="formulario-nova">

            <h2>Adicionar nova tarefa</h2>

            <p class="subtitulo">
                Registre uma atividade e defina quando ela precisa ser entregue.
            </p>

            <?php if (count($disciplinas) > 0): ?>

                <form method="POST">

                    <input type="hidden" name="acao" value="cadastrar">

                    <input
                        type="hidden"
                        name="token_csrf"
                        value="<?php echo escapar($token_csrf); ?>"
                    >

                    <div class="campo">

                        <label for="titulo">Título da tarefa</label>

                        <input
                            type="text"
                            id="titulo"
                            name="titulo"
                            maxlength="150"
                            placeholder="Ex.: Entregar projeto de programação"
                            required
                        >

                    </div>

                    <div class="campo">

                        <label for="descricao">Descrição</label>

                        <textarea
                            id="descricao"
                            name="descricao"
                            placeholder="Descreva os detalhes da atividade..."
                        ></textarea>

                    </div>

                    <div class="linha-campos">

                        <div class="campo">

                            <label for="disciplina_id">Disciplina</label>

                            <select id="disciplina_id" name="disciplina_id" required>

                                <option value="">Selecione uma disciplina</option>

                                <?php foreach ($disciplinas as $disciplina): ?>

                                    <option value="<?php echo (int) $disciplina["id"]; ?>">
                                        <?php echo escapar($disciplina["nome"]); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="campo">

                            <label for="tipo">Tipo de tarefa</label>

                            <select id="tipo" name="tipo" required>

                                <option value="atividade">Atividade</option>
                                <option value="trabalho">Trabalho</option>
                                <option value="prova">Prova</option>
                                <option value="outro">Outro</option>

                            </select>

                        </div>

                    </div>

                    <div class="campo">

                        <label for="data_entrega">Data de entrega</label>

                        <input
                            type="date"
                            id="data_entrega"
                            name="data_entrega"
                        >

                    </div>

                    <button type="submit" class="botao-salvar">
                        Cadastrar tarefa
                    </button>

                </form>

            <?php else: ?>

                <div class="vazio">

                    <h3>Você ainda não tem disciplinas cadastradas.</h3>

                    <p>
                        Antes de adicionar tarefas, cadastre pelo menos uma disciplina.
                    </p>

                    <br>

                    <a href="disciplinas.php" class="voltar">
                        Cadastrar disciplina →
                    </a>

                </div>

            <?php endif; ?>

        </section>

        <!-- LISTA DE TAREFAS -->

        <section class="cartao">

            <div class="lista-topo">

                <h2>Suas atividades</h2>

                <span><?php echo $total_tarefas; ?> registros</span>

            </div>

            <?php if ($total_tarefas > 0): ?>

                <div class="lista-tarefas">

                    <?php foreach ($tarefas as $tarefa): ?>

                        <article class="tarefa">

                            <div class="tarefa-info">

                                <span class="etiqueta etiqueta-disciplina">
                                    <?php echo escapar($tarefa["nome_disciplina"]); ?>
                                </span>

                                <span class="tipo">
                                    <?php echo escapar(ucfirst($tarefa["tipo"])); ?>
                                </span>

                                <h3>
                                    <?php echo escapar($tarefa["titulo"]); ?>
                                </h3>

                                <p>
                                    <?php
                                    echo escapar(
                                        $tarefa["descricao"] ?: "Sem descrição."
                                    );
                                    ?>
                                </p>

                                <p>
                                    Entrega:
                                    <?php echo escapar(formatarData($tarefa["data_entrega"])); ?>
                                </p>

                                <br>

                                <?php

                                $classe_status = "status-pendente";

                                if ($tarefa["status"] === "em andamento") {
                                    $classe_status = "status-andamento";

                                } elseif ($tarefa["status"] === "concluida") {
                                    $classe_status = "status-concluida";
                                }

                                ?>

                                <span class="etiqueta <?php echo $classe_status; ?>">
                                    <?php echo escapar(ucfirst($tarefa["status"])); ?>
                                </span>

                            </div>

                            <div class="tarefa-acoes">

                                <!-- ALTERAR STATUS -->

                                <form method="POST">

                                    <input type="hidden" name="acao" value="status">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int) $tarefa["id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="token_csrf"
                                        value="<?php echo escapar($token_csrf); ?>"
                                    >

                                    <select
                                        name="status"
                                        class="botao-status"
                                        onchange="this.form.submit()"
                                        aria-label="Alterar status da tarefa"
                                    >

                                        <option
                                            value="pendente"
                                            <?php echo $tarefa["status"] === "pendente" ? "selected" : ""; ?>
                                        >
                                            Pendente
                                        </option>

                                        <option
                                            value="em andamento"
                                            <?php echo $tarefa["status"] === "em andamento" ? "selected" : ""; ?>
                                        >
                                            Em andamento
                                        </option>

                                        <option
                                            value="concluida"
                                            <?php echo $tarefa["status"] === "concluida" ? "selected" : ""; ?>
                                        >
                                            Concluída
                                        </option>

                                    </select>

                                </form>

                                <!-- EXCLUIR -->

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Deseja realmente excluir esta tarefa?');"
                                >

                                    <input type="hidden" name="acao" value="excluir">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int) $tarefa["id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="token_csrf"
                                        value="<?php echo escapar($token_csrf); ?>"
                                    >

                                    <button type="submit" class="botao-excluir">
                                        Excluir
                                    </button>

                                </form>

                            </div>

                            <!-- EDITAR -->

                            <details class="edicao">

                                <summary>Editar tarefa</summary>

                                <form method="POST">

                                    <input type="hidden" name="acao" value="editar">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int) $tarefa["id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="token_csrf"
                                        value="<?php echo escapar($token_csrf); ?>"
                                    >

                                    <div class="campo">

                                        <label>Título</label>

                                        <input
                                            type="text"
                                            name="titulo"
                                            maxlength="150"
                                            value="<?php echo escapar($tarefa["titulo"]); ?>"
                                            required
                                        >

                                    </div>

                                    <div class="campo">

                                        <label>Descrição</label>

                                        <textarea name="descricao"><?php
                                            echo escapar($tarefa["descricao"] ?? "");
                                        ?></textarea>

                                    </div>

                                    <div class="linha-campos">

                                        <div class="campo">

                                            <label>Disciplina</label>

                                            <select name="disciplina_id" required>

                                                <?php foreach ($disciplinas as $disciplina): ?>

                                                    <option
                                                        value="<?php echo (int) $disciplina["id"]; ?>"
                                                        <?php echo (int) $tarefa["disciplina_id"] === (int) $disciplina["id"] ? "selected" : ""; ?>
                                                    >
                                                        <?php echo escapar($disciplina["nome"]); ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                        <div class="campo">

                                            <label>Tipo</label>

                                            <select name="tipo" required>

                                                <option
                                                    value="atividade"
                                                    <?php echo $tarefa["tipo"] === "atividade" ? "selected" : ""; ?>
                                                >
                                                    Atividade
                                                </option>

                                                <option
                                                    value="trabalho"
                                                    <?php echo $tarefa["tipo"] === "trabalho" ? "selected" : ""; ?>
                                                >
                                                    Trabalho
                                                </option>

                                                <option
                                                    value="prova"
                                                    <?php echo $tarefa["tipo"] === "prova" ? "selected" : ""; ?>
                                                >
                                                    Prova
                                                </option>

                                                <option
                                                    value="outro"
                                                    <?php echo $tarefa["tipo"] === "outro" ? "selected" : ""; ?>
                                                >
                                                    Outro
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="campo">

                                        <label>Data de entrega</label>

                                        <input
                                            type="date"
                                            name="data_entrega"
                                            value="<?php echo escapar($tarefa["data_entrega"] ?? ""); ?>"
                                        >

                                    </div>

                                    <button type="submit" class="botao-salvar">
                                        Salvar alterações
                                    </button>

                                </form>

                            </details>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="vazio">

                    <h3>Nenhuma tarefa cadastrada.</h3>

                    <p>
                        Suas atividades aparecerão aqui assim que você cadastrar a primeira.
                    </p>

                </div>

            <?php endif; ?>

        </section>

        <a href="inicio.php" class="voltar">
            ← Voltar ao painel inicial
        </a>

    </main>

    <!-- BOTÃO FLUTUANTE DO ASSISTENTE -->

    <a
        href="assistente.php"
        class="botao-studia"
        aria-label="Abrir assistente studIA"
    >
        <span class="botao-studia-icone">✦</span>
        <span>studIA</span>
    </a>

</body>
</html>