# TurnoMySQL

**Sistema web para gestão de turnos de trabalho aos sábados e controle de ausências de colaboradores.**

## 📋 Descrição

O **TurnoMySQL** é um sistema desenvolvido em **PHP 8.2** com **Tailwind CSS 4.1** que permite o controle eficiente dos turnos trabalhados aos sábados por colaboradores, além de gerenciar ausências justificadas. A aplicação conta com funcionalidades como:

- Cadastro e visualização de turnos realizados.
- Registro de ausências.
- Visualização de feriados nacionais via Google Calendar API (somente leitura).
- Relatórios com total de turnos e horas trabalhadas por colaborador.

## 📸 Funcionalidades

- ✅ Cadastro de colaboradores
- 📅 Gestão de turnos de sábado
- ❌ Controle de ausências
- 🗓️ Visualização de feriados nacionais (Google Calendar)
- 📊 Geração de relatórios de turnos e horas trabalhadas

## 💻 Tecnologias Utilizadas

- **PHP 8.2**
- **Tailwind CSS 4.1**
- **MySQL**
- **Google Calendar API** (somente leitura para feriados nacionais)

## ⚙️ Instalação

1. Clone o repositório:
   ```bash
   git clone https://github.com/luisdalmora/TurnoMySQL.git

2. Configure o ambiente:

Crie um banco de dados MySQL e importe o arquivo banco.sql que está no projeto.

Atualize as credenciais de conexão com o banco no arquivo conexao.php.

3. Certifique-se de que o servidor PHP está rodando (PHP 8.2 recomendado).

4. Acesse o projeto via navegador.

📁 Estrutura do Projeto
TurnoMySQL/
├── calendario/           # Integração com Google Calendar (feriados)
├── css/                  # Estilos com Tailwind CSS
├── includes/             # Arquivos auxiliares (ex: conexão, header, footer)
├── js/                   # Scripts JavaScript
├── relatorios/           # Relatórios de turnos e horas
├── views/                # Telas e formulários
├── banco.sql             # Script de criação do banco de dados
└── index.php             # Página inicial

📅 Feriados Nacionais
O sistema utiliza a API do Google Calendar para exibir feriados nacionais automaticamente no calendário. Essa funcionalidade é somente para visualização e não altera registros de turnos.

📈 Relatórios
Na aba de relatórios, é possível visualizar o total de turnos e o número de horas trabalhadas por colaborador em um período específico, facilitando o acompanhamento da jornada.

🤝 Contribuições
Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou enviar pull requests.

📄 Licença
Este projeto está sob a licença MIT.