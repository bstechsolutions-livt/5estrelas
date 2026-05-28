---
inclusion: always
---

# Regras de UX Mobile

O sistema é entregue em duas formas: web (desktop) e app mobile (Flutter WebView). Apesar do app ser um WebView, **NÃO** queremos que pareça web responsivo "esmagado" no celular. Queremos que pareça app nativo.

## Princípios

1. **Detecção dupla**: usar `useDevice()` que considera tanto `is_mobile_app` (vem do app Flutter via header `X-Client`) quanto a largura da tela (`< 1024px`).
2. **Layout drasticamente diferente**, não só responsivo. Mudar estrutura, não só esconder/mostrar elementos.
3. **Toda spec nova** que cria tela DEVE incluir versão mobile dedicada.
4. **Performance e fluidez**: menos animações pesadas, scroll nativo sempre.

## Padrões mobile

### Navegação
- **Sem sidebar lateral** no mobile.
- **Bottom navigation** com 3-5 ícones principais (Início, Notícias, Perfil...).
- **Drawer / Menu hambúrguer** pra páginas secundárias (Aparência, Auditoria, etc) acessível pelo header.
- **Header simplificado**: avatar à direita, nome do app/logo à esquerda, sem ícones a mais.

### Telas
- **Login**: tela cheia, sem "card branco" pequeno. Background ocupa tudo, formulário direto sobre ele com blur ou caixa simples.
- **Dashboard**: stories ocupam toda a largura horizontal (sem padding). Feed ocupa tela toda. Atalhos não no dashboard inicial — vão para outra aba/drawer.
- **DataTables (lista de coisas)**: NUNCA mostrar tabela. Sempre lista de cards verticais, com 1 ação principal por toque (abre detalhes ou ações).
- **Forms**: full-width, inputs grandes (44px+ de altura), botões grudados na parte inferior (sticky).
- **Modais**: usar **bottom sheet** (sheet que sobe do fundo) ao invés de dialog flutuante. Pra confirmações curtas, bottom sheet pequeno.
- **Filtros**: botão "Filtros" abre bottom sheet com os filtros (não filtros sempre visíveis).

### Tipografia e espaçamento
- Tamanho mínimo de fonte: 14px.
- Áreas de toque mínimas: 44x44px.
- Padding lateral nas telas: 16px (não 24+ como no desktop).
- Espaços entre cards: 12px.

### Gestos esperados
- **Pull to refresh** onde fizer sentido (feed, listas).
- **Swipe horizontal** em stories (já temos).
- **Swipe pra fechar** em bottom sheets.
- **Long press** pra menu contextual em listas (futuro).

### Loading/Estados
- **Skeleton screens** ao invés de spinner centralizado quando possível.
- **Empty states** com ilustração simples + texto + CTA.
- **Errors inline** em forms, não toast pra erros de validação.

## Convenções de código

### Composable `useDevice()`
```js
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useWindowSize } from '@vueuse/core'

export function useDevice() {
  const page = usePage()
  const { width } = useWindowSize()

  const isMobileApp = computed(() => page.props.is_mobile_app === true)
  const isSmallScreen = computed(() => width.value < 1024)

  // Mobile = é o app Flutter OU tela pequena
  const isMobile = computed(() => isMobileApp.value || isSmallScreen.value)
  const isDesktop = computed(() => !isMobile.value)

  return { isMobileApp, isSmallScreen, isMobile, isDesktop }
}
```

### Componentes
- **Layout**: `AppLayout.vue` (desktop) / `AppLayoutMobile.vue` (mobile). Page escolhe via `useDevice()`.
- **Pages com versão dupla**: arquivo `Pages/X.vue` decide com base em `useDevice` qual variante renderizar (`<DesktopView />` ou `<MobileView />`).
- **Componentes compartilhados**: ficam em `Components/` (ex: `NewsCard`).
- **Componentes mobile-only**: prefixar com `Mobile` (ex: `MobileBottomNav`).

### Estrutura de pastas sugerida
```
resources/js/
├── Layouts/
│   ├── AppLayout.vue          # Desktop
│   └── AppLayoutMobile.vue    # Mobile
├── Components/
│   ├── Mobile/
│   │   ├── BottomNav.vue
│   │   ├── BottomSheet.vue
│   │   └── MobileHeader.vue
│   └── Dashboard/
│       └── ... (componentes compartilhados quando possível)
├── Pages/
│   ├── Dashboard.vue          # Decide entre Desktop e Mobile
│   ├── Dashboard.desktop.vue  # opcional, ou inline
│   └── Dashboard.mobile.vue   # opcional, ou inline
└── composables/
    └── useDevice.js
```

## Critério de "concluído" para uma tela

Uma tela só é considerada **concluída** quando:
1. Funciona bem no desktop
2. Funciona bem no mobile (largura de celular comum, 360-414px)
3. Funciona bem no app Flutter
4. Foi testada com volume real de dados (DemoSeeder)

## DemoSeeder e mobile

O DemoSeeder também serve pra testar UX mobile com dados volumosos. Ao desenvolver versão mobile, sempre testar com o seeder rodado.
