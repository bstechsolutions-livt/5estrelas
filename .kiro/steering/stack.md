---
inclusion: always
---

# Stack Tecnológico - Plataforma 5 Estrelas

## Requisito-chave: White-label

Tudo configurável em tela administrativa, sem rebuild:
- Cores primárias e secundárias
- Logo (header, favicon, login)
- Background da tela de login
- Nome da empresa
- Tipografia (opcional)
- Tema claro/escuro (opcional)

Isso significa que o frontend precisa consumir configurações de tema **em runtime** (via API ou config injetada no boot), não em build time.

---

## Opções analisadas

### Opção A: Laravel + Vue + Inertia + PrimeVue

| Aspecto | Avaliação |
|---------|-----------|
| Produtividade | Alta. Inertia elimina a necessidade de API separada para o frontend. |
| White-label | **PrimeVue suporta temas via CSS variables nativas.** Troca de cores em runtime é trivial: basta injetar variáveis CSS no `:root` vindas da API. |
| Componentes | PrimeVue tem DataTable, Calendar, FileUpload, Charts, Dialog, etc. Cobre 90%+ do que o projeto precisa. |
| App mobile | Inertia não roda nativo. Precisaria de um app separado (Flutter, React Native, ou PWA). |
| Complexidade | Média. Monolito com SPA-like experience. |
| Ecossistema Laravel | Funciona perfeitamente. Inertia é first-class no Laravel. |

**Veredicto**: Excelente para o painel web. PrimeVue com CSS variables resolve white-label sem dor. O ponto fraco é o app mobile — Inertia não ajuda ali.

---

### Opção B: Laravel + Vue + Inertia + shadcn-vue (cn)

| Aspecto | Avaliação |
|---------|-----------|
| Produtividade | Alta. Mesmo modelo do Inertia. |
| White-label | shadcn usa Tailwind CSS variables. Funciona em runtime se você injetar as variáveis via API no load. Precisa de um pouco mais de setup manual que PrimeVue. |
| Componentes | Mais minimalista. Não tem DataTable robusto, Charts, FileUpload complexo out-of-the-box. Vai precisar compor ou adicionar libs extras. |
| App mobile | Mesmo problema: Inertia não roda nativo. |
| Complexidade | Média-baixa para UI simples, mas sobe rápido quando precisa de componentes complexos (tabelas com filtro, paginação server-side, etc). |
| Ecossistema Laravel | É o padrão do starter kit novo do Laravel 12. |

**Veredicto**: Bonito e moderno, mas para um sistema de gestão com muitas tabelas, workflows, formulários complexos e dashboards, vai exigir muito mais trabalho manual que PrimeVue. shadcn brilha em landing pages e apps simples, não em ERPs.

---

### Opção C: Laravel (API) + Vue SPA separado + PrimeVue

| Aspecto | Avaliação |
|---------|-----------|
| Produtividade | Média. Precisa manter API + SPA separados. Mais boilerplate. |
| White-label | Mesmo esquema de CSS variables. Funciona igual. |
| Componentes | PrimeVue completo. |
| App mobile | **Vantagem**: a API já está pronta para o app mobile consumir. Não precisa duplicar lógica. |
| Complexidade | Maior. Dois deploys, CORS, auth via token (Sanctum), versionamento de API. |
| Ecossistema Laravel | Funciona, mas perde a simplicidade do Inertia. |

**Veredicto**: Faz sentido se o app mobile for nativo (Flutter/RN) e precisar da mesma API. Mas adiciona complexidade operacional.

---

### Opção D: Laravel + Vue + Inertia + PrimeVue (web) + Flutter/Capacitor (mobile)

| Aspecto | Avaliação |
|---------|-----------|
| Produtividade | Alta no web, média no mobile. |
| White-label | Web: CSS variables. Mobile: tema via API no boot do app. |
| App mobile | Flutter ou Capacitor (wrapping do Vue). Capacitor é mais rápido se quiser reaproveitar código Vue. |
| Complexidade | Média-alta. Dois projetos (web + mobile), mas compartilham o mesmo backend Laravel. |

**Veredicto**: Melhor dos mundos se aceitar a complexidade de manter o app mobile separado.

---

