<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    :closable="false"
    :header="`Fluxo/Workflow - ${assunto?.assunto || 'Assunto'}`"
    class="!w-full sm:!w-[95vw] !max-w-full sm:!max-w-5xl mx-0 sm:mx-auto"
    @show="carregarFluxo"
    :pt="{
      root: {
        class:
          'overflow-hidden !rounded-none sm:!rounded-xl m-0 sm:m-4 !max-w-full'
      },
      header: {
        class:
          'border-b border-gray-100 dark:border-slate-700 text-base sm:text-lg'
      },
      content: {
        class:
          'p-0 max-h-[85vh] sm:max-h-[75vh] overflow-y-auto overflow-x-hidden'
      }
    }"
  >
    <div
      class="p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-5 overflow-x-hidden w-full"
    >
      <!-- Descrição -->
      <div
        class="hidden sm:block text-sm text-gray-600 bg-gradient-to-r from-teal-50 to-emerald-50 p-3 sm:p-4 rounded-xl border border-teal-200"
      >
        <div class="flex items-center mb-2">
          <i class="pi pi-directions text-teal-600 mr-2"></i>
          <strong class="text-teal-800 text-xs sm:text-sm">
            Como funciona o Fluxo/Workflow?
          </strong>
        </div>
        <ul
          class="list-disc list-inside space-y-1 text-teal-700 text-xs sm:text-sm"
        >
          <li>
            Configure a sequência de etapas por onde a ticket passará
            automaticamente
          </li>
          <li>
            Cada etapa pertence a um departamento — ao avançar, a ticket é
            <strong>transferida automaticamente</strong>
          </li>
          <li>
            Adicione
            <strong>decisões</strong>
            (ex: Aprovado, Reprovado) para determinar o próximo destino. Sem
            decisões, avança para a próxima
          </li>
          <li>
            Vincule uma
            <strong>etapa de andamento</strong>
            para atualizar automaticamente o status ao avançar no fluxo
          </li>
          <li>
            Ative
            <strong>Manter responsável</strong>
            em cada etapa para preservar o atribuído ao avançar
          </li>
          <li>
            Use
            <strong>Clonar Fluxo</strong>
            para copiar o workflow de outro assunto já configurado
          </li>
          <li>
            Ao
            <strong>concluir</strong>
            o fluxo, a ticket é resolvida. Ao
            <strong>cancelar</strong>
            , é cancelada
          </li>
        </ul>
      </div>

      <!-- Nome do Fluxo -->
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
          <label class="block text-xs font-medium text-gray-600 mb-1">
            Nome do Fluxo
          </label>
          <input
            type="text"
            v-model="nomeFluxo"
            placeholder="Ex: Fluxo de Contratação"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
          />
        </div>
        <div class="flex-1">
          <label class="block text-xs font-medium text-gray-600 mb-1">
            Descrição (opcional)
          </label>
          <input
            type="text"
            v-model="descricaoFluxo"
            placeholder="Descrição do fluxo..."
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
          />
        </div>
      </div>

      <!-- Header etapas -->
      <div
        class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-0"
      >
        <h4 class="text-base sm:text-lg font-semibold text-gray-800">
          Etapas do Fluxo ({{ etapas.length }})
        </h4>
        <div
          class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 w-full sm:w-auto"
        >
          <label
            v-if="etapas.length > 0"
            class="flex items-center gap-2 cursor-pointer select-none"
          >
            <input
              type="checkbox"
              :checked="etapas.every((e) => e.manter_responsavel === 'S')"
              @change="toggleManterResponsavelTodas($event.target.checked)"
              class="w-4 h-4 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-xs text-gray-600">
              Manter responsável ao avançar (todas)
            </span>
          </label>
          <Button
            label="Nova Etapa"
            icon="pi pi-plus"
            severity="success"
            outlined
            size="small"
            class="w-full sm:w-auto"
            @click="adicionarEtapa"
          />
          <Button
            v-if="etapas.length === 0"
            label="Clonar Fluxo"
            icon="pi pi-copy"
            severity="info"
            outlined
            size="small"
            class="w-full sm:w-auto"
            @click="dialogClonarFluxo = true"
          />
        </div>
      </div>

      <!-- Lista vazia -->
      <div
        v-if="etapas.length === 0"
        class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-500"
      >
        <div
          class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full flex items-center justify-center mb-3 sm:mb-4"
        >
          <i class="pi pi-directions !text-2xl sm:text-3xl text-gray-400"></i>
        </div>
        <p class="text-base sm:text-lg font-medium mb-2">
          Nenhuma etapa configurada
        </p>
        <p class="text-xs sm:text-sm text-center max-w-md px-4">
          Clique em "Nova Etapa" para começar a montar o fluxo de workflow
        </p>
      </div>

      <!-- Lista de etapas -->
      <div v-else>
        <div class="space-y-4">
          <div
            v-for="(etapa, index) in etapas"
            :key="etapa._key"
          >
            <div
              class="bg-white border-2 rounded-xl transition-all duration-200 hover:shadow-md"
              :style="{ borderColor: etapa.cor || '#3B82F6' }"
            >
              <!-- Cabeçalho da etapa -->
              <div class="p-2.5 sm:p-3">
                <div class="flex items-start gap-2">
                  <!-- Campo de ordenação por número -->
                  <div class="flex items-center gap-1 pt-1">
                    <input
                      type="number"
                      :value="index + 1"
                      @change="
                        (e) => {
                          moverEtapa(index, parseInt(e.target.value) - 1)
                          e.target.value = index + 1
                        }
                      "
                      @blur="$event.target.value = index + 1"
                      min="1"
                      :max="etapas.length"
                      class="w-10 h-10 text-center text-sm font-bold text-white border-0 rounded-full focus:ring-2 focus:ring-blue-400 transition-colors [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none cursor-pointer"
                      :style="{ backgroundColor: etapa.cor || '#3B82F6' }"
                      v-tooltip.top="`Ordem: digite 1 a ${etapas.length}`"
                    />
                  </div>

                  <!-- Dados da etapa -->
                  <div class="flex-1 min-w-0 space-y-1.5">
                    <!-- Nome + Departamento + Trash -->
                    <div class="flex flex-col sm:flex-row gap-2 min-w-0">
                      <input
                        type="text"
                        v-model="etapa.nome"
                        placeholder="Nome da etapa"
                        class="flex-1 font-medium px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        :class="{ 'border-red-500': !etapa.nome }"
                      />
                      <Select
                        v-model="etapa.departamento"
                        :options="departamentosOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Departamento"
                        filter
                        class="w-full sm:w-56 min-w-0"
                        :class="{ 'border-red-500': !etapa.departamento }"
                        @change="onDepartamentoChange(etapa)"
                      />
                      <Button
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        class="hidden sm:flex flex-shrink-0"
                        @click="removerEtapa(index)"
                        v-tooltip.top="'Remover etapa'"
                      />
                    </div>

                    <!-- Assunto + Etapa andamento lado a lado -->
                    <div
                      v-if="
                        etapa.departamento &&
                        (assuntosParaEtapa(etapa.departamento).length > 0 ||
                          etapasAndamentoParaEtapa(etapa).length > 0)
                      "
                      class="flex flex-col sm:flex-row gap-2 min-w-0"
                    >
                      <div
                        v-if="assuntosParaEtapa(etapa.departamento).length > 0"
                        class="flex-1 min-w-0"
                      >
                        <Select
                          v-model="etapa.assunto_id"
                          :options="assuntosParaEtapa(etapa.departamento)"
                          optionLabel="label"
                          optionValue="value"
                          placeholder="Assunto do departamento (opcional)"
                          filter
                          showClear
                          class="w-full"
                          @change="onAssuntoEtapaChange(etapa)"
                        />
                        <span
                          class="text-[10px] text-gray-400 mt-0.5 flex items-center"
                        >
                          <i class="pi pi-info-circle mr-0.5 text-[9px]"></i>
                          Muda o assunto ao transferir
                        </span>
                      </div>
                      <div
                        v-if="etapasAndamentoParaEtapa(etapa).length > 0"
                        class="flex-1 min-w-0"
                      >
                        <Select
                          v-model="etapa.etapa_andamento_id"
                          :options="etapasAndamentoParaEtapa(etapa)"
                          optionLabel="nome"
                          optionValue="id"
                          placeholder="Etapa de andamento (opcional)"
                          filter
                          showClear
                          class="w-full"
                        />
                        <span
                          class="text-[10px] text-gray-400 mt-0.5 flex items-center"
                        >
                          <i class="pi pi-sync mr-0.5 text-[9px]"></i>
                          Atualiza automaticamente ao avançar
                        </span>
                      </div>
                    </div>

                    <!-- Sem etapas de andamento: oferecer clonar -->
                    <div
                      v-if="
                        etapa.departamento &&
                        etapasAndamentoParaEtapa(etapa).length === 0 &&
                        (etapa.assunto_id || assunto?.id) &&
                        assuntosComEtapas.length > 0
                      "
                      class="flex flex-col sm:flex-row items-start sm:items-center gap-2 min-w-0"
                    >
                      <Select
                        v-model="clonarOrigemAssunto"
                        :options="
                          assuntosComEtapas.filter(
                            (a) => a.value !== (etapa.assunto_id || assunto?.id)
                          )
                        "
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Copiar etapas de..."
                        fluid
                        class="w-full sm:flex-1 min-w-0"
                        size="small"
                      />
                      <Button
                        v-if="clonarOrigemAssunto"
                        label="Copiar"
                        icon="pi pi-copy"
                        severity="info"
                        text
                        size="small"
                        :loading="clonarLoading"
                        @click="clonarEtapasAndamento(etapa)"
                      />
                      <span
                        class="text-[10px] text-gray-400 flex items-center gap-0.5"
                      >
                        <i class="pi pi-info-circle text-[9px]"></i>
                        Sem etapas de andamento
                      </span>
                    </div>

                    <!-- Descrição + Cor + Ícone -->
                    <div class="flex flex-col sm:flex-row gap-2 min-w-0">
                      <input
                        type="text"
                        v-model="etapa.descricao"
                        placeholder="Descrição (opcional)"
                        class="flex-1 px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                      />
                      <div class="flex items-center gap-1.5 flex-shrink-0">
                        <!-- Cor -->
                        <div class="relative">
                          <button
                            type="button"
                            @click="toggleColorPicker(index)"
                            class="w-8 h-8 rounded-lg border-2 border-gray-200 hover:border-gray-400 transition-colors flex items-center justify-center"
                            :style="{ backgroundColor: etapa.cor || '#3B82F6' }"
                            v-tooltip.top="'Cor'"
                          >
                            <i
                              class="pi pi-palette text-white text-[10px] drop-shadow"
                            ></i>
                          </button>
                          <div
                            v-if="colorPickerAberto === index"
                            class="absolute top-10 right-0 z-50 bg-white rounded-xl shadow-xl border p-2.5 w-48"
                          >
                            <div class="grid grid-cols-5 gap-1.5 mb-2">
                              <button
                                v-for="cor in coresPredefinidas"
                                :key="cor"
                                type="button"
                                class="w-7 h-7 rounded-lg border-2 hover:scale-110 transition-transform"
                                :class="
                                  etapa.cor === cor
                                    ? 'border-gray-800 ring-2 ring-offset-1'
                                    : 'border-transparent'
                                "
                                :style="{ backgroundColor: cor }"
                                @click="selecionarCor(index, cor)"
                              ></button>
                            </div>
                            <InputText
                              v-model="etapa.cor"
                              placeholder="#3B82F6"
                              class="w-full text-xs"
                              @blur="colorPickerAberto = null"
                            />
                          </div>
                        </div>
                        <!-- Ícone -->
                        <Select
                          v-model="etapa.icone"
                          :options="iconesPredefinidos"
                          option-value="value"
                          option-label="label"
                          placeholder="Ícone"
                          filter
                          :filter-fields="['label', 'value']"
                          filter-placeholder="Buscar ícone..."
                          empty-filter-message="Nenhum ícone encontrado"
                          empty-message="Nenhum ícone disponível"
                          class="w-28"
                          :virtual-scroller-options="{ itemSize: 36 }"
                        >
                          <template #value="slotProps">
                            <div
                              v-if="slotProps.value"
                              class="flex items-center gap-1"
                            >
                              <i
                                :class="slotProps.value"
                                class="text-xs"
                              ></i>
                              <span class="text-[10px] hidden sm:inline">
                                {{ obterLabelIcone(slotProps.value) }}
                              </span>
                            </div>
                          </template>
                          <template #option="slotProps">
                            <div class="flex items-center gap-2">
                              <i
                                :class="slotProps.option.value"
                                class="text-sm"
                              ></i>
                              <span>{{ slotProps.option.label }}</span>
                            </div>
                          </template>
                        </Select>
                        <!-- Trash mobile -->
                        <Button
                          icon="pi pi-trash"
                          severity="danger"
                          text
                          rounded
                          size="small"
                          class="sm:hidden flex-shrink-0"
                          @click="removerEtapa(index)"
                        />
                      </div>
                    </div>

                    <!-- SLA + Instruções (colapsado numa linha) -->
                    <div class="flex flex-col sm:flex-row gap-2 min-w-0">
                      <div class="sm:w-32">
                        <label
                          class="text-[10px] text-gray-500 font-medium mb-0.5 block"
                        >
                          Prazo SLA (horas)
                        </label>
                        <input
                          type="number"
                          v-model.number="etapa.prazo_horas"
                          placeholder="Ex: 24"
                          min="1"
                          class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                      </div>
                      <div class="flex-1">
                        <label
                          class="text-[10px] text-gray-500 font-medium mb-0.5 block"
                        >
                          Instruções para o responsável
                        </label>
                        <input
                          type="text"
                          v-model="etapa.instrucoes"
                          placeholder="Instruções exibidas ao responsável nesta etapa..."
                          class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                      </div>
                    </div>

                    <!-- Responsável + Checkboxes -->
                    <div class="min-w-0">
                      <label
                        class="text-[10px] text-gray-500 font-medium mb-0.5 block"
                      >
                        Responsável
                        <span class="text-gray-400 font-normal">
                          (opcional — será atribuído automaticamente ao entrar
                          nesta etapa)
                        </span>
                      </label>
                      <!-- Linha 1: Input + Chip -->
                      <div class="flex items-center gap-2 mb-1.5">
                        <div class="w-full sm:w-64">
                          <Funcionario
                            :key="`resp_${index}_${etapa._respKey || 0}`"
                            :model-value="null"
                            :retorna-objeto="true"
                            placeholder="Buscar funcionário..."
                            @update:model-value="
                              definirResponsavelEtapa(index, $event)
                            "
                          />
                        </div>
                        <span
                          v-if="etapa.responsavel_padrao"
                          class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-[11px] border border-blue-200"
                        >
                          <i class="pi pi-user text-[9px]"></i>
                          {{
                            etapa._nomeResponsavel ||
                            `Mat. ${etapa.responsavel_padrao}`
                          }}
                          <button
                            type="button"
                            class="text-blue-400 hover:text-red-500 ml-0.5"
                            @click="limparResponsavelEtapa(index)"
                          >
                            <i class="pi pi-times text-[9px]"></i>
                          </button>
                        </span>
                      </div>
                      <!-- Linha 2: Checkboxes -->
                      <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <label
                          class="flex items-center gap-1 cursor-pointer shrink-0"
                        >
                          <input
                            type="checkbox"
                            :checked="etapa.manter_responsavel === 'S'"
                            @change="
                              etapa.manter_responsavel = $event.target.checked
                                ? 'S'
                                : 'N'
                            "
                            class="w-3.5 h-3.5 rounded border-gray-300 text-blue-500 focus:ring-blue-500"
                          />
                          <span class="text-[10px] text-gray-500">
                            Manter responsável ao avançar
                          </span>
                        </label>
                        <label
                          class="flex items-center gap-1 cursor-pointer shrink-0"
                        >
                          <input
                            type="checkbox"
                            :checked="
                              etapa.permitir_responsavel_externo === 'S'
                            "
                            @change="
                              etapa.permitir_responsavel_externo = $event.target
                                .checked
                                ? 'S'
                                : 'N'
                            "
                            class="w-3.5 h-3.5 rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                          />
                          <span class="text-[10px] text-gray-500">
                            Permitir pessoa de outro departamento
                          </span>
                        </label>
                        <!--
                          Permissão do solicitante na etapa: Nenhum (N), Permitir (S) ou Exclusivo (E).
                          Estende o campo existente `permitir_solicitante_avancar` — sem migration.
                        -->
                        <div class="flex flex-col gap-0.5 shrink-0">
                          <div class="flex items-center gap-1.5">
                            <span class="text-[10px] text-gray-500">
                              Solicitante:
                            </span>
                            <div
                              role="radiogroup"
                              aria-label="Permissão do solicitante nesta etapa"
                              class="inline-flex rounded-md border border-gray-300 overflow-hidden bg-white"
                            >
                              <button
                                type="button"
                                role="radio"
                                :aria-checked="
                                  etapa.permitir_solicitante_avancar === 'N'
                                "
                                title="Só o responsável do departamento avança esta etapa"
                                class="px-2 py-0.5 text-[10px] font-medium transition-colors"
                                :class="
                                  etapa.permitir_solicitante_avancar === 'N'
                                    ? 'bg-gray-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100'
                                "
                                @click="
                                  etapa.permitir_solicitante_avancar = 'N'
                                "
                              >
                                Não atua
                              </button>
                              <button
                                type="button"
                                role="radio"
                                :aria-checked="
                                  etapa.permitir_solicitante_avancar === 'S'
                                "
                                title="Responsável e solicitante podem avançar esta etapa"
                                class="px-2 py-0.5 text-[10px] font-medium transition-colors border-l border-gray-300"
                                :class="
                                  etapa.permitir_solicitante_avancar === 'S'
                                    ? 'bg-green-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100'
                                "
                                @click="
                                  etapa.permitir_solicitante_avancar = 'S'
                                "
                              >
                                Pode avançar
                              </button>
                              <button
                                type="button"
                                role="radio"
                                :aria-checked="
                                  etapa.permitir_solicitante_avancar === 'E'
                                "
                                title="O fluxo aguarda o solicitante para continuar — responsável não vê campos nem botões de avanço"
                                class="px-2 py-0.5 text-[10px] font-medium transition-colors border-l border-gray-300"
                                :class="
                                  etapa.permitir_solicitante_avancar === 'E'
                                    ? 'bg-amber-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100'
                                "
                                @click="
                                  etapa.permitir_solicitante_avancar = 'E'
                                "
                              >
                                Aguardar solicitante
                              </button>
                            </div>
                          </div>
                          <small
                            v-if="etapa.permitir_solicitante_avancar === 'E'"
                            class="text-[10px] text-amber-600 flex items-center gap-1"
                          >
                            <i class="pi pi-clock text-[9px]"></i>
                            O fluxo aguarda o solicitante para continuar —
                            responsável não vê campos nem botões de avanço.
                          </small>
                        </div>
                        <label
                          class="flex items-center gap-1 cursor-pointer shrink-0"
                        >
                          <input
                            type="checkbox"
                            :checked="etapa.exibir_campos_assunto === 'S'"
                            @change="
                              etapa.exibir_campos_assunto = $event.target
                                .checked
                                ? 'S'
                                : 'N'
                            "
                            class="w-3.5 h-3.5 rounded border-gray-300 text-purple-500 focus:ring-purple-500"
                          />
                          <span class="text-[10px] text-gray-500">
                            Exibir campos do assunto nesta etapa
                          </span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Decisões da etapa -->
              <div
                class="border-t px-3 sm:px-4 py-2.5 bg-gray-50/50 overflow-x-hidden"
              >
                <div class="flex items-center justify-between gap-2 mb-1.5">
                  <span
                    class="text-xs font-semibold text-gray-600 flex items-center gap-1"
                  >
                    <i class="pi pi-question-circle text-[11px]"></i>
                    Decisões
                    <span class="text-gray-400 font-normal hidden sm:inline">
                      (opcional — sem decisões, avança para a próxima etapa)
                    </span>
                  </span>
                  <Button
                    label="Decisão"
                    icon="pi pi-plus"
                    severity="info"
                    text
                    size="small"
                    class="shrink-0"
                    @click="adicionarDecisao(index)"
                  />
                </div>

                <div
                  v-if="etapa.decisoes && etapa.decisoes.length > 0"
                  class="space-y-1.5"
                >
                  <div
                    v-for="(decisao, dIndex) in etapa.decisoes"
                    :key="dIndex"
                    class="p-2 bg-white rounded-lg border min-w-0"
                  >
                    <!-- Linha 1: Número + Label + Cor + Remover -->
                    <div class="flex items-start gap-2 min-w-0">
                      <div class="flex-shrink-0 mt-5">
                        <input
                          type="text"
                          inputmode="numeric"
                          :value="dIndex + 1"
                          class="w-9 h-8 text-center text-sm font-bold border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          @change="
                            reordenarDecisao(index, dIndex, $event.target.value)
                          "
                          @keypress="somenteNumeros($event)"
                        />
                      </div>
                      <div class="flex-1 min-w-0">
                        <label
                          class="text-[10px] text-gray-500 font-medium mb-0.5 block"
                        >
                          Rótulo da decisão
                        </label>
                        <input
                          type="text"
                          v-model="decisao.label"
                          placeholder='Ex: "Aprovado", "Reprovado"'
                          class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                          :class="{ 'border-red-500': !decisao.label }"
                        />
                      </div>
                      <div class="flex items-center gap-1 mt-4 flex-shrink-0">
                        <div class="relative">
                          <button
                            type="button"
                            @click="toggleDecisaoColorPicker(index, dIndex)"
                            class="w-7 h-7 rounded-lg border-2 border-gray-200 hover:border-gray-400 transition-colors flex items-center justify-center"
                            :style="{
                              backgroundColor: decisao.cor || '#3B82F6'
                            }"
                          >
                            <i
                              class="pi pi-palette text-white text-[9px] drop-shadow"
                            ></i>
                          </button>
                          <div
                            v-if="decisaoColorPicker === `${index}-${dIndex}`"
                            class="absolute right-0 z-50 bg-white rounded-xl shadow-xl border p-2 w-44 mt-1"
                          >
                            <div class="grid grid-cols-5 gap-1">
                              <button
                                v-for="cor in coresPredefinidas"
                                :key="cor"
                                type="button"
                                class="w-6 h-6 rounded border hover:scale-110"
                                :style="{ backgroundColor: cor }"
                                @click="
                                  selecionarCorDecisao(index, dIndex, cor)
                                "
                              ></button>
                            </div>
                          </div>
                        </div>
                        <Button
                          icon="pi pi-times"
                          severity="danger"
                          text
                          rounded
                          size="small"
                          class="flex-shrink-0"
                          @click="removerDecisao(index, dIndex)"
                        />
                      </div>
                    </div>

                    <!-- Linha 2: Ação + Destino (tudo numa linha) -->
                    <div
                      class="flex flex-col sm:flex-row gap-1.5 mt-1.5 min-w-0"
                    >
                      <Select
                        v-model="decisao.acao"
                        :options="acoesDecisao"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Ação"
                        class="w-full sm:w-48 flex-shrink-0"
                      />
                      <Select
                        v-if="
                          decisao.acao === 'avancar' ||
                          decisao.acao === 'atribuir_avancar' ||
                          decisao.acao === 'voltar' ||
                          decisao.acao === 'voltar_solicitante'
                        "
                        v-model="decisao.etapa_destino_index"
                        :options="opcoesDestinoDecisao(index, decisao.acao)"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="
                          decisao.acao === 'voltar_solicitante'
                            ? 'Retornar para qual etapa?'
                            : 'Ir para qual etapa?'
                        "
                        showClear
                        class="w-full sm:flex-1"
                      />
                      <Select
                        v-if="decisao.acao === 'abrir_solicitacao'"
                        v-model="decisao.etapa_destino_index"
                        :options="opcoesDestinoDecisao(index, 'avancar')"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Avançar para qual etapa?"
                        showClear
                        class="w-full sm:flex-1"
                      />
                    </div>

                    <!-- Abrir ticket: assunto vinculado -->
                    <Select
                      v-if="decisao.acao === 'abrir_solicitacao'"
                      v-model="decisao.abrir_solicitacao_assunto_id"
                      :options="todosAssuntos"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Assunto da ticket vinculada"
                      filter
                      class="w-full mt-1.5"
                      :class="{
                        'border-red-500': !decisao.abrir_solicitacao_assunto_id
                      }"
                    />

                    <!-- Etapa de andamento da decisão -->
                    <div
                      v-if="etapasAndamentoParaEtapa(etapa).length > 0"
                      class="flex flex-col sm:flex-row items-start sm:items-center gap-1.5 mt-1.5 min-w-0"
                    >
                      <Select
                        v-model="decisao.etapa_andamento_id"
                        :options="etapasAndamentoParaEtapa(etapa)"
                        optionLabel="nome"
                        optionValue="id"
                        placeholder="Etapa de andamento (opcional)"
                        filter
                        showClear
                        class="w-full sm:flex-1 min-w-0"
                      />
                      <span class="text-[10px] text-gray-400 flex items-center">
                        <i class="pi pi-sync mr-0.5 text-[9px]"></i>
                        Atualiza andamento ao escolher
                      </span>
                    </div>
                  </div>
                </div>

                <div
                  v-else
                  class="text-xs text-gray-400 italic py-1"
                >
                  Sem decisões — ao concluir esta etapa, avança automaticamente
                  para a etapa {{ index + 2 }}
                  <span v-if="index === etapas.length - 1">
                    (finaliza o fluxo)
                  </span>
                </div>
              </div>

              <!-- ═══ CAMPOS DA ETAPA ═══ -->
              <div
                class="border-t px-3 sm:px-4 py-2.5 bg-amber-50/30 rounded-b-xl overflow-x-hidden"
              >
                <div class="flex items-center justify-between gap-2 mb-1.5">
                  <span
                    class="text-xs font-semibold text-gray-600 flex items-center gap-1"
                  >
                    <i class="pi pi-list text-[11px]"></i>
                    Campos
                    <span class="text-gray-400 font-normal hidden sm:inline">
                      (opcional — campos para o responsável preencher)
                    </span>
                  </span>
                  <div class="flex items-center gap-1 shrink-0">
                    <Button
                      label="Predefinido"
                      icon="pi pi-cog"
                      severity="warning"
                      text
                      size="small"
                      @click="abrirCamposPredefinidos(index)"
                    />
                    <Button
                      label="Campo"
                      icon="pi pi-plus"
                      severity="info"
                      text
                      size="small"
                      @click="adicionarCampo(index)"
                    />
                  </div>
                </div>

                <div
                  v-if="etapa.campos && etapa.campos.length > 0"
                  class="space-y-2"
                >
                  <div
                    v-for="(campo, cIndex) in etapa.campos"
                    :key="cIndex"
                    class="p-2.5 bg-white rounded-lg border min-w-0"
                  >
                    <!-- Linha 1: Ordem + Label + Obrigatório + Remover -->
                    <div
                      class="flex flex-col sm:flex-row sm:items-center gap-2 mb-1.5 min-w-0"
                    >
                      <!-- Ordem numérica -->
                      <div class="flex-shrink-0 sm:mt-4">
                        <label
                          class="text-[10px] text-gray-500 font-medium mb-0.5 block sm:hidden"
                        >
                          Ordem
                        </label>
                        <input
                          type="number"
                          :value="cIndex + 1"
                          @change="
                            reordenarCampo(
                              index,
                              cIndex,
                              parseInt($event.target.value)
                            )
                          "
                          @input="
                            limitarOrdemCampo($event, etapa.campos.length)
                          "
                          min="1"
                          :max="etapa.campos.length"
                          class="w-12 px-1.5 py-1.5 text-xs text-center border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                      </div>
                      <div class="flex-1 min-w-0">
                        <label
                          class="text-xs text-gray-500 font-medium mb-0.5 block"
                        >
                          {{ campo.predefinido === "S" ? "🔒 " : "" }}Rótulo do
                          campo
                        </label>
                        <input
                          type="text"
                          v-model="campo.label"
                          placeholder='Ex: "Justificativa", "Valor"'
                          class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                          :class="{ 'border-red-500': !campo.label }"
                          :disabled="campo.predefinido === 'S'"
                        />
                      </div>
                      <div
                        class="flex items-center gap-2 sm:gap-1 flex-shrink-0 sm:mt-4"
                      >
                        <div class="flex items-center gap-1">
                          <label class="text-[10px] text-gray-500">
                            Obrigatório
                          </label>
                          <input
                            type="checkbox"
                            :checked="campo.obrigatorio === 'S'"
                            @change="
                              campo.obrigatorio = $event.target.checked
                                ? 'S'
                                : 'N'
                            "
                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                        </div>
                        <Button
                          icon="pi pi-times"
                          severity="danger"
                          text
                          rounded
                          size="small"
                          class="flex-shrink-0"
                          @click="removerCampo(index, cIndex)"
                        />
                      </div>
                    </div>

                    <!-- Linha 2: Tipo + Placeholder + Decisão -->
                    <div class="flex flex-col sm:flex-row gap-2 min-w-0">
                      <Select
                        v-model="campo.tipo"
                        :options="tiposCampo"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Tipo"
                        class="w-full sm:w-36 flex-shrink-0"
                        :disabled="campo.predefinido === 'S'"
                      />
                      <input
                        v-if="
                          campo.tipo !== 'checkbox' && campo.tipo !== 'arquivo'
                        "
                        type="text"
                        v-model="campo.placeholder"
                        placeholder="Placeholder (opcional)"
                        class="flex-1 min-w-0 px-2.5 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                      />
                      <div
                        v-if="etapa.decisoes && etapa.decisoes.length > 0"
                        class="flex items-center gap-1.5 flex-shrink-0"
                      >
                        <i
                          class="pi pi-directions text-[10px] text-gray-400"
                        ></i>
                        <Select
                          v-model="campo.decisao_index"
                          :options="[
                            { label: 'Todas (sempre aparece)', value: null },
                            ...etapa.decisoes.map((d, di) => ({
                              label: d.label || `Decisão ${di + 1}`,
                              value: di
                            }))
                          ]"
                          optionLabel="label"
                          optionValue="value"
                          placeholder="Todas (sempre aparece)"
                          class="w-full sm:w-52"
                        />
                      </div>
                    </div>

                    <!-- Opções (quando tipo = selecao) -->
                    <div
                      v-if="campo.tipo === 'selecao'"
                      class="mt-2"
                    >
                      <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-gray-500 font-medium">
                          Opções
                        </label>
                        <button
                          type="button"
                          @click="campo.opcoes = [...(campo.opcoes || []), '']"
                          class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors"
                        >
                          <i class="pi pi-plus text-xs"></i>
                          Opção
                        </button>
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                        <div
                          v-for="(opcao, opIndex) in campo.opcoes || []"
                          :key="opIndex"
                          class="flex items-center gap-1"
                        >
                          <input
                            type="text"
                            :value="opcao"
                            @input="campo.opcoes[opIndex] = $event.target.value"
                            placeholder="Valor da opção"
                            class="flex-1 px-2 py-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                          />
                          <button
                            type="button"
                            @click="campo.opcoes.splice(opIndex, 1)"
                            class="p-1 text-red-400 hover:text-red-600 transition-colors"
                            title="Remover opção"
                          >
                            <i class="pi pi-times text-xs"></i>
                          </button>
                        </div>
                      </div>
                      <div
                        v-if="!campo.opcoes || campo.opcoes.length === 0"
                        class="text-xs text-gray-400 italic py-1"
                      >
                        Nenhuma opção adicionada
                      </div>
                    </div>
                  </div>
                </div>

                <div
                  v-else
                  class="text-xs text-gray-400 italic py-1"
                >
                  Sem campos — o responsável não precisará preencher informações
                  extras
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Visualização do Fluxo -->
      <div
        v-if="etapas.length > 0"
        class="border-t pt-3 sm:pt-4 flex justify-center sm:justify-end"
      >
        <Button
          label="Ver Fluxo"
          icon="pi pi-eye"
          severity="info"
          outlined
          size="small"
          class="w-full sm:w-auto"
          @click="dialogFluxoVisual = true"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex flex-row justify-end gap-2">
        <Button
          label="Cancelar"
          icon="pi pi-times"
          outlined
          severity="secondary"
          @click="fecharDialog"
        />
        <Button
          label="Salvar Fluxo"
          icon="pi pi-check"
          severity="success"
          outlined
          :loading="loading"
          @click="salvarFluxo"
        />
      </div>
    </template>
  </Dialog>

  <!-- Dialog de Visualização do Fluxo -->
  <Dialog
    v-model:visible="dialogFluxoVisual"
    modal
    :closable="false"
    header="Visualização do Fluxo"
    class="w-full sm:w-[95vw] max-w-5xl mx-0 sm:mx-auto"
    :pt="{
      root: { class: '!rounded-none sm:!rounded-xl m-0 sm:m-4' },
      content: { class: 'p-3 sm:p-6' }
    }"
  >
    <div class="space-y-4">
      <!-- Fluxo visual -->
      <div class="flex flex-col items-center gap-3 pb-2">
        <template
          v-for="(etapa, index) in etapas"
          :key="index"
        >
          <!-- Etapa -->
          <div
            class="w-full max-w-md px-4 py-3 rounded-xl text-white text-sm font-medium shadow-lg"
            :style="{ backgroundColor: etapa.cor || '#3B82F6' }"
          >
            <div class="flex items-center gap-2 mb-1">
              <i
                :class="etapa.icone || 'pi pi-circle'"
                class="text-sm"
              ></i>
              <span class="font-bold">
                {{ index + 1 }}. {{ etapa.nome || "Etapa " + (index + 1) }}
              </span>
            </div>
            <div class="text-xs opacity-80">
              <i class="pi pi-building mr-1"></i>
              {{ etapa.departamento || "Sem departamento" }}
            </div>
          </div>

          <!-- Decisões ou seta -->
          <div
            v-if="etapa.decisoes && etapa.decisoes.length > 0"
            class="flex flex-wrap justify-center gap-2"
          >
            <div
              v-for="(decisao, dIndex) in etapa.decisoes"
              :key="dIndex"
              class="flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium text-white shadow"
              :style="{ backgroundColor: decisao.cor || '#6B7280' }"
            >
              {{ decisao.label }}
              <span class="opacity-70">
                → {{ obterNomeDestinoDecisao(decisao, index) }}
              </span>
            </div>
          </div>
          <div
            v-else-if="index < etapas.length - 1"
            class="flex flex-col items-center"
          >
            <i class="pi pi-arrow-down text-gray-400 text-xl"></i>
          </div>
        </template>
      </div>
    </div>
    <template #footer>
      <Button
        label="Fechar"
        icon="pi pi-times"
        severity="secondary"
        outlined
        class="w-full sm:w-auto"
        @click="dialogFluxoVisual = false"
      />
    </template>
  </Dialog>

  <!-- Dialog de Campos Predefinidos -->
  <Dialog
    v-model:visible="dialogCamposPredefinidos"
    modal
    :closable="false"
    header="Campos Predefinidos"
    class="w-full sm:w-[95vw] max-w-lg mx-0 sm:mx-auto"
    :pt="{
      root: { class: '!rounded-none sm:!rounded-xl m-0 sm:m-4' },
      content: { class: 'p-3 sm:p-6' }
    }"
  >
    <p class="text-sm text-gray-500 mb-4">
      Selecione os campos predefinidos para adicionar à etapa. Campos já
      adicionados ficam desabilitados.
    </p>
    <div class="space-y-2">
      <div
        v-for="campoPre in camposPredefinidosList"
        :key="campoPre.key"
        class="flex items-center justify-between p-3 rounded-lg border hover:bg-gray-50 transition-colors"
        :class="
          isCampoPredefinidoNaEtapa(campoPre.key)
            ? 'opacity-50 bg-gray-50'
            : 'cursor-pointer'
        "
      >
        <div class="flex-1">
          <div class="text-sm font-medium text-gray-700">
            {{ campoPre.label }}
          </div>
          <div class="text-xs text-gray-400 flex items-center gap-2">
            <span class="capitalize">{{ campoPre.tipo }}</span>
            <span
              v-if="campoPre.obrigatorio === 'S'"
              class="text-red-500 font-semibold"
            >
              • Obrigatório
            </span>
          </div>
        </div>
        <Button
          :label="
            isCampoPredefinidoNaEtapa(campoPre.key) ? 'Adicionado' : 'Adicionar'
          "
          :icon="
            isCampoPredefinidoNaEtapa(campoPre.key)
              ? 'pi pi-check'
              : 'pi pi-plus'
          "
          :severity="
            isCampoPredefinidoNaEtapa(campoPre.key) ? 'secondary' : 'info'
          "
          size="small"
          outlined
          :disabled="isCampoPredefinidoNaEtapa(campoPre.key)"
          @click="adicionarCampoPredefinido(campoPre)"
        />
      </div>
    </div>
    <template #footer>
      <Button
        label="Fechar"
        icon="pi pi-times"
        severity="secondary"
        outlined
        @click="dialogCamposPredefinidos = false"
      />
    </template>
  </Dialog>

  <!-- Dialog Clonar Fluxo -->
  <Dialog
    v-model:visible="dialogClonarFluxo"
    header="Clonar Fluxo de outro Assunto"
    modal
    :style="{ width: '400px' }"
    :closable="!clonarLoading"
    @show="carregarAssuntosComFluxo"
  >
    <div class="space-y-4">
      <p class="text-sm text-gray-600">
        Selecione o assunto de onde deseja copiar o fluxo de workflow.
      </p>
      <Select
        v-model="clonarFluxoAssuntoId"
        :options="assuntosParaClonar"
        optionLabel="label"
        optionValue="value"
        placeholder="Selecione o assunto..."
        filter
        fluid
        :loading="carregandoAssuntosClonar"
      />
    </div>
    <template #footer>
      <Button
        label="Cancelar"
        icon="pi pi-times"
        severity="secondary"
        outlined
        :disabled="clonarLoading"
        @click="dialogClonarFluxo = false"
      />
      <Button
        label="Clonar"
        icon="pi pi-copy"
        severity="info"
        :disabled="!clonarFluxoAssuntoId"
        :loading="clonarLoading"
        @click="clonarFluxoDeAssunto"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed } from "vue"
