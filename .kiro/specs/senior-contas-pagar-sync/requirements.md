# Requirements Document

## Introduction

Atualmente o módulo **Contas a Pagar** opera com dados de exemplo (mock/DemoSeeder). Esta feature substitui esses dados por títulos reais vindos do **Senior ERP (G5 / Gestão Empresarial)**, consumidos via webservice SOAP do serviço `com_senior_g5_co_mfi_cpa_titulos`.

A solução tem quatro frentes:

1. **Espelhamento de dados**: expandir a tabela `payables` para receber TODOS os campos relevantes que a Senior expõe para um título a pagar (a estrutura da Senior tem dezenas de campos — ver Glossário e Apêndice A — contra os 17 atuais), preservando os campos de workflow internos do sistema 5estrelas.
2. **Sincronização periódica**: um job/comando agendado, idempotente, que consulta os títulos na Senior e faz *upsert* (insere novos, atualiza alterados) na tabela `payables`, com frequência configurável e suporte a sincronização incremental.
3. **Observabilidade e auditoria**: registro de cada execução do sync (início, fim, contagens, falhas) e logs de auditoria conforme `auditoria.md`.
4. **Apresentação**: ajustar o front-end (PrimeVue DataTable desktop + lista de cards mobile) para mostrar apenas as colunas básicas e relevantes, sem scroll horizontal, com tela/dialog de detalhes (e bottom sheet no mobile) para o restante dos campos.

A descoberta do serviço foi feita a partir da documentação oficial da Senior (ERP 5.10.4). O serviço de Contas a Pagar (`com_senior_g5_co_mfi_cpa_titulos`) expõe operações de consulta read-only seguras. A operação atual mais rica é **`ConsultarTitulosAbertosCP`** (versão 3), que retorna títulos abertos com rateios e dezenas de campos. As demais (`ConsultarTitulosCP` está depreciada, `buscarPendentesCP`, `ConsultarCP`) ficam como alternativas/complementos.

## Glossary

- **Senior**: Senior ERP G5 / Gestão Empresarial (versão 5.10.4), sistema de origem dos dados financeiros.
- **CP**: Contas a Pagar (módulo financeiro da Senior).
- **Título (CP)**: registro de uma obrigação a pagar na Senior, identificado pela chave de negócio Empresa + Filial + Número do Título + Tipo + Fornecedor.
- **Senior_CP_Service**: o serviço SOAP `com_senior_g5_co_mfi_cpa_titulos` da Senior.
- **Senior_CP_Client**: componente do sistema 5estrelas responsável por montar o envelope SOAP, autenticar e invocar as operações de consulta do Senior_CP_Service.
- **Payable**: registro da tabela `payables` no sistema 5estrelas que representa um título a pagar.
- **Payables_Sync**: o processo (job/comando agendado) que consulta a Senior e realiza o upsert dos títulos na tabela `payables`.
- **Payable_Mapper**: componente que mapeia os campos retornados pelo Senior_CP_Service para colunas da tabela `payables`.
- **Status_Mapper**: componente que traduz a situação do título na Senior (`sitTit`) para o status interno do workflow do 5estrelas.
- **Sync_Run**: registro de uma execução do Payables_Sync (linha em `payable_sync_runs`), com horário, contagens e resultado.
- **Senior_Origin_Fields**: campos do Payable que têm origem na Senior e são tratados como somente-leitura no 5estrelas.
- **Workflow_Fields**: campos do Payable controlados pelo workflow interno do 5estrelas (status de aprovação, preparador, aprovador, borderô, comentários, documentos, motivo de reprovação), que NÃO são sobrescritos pela sincronização.
- **Business_Key**: chave de negócio que identifica unicamente um título da Senior dentro do 5estrelas, derivada de codEmp + codFil + numTit + codTpt + codFor; armazenada em `senior_id`.
- **Incremental_Sync**: sincronização que consulta apenas títulos com vencimento/alteração dentro de uma janela, reduzindo o volume por execução.
- **Full_Sync**: sincronização que consulta a base completa de títulos abertos (sem filtro de janela), usada em carga inicial ou reconciliação.
- **Environment**: ambiente Senior alvo da integração, sendo `HML` (homologação, `webh17...:30661`) ou `PRD` (produção, `webp27...:30361`).
- **Payables_DataTable**: a DataTable PrimeVue da tela de Contas a Pagar no desktop.
- **Payable_Details_View**: a visualização de detalhes de um título (dialog/tela no desktop, bottom sheet no mobile) que exibe os campos completos.
- **DemoSeeder**: seeder de massa de teste local (`database/seeders/DemoSeeder.php`).

