# Studia — Plataforma de Organização Acadêmica

O **Studia** é uma aplicação web desenvolvida para auxiliar estudantes na organização da rotina acadêmica, reunindo disciplinas, tarefas, materiais e grupos de estudo em um único ambiente.

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
- Cadastro, edição e organização de disciplinas.
- Gerenciamento de tarefas e prazos.
- Armazenamento e organização de materiais.
- Associação de materiais às tarefas.
- Criação e participação em grupos de estudo.
- Busca de grupos.
- Assistente acadêmico com inteligência artificial (studIA).
- Formulário de contato.
- Listagens públicas de disciplinas e grupos.
- Registro de atividades do sistema.
- Termos de uso e informações de privacidade.

## Requisitos

- XAMPP ou ambiente equivalente.
- PHP com PDO, cURL e suporte a conexões SMTP.
- MySQL ou MariaDB.
- Node.js e npm.
- Conexão com a internet para os serviços externos.

## Instalação local

1. Instale e inicie o Apache e o MySQL pelo XAMPP.
2. Coloque a pasta `studia` dentro do diretório `htdocs`.
3. Acesse o phpMyAdmin.
4. Crie um banco de dados chamado `studia`.
5. Importe o arquivo `studia.sql` para criar as tabelas.
6. Configure as credenciais do banco em `configuracoes/conexao.php`.
7. Copie `configuracoes/segredos.example.php` para `configuracoes/segredos.php`.
8. Edite `configuracoes/segredos.php` e informe suas próprias chaves do Gemini e do Brevo.
9. Abra o terminal na pasta do projeto e execute:

   ```bash
   npm install
   npm run build
   ```

10. Inicie o Apache e o MySQL e acesse:

   http://localhost/studia/

## Configurações privadas

As credenciais de serviços externos ficam em `configuracoes/segredos.php`.

Esse arquivo é ignorado pelo Git e não deve ser publicado no repositório.

O arquivo `configuracoes/segredos.example.php` serve apenas como modelo de configuração e não contém credenciais reais.

## Banco de dados

O arquivo `studia.sql` contém a estrutura das tabelas do banco de dados, sem os cadastros pessoais utilizados durante o desenvolvimento.

Após a importação, o usuário poderá criar uma nova conta pela aplicação.

## Repositório

Código-fonte disponível em:

https://github.com/AgathaRayssa/studia

## Projeto acadêmico

Desenvolvido como projeto acadêmico.