import axios from "axios"
import { toastError, toastSuccess } from "@/utils/globalFunctions"
import Funcionario from "@/Components/Componentes/Funcionario.vue"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  assunto: { type: Object, default: null },
  departamentos: { type: Array, default: () => [] },
  assuntos: { type: Array, default: () => [] }
})

const emit = defineEmits(["update:modelValue", "fluxoSalvo"])

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value)
})

const loading = ref(false)
const nomeFluxo = ref("Fluxo")
const descricaoFluxo = ref("")
const etapas = ref([])
const fluxoExistente = ref(false)
const colorPickerAberto = ref(null)
const decisaoColorPicker = ref(null)
const dialogFluxoVisual = ref(false)
const etapasAndamentoPorAssunto = ref({})
const clonarOrigemAssunto = ref(null)
const clonarLoading = ref(false)
const dialogClonarFluxo = ref(false)
const clonarFluxoAssuntoId = ref(null)

// Assuntos disponíveis para clonar fluxo (somente os que possuem fluxo)
const assuntosParaClonar = ref([])
const carregandoAssuntosClonar = ref(false)

async function carregarAssuntosComFluxo() {
  assuntosParaClonar.value = []
  clonarFluxoAssuntoId.value = null
  carregandoAssuntosClonar.value = true
  const outrosAssuntos = props.assuntos.filter(
    (a) => a.id !== props.assunto?.id
  )
  try {
    const results = await Promise.allSettled(
      outrosAssuntos.map((a) =>
        axios
          .get(`/solicitacoes/configuracoes/fluxo/${a.id}`)
          .then(({ data }) => ({
            assunto: a,
            data
          }))
      )
    )
    for (const r of results) {
      if (
        r.status === "fulfilled" &&
        r.value.data?.etapas?.some((e) => e.ativo === "S")
      ) {
        assuntosParaClonar.value.push({
          label: r.value.assunto.assunto,
          value: r.value.assunto.id
        })
      }
    }
  } finally {
    carregandoAssuntosClonar.value = false
  }
}

