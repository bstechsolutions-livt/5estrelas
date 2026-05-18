# Spec 04 - Perfil do Usuário

## Objetivo
Permitir que o usuário logado gerencie seus próprios dados: foto, nome, e-mail e senha. Acesso via clique no nome/avatar do header (atualmente "Ver perfil"). Não exige permissão extra (qualquer autenticado acessa o próprio perfil).

## Requisitos

### R1: Coluna `avatar_path` em users
- Migration adicionando `avatar_path` (string nullable)

### R2: Tela de perfil (`/perfil`)
- 3 seções principais:
  1. **Informações pessoais**: foto + nome + e-mail
  2. **Trocar senha**: senha atual + nova senha + confirmação
  3. **Conta**: e-mail (apenas exibição) e botão de logout

### R3: Foto/Avatar
- Upload de imagem (JPG, PNG, WebP) com preview circular
- Recomenda 256x256 ou 512x512px
- Botão "Remover foto" se já tiver
- Storage em `storage/app/public/avatars/`
- Compartilhar `avatar_url` em `auth.user` via Inertia

### R4: Atualizar dados pessoais
- Form separado: nome + e-mail + foto
- Validação: e-mail único (ignorando o próprio)
- Upload da foto opcional
- Toast de sucesso

### R5: Trocar senha
- Form com 3 campos: senha_atual, nova_senha, confirmacao
- Validação:
  - senha_atual deve bater com a do banco
  - nova_senha mínimo 8 chars
  - nova_senha === confirmacao
  - nova_senha != senha_atual
- Toast de sucesso
- Limpar campos após salvar

### R6: Avatar no header e sidebar
- Substituir o circulo com inicial pelo avatar quando houver
- Header: avatar redondo do lado do nome
- Fallback: inicial em circulo colorido (como já é)

### R7: Link para o perfil
- "Ver perfil" no header agora é clicável → `/perfil`
- Estilo de link sutil

## Entregável
- Logado, clicar no nome/avatar no header → vai pra `/perfil`
- Trocar nome, salvar, ver atualizando no header em tempo real
- Subir uma foto, ver no header e sidebar
- Trocar senha (errar a atual = erro; acertar = OK)
- Logout pela tela de perfil

## Fora do escopo
- Recuperação de senha (esqueci minha senha) - próxima spec
- 2FA
- Histórico de logins
- Preferências (idioma, tema, etc)