## Requirements

### Requirement 1: Cliente SOAP de consulta à Senior

**User Story:** Como integrador do sistema 5estrelas, quero um cliente SOAP que consulte os títulos a pagar na Senior, para que os dados reais possam ser trazidos para o sistema.

#### Acceptance Criteria

1. THE Senior_CP_Client SHALL invocar a operação `ConsultarTitulosAbertosCP` do Senior_CP_Service usando o endpoint correspondente ao Environment configurado.
2. THE Senior_CP_Client SHALL incluir no envelope SOAP os campos `user`, `password` e `encryption` com os valores das credenciais de integração configuradas, utilizando o valor `0` (sem criptografia) para o campo `encryption` quando nenhum tipo de criptografia estiver configurado.
3. WHEN o Environment configurado é `HML`, THE Senior_CP_Client SHALL utilizar o endpoint base `https://webh17.seniorcloud.com.br:30661/g5-senior-services`.
4. WHEN o Environment configurado é `PRD`, THE Senior_CP_Client SHALL utilizar o endpoint base `https://webp27.seniorcloud.com.br:30361/g5-senior-services`.
5. THE Senior_CP_Client SHALL utilizar exclusivamente operações de consulta read-only (`ConsultarTitulosAbertosCP`, `ConsultarTitulosCP`, `buscarPendentesCP`, `ConsultarCP`) e NÃO invocar operações de gravação, baixa ou exclusão.
6. WHEN a resposta da Senior contém um ou mais títulos, THE Senior_CP_Client SHALL retornar a coleção de títulos com todos os campos recebidos preservados, sem alterar, truncar ou descartar qualquer campo da resposta original.
7. IF o campo `tipoRetorno` ou `erroExecucao` da resposta indica erro, THEN THE Senior_CP_Client SHALL retornar uma falha contendo a mensagem `mensagemRetorno` da Senior, NÃO retornar nenhum título e preservar inalterado o estado do sistema (nenhuma persistência decorrente da consulta).
8. WHERE o número de títulos disponíveis excede 500 títulos por resposta, THE Senior_CP_Client SHALL paginar as consultas por janela de vencimento (`vctIni`/`vctFim`) até obter todos os títulos do intervalo solicitado, sem duplicar nem omitir títulos entre as páginas.
9. IF a chamada SOAP não obtém resposta dentro de 30 segundos, THEN THE Senior_CP_Client SHALL abortar a requisição, retornar uma falha indicando o esgotamento do tempo limite e preservar inalterado o estado do sistema.
10. IF a chamada SOAP falha por erro de conexão ou tempo limite, THEN THE Senior_CP_Client SHALL repetir a requisição em até 3 tentativas adicionais antes de retornar uma falha indicando a indisponibilidade do Senior_CP_Service.

### Requirement 2: Resiliência da comunicação com a Senior

**User Story:** Como operador do sistema, quero que falhas de comunicação com a Senior sejam tratadas sem corromper os dados locais, para que o sistema permaneça estável quando a Senior estiver indisponível.

#### Acceptance Criteria

1. THE Senior_CP_Client SHALL aplicar um timeout de conexão e um timeout de resposta configuráveis entre 5 e 300 segundos cada, adotando o valor padrão de 60 segundos para cada um quando não configurados.
2. IF a chamada ao Senior_CP_Service excede o timeout de conexão ou o timeout de resposta configurado, THEN THE Senior_CP_Client SHALL encerrar a chamada e retornar uma falha de timeout que identifique ao chamador que o limite de tempo foi atingido.
3. IF a chamada ao Senior_CP_Service falha por erro de rede ou indisponibilidade, THEN THE Payables_Sync SHALL registrar a falha no Sync_Run e finalizar a execução sem inserir, atualizar ou remover qualquer registro existente na tabela `payables`.
4. IF uma chamada ao Senior_CP_Service falha por erro transitório (timeout, indisponibilidade temporária do serviço ou erro de rede), THEN THE Payables_Sync SHALL repetir a chamada em até 3 tentativas adicionais, aguardando intervalos crescentes de 2, 4 e 8 segundos antes de cada nova tentativa, e marcar o Sync_Run como falho caso todas as tentativas se esgotem.
5. THE Senior_CP_Client SHALL remover ou rejeitar caracteres de controle (caracteres com código de 0 a 31, exceto tabulação, quebra de linha e retorno de carro) dos parâmetros antes de montar o envelope, para evitar erro de parser XML na Senior.