// Assuntos que possuem etapas de andamento carregadas
const assuntosComEtapas = computed(() => {
  const resultado = []
  for (const [assuntoId, etapasList] of Object.entries(
    etapasAndamentoPorAssunto.value
  )) {
    if (etapasList && etapasList.length > 0) {
      // Buscar nome do assunto nos caches disponíveis
      const id = Number(assuntoId)
      let nome = null
      // Tentar props.assuntos
      const fromProps = props.assuntos.find(
        (a) => a.value === id || a.id === id
      )
      if (fromProps) {
        nome = fromProps.label || fromProps.assunto
      }
      // Tentar assuntosPorDepto
      if (!nome) {
        for (const deptoAssuntos of Object.values(assuntosPorDepto.value)) {
          const found = deptoAssuntos.find((a) => a.value === id)
          if (found) {
            nome = found.label
            break
          }
        }
      }
      // Fallback: assunto principal
      if (!nome && props.assunto?.id === id) {
        nome = props.assunto.assunto || props.assunto.nome
      }
      resultado.push({
        value: id,
        label: nome || `Assunto #${assuntoId}`
      })
    }
  }
  return resultado
})

// Departamentos disponíveis para seleção
const departamentosOptions = computed(() => {
  if (props.departamentos && props.departamentos.length > 0) {
    return props.departamentos
  }
  return []
})

