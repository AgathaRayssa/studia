
<?php
// Inicia a sessão caso ainda não esteja iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Caminho automático das páginas do Studia.
$base = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\") . "/";

// Identifica a página atual.
$pagina_atual = basename($_SERVER["SCRIPT_NAME"]);

$menu = [
    [
        "nome" => "Início",
        "arquivo" => "inicio.php",
        "icone" => "⌂"
    ],
    [
        "nome" => "Disciplinas",
        "arquivo" => "disciplinas.php",
        "icone" => "▤"
    ],
    [
        "nome" => "Tarefas",
        "arquivo" => "tarefas.php",
        "icone" => "✓"
    ],
    [
        "nome" => "Materiais",
        "arquivo" => "materiais.php",
        "icone" => "▧"
    ],
    [
        "nome" => "Grupos de estudo",
        "arquivo" => "grupos.php",
        "icone" => "♧"
    ],
    [
        "nome" => "Meu perfil",
        "arquivo" => "perfil.php",
        "icone" => "○"
    ],
    [
        "nome" => "Contato",
        "arquivo" => "contato.php",
        "icone" => "✉"
    ]
];
?>

<!-- MENU DE NAVEGAÇÃO DO STUDIA -->
<nav class="menu-studia">

    <a href="<?php echo $base; ?>inicio.php" class="menu-logo">
        Studia<span>.</span>
    </a>

    <div class="menu-links">

        <?php foreach ($menu as $item): ?>

            <a
                href="<?php echo $base . $item["arquivo"]; ?>"
                class="menu-link <?php echo $pagina_atual === $item["arquivo"] ? "ativo" : ""; ?>"
            >
                <span class="menu-icone">
                    <?php echo $item["icone"]; ?>
                </span>

                <?php echo htmlspecialchars($item["nome"], ENT_QUOTES, "UTF-8"); ?>
            </a>

        <?php endforeach; ?>

    </div>

    <a href="<?php echo $base; ?>sair.php" class="menu-sair">
        <span>↪</span>
        Sair da conta
    </a>

</nav>

<!-- INÍCIO DO CSS -->
<style>
    .menu-studia {
        width: 100%;
        min-height: 72px;
        padding: 12px 5%;
        background: #302650;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 25px;
        flex-wrap: wrap;
        font-family: Arial, sans-serif;
        box-sizing: border-box;
    }

    .menu-logo {
        color: white;
        font-size: 27px;
        font-weight: bold;
        text-decoration: none;
        white-space: nowrap;
    }

    .menu-logo span {
        color: #b9a5ff;
    }

    .menu-links {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        flex-wrap: wrap;
    }

    .menu-link {
        color: #e9e4f7;
        text-decoration: none;
        font-size: 13px;
        padding: 11px 12px;
        border-radius: 8px;
        transition: 0.2s;
        white-space: nowrap;
    }

    .menu-link:hover,
    .menu-link.ativo {
        background: #51417c;
        color: white;
    }

    .menu-icone {
        margin-right: 5px;
        font-size: 16px;
    }

    .menu-sair {
        color: #f1d9e0;
        text-decoration: none;
        font-size: 13px;
        white-space: nowrap;
    }

    .menu-sair:hover {
        color: white;
    }

    @media (max-width: 850px) {
        .menu-studia {
            justify-content: center;
        }

        .menu-links {
            width: 100%;
        }
    }

    @media (max-width: 500px) {
        .menu-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            width: 100%;
        }

        .menu-link {
            text-align: center;
        }
    }
</style>
<!-- FIM DO CSS -->