### Requirement 3: Espelhamento dos campos da Senior na tabela `payables`

**User Story:** Como analista financeiro, quero que todos os campos relevantes do título na Senior sejam armazenados localmente, para que eu tenha acesso à informação completa sem depender de consultar a Senior diretamente.

#### Acceptance Criteria

1. THE tabela `payables` SHALL conter uma coluna dedicada para cada campo de cabeçalho do título retornado por `ConsultarTitulosAbertosCP`, conforme listado no Apêndice A.
2. THE Payable_Mapper SHALL mapear cada campo da Senior listado no Apêndice A para a coluna correspondente de `payables`.
3. THE sistema SHALL armazenar os rateios (cost-center splits) de cada título em uma estrutura associada ao Payable, preservando a relação um-para-muitos entre título e rateios.
4. WHERE um campo retornado pela Senior não possui coluna dedicada mapeada, THE Payable_Mapper SHALL armazenar o conteúdo bruto do título em uma coluna JSON `senior_raw` no Payable.
5. THE Payable_Mapper SHALL converter valores monetários da Senior para o tipo decimal com duas casas decimais e datas para o tipo date/datetime correspondente.
6. THE sistema SHALL preservar os Workflow_Fields existentes (`status`, `prepared_by`, `approved_by`, `sent_for_approval_at`, `approved_at`, `rejection_reason`, `bordero_id`, comentários e documentos) sem alteração de schema que cause perda de dados.
7. WHEN a resposta da Senior não inclui um campo mapeado ou o retorna nulo/vazio, THE Payable_Mapper SHALL gravar valor nulo na coluna correspondente sem interromper o mapeamento dos demais campos do título.
8. IF a conversão de um valor monetário ou de data de um título falha, THEN THE Payable_Mapper SHALL preservar o valor original recebido em `senior_raw`, gravar nulo na coluna tipada correspondente e prosseguir com o mapeamento dos demais títulos sem interromper a sincronização.
9. WHEN um título retornado pela Senior não possui rateios, THE Payable_Mapper SHALL persistir o Payable sem registros de rateio associados.

### Requirement 4: Upsert idempotente dos títulos

**User Story:** Como operador do sistema, quero que a sincronização insira novos títulos e atualize os existentes sem duplicar registros, para que a tabela `payables` reflita fielmente a Senior.

#### Acceptance Criteria

1. THE Payables_Sync SHALL identificar cada título pela Business_Key e armazená-la na coluna `senior_id` sob uma restrição de unicidade.
2. WHEN um título consultado na Senior não possui Payable correspondente pela Business_Key, THE Payables_Sync SHALL inserir um novo Payable com os Senior_Origin_Fields mapeados.
3. WHEN um título consultado na Senior já possui Payable correspondente pela Business_Key, THE Payables_Sync SHALL atualizar os Senior_Origin_Fields do Payable existente, preservando a Business_Key.
4. WHEN o Payables_Sync atualiza um Payable existente, THE Payables_Sync SHALL preservar os Workflow_Fields inalterados.
5. WHEN o Payables_Sync executa duas vezes seguidas sobre o mesmo conjunto de títulos sem alterações na Senior, THE Payables_Sync SHALL resultar, na segunda execução, em zero inserções e zero atualizações de conteúdo (idempotência).
6. THE Payables_Sync SHALL realizar as inserções e atualizações em lote, com no máximo 500 registros por operação.
7. IF um título consultado na Senior não possui Business_Key derivável, THEN THE Payables_Sync SHALL descartar esse título, registrar o motivo do descarte e prosseguir com os demais títulos sem interrupção.
8. IF a gravação de um lote de inserções ou atualizações falha, THEN THE Payables_Sync SHALL reverter as alterações desse lote, preservar o estado anterior dos registros afetados e registrar o lote afetado.

### Requirement 5: Sincronização incremental e completa

**User Story:** Como administrador, quero controlar o escopo de cada sincronização, para equilibrar a carga sobre a Senior e a atualidade dos dados.

#### Acceptance Criteria

