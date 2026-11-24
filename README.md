
# Studia — Organização Acadêmica

O Studia é uma aplicação web desenvolvida para auxiliar estudantes na organização de disciplinas, tarefas, materiais e grupos de estudo.

## Tecnologias utilizadas

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- React
- React Router
- AJAX
- Esbuild

## Funcionalidades

- Cadastro e autenticação de usuários.
- Recuperação de senha por e-mail.
- Gerenciamento de perfil e foto.
- Cadastro e organização de disciplinas.
- Gerenciamento de tarefas e prazos.
- Armazenamento de materiais de estudo.
- Associação de materiais às tarefas.
- Criação e participação em grupos de estudo.
- Busca de grupos.
- Assistente acadêmico com inteligência artificial (studIA).
- Formulário de contato.
- Listagens públicas de disciplinas e grupos.
- Registro de atividades do sistema.

## Requisitos

- XAMPP ou ambiente equivalente.
- PHP com PDO habilitado.
- MySQL ou MariaDB.
- Node.js e npm.

## Instalação local

1. Instale e inicie o Apache e o MySQL pelo XAMPP.
2. Coloque a pasta `studia` dentro de `htdocs`.
3. Acesse o phpMyAdmin.
4. Crie um banco de dados chamado `studia`.
5. Importe o arquivo `studia.sql`.
6. Configure as credenciais do banco em `configuracoes/conexao.php`.
7. Configure as chaves de API e SMTP em `configuracoes/segredos.php`.
8. Abra o terminal na pasta do projeto e execute:

   ```bash
   npm install
   npm run build
   ```

9. Acesse:

   http://localhost/studia/

## Configurações privadas

As credenciais de serviços externos devem ser configuradas localmente em `configuracoes/segredos.php`.

Esse arquivo não deve ser compartilhado nem publicado no repositório.

## Banco de dados

O arquivo `studia.sql` contém a estrutura e os dados exportados do banco de dados utilizado no desenvolvimento.

## Projeto acadêmico

Desenvolvido como projeto acadêmico.
