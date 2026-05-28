## Spec 09 - Mobile UX (Layout base + telas principais)

## Objetivo
Criar layouts e variantes mobile dedicadas para as telas existentes, conforme regras em `mobile-ux.md`. Não é responsivo "esmagado" — é UX pensada como app nativo.

## Escopo (parte 1 - layout base + dashboard + login)

### R1: Composable `useDevice()`
- Detecta `is_mobile_app` (header X-Client do Flutter) ou tela pequena (<1024px)
- Exporta `isMobile`, `isDesktop`, `isMobileApp`, `isSmallScreen`

### R2: `AppLayoutMobile.vue`
- Header simples: logo/nome à esquerda, avatar à direita
- Menu hambúrguer abre drawer lateral (Drawer/Sidebar do PrimeVue) com itens secundários
- Bottom navigation fixa na parte inferior com 4 ícones principais:
  - Início (Dashboard)
  - Notícias (lista de posts)
  - Adicionar (atalhos / ações - bottom sheet)
  - Perfil
- Conteúdo no meio scrollável

### R3: `AppLayout.vue` (existente) usado só em desktop

### R4: `Pages/Dashboard.vue` mobile
- Cabeçalho saudação compacto
- Stories: edge-to-edge sem padding lateral, scroll horizontal
- Feed: cards full-width, sem bordas laterais, padding reduzido
- Sem coluna de atalhos no dashboard mobile (vai pra bottom sheet ao tocar no ícone "Adicionar")

### R5: `Pages/Auth/Login.vue` mobile
- Tela cheia com background
- Sem card branco — formulário direto sobre o background com leve blur
- Inputs grandes
- Botão "Entrar" sticky no fundo

### R6: Sidebar mobile (drawer)
- Mesmo conteúdo do menu lateral atual
- Fecha ao tocar fora ou em um item

### R7: Validação
- Testar no Chrome (responsive mode 375px)
- Testar no app Flutter no celular físico
- Comparar com versão desktop (que continua intacta)

## Fora do escopo
- Versão mobile das outras telas (vai em spec separada — Spec 10)
- Filtros bottom sheet, long press, push notifications
- Skeleton screens (pode entrar em spec dedicada de polish)