1. THE Payables_Sync SHALL suportar o modo Incremental_Sync, consultando apenas os títulos cujo vencimento esteja dentro de uma janela configurável de dias anteriores e posteriores à data atual, com valor padrão de 90 dias para trás e 90 dias para frente e limites configuráveis entre 1 e 3650 dias em cada sentido.
2. THE Payables_Sync SHALL suportar o modo Full_Sync, consultando a base completa de títulos abertos sem aplicar filtro de janela de vencimento.
3. WHEN o Payables_Sync é executado sem modo especificado, THE Payables_Sync SHALL executar em modo Incremental_Sync.
4. WHEN uma execução do Payables_Sync conclui com sucesso, THE Payables_Sync SHALL registrar no Sync_Run o horário de conclusão dessa execução como horário da última execução bem-sucedida.
5. WHERE existe um horário de última execução bem-sucedida registrado, THE Incremental_Sync SHALL utilizar esse horário como início da janela de consulta, em vez da extremidade de dias anteriores da janela padrão configurada.
6. IF não existe horário de última execução bem-sucedida registrado, THEN THE Incremental_Sync SHALL utilizar a janela padrão configurada a partir da data atual.

### Requirement 6: Agendamento com frequência configurável

**User Story:** Como administrador, quero agendar a sincronização para rodar periodicamente com frequência ajustável, para manter os dados atualizados sem sobrecarregar a operação.

#### Acceptance Criteria

1. THE sistema SHALL agendar a execução do Payables_Sync em intervalo periódico fixo, com valor padrão de 5 minutos.
2. THE intervalo de execução do Payables_Sync SHALL ser configurável sem alteração de código, aceitando valores inteiros de 1 a 1440 minutos.
3. IF o intervalo configurado está fora da faixa de 1 a 1440 minutos, THEN THE sistema SHALL rejeitar o valor, manter o último intervalo válido (ou o padrão de 5 minutos quando não houver valor anterior) e registrar uma mensagem de erro indicando o intervalo inválido.
4. WHILE existe uma execução do Payables_Sync em andamento (Sync_Run sem horário de término registrado), THE agendador SHALL impedir o início de uma nova execução concorrente do Payables_Sync.
5. IF uma execução agendada não pôde iniciar por já existir outra em andamento, THEN THE sistema SHALL ignorar a nova execução sem alterar registros da tabela `payables` e registrar a ocorrência de sobreposição no módulo `financeiro.contas_pagar` com o horário do evento.

### Requirement 7: Tratamento de títulos ausentes na Senior

**User Story:** Como analista financeiro, quero saber quando um título deixa de existir na Senior (baixado ou excluído), para que a informação local reflita a realidade sem perder o histórico de workflow.

#### Acceptance Criteria

1. WHEN uma execução em modo Full_Sync conclui com sucesso e um Payable com `senior_id` preenchido (origem Senior) não consta no conjunto de títulos retornado pela Senior nessa execução, THE Payables_Sync SHALL gravar em `senior_missing_at` o horário de conclusão dessa execução.
2. IF uma execução em modo Incremental_Sync não retorna um Payable de origem Senior, THEN THE Payables_Sync SHALL NÃO marcar esse Payable como ausente, por a janela incremental não cobrir a base completa de títulos.
3. THE Payables_Sync SHALL preservar fisicamente o registro local de um título ausente na Senior, incluindo seus Workflow_Fields, em vez de removê-lo da tabela `payables`.
4. WHEN um Payable previamente marcado com `senior_missing_at` preenchido volta a constar no conjunto de títulos retornado pela Senior em uma execução bem-sucedida, THE Payables_Sync SHALL redefinir `senior_missing_at` para nulo, preservando os Workflow_Fields inalterados.
5. WHEN a Payables_DataTable exibe um Payable com `senior_missing_at` preenchido, THE Payables_DataTable SHALL apresentar um indicador visual textual (rótulo/badge) identificando o título como ausente na Senior, distinto da apresentação dos títulos ativos.

### Requirement 8: Mapeamento de status Senior para status interno

**User Story:** Como analista financeiro, quero que a situação do título na Senior seja traduzida para o workflow interno de forma previsível, para que os filtros por status continuem funcionando.

#### Acceptance Criteria