// Todos os assuntos para o selector de "abrir ticket vinculada"
const todosAssuntos = computed(() => {
  return (props.assuntos || []).map((a) => ({
    label: `${a.assunto} (${a.departamento || "?"})`,
    value: a.id
  }))
})

// Assuntos por departamento (cache local)
const assuntosPorDepto = ref({})

async function carregarAssuntosDepto(departamento) {
  if (!departamento || assuntosPorDepto.value[departamento]) return
  try {
    const res = await axios.post("/solicitacoes/configuracoes/assuntos", {
      departamento: departamento,
      incluir_inativos: false
    })
    assuntosPorDepto.value[departamento] = (res.data.assuntos || []).map(
      (a) => ({ label: a.assunto, value: a.id })
    )
  } catch (e) {
    console.warn("Erro ao carregar assuntos do departamento:", e)
    assuntosPorDepto.value[departamento] = []
  }
}

function assuntosParaEtapa(departamento) {
  if (!departamento) return []
  return assuntosPorDepto.value[departamento] || []
}

function onDepartamentoChange(etapa) {
  etapa.assunto_id = null
  etapa.etapa_andamento_id = null
  if (etapa.departamento) {
    carregarAssuntosDepto(etapa.departamento)
  }
}

function onAssuntoEtapaChange(etapa) {
  etapa.etapa_andamento_id = null
  const assuntoId = etapa.assunto_id || props.assunto?.id
  if (assuntoId) carregarEtapasAndamentoAssunto(assuntoId)
}

async function carregarEtapasAndamentoAssunto(assuntoId) {
  if (!assuntoId || etapasAndamentoPorAssunto.value[assuntoId]) return
  try {
    const { data } = await axios.get(
      `/solicitacoes/configuracoes/etapas/${assuntoId}`
    )
    etapasAndamentoPorAssunto.value[assuntoId] = (data || []).filter(
      (e) => e.ativo === "S"
    )
  } catch (e) {
    console.warn("Erro ao carregar etapas de andamento:", e)
    etapasAndamentoPorAssunto.value[assuntoId] = []
  }
}

function etapasAndamentoParaEtapa(etapa) {
  const assuntoId = etapa.assunto_id || props.assunto?.id
  return etapasAndamentoPorAssunto.value[assuntoId] || []
}

async function clonarEtapasAndamento(etapa) {
  const destinoId = etapa.assunto_id || props.assunto?.id
  if (!clonarOrigemAssunto.value || !destinoId) return

  clonarLoading.value = true
  try {
    const { data } = await axios.post(
      "/solicitacoes/configuracoes/clonar-etapas",
      {
        origem_assunto_id: clonarOrigemAssunto.value,
        destino_assunto_id: destinoId
      }
    )
    if (data.success) {
      etapasAndamentoPorAssunto.value[destinoId] = (data.etapas || []).filter(
        (e) => e.ativo === "S"
      )
      clonarOrigemAssunto.value = null
      toastSuccess(data.message)
    } else {
      toastError(data.message)
    }
  } catch (e) {
    toastError("Erro ao clonar etapas de andamento")
  } finally {
    clonarLoading.value = false
  }
}

