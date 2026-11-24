
import React from "react";
import { createRoot } from "react-dom/client";
import {
    HashRouter,
    Routes,
    Route,
    Link
} from "react-router-dom";

const h = React.createElement;
const app = document.getElementById("app");

function formatarData(data) {
    if (!data) return "Sem data";
    const partes = data.split("-");
    if (partes.length !== 3) return data;
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function Cabecalho({ titulo, descricao }) {
    const data = new Date().toLocaleDateString("pt-BR", {
        day: "2-digit",
        month: "long",
        year: "numeric"
    });

    return h("div", { className: "topo" },
        h("div", null,
            h("h1", null, titulo),
            h("p", null, descricao)
        ),
        h("div", { className: "data-atual" }, data)
    );
}

function Card({ icone, numero, nome }) {
    return h("div", { className: "card" },
        h("div", { className: "card-topo" },
            h("div", { className: "card-icone" }, icone),
            h("div", { className: "card-numero" }, numero)
        ),
        h("div", { className: "card-nome" }, nome)
    );
}

function Inicio({ dados }) {
    const tarefas = dados.proximas_tarefas || [];

    return h(React.Fragment, null,
        h(Cabecalho, {
            titulo: "Visão geral",
            descricao: "Acompanhe sua rotina acadêmica em um só lugar."
        }),

        h("section", { className: "boas-vindas" },
            h("h2", null, `Olá, ${dados.usuario}!`),
            h("p", null,
                "Organize seus estudos, acompanhe suas atividades e mantenha tudo em dia. Seu espaço de estudos começa aqui."
            )
        ),

        h("div", { className: "secao-titulo" },
            h("h2", null, "Resumo da sua conta")
        ),

        h("section", { className: "cards" },
            h(Card, {
                icone: "▤",
                numero: dados.totais.disciplinas,
                nome: "Disciplinas"
            }),
            h(Card, {
                icone: "✓",
                numero: dados.totais.tarefas,
                nome: "Tarefas cadastradas"
            }),
            h(Card, {
                icone: "▧",
                numero: dados.totais.materiais,
                nome: "Materiais"
            }),
            h(Card, {
                icone: "♧",
                numero: dados.totais.grupos,
                nome: "Grupos de estudo"
            })
        ),

        h("section", { className: "conteudo-inferior" },
            h("div", { className: "painel" },
                h("h3", null, "Próximas tarefas"),

                tarefas.length === 0
                    ? h("p", { className: "vazio" },
                        "Você ainda não possui tarefas próximas. Quando cadastrar uma tarefa com data de entrega, ela aparecerá aqui."
                    )
                    : tarefas.map(tarefa =>
                        h("div", {
                            className: "tarefa",
                            key: tarefa.id
                        },
                            h("div", null,
                                h("strong", null, tarefa.titulo),
                                h("small", null, tarefa.disciplina)
                            ),
                            h("span", { className: "prazo" },
                                formatarData(tarefa.data_entrega)
                            )
                        )
                    )
            ),

            h("div", { className: "painel" },
                h("h3", null, "Acesso rápido"),
                h("div", { className: "atalhos" },

                    h(Link, {
                        to: "/planejamento",
                        className: "atalho"
                    },
                        h("div", { className: "atalho-icone" }, "▤"),
                        h("div", null,
                            h("strong", null, "Planejamento"),
                            h("small", null, "Organize sua rotina")
                        )
                    ),

                    h(Link, {
                        to: "/progresso",
                        className: "atalho"
                    },
                        h("div", { className: "atalho-icone" }, "↗"),
                        h("div", null,
                            h("strong", null, "Meu progresso"),
                            h("small", null, "Confira seus dados acadêmicos")
                        )
                    ),

                    h("a", {
                        href: "disciplinas.php",
                        className: "atalho"
                    },
                        h("div", { className: "atalho-icone" }, "+"),
                        h("div", null,
                            h("strong", null, "Minhas disciplinas"),
                            h("small", null, "Acesse suas disciplinas")
                        )
                    )
                )
            )
        )
    );
}

function Planejamento({ dados }) {
    return h(React.Fragment, null,
        h(Cabecalho, {
            titulo: "Planejamento",
            descricao: "Organize suas atividades acadêmicas."
        }),

        h("div", { className: "pagina-extra" },
            h("h2", null, "Seu espaço de planejamento"),
            h("p", null,
                `Você possui ${dados.totais.disciplinas} disciplinas e ${dados.totais.tarefas} tarefas cadastradas. Acesse as páginas abaixo para continuar organizando sua rotina.`
            ),
            h("a", {
                href: "disciplinas.php",
                className: "botao-voltar"
            }, "Acessar disciplinas"),
            h("a", {
                href: "tarefas.php",
                className: "botao-voltar",
                style: { marginLeft: "10px" }
            }, "Acessar tarefas")
        )
    );
}

function Progresso({ dados }) {
    return h(React.Fragment, null,
        h(Cabecalho, {
            titulo: "Meu progresso",
            descricao: "Acompanhe seus registros acadêmicos."
        }),

        h("section", { className: "cards" },
            h(Card, {
                icone: "▤",
                numero: dados.totais.disciplinas,
                nome: "Disciplinas cadastradas"
            }),
            h(Card, {
                icone: "✓",
                numero: dados.totais.tarefas,
                nome: "Tarefas cadastradas"
            }),
            h(Card, {
                icone: "▧",
                numero: dados.totais.materiais,
                nome: "Materiais cadastrados"
            }),
            h(Card, {
                icone: "♧",
                numero: dados.totais.grupos,
                nome: "Grupos de estudo"
            })
        ),

        h("div", {
            className: "pagina-extra",
            style: { marginTop: "22px" }
        },
            h("h2", null, "Continue avançando!"),
            h("p", null,
                "Mantenha suas disciplinas, tarefas e materiais atualizados para acompanhar melhor sua organização acadêmica."
            )
        )
    );
}

function Aplicacao({ dados }) {
    return h(HashRouter, null,
        h(Routes, null,
            h(Route, {
                path: "/",
                element: h(Inicio, { dados })
            }),
            h(Route, {
                path: "/planejamento",
                element: h(Planejamento, { dados })
            }),
            h(Route, {
                path: "/progresso",
                element: h(Progresso, { dados })
            }),
            h(Route, {
                path: "*",
                element: h(Inicio, { dados })
            })
        )
    );
}

async function iniciar() {
    try {
        const resposta = await fetch("inicio.php?api=dashboard", {
            method: "GET",
            headers: { "Accept": "application/json" },
            cache: "no-store"
        });

        if (!resposta.ok) {
            throw new Error("Erro HTTP " + resposta.status);
        }

        const dados = await resposta.json();

        if (!dados.sucesso) {
            throw new Error(dados.erro || "Erro ao carregar os dados.");
        }

        createRoot(app).render(
            h(Aplicacao, { dados })
        );

    } catch (erro) {
        console.error("Erro ao iniciar o Studia:", erro);

        app.innerHTML = `
            <div class="erro-carregamento">
                <strong>Não foi possível carregar o painel.</strong>
                <p style="margin-top:8px">${String(erro.message)}</p>
                <p style="margin-top:8px">Atualize a página e tente novamente.</p>
            </div>
        `;
    }
}

iniciar();