## Minha recomendação

### **Laravel + Vue 3 + Inertia + PrimeVue 4** (web) + **API endpoints dedicados para o app mobile**

Razões:

1. **PrimeVue 4 com CSS variables** resolve white-label de forma nativa. Você carrega as configs do tenant via API no boot e injeta no `:root`. Pronto — cores, logo, nome, tudo dinâmico.

2. **Inertia** mantém a produtividade alta: sem API separada para o frontend web, sem gerenciar tokens no browser, sem CORS. O Laravel cuida de tudo (session auth, middleware, policies).

3. **PrimeVue** tem os componentes que um sistema de gestão precisa: DataTable com server-side, TreeTable, Charts, FileUpload, Stepper, Timeline, Dialog, Toast, ConfirmDialog, etc. Não vai precisar reinventar a roda.

4. **Para o app mobile**: o Laravel já expõe rotas API (Sanctum). Basta criar endpoints específicos para o app de fiscalização. O app pode ser feito em **Flutter** (melhor performance e UX nativa) ou **Capacitor** (se quiser reaproveitar componentes Vue).

5. **White-label na prática**:
   - Tabela `tenants` ou `settings` com: cores, logo_url, favicon_url, login_bg_url, nome_empresa
   - Endpoint `/api/theme` ou middleware Inertia que injeta as configs como shared props
   - No Vue, um composable `useTheme()` que aplica as variáveis CSS no mount
   - PrimeVue respeita as variáveis automaticamente

---

## Stack final proposta

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 12 (PHP 8.3+) |
| Frontend Web | Vue 3 + Inertia.js + PrimeVue 4 |
| Estilização | Tailwind CSS 4 + PrimeVue CSS variables (tema dinâmico) |
| Auth | Laravel Sanctum (session para web, token para mobile) |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis |
| Storage | S3-compatible (Backblaze B2) via Laravel Filesystem |
| App Mobile | Flutter com WebView (wrapper do sistema web) |
| Realtime | Laravel Reverb (WebSockets) para dashboards e notificações |
| Search | Meilisearch (opcional, para buscas rápidas em contratos/NFs) |
| Auditoria | Regras de negócio programáticas (sem IA por enquanto) |
| CI/CD | GitHub Actions |
| Infra | VPS Linux (Hostinger KVM ou equivalente) |

---

## Estrutura de pastas sugerida (monorepo)

```
5estrelas/
├── app/                    # Laravel (backend + Inertia + Vue + PrimeVue)
│   ├── app/
│   ├── routes/
│   ├── resources/
│   │   └── js/            # Vue + Inertia + PrimeVue
│   ├── database/
│   └── ...
├── mobile/                 # Flutter (WebView wrapper)
├── docs/                   # Documentação
├── infra/                  # Docker, deploy scripts
└── .kiro/                  # Steering files
```

---

## Decisões definidas

1. **App mobile**: Flutter com WebView apontando para o sistema web. Não é app nativo separado — é um wrapper que carrega a aplicação Inertia/Vue dentro do Flutter. Simples, rápido de manter, uma única base de código para web e mobile.
2. **Não é multi-tenant**: o sistema é single-tenant, mas white-label no sentido de ser **altamente configurável** (cores, logos, nome) para poder ser reimplantado para outros clientes no futuro sem refatoração. Cada deploy = um cliente, um banco.
3. **IA/Auditoria**: por enquanto, resolver com programação tradicional (regras de negócio, cruzamentos, queries). Se no futuro fizer sentido, integra IA. Não é prioridade agora.
4. **Realtime**: Laravel Reverb (WebSocket) para dashboards ao vivo e notificações.
5. **Banco de dados**: PostgreSQL 16.

---

## Por que NÃO shadcn/cn para este projeto

- Sistema de gestão = muitas tabelas complexas, filtros, paginação server-side, formulários grandes
- shadcn não tem DataTable, Chart, FileUpload, TreeTable prontos
- Você vai gastar semanas construindo o que PrimeVue entrega pronto
- shadcn é ótimo para SaaS simples, landing pages, apps com poucas telas
- Para ERP/gestão, PrimeVue (ou Quasar, Vuetify) são escolhas mais pragmáticas