async function clonarFluxoDeAssunto() {
  if (!clonarFluxoAssuntoId.value) return

  clonarLoading.value = true
  try {
    const { data } = await axios.get(
      `/solicitacoes/configuracoes/fluxo/${clonarFluxoAssuntoId.value}`
    )
    if (data && data.etapas?.length > 0) {
      const etapasData = (data.etapas || []).filter((e) => e.ativo === "S")
      const depto = props.assunto?.departamento || undefined

      etapas.value = etapasData.map((e, i) => ({
        id: null,
        _key: `clone_${Date.now()}_${i}`,
        nome: e.nome,
        descricao: e.descricao,
        departamento: depto || e.departamento,
        assunto_id: null,
        etapa_andamento_id: null,
        manter_responsavel: e.manter_responsavel || "N",
        responsavel_padrao: null,
        prazo_horas: e.prazo_horas || null,
        instrucoes: e.instrucoes || null,
        cor: e.cor || coresPredefinidas[i % coresPredefinidas.length],
        icone: e.icone || "pi pi-circle",
        ordem: i,
        decisoes: (e.decisoes || []).map((d, dIndex) => ({
          id: null,
          label: d.label,
          cor: d.cor,
          icone: d.icone,
          acao: d.acao || "avancar",
          etapa_destino_index: d.etapa_destino_id
            ? etapasData.findIndex((et) => et.id === d.etapa_destino_id)
            : -1,
          etapa_andamento_id: null,
          abrir_solicitacao_assunto_id: d.abrir_solicitacao_assunto_id || null,
          ordem: dIndex
        })),
        campos: (e.campos || []).map((c, cIndex) => ({
          id: null,
          label: c.label,
          tipo: c.tipo || "texto",
          placeholder: c.placeholder || null,
          opcoes: c.opcoes || [],
          obrigatorio: c.obrigatorio || "N",
          ordem: cIndex
        })),
        responsaveis_permitidos: [],
        permitir_responsavel_externo: "N",
        exibir_campos_assunto: e.exibir_campos_assunto || "N",
        _novoPermitido: null
      }))

      nomeFluxo.value = data.nome || "Fluxo"
      descricaoFluxo.value = ""

      // Carregar assuntos do departamento
      if (depto) carregarAssuntosDepto(depto)

      dialogClonarFluxo.value = false
      clonarFluxoAssuntoId.value = null
      toastSuccess(
        `Fluxo clonado com ${etapasData.length} etapas. Revise e salve.`
      )
    } else {
      toastError("O assunto selecionado não possui fluxo configurado")
    }
  } catch (e) {
    toastError("Erro ao clonar fluxo")
  } finally {
    clonarLoading.value = false
  }
}

const coresPredefinidas = [
  "#3B82F6",
  "#10B981",
  "#F59E0B",
  "#EF4444",
  "#8B5CF6",
  "#EC4899",
  "#06B6D4",
  "#F97316",
  "#6366F1",
  "#14B8A6",
  "#84CC16",
  "#A855F7",
  "#22C55E",
  "#0EA5E9",
  "#64748B"
]

