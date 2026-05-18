---
inclusion: always
---

# Regras de Trabalho - Projeto 5 Estrelas

## Filosofia de entrega

- **Specs minúsculas**: cada spec deve ser o menor entregável possível que o Bruno consiga rodar local e testar
- **Entregáveis testáveis**: cada spec termina com algo visível rodando no browser ou no app
- **Incremental**: não tentar entregar tudo de uma vez. Pedaço por pedaço, testando cada um
- **Local first**: desenvolvimento local, deploy pra servidor só quando estiver validado

## Estrutura do workspace

- Projeto Laravel fica na raiz deste workspace (após criação)
- Projeto Flutter fica em pasta `mobile/` (criado depois)
- Documentação em `docs/`
- Steering/regras em `.kiro/steering/`

## Referência visual

- Inspiração: EasyIntra (intranet.grupobig.com.br)
- Sidebar escura com menu lateral colapsável
- Header com nome do usuário, avatar, notificações
- Painel principal com: acesso rápido, resumo executivo, notícias/destaques
- Cards de atalho com ícones
- Área de notícias/comunicados no lado direito
- Visual limpo, profissional, dark sidebar + conteúdo claro
- **NÃO copiar** — usar como inspiração de layout e pegada visual

## Permissões

- Sem roles/papéis
- Usuário + permissões diretas (granulares)
- Tabela `permissions` + pivot `user_permission`
- Tela admin com checkboxes de permissões por usuário
- Pode usar Spatie Permission por baixo (só a parte de permissions, sem roles)

## White-label

- Tabela `settings` com: cores primárias/secundárias, logo, favicon, bg login, nome empresa
- CSS variables injetadas em runtime via API/Inertia shared props
- PrimeVue respeita as variáveis automaticamente
- Configurável via painel administrativo

## Regras técnicas

- Laravel 12 + Vue 3 + Inertia + PrimeVue 4 + Tailwind CSS 4
- PostgreSQL 16
- Redis para cache/queue
- Laravel Reverb para WebSocket
- Laravel Sanctum para auth
- Flutter com WebView para app mobile
- Backblaze B2 (S3-compatible) para storage de fotos/evidências
- GitHub Actions para CI/CD

## O que NÃO fazer

- Não criar specs grandes demais
- Não implementar módulos inteiros de uma vez
- Não usar roles/papéis
- Não usar shadcn/cn (usar PrimeVue)
- Não complicar com multi-tenant (é single-tenant configurável)
- Não usar IA por enquanto (regras programáticas)
