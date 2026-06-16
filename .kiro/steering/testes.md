# Testes automatizados — REGRA INEGOCIÁVEL (Bruno)

> O Bruno NÃO testa manualmente. Cobertura é 100% responsabilidade do agente.
> **Nenhuma feature/tela/rota é "concluída" nem vai pra produção sem teste automatizado passando.**

## O que testar — SEMPRE os dois lados

### 1. Backend — Feature/Unit test (PHPUnit / `php artisan test`)
Para CADA controller/rota nova ou alterada, cobrir:
- **Cada endpoint**: index, show, store, update, destroy + ações específicas (salvar, vincular, mudar status, aprovar, etc.)
- **Caminho feliz**: cria/atualiza/exclui e confirma no banco (`assertDatabaseHas` / `assertDatabaseMissing`).
- **Validação**: payload inválido → 422 (`assertJsonValidationErrors`).
- **Permissão**: usuário sem a permissão certa → 403; sem login → redirect/401.
- **Regras de negócio**: cálculos, totais, upsert (não duplicar), numeração, etc.

### 2. Frontend — Laravel Dusk (`php artisan dusk`)
Teste de browser real (Chrome) que **aperta cada botão**. Para CADA tela:
- A tela **renderiza** (título, seções, KPIs).
- **Cada ação/botão principal**: criar, editar, excluir, salvar, filtrar, trocar aba, abrir modal, vincular, exportar, etc. — clicando de verdade.
- Confirmar o efeito (toast de sucesso, linha aparece/some, valor atualiza, `assertDatabaseHas` quando aplicável).

## Fluxo obrigatório antes de dar como pronto
1. Escrever os testes (feature + Dusk).
2. Rodar `php artisan test --filter=<Feature>` → tem que passar.
3. Subir `php artisan serve --port=8778` (env dusk.local) e rodar `php artisan dusk --filter=<Tela>` → tem que passar.
4. Rodar `php artisan test` (suíte inteira) pra garantir que nada quebrou.
5. Só então buildar e deployar. **Deploy só com testes verdes.**
6. Reportar o resultado exato (X passed).

## Padrões do projeto (já estabelecidos)
- Feature tests em `app/tests/Feature/`, usando `RefreshDatabase` e helper de user com permissões (ver `ComercialClienteTest`, `ComercialFaturamentoTest`).
- Dusk tests em `app/tests/Browser/`, logando como `bruno@bstechsolutions.com` (permissão wildcard local) — ver `ComercialFaturamentoTest`, `ComercialPropostaTest`.
- **Atributos `dusk="..."`** nos botões/inputs importantes para seletores estáveis.
- **Gotcha Dusk**: textos com `text-transform:uppercase` no CSS são "vistos" em MAIÚSCULAS pelo Selenium — a asserção deve casar com o texto exibido (ex.: `assertSee('SALVAR')`).
- ChromeDriver: `php artisan dusk:chrome-driver --detect` se o Chrome atualizar.
- Config Dusk em `.env.dusk.local` (não commitado; Postgres `estrelas`, APP_URL `http://127.0.0.1:8778`).

## Gotcha recorrente já encontrado
- Telas que usam `toast.add()` (PrimeVue) PRECISAM renderizar `<Toast />` no template (com `import Toast from "primevue/toast"`), senão o feedback nunca aparece. Conferir isso em toda tela com toast.

## Cobertura pendente (dívida a quitar)
Telas antigas do Comercial sem teste de feature completo / com possível bug de `<Toast />`:
- Cotação (só Dusk), Valores/Configuracoes (só Dusk), Propostas (feature ok, revisar Dusk de cada ação), Clientes (feature ok, falta Dusk).
Quitar essa dívida quando tocar em cada tela.