const iconesPredefinidos = [
  { value: "pi pi-address-book", label: "Agenda" },
  { value: "pi pi-align-center", label: "Alinhar Centro" },
  { value: "pi pi-align-justify", label: "Justificar" },
  { value: "pi pi-align-left", label: "Alinhar Esquerda" },
  { value: "pi pi-align-right", label: "Alinhar Direita" },
  { value: "pi pi-android", label: "Android" },
  { value: "pi pi-angle-double-down", label: "Seta Dupla Baixo" },
  { value: "pi pi-angle-double-left", label: "Seta Dupla Esquerda" },
  { value: "pi pi-angle-double-right", label: "Seta Dupla Direita" },
  { value: "pi pi-angle-double-up", label: "Seta Dupla Cima" },
  { value: "pi pi-angle-down", label: "Seta Baixo" },
  { value: "pi pi-angle-left", label: "Seta Esquerda" },
  { value: "pi pi-angle-right", label: "Seta Direita" },
  { value: "pi pi-angle-up", label: "Seta Cima" },
  { value: "pi pi-apple", label: "Apple" },
  { value: "pi pi-arrow-circle-down", label: "Círculo Seta Baixo" },
  { value: "pi pi-arrow-circle-left", label: "Círculo Seta Esquerda" },
  { value: "pi pi-arrow-circle-right", label: "Círculo Seta Direita" },
  { value: "pi pi-arrow-circle-up", label: "Círculo Seta Cima" },
  { value: "pi pi-arrow-down", label: "Seta para Baixo" },
  { value: "pi pi-arrow-down-left", label: "Seta Diagonal Esquerda" },
  {
    value: "pi pi-arrow-down-left-and-arrow-up-right-to-center",
    label: "Minimizar"
  },
  { value: "pi pi-arrow-down-right", label: "Seta Diagonal Direita" },
  { value: "pi pi-arrow-left", label: "Seta para Esquerda" },
  { value: "pi pi-arrow-right", label: "Seta para Direita" },
  { value: "pi pi-arrow-right-arrow-left", label: "Transferência" },
  { value: "pi pi-arrows-alt", label: "Mover" },
  { value: "pi pi-arrows-h", label: "Setas Horizontal" },
  { value: "pi pi-arrows-v", label: "Setas Vertical" },
  { value: "pi pi-arrow-up", label: "Seta para Cima" },
  { value: "pi pi-arrow-up-left", label: "Seta Cima Esquerda" },
  { value: "pi pi-arrow-up-right", label: "Seta Cima Direita" },
  {
    value: "pi pi-arrow-up-right-and-arrow-down-left-from-center",
    label: "Expandir"
  },
  { value: "pi pi-asteriks", label: "Asterisco" },
  { value: "pi pi-at", label: "Arroba" },
  { value: "pi pi-backward", label: "Retroceder" },
  { value: "pi pi-ban", label: "Proibido" },
  { value: "pi pi-barcode", label: "Código de Barras" },
  { value: "pi pi-bars", label: "Menu" },
  { value: "pi pi-bell", label: "Sino / Notificação" },
  { value: "pi pi-bell-slash", label: "Sem Notificação" },
  { value: "pi pi-bitcoin", label: "Bitcoin" },
  { value: "pi pi-bolt", label: "Raio" },
  { value: "pi pi-book", label: "Livro" },
  { value: "pi pi-bookmark", label: "Marcador" },
  { value: "pi pi-bookmark-fill", label: "Marcador Preenchido" },
  { value: "pi pi-box", label: "Caixa / Estoque" },
  { value: "pi pi-briefcase", label: "Maleta / Trabalho" },
  { value: "pi pi-building", label: "Prédio / Empresa" },
  { value: "pi pi-building-columns", label: "Instituição" },
  { value: "pi pi-bullseye", label: "Alvo" },
  { value: "pi pi-calculator", label: "Calculadora" },
  { value: "pi pi-calendar", label: "Calendário" },
  { value: "pi pi-calendar-clock", label: "Calendário Relógio" },
  { value: "pi pi-calendar-minus", label: "Calendário Menos" },
  { value: "pi pi-calendar-plus", label: "Calendário Mais" },
  { value: "pi pi-calendar-times", label: "Calendário Cancelar" },
  { value: "pi pi-camera", label: "Câmera" },
  { value: "pi pi-car", label: "Carro" },
  { value: "pi pi-caret-down", label: "Indicador Baixo" },
  { value: "pi pi-caret-left", label: "Indicador Esquerda" },
  { value: "pi pi-caret-right", label: "Indicador Direita" },
  { value: "pi pi-caret-up", label: "Indicador Cima" },
  { value: "pi pi-cart-arrow-down", label: "Carrinho Download" },
  { value: "pi pi-cart-minus", label: "Carrinho Menos" },
  { value: "pi pi-cart-plus", label: "Carrinho Mais" },
  { value: "pi pi-chart-bar", label: "Gráfico Barras" },
  { value: "pi pi-chart-line", label: "Gráfico Linhas" },
  { value: "pi pi-chart-pie", label: "Gráfico Pizza" },
  { value: "pi pi-chart-scatter", label: "Gráfico Dispersão" },
  { value: "pi pi-check", label: "Verificado" },
  { value: "pi pi-check-circle", label: "Check Círculo" },
  { value: "pi pi-check-square", label: "Check Quadrado" },
  { value: "pi pi-chevron-circle-down", label: "Chevron Círculo Baixo" },
  { value: "pi pi-chevron-circle-left", label: "Chevron Círculo Esquerda" },
  { value: "pi pi-chevron-circle-right", label: "Chevron Círculo Direita" },
  { value: "pi pi-chevron-circle-up", label: "Chevron Círculo Cima" },
  { value: "pi pi-chevron-down", label: "Chevron Baixo" },
  { value: "pi pi-chevron-left", label: "Chevron Esquerda" },
  { value: "pi pi-chevron-right", label: "Chevron Direita" },
  { value: "pi pi-chevron-up", label: "Chevron Cima" },
  { value: "pi pi-circle", label: "Círculo" },
  { value: "pi pi-circle-fill", label: "Círculo Preenchido" },
  { value: "pi pi-circle-off", label: "Círculo Desligado" },
  { value: "pi pi-circle-on", label: "Círculo Ligado" },
  { value: "pi pi-clipboard", label: "Prancheta" },
  { value: "pi pi-clock", label: "Relógio" },
  { value: "pi pi-clone", label: "Clonar / Duplicar" },
  { value: "pi pi-cloud", label: "Nuvem" },
  { value: "pi pi-cloud-download", label: "Baixar da Nuvem" },
  { value: "pi pi-cloud-upload", label: "Enviar p/ Nuvem" },
  { value: "pi pi-code", label: "Código" },
  { value: "pi pi-cog", label: "Engrenagem / Config" },
  { value: "pi pi-comment", label: "Comentário" },
  { value: "pi pi-comments", label: "Comentários / Chat" },
  { value: "pi pi-compass", label: "Bússola" },
  { value: "pi pi-copy", label: "Copiar" },
  { value: "pi pi-credit-card", label: "Cartão de Crédito" },
  { value: "pi pi-crown", label: "Coroa / Gestor" },
  { value: "pi pi-database", label: "Banco de Dados" },
  { value: "pi pi-delete-left", label: "Apagar" },
  { value: "pi pi-desktop", label: "Computador" },
  { value: "pi pi-directions", label: "Direções" },
  { value: "pi pi-directions-alt", label: "Direções Alt" },
  { value: "pi pi-discord", label: "Discord" },
  { value: "pi pi-dollar", label: "Dólar / Moeda" },
  { value: "pi pi-download", label: "Download" },
  { value: "pi pi-eject", label: "Ejetar" },
  { value: "pi pi-ellipsis-h", label: "Reticências Horizontal" },
  { value: "pi pi-ellipsis-v", label: "Reticências Vertical" },
  { value: "pi pi-envelope", label: "Envelope / Email" },
  { value: "pi pi-equals", label: "Igual" },
  { value: "pi pi-eraser", label: "Borracha" },
  { value: "pi pi-ethereum", label: "Ethereum" },
  { value: "pi pi-euro", label: "Euro" },
  { value: "pi pi-exclamation-circle", label: "Atenção Círculo" },
  { value: "pi pi-exclamation-triangle", label: "Alerta Triângulo" },
  { value: "pi pi-expand", label: "Expandir Tela" },
  { value: "pi pi-external-link", label: "Link Externo" },
  { value: "pi pi-eye", label: "Olho / Visualizar" },
  { value: "pi pi-eye-slash", label: "Ocultar" },
  { value: "pi pi-facebook", label: "Facebook" },
  { value: "pi pi-face-smile", label: "Rosto Feliz" },
  { value: "pi pi-fast-backward", label: "Retroceder Rápido" },
  { value: "pi pi-fast-forward", label: "Avançar Rápido" },
  { value: "pi pi-file", label: "Arquivo" },
  { value: "pi pi-file-arrow-up", label: "Enviar Arquivo" },
  { value: "pi pi-file-check", label: "Arquivo Verificado" },
  { value: "pi pi-file-edit", label: "Editar Arquivo" },
  { value: "pi pi-file-excel", label: "Arquivo Excel" },
  { value: "pi pi-file-export", label: "Exportar Arquivo" },
  { value: "pi pi-file-import", label: "Importar Arquivo" },
  { value: "pi pi-file-o", label: "Arquivo Vazio" },
  { value: "pi pi-file-pdf", label: "Arquivo PDF" },
  { value: "pi pi-file-plus", label: "Novo Arquivo" },
  { value: "pi pi-file-word", label: "Arquivo Word" },
  { value: "pi pi-filter", label: "Filtro" },
  { value: "pi pi-filter-fill", label: "Filtro Preenchido" },
  { value: "pi pi-filter-slash", label: "Sem Filtro" },
  { value: "pi pi-flag", label: "Bandeira" },
  { value: "pi pi-flag-fill", label: "Bandeira Preenchida" },
  { value: "pi pi-folder", label: "Pasta" },
  { value: "pi pi-folder-open", label: "Pasta Aberta" },
  { value: "pi pi-folder-plus", label: "Nova Pasta" },
  { value: "pi pi-forward", label: "Avançar" },
  { value: "pi pi-gauge", label: "Velocímetro" },
  { value: "pi pi-gift", label: "Presente" },
  { value: "pi pi-github", label: "Github" },
  { value: "pi pi-globe", label: "Globo / Internet" },
  { value: "pi pi-google", label: "Google" },
  { value: "pi pi-graduation-cap", label: "Formatura" },
  { value: "pi pi-hammer", label: "Martelo / Jurídico" },
  { value: "pi pi-hashtag", label: "Hashtag" },
  { value: "pi pi-headphones", label: "Fone de Ouvido" },
  { value: "pi pi-heart", label: "Coração" },
  { value: "pi pi-heart-fill", label: "Coração Preenchido" },
  { value: "pi pi-history", label: "Histórico" },
  { value: "pi pi-home", label: "Casa / Início" },
  { value: "pi pi-hourglass", label: "Ampulheta" },
  { value: "pi pi-id-card", label: "Crachá / Identidade" },
  { value: "pi pi-image", label: "Imagem" },
  { value: "pi pi-images", label: "Imagens" },
  { value: "pi pi-inbox", label: "Caixa de Entrada" },
  { value: "pi pi-indian-rupee", label: "Rúpia Indiana" },
  { value: "pi pi-info", label: "Informação" },
  { value: "pi pi-info-circle", label: "Informação Círculo" },
  { value: "pi pi-instagram", label: "Instagram" },
  { value: "pi pi-key", label: "Chave" },
  { value: "pi pi-language", label: "Idioma" },
  { value: "pi pi-lightbulb", label: "Lâmpada / Ideia" },
  { value: "pi pi-link", label: "Link" },
  { value: "pi pi-linkedin", label: "LinkedIn" },
  { value: "pi pi-list", label: "Lista" },
  { value: "pi pi-list-check", label: "Lista Verificação" },
  { value: "pi pi-lock", label: "Cadeado / Bloqueado" },
  { value: "pi pi-lock-open", label: "Desbloqueado" },
  { value: "pi pi-map", label: "Mapa" },
  { value: "pi pi-map-marker", label: "Localização" },
  { value: "pi pi-mars", label: "Masculino" },
  { value: "pi pi-megaphone", label: "Megafone" },
  { value: "pi pi-microchip", label: "Microchip" },
  { value: "pi pi-microchip-ai", label: "Inteligência Artificial" },
  { value: "pi pi-microphone", label: "Microfone" },
  { value: "pi pi-microsoft", label: "Microsoft" },
  { value: "pi pi-minus", label: "Menos" },
  { value: "pi pi-minus-circle", label: "Menos Círculo" },
  { value: "pi pi-mobile", label: "Celular" },
  { value: "pi pi-money-bill", label: "Dinheiro" },
  { value: "pi pi-moon", label: "Lua / Modo Escuro" },
  { value: "pi pi-objects-column", label: "Objetos Coluna" },
  { value: "pi pi-palette", label: "Paleta de Cores" },
  { value: "pi pi-paperclip", label: "Clipe / Anexo" },
  { value: "pi pi-pause", label: "Pausar" },
  { value: "pi pi-pause-circle", label: "Pausar Círculo" },
  { value: "pi pi-paypal", label: "PayPal" },
  { value: "pi pi-pencil", label: "Lápis / Editar" },
  { value: "pi pi-pen-to-square", label: "Caneta / Editar" },
  { value: "pi pi-percentage", label: "Porcentagem" },
  { value: "pi pi-phone", label: "Telefone" },
  { value: "pi pi-pinterest", label: "Pinterest" },
  { value: "pi pi-play", label: "Reproduzir" },
  { value: "pi pi-play-circle", label: "Reproduzir Círculo" },
  { value: "pi pi-plus", label: "Mais / Adicionar" },
  { value: "pi pi-plus-circle", label: "Mais Círculo" },
  { value: "pi pi-pound", label: "Libra" },
  { value: "pi pi-power-off", label: "Desligar" },
  { value: "pi pi-prime", label: "Prime" },
  { value: "pi pi-print", label: "Imprimir" },
  { value: "pi pi-qrcode", label: "QR Code" },
  { value: "pi pi-question", label: "Interrogação" },
  { value: "pi pi-question-circle", label: "Dúvida / Ajuda" },
  { value: "pi pi-receipt", label: "Recibo / Nota Fiscal" },
  { value: "pi pi-reddit", label: "Reddit" },
  { value: "pi pi-refresh", label: "Atualizar" },
  { value: "pi pi-replay", label: "Repetir" },
  { value: "pi pi-reply", label: "Responder" },
  { value: "pi pi-save", label: "Salvar" },
  { value: "pi pi-search", label: "Pesquisar / Lupa" },
  { value: "pi pi-search-minus", label: "Diminuir Zoom" },
  { value: "pi pi-search-plus", label: "Aumentar Zoom" },
  { value: "pi pi-send", label: "Enviar" },
  { value: "pi pi-server", label: "Servidor" },
  { value: "pi pi-share-alt", label: "Compartilhar" },
  { value: "pi pi-shield", label: "Escudo / Segurança" },
  { value: "pi pi-shop", label: "Loja" },
  { value: "pi pi-shopping-bag", label: "Sacola de Compras" },
  { value: "pi pi-shopping-cart", label: "Carrinho de Compras" },
  { value: "pi pi-sign-in", label: "Entrar" },
  { value: "pi pi-sign-out", label: "Sair" },
  { value: "pi pi-sitemap", label: "Organograma" },
  { value: "pi pi-slack", label: "Slack" },
  { value: "pi pi-sliders-h", label: "Controles Horizontal" },
  { value: "pi pi-sliders-v", label: "Controles Vertical" },
  { value: "pi pi-sort", label: "Ordenar" },
  { value: "pi pi-sort-alpha-alt-down", label: "Ordem Alfa Z-A" },
  { value: "pi pi-sort-alpha-alt-up", label: "Ordem Alfa A-Z Alt" },
  { value: "pi pi-sort-alpha-down", label: "Ordem Alfa A-Z" },
  { value: "pi pi-sort-alpha-up", label: "Ordem Alfa Z-A Alt" },
  { value: "pi pi-sort-alt", label: "Ordenar Alt" },
  { value: "pi pi-sort-alt-slash", label: "Sem Ordem" },
  { value: "pi pi-sort-amount-down", label: "Ordem Decrescente" },
  { value: "pi pi-sort-amount-down-alt", label: "Ordem Decrescente Alt" },
  { value: "pi pi-sort-amount-up", label: "Ordem Crescente" },
  { value: "pi pi-sort-amount-up-alt", label: "Ordem Crescente Alt" },
  { value: "pi pi-sort-down", label: "Ordenar Baixo" },
  { value: "pi pi-sort-down-fill", label: "Ordenar Baixo Cheio" },
  { value: "pi pi-sort-numeric-alt-down", label: "Ordem Num 9-1" },
  { value: "pi pi-sort-numeric-alt-up", label: "Ordem Num 1-9 Alt" },
  { value: "pi pi-sort-numeric-down", label: "Ordem Num 1-9" },
  { value: "pi pi-sort-numeric-up", label: "Ordem Num 9-1 Alt" },
  { value: "pi pi-sort-up", label: "Ordenar Cima" },
  { value: "pi pi-sort-up-fill", label: "Ordenar Cima Cheio" },
  { value: "pi pi-sparkles", label: "Brilhos / Novidade" },
  { value: "pi pi-spinner", label: "Carregando" },
  { value: "pi pi-spinner-dotted", label: "Carregando Pontilhado" },
  { value: "pi pi-star", label: "Estrela" },
  { value: "pi pi-star-fill", label: "Estrela Preenchida" },
  { value: "pi pi-star-half", label: "Meia Estrela" },
  { value: "pi pi-star-half-fill", label: "Meia Estrela Preenchida" },
  { value: "pi pi-step-backward", label: "Passo Anterior" },
  { value: "pi pi-step-backward-alt", label: "Passo Anterior Alt" },
  { value: "pi pi-step-forward", label: "Próximo Passo" },
  { value: "pi pi-step-forward-alt", label: "Próximo Passo Alt" },
  { value: "pi pi-stop", label: "Parar" },
  { value: "pi pi-stop-circle", label: "Parar Círculo" },
  { value: "pi pi-stopwatch", label: "Cronômetro" },
  { value: "pi pi-sun", label: "Sol / Modo Claro" },
  { value: "pi pi-sync", label: "Sincronizar" },
  { value: "pi pi-table", label: "Tabela" },
  { value: "pi pi-tablet", label: "Tablet" },
  { value: "pi pi-tag", label: "Etiqueta" },
  { value: "pi pi-tags", label: "Etiquetas" },
  { value: "pi pi-telegram", label: "Telegram" },
  { value: "pi pi-th-large", label: "Grade Grande" },
  { value: "pi pi-thumbs-down", label: "Reprovado" },
  { value: "pi pi-thumbs-down-fill", label: "Reprovado Cheio" },
  { value: "pi pi-thumbs-up", label: "Aprovado" },
  { value: "pi pi-thumbs-up-fill", label: "Aprovado Cheio" },
  { value: "pi pi-thumbtack", label: "Tachinha / Fixar" },
  { value: "pi pi-ticket", label: "Ticket / Chamado" },
  { value: "pi pi-tiktok", label: "TikTok" },
  { value: "pi pi-times", label: "Fechar / X" },
  { value: "pi pi-times-circle", label: "Fechar Círculo" },
  { value: "pi pi-trash", label: "Lixeira / Excluir" },
  { value: "pi pi-trophy", label: "Troféu" },
  { value: "pi pi-truck", label: "Caminhão / Entrega" },
  { value: "pi pi-turkish-lira", label: "Lira Turca" },
  { value: "pi pi-twitch", label: "Twitch" },
  { value: "pi pi-twitter", label: "Twitter" },
  { value: "pi pi-undo", label: "Desfazer" },
  { value: "pi pi-unlock", label: "Desbloquear" },
  { value: "pi pi-upload", label: "Upload / Enviar" },
  { value: "pi pi-user", label: "Usuário" },
  { value: "pi pi-user-edit", label: "Editar Usuário" },
  { value: "pi pi-user-minus", label: "Remover Usuário" },
  { value: "pi pi-user-plus", label: "Adicionar Usuário" },
  { value: "pi pi-users", label: "Usuários / Grupo" },
  { value: "pi pi-venus", label: "Feminino" },
  { value: "pi pi-verified", label: "Verificado / Selo" },
  { value: "pi pi-video", label: "Vídeo" },
  { value: "pi pi-vimeo", label: "Vimeo" },
  { value: "pi pi-volume-down", label: "Volume Baixo" },
  { value: "pi pi-volume-off", label: "Sem Som" },
  { value: "pi pi-volume-up", label: "Volume Alto" },
  { value: "pi pi-wallet", label: "Carteira / Financeiro" },
  { value: "pi pi-warehouse", label: "Armazém / Depósito" },
  { value: "pi pi-wave-pulse", label: "Pulso / Saúde" },
  { value: "pi pi-whatsapp", label: "WhatsApp" },
  { value: "pi pi-wifi", label: "Wi-Fi / Rede" },
  { value: "pi pi-window-maximize", label: "Maximizar Janela" },
  { value: "pi pi-window-minimize", label: "Minimizar Janela" },
  { value: "pi pi-wrench", label: "Chave Inglesa / Manutenção" },
  { value: "pi pi-youtube", label: "Youtube" }
]