1. THE Status_Mapper SHALL traduzir o campo de situação do título da Senior (`sitTit`) para exatamente um dos status internos definidos em `Payable::STATUS_LABELS` (`pendente`, `em_preparacao`, `aguardando_aprovacao`, `aprovado`, `reprovado`, `pago`).
2. WHEN um título não possui Payable correspondente pela Business_Key (primeira sincronização do título), THE Status_Mapper SHALL definir o status interno inicial a partir da situação da Senior.
3. IF um Payable já passou por etapas do workflow interno (status interno diferente de `pendente`), THEN THE Payables_Sync SHALL preservar o status interno e NÃO sobrescrevê-lo com o status derivado da Senior.
4. WHERE a situação da Senior não possui correspondência definida no Status_Mapper, THE Status_Mapper SHALL atribuir o status interno `pendente` e registrar a situação original no Payable.
5. IF o campo `sitTit` está ausente, vazio ou nulo, THEN THE Status_Mapper SHALL atribuir o status interno `pendente`, preservar a situação original recebida no Payable e registrar um log de auditoria indicando situação indefinida.
6. THE Status_Mapper SHALL determinar o status interno de cada título em no máximo 100 ms e sem realizar chamadas externas adicionais.

### Requirement 9: Observabilidade e auditoria da sincronização

**User Story:** Como administrador, quero acompanhar o resultado de cada execução da sincronização, para detectar falhas e validar o volume importado.

#### Acceptance Criteria

1. WHEN o Payables_Sync inicia, THE sistema SHALL criar um Sync_Run com horário de início, Environment (Produção ou Homologação), modo de execução (manual ou agendado) e resultado inicial igual a "em andamento".
2. WHEN o Payables_Sync conclui, THE sistema SHALL atualizar o Sync_Run com horário de término, quantidade de títulos inseridos, quantidade de títulos atualizados e quantidade de títulos marcados como ausentes (cada contagem representada por um inteiro maior ou igual a 0) e resultado igual a "sucesso" ou "falha".
3. WHEN o Payables_Sync conclui uma execução com pelo menos uma inserção ou uma atualização, THE sistema SHALL registrar um log de auditoria no módulo `financeiro.contas_pagar` contendo as três contagens da execução (inseridos, atualizados, ausentes) e o identificador do Sync_Run correspondente.
4. IF o Payables_Sync falha, THEN THE sistema SHALL registrar no Sync_Run o resultado "falha" e a mensagem de erro retornada pela Senior ou pela falha de comunicação, truncada a no máximo 2000 caracteres, preservando as contagens já processadas até o ponto da falha.
5. WHEN um administrador com permissão de visualização acessa a interface administrativa, THE sistema SHALL exibir o horário de início, o horário de término e o resultado da execução mais recente do Payables_Sync, identificada pelo maior horário de início registrado.
6. IF nenhuma execução do Payables_Sync foi registrada quando a interface administrativa é acessada, THEN THE sistema SHALL exibir indicação de que não há execuções registradas.

### Requirement 10: Apresentação enxuta com detalhes sob demanda (desktop)

**User Story:** Como analista financeiro, quero ver as colunas mais relevantes do título sem scroll horizontal e acessar o detalhamento completo ao clicar, para navegar rapidamente sem perder informação.

#### Acceptance Criteria

1. WHILE a largura da viewport for maior ou igual a 1024px, THE Payables_DataTable SHALL exibir exatamente as 7 colunas básicas (fornecedor, número do título, vencimento, valor original, valor em aberto, status e filial) sem apresentar barra de scroll horizontal.
2. WHEN o usuário clica em uma linha da Payables_DataTable, THE Payable_Details_View SHALL exibir, em até 2 segundos, todos os campos do título, incluindo os Senior_Origin_Fields e os rateios associados.
3. WHEN a Payable_Details_View é exibida, THE Payable_Details_View SHALL renderizar os Senior_Origin_Fields como campos somente-leitura (não editáveis) e os Workflow_Fields como campos editáveis, aplicando indicação visual distinta entre os dois grupos.
4. WHEN a Payable_Details_View é exibida, THE Payable_Details_View SHALL agrupar os Senior_Origin_Fields nas categorias identificação, valores, datas, conta/centro de custo e origem fiscal, cada categoria com rótulo próprio.
5. IF o carregamento dos campos do título na Payable_Details_View falhar, THEN THE Payable_Details_View SHALL exibir mensagem de erro indicando a falha de carregamento e SHALL preservar a Payables_DataTable e sua seleção atual sem alteração.

