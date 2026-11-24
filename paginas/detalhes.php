
<?php

// ==================================================
// VERIFICAÇÃO DE LOGIN
// ==================================================

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../configuracoes/conexao.php";
require_once "../logs/registro.php";

$usuario_id = $_SESSION["usuario_id"];

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================

function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function formatarData($data) {
    if (empty($data)) {
        return "Não informada";
    }

    $timestamp = strtotime($data);

    return $timestamp ? date("d/m/Y H:i", $timestamp) : escapar($data);
}

// ==================================================
// IDENTIFICAÇÃO DO CONTEÚDO
// ==================================================

$tipo = $_GET["tipo"] ?? "";
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

$tituloPagina = "Detalhes";
$subtitulo = "";
$detalhes = [];
$paginaVoltar = "inicio.php";
$encontrado = false;
$erro = "";

try {

    if (!$id || $id < 1) {
        $erro = "O conteúdo solicitado não foi encontrado.";

    } else {

        switch ($tipo) {

            // ==========================================
            // DETALHES DE UMA DISCIPLINA
            // ==========================================

            case "disciplina":

                $consulta = $conexao->prepare("
                    SELECT
                        d.id,
                        d.nome,
                        d.descricao,
                        d.cor,
                        d.data_criacao,
                        (
                            SELECT COUNT(*)
                            FROM tarefas t
                            WHERE t.disciplina_id = d.id
                        ) AS total_tarefas
                    FROM disciplinas d
                    WHERE d.id = :id
                    AND d.usuario_id = :usuario_id
                    LIMIT 1
                ");

                $consulta->execute([
                    ":id" => $id,
                    ":usuario_id" => $usuario_id
                ]);

                $dados = $consulta->fetch(PDO::FETCH_ASSOC);

                if ($dados) {

                    $encontrado = true;
                    $tituloPagina = "Detalhes da disciplina";
                    $subtitulo = $dados["nome"];
                    $paginaVoltar = "disciplinas.php";

                    $detalhes = [
                        "Nome" => $dados["nome"],
                        "Descrição" => $dados["descricao"] ?: "Nenhuma descrição cadastrada.",
                        "Cor identificadora" => $dados["cor"],
                        "Quantidade de tarefas" => $dados["total_tarefas"],
                        "Data de criação" => formatarData($dados["data_criacao"])
                    ];
                }

                break;

            // ==========================================
            // DETALHES DE UMA TAREFA
            // ==========================================

            case "tarefa":

                $consulta = $conexao->prepare("
                    SELECT
                        t.id,
                        t.titulo,
                        t.descricao,
                        t.tipo,
                        t.data_entrega,
                        t.status,
                        t.data_criacao,
                        d.nome AS disciplina
                    FROM tarefas t
                    INNER JOIN disciplinas d
                        ON d.id = t.disciplina_id
                    WHERE t.id = :id
                    AND d.usuario_id = :usuario_id
                    LIMIT 1
                ");

                $consulta->execute([
                    ":id" => $id,
                    ":usuario_id" => $usuario_id
                ]);

                $dados = $consulta->fetch(PDO::FETCH_ASSOC);

                if ($dados) {

                    $encontrado = true;
                    $tituloPagina = "Detalhes da tarefa";
                    $subtitulo = $dados["titulo"];
                    $paginaVoltar = "tarefas.php";

                    $detalhes = [
                        "Título" => $dados["titulo"],
                        "Descrição" => $dados["descricao"] ?: "Nenhuma descrição cadastrada.",
                        "Disciplina" => $dados["disciplina"],
                        "Tipo" => $dados["tipo"],
                        "Data de entrega" => formatarData($dados["data_entrega"]),
                        "Status" => $dados["status"],
                        "Data de criação" => formatarData($dados["data_criacao"])
                    ];
                }

                break;

            // ==========================================
            // DETALHES DE UM MATERIAL
            // ==========================================

            case "material":

                $consulta = $conexao->prepare("
                    SELECT
                        id,
                        titulo,
                        descricao,
                        tipo,
                        arquivo,
                        data_criacao
                    FROM materiais
                    WHERE id = :id
                    AND usuario_id = :usuario_id
                    LIMIT 1
                ");

                $consulta->execute([
                    ":id" => $id,
                    ":usuario_id" => $usuario_id
                ]);

                $dados = $consulta->fetch(PDO::FETCH_ASSOC);

                if ($dados) {

                    $encontrado = true;
                    $tituloPagina = "Detalhes do material";
                    $subtitulo = $dados["titulo"];
                    $paginaVoltar = "materiais.php";

                    $detalhes = [
                        "Título" => $dados["titulo"],
                        "Descrição" => $dados["descricao"] ?: "Nenhuma descrição cadastrada.",
                        "Tipo de material" => $dados["tipo"],
                        "Arquivo associado" => $dados["arquivo"] ?: "Nenhum arquivo anexado.",
                        "Data de criação" => formatarData($dados["data_criacao"])
                    ];
                }

                break;

            // ==========================================
            // DETALHES DE UM GRUPO DE ESTUDOS
            // ==========================================

            case "grupo":

                $consulta = $conexao->prepare("
                    SELECT
                        g.id,
                        g.nome,
                        g.descricao,
                        g.codigo_convite,
                        g.data_criacao,
                        u.nome AS criador,
                        (
                            SELECT COUNT(*)
                            FROM usuarios_grupos ug
                            WHERE ug.group_id = g.id
                        ) + 1 AS total_membros
                    FROM grupos_estudo g
                    INNER JOIN usuarios u
                        ON u.id = g.criador_id
                    WHERE g.id = :id
                    AND (
                        g.criador_id = :usuario_id
                        OR EXISTS (
                            SELECT 1
                            FROM usuarios_grupos ug2
                            WHERE ug2.group_id = g.id
                            AND ug2.user_id = :usuario_id2
                        )
                    )
                    LIMIT 1
                ");

                $consulta->execute([
                    ":id" => $id,
                    ":usuario_id" => $usuario_id,
                    ":usuario_id2" => $usuario_id
                ]);

                $dados = $consulta->fetch(PDO::FETCH_ASSOC);

                if ($dados) {

                    $encontrado = true;
                    $tituloPagina = "Detalhes do grupo";
                    $subtitulo = $dados["nome"];
                    $paginaVoltar = "grupos.php";

                    $detalhes = [
                        "Nome do grupo" => $dados["nome"],
                        "Descrição" => $dados["descricao"] ?: "Nenhuma descrição cadastrada.",
                        "Criador" => $dados["criador"],
                        "Código de convite" => $dados["codigo_convite"],
                        "Quantidade de membros" => $dados["total_membros"],
                        "Data de criação" => formatarData($dados["data_criacao"])
                    ];
                }

                break;

            default:
                $erro = "Tipo de conteúdo inválido.";
                break;
        }

        if (!$encontrado && $erro === "") {
            $erro = "Conteúdo não encontrado ou você não tem permissão para acessá-lo.";
        }
    }

    // ==================================================
    // REGISTRO DE LOG
    // ==================================================

    if ($encontrado) {

        registrarLog(
            "Detalhes de conteúdo visualizados",
            "Tipo: " . $tipo . " | ID: " . $id
        );

    } else {

        registrarLog(
            "Falha ao acessar detalhes",
            "Tipo: " . $tipo . " | ID: " . ($id ?: "inválido")
        );
    }

} catch (PDOException $e) {

    $erro = "Não foi possível carregar os detalhes. Tente novamente.";

    registrarLog(
        "Erro ao carregar detalhes",
        "Tipo: " . $tipo . " | ID: " . ($id ?: "inválido") .
        " | Erro: " . get_class($e)
    );
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo escapar($tituloPagina); ?> | Studia</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f6f5fa;
            color: #302650;
            min-height: 100vh;
        }

        header {
            background: #302650;
            padding: 22px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 26px;
            font-weight: bold;
            text-decoration: none;
        }

        .voltar {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .container {
            width: 90%;
            max-width: 850px;
            margin: 50px auto;
        }

        .cabecalho {
            margin-bottom: 25px;
        }

        .cabecalho h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .cabecalho p {
            color: #777;
            font-size: 15px;
        }

        .card {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(48, 38, 80, 0.07);
        }

        .card h2 {
            font-size: 21px;
            margin-bottom: 25px;
            color: #51417c;
        }

        .informacao {
            padding: 17px 0;
            border-bottom: 1px solid #eeeaf5;
        }

        .informacao:last-child {
            border-bottom: none;
        }

        .informacao strong {
            display: block;
            font-size: 13px;
            color: #817895;
            margin-bottom: 8px;
        }

        .informacao p {
            font-size: 15px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .botao-voltar {
            display: inline-block;
            margin-top: 25px;
            padding: 13px 22px;
            background: #6551a1;
            color: white;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s;
        }

        .botao-voltar:hover {
            background: #51417c;
        }

        .erro {
            background: white;
            padding: 30px;
            border-radius: 14px;
            color: #a12c2c;
            box-shadow: 0 8px 30px rgba(48, 38, 80, 0.07);
            line-height: 1.7;
        }

        @media (max-width: 650px) {

            header {
                padding: 20px 5%;
            }

            .container {
                margin: 30px auto;
            }

            .card {
                padding: 25px 20px;
            }

            .cabecalho h1 {
                font-size: 23px;
            }
        }

    </style>

</head>

<body>

<header>

    <a href="../index.php" class="logo">Studia.</a>

    <a href="<?php echo escapar($paginaVoltar); ?>" class="voltar">
        Voltar
    </a>

</header>

<main class="container">

    <div class="cabecalho">

        <h1><?php echo escapar($tituloPagina); ?></h1>

        <?php if ($encontrado): ?>

            <p><?php echo escapar($subtitulo); ?></p>

        <?php endif; ?>

    </div>

    <?php if ($encontrado): ?>

        <section class="card">

            <h2>Informações do conteúdo</h2>

            <?php foreach ($detalhes as $rotulo => $valor): ?>

                <div class="informacao">

                    <strong><?php echo escapar($rotulo); ?></strong>

                    <p><?php echo escapar($valor); ?></p>

                </div>

            <?php endforeach; ?>

            <a
                href="<?php echo escapar($paginaVoltar); ?>"
                class="botao-voltar"
            >
                Voltar para a página anterior
            </a>

        </section>

    <?php else: ?>

        <div class="erro">

            <?php echo escapar($erro); ?>

            <br>

            <a
                href="<?php echo escapar($paginaVoltar); ?>"
                class="botao-voltar"
            >
                Voltar
            </a>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