const acoesDecisao = [
  { value: "avancar", label: "Ir para etapa" },
  { value: "atribuir_avancar", label: "Atribuir responsável e avançar" },
  { value: "voltar_solicitante", label: "Voltar para solicitante" },
  { value: "abrir_solicitacao", label: "Abrir ticket vinculada" },
  { value: "finalizar", label: "Finalizar fluxo" },
  { value: "cancelar", label: "Cancelar fluxo" },
  { value: "resolver", label: "Resolver fluxo" }
]

const tiposCampo = [
  { value: "texto", label: "Texto" },
  { value: "textarea", label: "Texto Longo" },
  { value: "numero", label: "Número" },
  { value: "data", label: "Data" },
  { value: "selecao", label: "Seleção" },
  { value: "checkbox", label: "Checkbox" },
  { value: "arquivo", label: "Arquivo" }
]

const camposPredefinidosList = ref([])
const dialogCamposPredefinidos = ref(false)
const etapaParaCamposPredefinidos = ref(null)

function obterLabelIcone(value) {
  return iconesPredefinidos.find((i) => i.value === value)?.label || value
}

function toggleColorPicker(index) {
  colorPickerAberto.value = colorPickerAberto.value === index ? null : index
}

function selecionarCor(index, cor) {
  etapas.value[index].cor = cor
  colorPickerAberto.value = null
}

function selecionarCorDecisao(etapaIndex, decisaoIndex, cor) {
  etapas.value[etapaIndex].decisoes[decisaoIndex].cor = cor
  decisaoColorPicker.value = null
}

function toggleDecisaoColorPicker(etapaIndex, decisaoIndex) {
  const key = `${etapaIndex}-${decisaoIndex}`
  decisaoColorPicker.value = decisaoColorPicker.value === key ? null : key
}

/**
 * Move uma etapa de uma posição para outra no array.
 */
function moverEtapa(deIndex, paraIndex) {
  if (isNaN(paraIndex)) return
  if (paraIndex < 0) paraIndex = 0
  if (paraIndex >= etapas.value.length) paraIndex = etapas.value.length - 1
  if (paraIndex === deIndex) return
  const lista = [...etapas.value]
  const [item] = lista.splice(deIndex, 1)
  lista.splice(paraIndex, 0, item)
  // Renumera a ordem de todas as etapas
  lista.forEach((e, i) => {
    e.ordem = i + 1
  })
  etapas.value = lista
}

/**
 * Opções de destino para uma decisão (todas as etapas exceto a própria).
 */
function opcoesDestinoDecisao(etapaIndex, acao) {
  const opcoes = etapas.value
    .map((e, i) => ({
      label: `${i + 1}. ${e.nome || "Etapa " + (i + 1)} (${e.departamento || "?"})`,
      value: i
    }))
    .filter((_, i) => acao === "voltar_solicitante" || i !== etapaIndex)

  if (acao !== "voltar_solicitante") {
    opcoes.push({ label: "🏁 Finalizar fluxo", value: -1 })
  }
  return opcoes
}

/**
 * Obtém o nome legível do destino de uma decisão para visualização.
 */
function obterNomeDestinoDecisao(decisao, etapaIndex) {
  if (
    decisao.acao === "finalizar" ||
    decisao.acao === "cancelar" ||
    decisao.acao === "resolver"
  ) {
    if (decisao.acao === "finalizar") return "Fim"
    if (decisao.acao === "cancelar") return "Cancelar"
    return "Resolvido"
  }
  if (decisao.acao === "voltar_solicitante") {
    if (
      decisao.etapa_destino_index !== undefined &&
      decisao.etapa_destino_index !== null &&
      decisao.etapa_destino_index >= 0
    ) {
      const etapaRet = etapas.value[decisao.etapa_destino_index]
      return etapaRet
        ? `Solicitante → ${etapaRet.nome || "Etapa " + (decisao.etapa_destino_index + 1)}`
        : "Solicitante"
    }
    return "Solicitante → 1ª etapa"
  }
  if (decisao.acao === "abrir_solicitacao") {
    const assuntoSel = todosAssuntos.value.find(
      (a) => a.value === decisao.abrir_solicitacao_assunto_id
    )
    const nomeAssunto = assuntoSel ? assuntoSel.label : "?"
    if (
      decisao.etapa_destino_index !== undefined &&
      decisao.etapa_destino_index !== null &&
      decisao.etapa_destino_index >= 0
    ) {
      const etapaDest = etapas.value[decisao.etapa_destino_index]
      return `Abrir → ${nomeAssunto} + ${etapaDest?.nome || "?"}`
    }
    return `Abrir → ${nomeAssunto}`
  }
  if (
    decisao.etapa_destino_index !== undefined &&
    decisao.etapa_destino_index !== null &&
    decisao.etapa_destino_index >= 0
  ) {
    const etapaDestino = etapas.value[decisao.etapa_destino_index]
    return etapaDestino
      ? etapaDestino.nome || `Etapa ${decisao.etapa_destino_index + 1}`
      : "?"
  }
  // Fallback: próxima etapa
  const proxIndex = etapaIndex + 1
  if (proxIndex < etapas.value.length) {
    return etapas.value[proxIndex].nome || `Etapa ${proxIndex + 1}`
  }
  return "Fim"
}

// ─── FUNÇÕES DE CAMPOS DA ETAPA ──────────────────────────────

function adicionarCampo(etapaIndex) {
  if (!etapas.value[etapaIndex].campos) {
    etapas.value[etapaIndex].campos = []
  }
  etapas.value[etapaIndex].campos.push({
    id: null,
    label: "",
    tipo: "texto",
    placeholder: "",
    opcoes: [],
    obrigatorio: "N",
    ordem: etapas.value[etapaIndex].campos.length,
    predefinido: "N",
    campo_predefinido_key: null,
    decisao_index: null
  })
}

function removerCampo(etapaIndex, campoIndex) {
  etapas.value[etapaIndex].campos.splice(campoIndex, 1)
}

function limitarOrdemCampo(event, max) {
  const val = parseInt(event.target.value)
  if (val > max) event.target.value = max
  if (val < 1 && event.target.value !== "") event.target.value = 1
}

function reordenarCampo(etapaIndex, campoIndex, novaPosicao) {
  const campos = etapas.value[etapaIndex].campos
  const pos = Math.max(1, Math.min(campos.length, novaPosicao)) - 1
  if (pos === campoIndex) return
  const [campo] = campos.splice(campoIndex, 1)
  campos.splice(pos, 0, campo)
  campos.forEach((c, i) => (c.ordem = i))
}

async function abrirCamposPredefinidos(etapaIndex) {
  etapaParaCamposPredefinidos.value = etapaIndex
  if (camposPredefinidosList.value.length === 0) {
    try {
      const { data } = await axios.get(
        "/solicitacoes/configuracoes/campos-predefinidos-fluxo"
      )
      camposPredefinidosList.value = data
    } catch (e) {
      console.warn("Erro ao carregar campos predefinidos:", e)
    }
  }
  dialogCamposPredefinidos.value = true
}

function adicionarCampoPredefinido(campoPre) {
  const etapaIndex = etapaParaCamposPredefinidos.value
  if (etapaIndex === null) return

  if (!etapas.value[etapaIndex].campos) {
    etapas.value[etapaIndex].campos = []
  }

  // Verificar se já existe
  const jaExiste = etapas.value[etapaIndex].campos.some(
    (c) => c.campo_predefinido_key === campoPre.key
  )
  if (jaExiste) return

  etapas.value[etapaIndex].campos.push({
    id: null,
    label: campoPre.label,
    tipo: campoPre.tipo,
    placeholder: campoPre.placeholder || "",
    opcoes: [],
    obrigatorio: campoPre.obrigatorio || "N",
    ordem: etapas.value[etapaIndex].campos.length,
    predefinido: "S",
    campo_predefinido_key: campoPre.key
  })
}

function isCampoPredefinidoNaEtapa(key) {
  const etapaIndex = etapaParaCamposPredefinidos.value
  if (etapaIndex === null) return false
  return (etapas.value[etapaIndex].campos || []).some(
    (c) => c.campo_predefinido_key === key
  )
}

// ─── CRUD ─────────────────────────────────────────────────────