### Requirement 11: Apresentação mobile dedicada

**User Story:** Como analista financeiro usando o app, quero uma versão mobile da tela de Contas a Pagar que pareça nativa, para consultar títulos com conforto no celular.

#### Acceptance Criteria

1. WHILE o dispositivo é detectado como mobile por `useDevice()`, THE tela de Contas a Pagar SHALL apresentar cada título como um card em uma lista vertical de rolagem nativa, sem exibir a Payables_DataTable e sem gerar scroll horizontal na faixa de largura de 360px a 414px.
2. THE versão mobile SHALL exibir em cada card somente o fornecedor, o vencimento, o valor em aberto e o status do título.
3. WHEN o usuário toca em um card de título no mobile, THE Payable_Details_View SHALL exibir, em um bottom sheet, os campos completos do título (Senior_Origin_Fields e rateios) agrupados por categoria (identificação, valores, datas, conta/centro de custo, origem fiscal), com controle visível de fechamento e fechável por gesto de arrastar para baixo.
4. WHILE o dispositivo é detectado como mobile por `useDevice()`, THE versão mobile SHALL distinguir visualmente os títulos marcados como ausentes na Senior (`senior_missing_at` preenchido) dos títulos ativos.
5. IF, após a conclusão do carregamento dos títulos no mobile, não há nenhum título a exibir, THEN THE versão mobile SHALL exibir um empty state contendo texto informativo e uma ação de atualização, em vez de uma lista vazia.

### Requirement 12: Compatibilidade com ambiente local sem Senior

**User Story:** Como desenvolvedor, quero rodar o sistema localmente sem acesso à Senior, para validar a interface com massa de teste.

#### Acceptance Criteria

1. THE DemoSeeder SHALL popular a tabela `payables` com 10 a 30 títulos de teste, com variação de situação e de filial e datas distribuídas no tempo, preenchendo todos os Senior_Origin_Fields do Apêndice A com valores não nulos válidos para seus tipos.
2. WHEN o DemoSeeder gera um título de teste, THE DemoSeeder SHALL associar de 1 a 5 rateios a esse título, cujos percentuais somem 100%.
3. WHEN o DemoSeeder gera títulos de teste, THE DemoSeeder SHALL atribuir a cada título uma Business_Key única na coluna `senior_id`, sem duplicatas.
4. WHERE o ambiente não possui acesso configurado à Senior, THE agendador SHALL concluir o ciclo sem invocar o Senior_CP_Client e sem gerar erro de execução.
5. WHEN o Payables_Sync é acionado enquanto está desabilitado por configuração, THE Payables_Sync SHALL registrar uma execução ignorada por configuração sem alterar registros da tabela `payables`.

---

## Appendix A: Campos da Senior (ConsultarTitulosAbertosCP)

Estrutura da operação `ConsultarTitulosAbertosCP` (versão 3, atual) do Senior_CP_Service, conforme extraída do WSDL/XSD vivo de PRODUÇÃO. Padrão de nomenclatura Senior: 3 letras + 3 letras. Tipos conforme declarados no XSD (`int`, `string`, `double`).

São três grupos: parâmetros de entrada da consulta, campos de cabeçalho do título (saída) e campos de cada rateio (saída, relação um-para-muitos com o título).

### A.1 Entrada — parâmetros de consulta (v3 In, 26 campos)

| Campo | Tipo |
|---|---|
| `codEmp` | int |
| `codFil` | int |
| `codFor` | string |
| `vctIni` | string |
| `vctFim` | string |
| `retRat` | string |
| `tipTit` | string |
| `filCtr` | int |
| `numCtr` | int |
| `filNfc` | int |
| `numNfc` | int |
| `forNfc` | int |
| `snfNfc` | string |
| `filNff` | int |
| `numNff` | int |
| `forNff` | int |
| `filNfv` | int |
| `numNfv` | int |
| `snfNfv` | string |
| `filOcp` | int |
| `numOcp` | int |
| `ocpFre` | int |
| `ocpNre` | int |
| `seqImo` | int |
| `flowInstanceID` | string |
| `flowName` | string |

> `vctIni`/`vctFim` definem a janela de vencimento (base do sync incremental). `retRat = "S"` faz a Senior retornar os rateios aninhados de cada título.

### A.2 Saída — Título / cabeçalho (v3 OutTitulos, 73 campos)

