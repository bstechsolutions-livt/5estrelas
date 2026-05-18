# Spec 05 - Recuperação de senha

## Objetivo
Permitir ao usuário recuperar a senha por e-mail (link com token expirável). Fluxo: solicitar → receber e-mail → clicar no link → redefinir senha.

## Requisitos

### R1: Tela "Esqueci minha senha" (`/esqueci-senha`)
- Acessível pela tela de login (link "Esqueci minha senha")
- Form com campo único: e-mail
- Mensagem de sucesso genérica (não revelar se o e-mail existe ou não, por segurança)
- Botão "Voltar ao login"

### R2: Envio de e-mail com link de redefinição
- Usar `Password::sendResetLink()` do Laravel
- E-mail com botão pra acessar a tela de redefinição
- Link contém token (válido por 60 minutos por padrão)
- E-mail customizado com identidade do sistema (nome, cor primária, logo)

### R3: Tela de redefinição (`/redefinir-senha/{token}`)
- Acessada pelo link do e-mail
- Form com: e-mail (preenchido pela query string), nova senha, confirmação
- Validação: token válido, senha mínima 8 chars, confirmação igual
- Após redefinir → faz login automaticamente e redireciona pro dashboard
- Mensagem de erro clara se token expirou ou for inválido

### R4: Configurações de e-mail
- `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME` usam o nome do sistema (das settings) quando possível
- Por enquanto: `MAIL_MAILER=log` (e-mail vai pro `storage/logs/laravel.log` pra testar local)
- Documentar como trocar pra SMTP/Mailgun/etc no futuro

### R5: Token e tabela
- Laravel já tem migration `password_reset_tokens` por padrão (verificar se está rodada)
- Sem mudanças adicionais

### R6: Atualizar tela de login
- Adicionar link "Esqueci minha senha" abaixo do campo "Lembrar-me"
- Estilo discreto, alinhado à direita

## Entregável
- Logout
- Na tela de login, clicar em "Esqueci minha senha"
- Digitar e-mail (admin@5estrelas.com.br) e enviar
- Ver mensagem genérica de sucesso
- Abrir `storage/logs/laravel.log` e ver o e-mail (ou copiar a URL de redefinição)
- Acessar a URL → tela de redefinição
- Definir nova senha → redirecionado pro dashboard logado

## Fora do escopo
- 2FA
- Configuração de SMTP em produção (deixar instruções)
- Notificação de "senha alterada" pro usuário
- Política de senhas avançada (requer maiúscula/número/etc)