async function carregarFluxo() {
  if (!props.assunto?.id) {
    etapas.value = []
    return
  }
  loading.value = true
  // Carregar etapas de andamento do assunto principal
  carregarEtapasAndamentoAssunto(props.assunto.id)
  try {
    const { data } = await axios.get(
      `/solicitacoes/configuracoes/fluxo/${props.assunto.id}`
    )
    if (data) {
      fluxoExistente.value = true
      nomeFluxo.value = data.nome || "Fluxo"
      descricaoFluxo.value = data.descricao || ""

      // Mapear etapas e decisões
      const etapasData = (data.etapas || []).filter((e) => e.ativo === "S")
      // Criar mapa de IDs reais para índices
      const idParaIndex = {}
      etapasData.forEach((e, i) => {
        idParaIndex[e.id] = i
      })

      etapas.value = etapasData.map((e, i) => ({
        ...e,
        _key: e.id || `new_${i}`,
        _novoPermitido: null,
        _novoResponsavel: null,
        _responsavel_tipo:
          e.responsavel_padrao && (e.responsaveis_permitidos || []).length > 0
            ? "exclusivo"
            : "padrao",
        _nomeResponsavel: (() => {
          if (!e.responsavel_padrao) return null
          const nFull =
            (e.responsaveis_permitidos || []).find(
              (r) => r.matricula == e.responsavel_padrao
            )?.nome_funcionario ||
            (e.responsaveis_permitidos || []).find(
              (r) => r.matricula == e.responsavel_padrao
            )?.funcionario?.nome ||
            e.nome_responsavel_padrao ||
            null
          if (!nFull) return null
          const p = nFull.trim().split(/\s+/)
          return p.length > 1 ? `${p[0]} ${p[p.length - 1]}` : p[0]
        })(),
        _respKey: 0,
        responsaveis_permitidos: (e.responsaveis_permitidos || []).map((r) => ({
          matricula: r.matricula,
          nome:
            r.nome_funcionario ||
            r.funcionario?.nome ||
            `Matrícula ${r.matricula}`
        })),
        decisoes: (e.decisoes || []).map((d) => ({
          ...d,
          etapa_destino_index: d.etapa_destino_id
            ? (idParaIndex[d.etapa_destino_id] ?? -1)
            : -1
        })),
        campos: (e.campos || []).map((c) => {
          const decisaoIdx = c.decisao_id
            ? (e.decisoes?.findIndex((d) => d.id === c.decisao_id) ?? null)
            : null
          return {
            ...c,
            opcoes: c.opcoes || [],
            decisao_index: decisaoIdx !== -1 ? decisaoIdx : null
          }
        })
      }))

      // Carregar assuntos dos departamentos das etapas existentes
      const deptos = [
        ...new Set(etapasData.map((e) => e.departamento).filter(Boolean))
      ]
      deptos.forEach((d) => carregarAssuntosDepto(d))

      // Carregar etapas de andamento dos assuntos usados nas etapas
      const assuntosIds = [
        ...new Set(etapasData.map((e) => e.assunto_id).filter(Boolean))
      ]
      assuntosIds.forEach((id) => carregarEtapasAndamentoAssunto(id))
    } else {
      fluxoExistente.value = false
      nomeFluxo.value = "Fluxo"
      descricaoFluxo.value = ""
      etapas.value = []
    }
  } catch (error) {
    console.error("Erro ao carregar fluxo:", error)
    toastError("Erro ao carregar fluxo")
    etapas.value = []
  } finally {
    loading.value = false
  }
}

function adicionarEtapa() {
  const depto = props.assunto?.departamento || undefined
  etapas.value.push({
    id: null,
    _key: `new_${Date.now()}`,
    nome: undefined,
    descricao: undefined,
    departamento: depto,
    assunto_id: null,
    etapa_andamento_id: null,
    manter_responsavel: "N",
    responsavel_padrao: null,
    prazo_horas: null,
    instrucoes: null,
    cor: coresPredefinidas[etapas.value.length % coresPredefinidas.length],
    icone: "pi pi-circle",
    ordem: etapas.value.length,
    decisoes: [],
    campos: [],
    responsaveis_permitidos: [],
    permitir_responsavel_externo: "N",
    permitir_solicitante_avancar: "N",
    exibir_campos_assunto: "N",
    _novoPermitido: null,
    _novoResponsavel: null,
    _responsavel_tipo: "padrao",
    _nomeResponsavel: null
  })
  if (depto) carregarAssuntosDepto(depto)
}

function removerEtapa(index) {
  etapas.value.splice(index, 1)
}

function adicionarResponsavelPermitido(etapaIndex, funcionario) {
  if (!funcionario || !funcionario.matricula) return
  const etapa = etapas.value[etapaIndex]
  if (!etapa.responsaveis_permitidos) etapa.responsaveis_permitidos = []

  const jaExiste = etapa.responsaveis_permitidos.some(
    (r) => r.matricula == funcionario.matricula
  )
  if (jaExiste) {
    toastError("Este funcionário já está na lista")
    etapa._novoPermitido = null
    return
  }

  etapa.responsaveis_permitidos.push({
    matricula: funcionario.matricula,
    nome: funcionario.nome || `Matrícula ${funcionario.matricula}`
  })
  etapa._novoPermitido = null
}

function definirResponsavelEtapa(etapaIndex, funcionario) {
  if (!funcionario || !funcionario.matricula) return
  const etapa = etapas.value[etapaIndex]
  const nomeCompleto = funcionario.nome || `Matrícula ${funcionario.matricula}`
  const partes = nomeCompleto.trim().split(/\s+/)
  const nome =
    partes.length > 1 ? `${partes[0]} ${partes[partes.length - 1]}` : partes[0]
  etapa.responsavel_padrao = funcionario.matricula
  etapa._nomeResponsavel = nome
  etapa.responsaveis_permitidos = [{ matricula: funcionario.matricula, nome }]
  etapa._respKey = (etapa._respKey || 0) + 1
}

function limparResponsavelEtapa(etapaIndex) {
  const etapa = etapas.value[etapaIndex]
  etapa.responsavel_padrao = null
  etapa._nomeResponsavel = null
  etapa.responsaveis_permitidos = []
}

function toggleManterResponsavelTodas(checked) {
  const valor = checked ? "S" : "N"
  etapas.value.forEach((e) => (e.manter_responsavel = valor))
}

function adicionarDecisao(etapaIndex) {
  if (!etapas.value[etapaIndex].decisoes) {
    etapas.value[etapaIndex].decisoes = []
  }
  etapas.value[etapaIndex].decisoes.push({
    id: null,
    label: "",
    cor: "#10B981",
    icone: null,
    etapa_destino_index:
      etapaIndex + 1 < etapas.value.length ? etapaIndex + 1 : -1,
    acao: "avancar",
    etapa_andamento_id: null,
    abrir_solicitacao_assunto_id: null,
    ordem: etapas.value[etapaIndex].decisoes.length
  })
}

function removerDecisao(etapaIndex, decisaoIndex) {
  etapas.value[etapaIndex].decisoes.splice(decisaoIndex, 1)
}

function moverDecisao(etapaIndex, decisaoIndex, direcao) {
  const decisoes = etapas.value[etapaIndex].decisoes
  const novoIndex = decisaoIndex + direcao
  if (novoIndex < 0 || novoIndex >= decisoes.length) return
  const temp = decisoes[decisaoIndex]
  decisoes[decisaoIndex] = decisoes[novoIndex]
  decisoes[novoIndex] = temp
}

function reordenarDecisao(etapaIndex, decisaoIndexAtual, novoNumero) {
  const decisoes = etapas.value[etapaIndex].decisoes
  let novoIndex = parseInt(novoNumero) - 1
  if (isNaN(novoIndex) || novoIndex === decisaoIndexAtual) return
  if (novoIndex < 0) novoIndex = 0
  if (novoIndex >= decisoes.length) novoIndex = decisoes.length - 1
  const [item] = decisoes.splice(decisaoIndexAtual, 1)
  decisoes.splice(novoIndex, 0, item)
}

function somenteNumeros(event) {
  if (event.key < "0" || event.key > "9") event.preventDefault()
}

async function salvarFluxo() {
  // Validações
  if (!nomeFluxo.value?.trim()) {
    toastError("Informe o nome do fluxo")
    return
  }
  const etapasSemNome = etapas.value.filter((e) => !e.nome?.trim())
  if (etapasSemNome.length > 0) {
    toastError("Todas as etapas devem ter um nome")
    return
  }
  const etapasSemDepto = etapas.value.filter((e) => !e.departamento?.trim())
  if (etapasSemDepto.length > 0) {
    toastError("Todas as etapas devem ter um departamento")
    return
  }
  const decisoesSemLabel = etapas.value.some((e) =>
    e.decisoes?.some((d) => !d.label?.trim())
  )
  if (decisoesSemLabel) {
    toastError("Todas as decisões devem ter um rótulo")
    return
  }
  const camposSemLabel = etapas.value.some((e) =>
    e.campos?.some((c) => !c.label?.trim())
  )
  if (camposSemLabel) {
    toastError("Todos os campos devem ter um rótulo")
    return
  }
  const abrirSemAssunto = etapas.value.some((e) =>
    e.decisoes?.some(
      (d) => d.acao === "abrir_solicitacao" && !d.abrir_solicitacao_assunto_id
    )
  )
  if (abrirSemAssunto) {
    toastError(
      'Decisões do tipo "Abrir ticket vinculada" devem ter um assunto selecionado'
    )
    return
  }

  loading.value = true
  try {
    // Montar payload — resolver índices de destino para IDs reais
    const etapasPayload = etapas.value.map((e, index) => ({
      id: e.id,
      nome: e.nome,
      descricao: e.descricao,
      departamento: e.departamento,
      assunto_id: e.assunto_id,
      etapa_andamento_id: e.etapa_andamento_id || null,
      manter_responsavel: e.manter_responsavel || "N",
      responsavel_padrao: e.responsavel_padrao || null,
      permitir_responsavel_externo: e.permitir_responsavel_externo || "N",
      permitir_solicitante_avancar: e.permitir_solicitante_avancar || "N",
      exibir_campos_assunto: e.exibir_campos_assunto || "N",
      prazo_horas: e.prazo_horas || null,
      instrucoes: e.instrucoes || null,
      cor: e.cor,
      icone: e.icone,
      ordem: index,
      decisoes: (e.decisoes || []).map((d, dIndex) => {
        let etapaDestinoId = null
        let acao = d.acao || "avancar"

        if (
          d.etapa_destino_index === -1 ||
          acao === "finalizar" ||
          acao === "cancelar" ||
          acao === "resolver"
        ) {
          etapaDestinoId = null
          if (d.etapa_destino_index === -1 && acao === "avancar") {
            acao = "finalizar"
          }
        } else if (
          d.etapa_destino_index !== undefined &&
          d.etapa_destino_index !== null
        ) {
          const etapaDestino = etapas.value[d.etapa_destino_index]
          etapaDestinoId = etapaDestino?.id || d.etapa_destino_index
        }

        return {
          id: d.id,
          label: d.label,
          cor: d.cor,
          icone: d.icone,
          etapa_destino_id: etapaDestinoId,
          acao: acao,
          etapa_andamento_id: d.etapa_andamento_id || null,
          abrir_solicitacao_assunto_id:
            acao === "abrir_solicitacao"
              ? d.abrir_solicitacao_assunto_id || null
              : null,
          ordem: dIndex
        }
      }),
      campos: (e.campos || []).map((c, cIndex) => ({
        id: c.id,
        label: c.label,
        tipo: c.tipo || "texto",
        placeholder: c.placeholder || null,
        opcoes: (c.opcoes || []).filter((o) => o && o.trim()),
        obrigatorio: c.obrigatorio || "N",
        ordem: cIndex,
        predefinido: c.predefinido || "N",
        campo_predefinido_key: c.campo_predefinido_key || null,
        decisao_index: c.decisao_index ?? null
      })),
      responsaveis_permitidos: (e.responsaveis_permitidos || []).map((r) => ({
        matricula: r.matricula
      }))
    }))

    const { data } = await axios.post(
      "/solicitacoes/configuracoes/salvar-fluxo",
      {
        assunto_id: props.assunto.id,
        nome: nomeFluxo.value,
        descricao: descricaoFluxo.value,
        etapas: etapasPayload
      }
    )

    if (data.success) {
      toastSuccess(data.message)
      emit("fluxoSalvo", data.fluxo)
      fecharDialog()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    console.error("Erro ao salvar fluxo:", error)
    toastError(error.response?.data?.message || "Erro ao salvar fluxo")
  } finally {
    loading.value = false
  }
}

function fecharDialog() {
  dialogVisible.value = false
  colorPickerAberto.value = null
  decisaoColorPicker.value = null
}
</script>

<style scoped>
.drag-handle:active {
  cursor: grabbing;
}
</style>