Todos os 73 campos de cabeçalho retornados, a serem espelhados na tabela `payables`. Agrupados por categoria semântica para facilitar a leitura e o agrupamento na Payable_Details_View; todos os campos abaixo existem no WSDL real.

**Identificação / chave**

| Campo | Tipo |
|---|---|
| `codEmp` | int |
| `codFil` | int |
| `numTit` | string |
| `codTpt` | string |
| `codFor` | int |
| `codTns` | string |
| `codNtg` | int |
| `docIdeFav` | string |
| `codDfs` | int |
| `codFrj` | string |
| `cpgSub` | string |
| `gerTep` | string |
| `seqCgt` | int |
| `seqImo` | int |
| `tipEfe` | string |

**Situação / valores**

| Campo | Tipo |
|---|---|
| `sitTit` | string |
| `vlrOri` | double |
| `vlrAbe` | double |
| `vlrDsc` | double |
| `codMoe` | string |
| `codFpg` | int |
| `codMpt` | string |

> `sitTit` é a base do Status_Mapper.

**Datas**

| Campo | Tipo |
|---|---|
| `datEmi` | string |
| `datEnt` | string |
| `vctOri` | string |
| `vctPro` | string |
| `datPpt` | string |
| `ultPgt` | string |
| `datDsc` | string |

**Descontos / juros / multa / negociação**

| Campo | Tipo |
|---|---|
| `perDsc` | int |
| `tolDsc` | int |
| `antDsc` | string |
| `perJrs` | int |
| `jrsDia` | double |
| `tipJrs` | string |
| `tolJrs` | int |
| `proJrs` | string |
| `perMul` | int |
| `tolMul` | int |
| `datNeg` | string |
| `jrsNeg` | double |
| `mulNeg` | double |
| `dscNeg` | double |
| `outNeg` | double |

**Conta / centro de custo / projeto**

| Campo | Tipo |
|---|---|
| `ctaFin` | int |
| `ctaRed` | int |
| `codCcu` | string |
| `numPrj` | int |
| `codFpj` | int |
| `codPor` | string |
| `codCrt` | string |
| `filCcr` | int |
| `numCcr` | int |

**Origem fiscal (referências de documentos)**

| Campo | Tipo |
|---|---|
| `filNfc` | int |
| `numNfc` | int |
| `forNfc` | int |
| `snfNfc` | string |
| `filCtr` | int |
| `numCtr` | int |
| `ctrFre` | int |
| `ctrNre` | int |
| `filNff` | int |
| `numNff` | int |
| `forNff` | int |
| `filNfv` | int |
| `numNfv` | int |
| `snfNfv` | string |
| `filOcp` | int |
| `numOcp` | int |
| `ocpFre` | int |
| `ocpNre` | int |
| `obsTcp` | string |

**Rateios (lista aninhada)**

| Campo | Tipo |
|---|---|
| `rateios` | lista de OutTitulosRateios (`maxOccurs=unbounded`) — ver A.3 |

### A.3 Saída — Rateio (v3 OutTitulosRateios, 20 campos)

Cada título carrega de 0 a N rateios na lista `rateios` (relação um-para-muitos). Campos de cada rateio:

| Campo | Tipo |
|---|---|
| `abrFpj` | string |
| `abrPrj` | string |
| `codCcu` | string |
| `codFpj` | int |
| `codTns` | string |
| `criRat` | int |
| `ctaFin` | int |
| `ctaRed` | int |
| `datBas` | string |
| `mesAno` | string |
| `numPrj` | int |
| `obsRat` | string |
| `perCta` | double |
| `perRat` | double |
| `seqMov` | int |
| `seqRat` | int |
| `somSub` | int |
| `tipOri` | string |
| `vlrCta` | double |
| `vlrRat` | double |

> Observação: a estrutura acima foi VERIFICADA contra o WSDL/XSD vivo de PRODUÇÃO do Senior_CP_Service em 14/06/2026 (operação `ConsultarTitulosAbertosCP` versão 3). Artefatos de referência: `docs/senior/cpa_titulos.wsdl`, `docs/senior/cpa_titulos.xsd` e `docs/senior/cpa_titulos-campos.md`. Ainda falta executar uma chamada real da operação com `codEmp` (consulta read-only) para validar o payload de dados retornado; somente o contrato (WSDL/XSD) foi confirmado até o momento.
