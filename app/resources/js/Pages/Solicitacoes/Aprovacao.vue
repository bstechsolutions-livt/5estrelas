<template>
  <div class="space-y-4">
    <!-- Abas de navegação modernizadas -->
    <div
      class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden"
    >
      <!-- Header com abas -->
      <div
        class="flex border-b border-gray-200 dark:border-slate-700 bg-gradient-to-r from-slate-50 to-white dark:from-slate-800 dark:to-slate-750"
      >
        <button
          @click="abaAtiva = 'nova'"
          :class="[
            'flex-1 sm:flex-none px-4 sm:px-6 py-3.5 text-sm font-semibold transition-all duration-200 flex items-center justify-center gap-2 relative',
            abaAtiva === 'nova'
              ? 'text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-800'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50'
          ]"
        >
          <span
            class="inline-flex items-center justify-center w-7 h-7 rounded-lg transition-all duration-200"
            :class="
              abaAtiva === 'nova'
                ? 'bg-blue-100 dark:bg-blue-900/30'
                : 'bg-gray-100 dark:bg-slate-700'
            "
          >
            <i
              class="pi pi-plus text-xs"
              :class="
                abaAtiva === 'nova'
                  ? 'text-blue-600 dark:text-blue-400'
                  : 'text-gray-500 dark:text-gray-400'
              "
            ></i>
          </span>
          <span class="hidden sm:inline">Nova Aprovação</span>
          <span class="sm:hidden">Nova</span>
          <!-- Indicador ativo -->
          <span
            v-if="abaAtiva === 'nova'"
            class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-blue-500 to-indigo-500"
          ></span>
        </button>

        <button
          v-if="aprovacoes.length > 0"
          @click="abaAtiva = 'historico'"
          :class="[
            'flex-1 sm:flex-none px-4 sm:px-6 py-3.5 text-sm font-semibold transition-all duration-200 flex items-center justify-center gap-2 relative',
            abaAtiva === 'historico'
              ? 'text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-800'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50'
          ]"
        >
          <span
            class="inline-flex items-center justify-center w-7 h-7 rounded-lg transition-all duration-200"
            :class="
              abaAtiva === 'historico'
                ? 'bg-blue-100 dark:bg-blue-900/30'
                : 'bg-gray-100 dark:bg-slate-700'
            "
          >
            <i
              class="pi pi-history text-xs"
              :class="
                abaAtiva === 'historico'
                  ? 'text-blue-600 dark:text-blue-400'
                  : 'text-gray-500 dark:text-gray-400'
              "
            ></i>
          </span>
          <span class="hidden sm:inline">Histórico</span>
          <span
            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 text-xs font-bold rounded-full"
            :class="
              abaAtiva === 'historico'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-300'
            "
          >
            {{ aprovacoes.length }}
          </span>
          <!-- Indicador ativo -->
          <span
            v-if="abaAtiva === 'historico'"
            class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-blue-500 to-indigo-500"
          ></span>
        </button>
      </div>

      <!-- Conteúdo da aba Nova Aprovação -->
      <div
        v-if="abaAtiva === 'nova'"
        class="p-4 sm:p-6"
      >
        <!-- Header da seção -->
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20"
          >
            <i class="pi pi-user-plus text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">
              Solicitar Aprovação
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Selecione um aprovador para esta solicitação
            </p>
          </div>
        </div>

        <div class="space-y-5">
          <!-- Seleção do aprovador -->
          <div class="space-y-2">
            <label
              class="flex items-center gap-1 text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              <span
                class="inline-flex items-center justify-center w-6 h-6 rounded-lg"
              >
                <i class="pi pi-user text-blue-600 text-xs"></i>
              </span>
              Aprovador
              <span class="text-red-500">*</span>
            </label>
            <Funcionario
              v-model="novaAprovacao.aprovador"
              :retornaObjeto="true"
              placeholder="Buscar funcionário para aprovação..."
            />
          </div>

          <!-- Observações -->
          <div class="space-y-2">
            <label
              class="flex items-center gap-1 text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              <span
                class="inline-flex items-center justify-center w-6 h-6 rounded-lg"
              >
                <i class="pi pi-file-edit text-yellow-600 text-xs"></i>
              </span>
              Observações
            </label>
            <Textarea
              v-model="novaAprovacao.observacoes"
              rows="3"
              autoResize
              placeholder="Observações sobre a aprovação (opcional)..."
              class="w-full !rounded-xl !border-gray-300 dark:!border-slate-600 focus:!border-blue-500 focus:!ring-blue-500"
            />
          </div>

          <!-- Botões -->
          <div
            class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-100 dark:border-slate-700"
          >
            <Button
              @click="solicitarAprovacao"
              :loading="loading"
              :disabled="!podeEnviarAprovacao"
              label="Solicitar Aprovação"
              outlined
              icon="pi pi-send"
              class="flex-1 sm:flex-none !rounded-xl"
            />
            <Button
              @click="limparFormulario"
              label="Limpar"
              icon="pi pi-eraser"
              severity="secondary"
              outlined
              class="!rounded-xl"
            />
          </div>
        </div>
      </div>

      <!-- Conteúdo da aba Histórico -->
      <div
        v-else-if="abaAtiva === 'historico'"
        class="p-4 sm:p-6"
      >
        <!-- Header da seção -->
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/20"
            >
              <i class="pi pi-history text-white"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                Histórico de Aprovações
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ aprovacoes.length }} aprovações solicitadas
              </p>
            </div>
          </div>

          <!-- Stats mini -->
          <div class="hidden sm:flex items-center gap-2">
            <span
              v-if="aprovacoesStats.pendentes > 0"
              class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded-lg text-xs font-medium"
            >
              <span
                class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"
              ></span>
              {{ aprovacoesStats.pendentes }} pendente{{
                aprovacoesStats.pendentes > 1 ? "s" : ""
              }}
            </span>
            <span
              v-if="aprovacoesStats.aprovadas > 0"
              class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-lg text-xs font-medium"
            >
              <i class="pi pi-check !text-[10px]"></i>
              {{ aprovacoesStats.aprovadas }}
            </span>
            <span
              v-if="aprovacoesStats.rejeitadas > 0"
              class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-xs font-medium"
            >
              <i class="pi pi-times !text-[10px]"></i>
              {{ aprovacoesStats.rejeitadas }}
            </span>
          </div>
        </div>

        <!-- Loading -->
        <div
          v-if="loading"
          class="flex flex-col items-center justify-center py-12"
        >
          <div class="relative">
            <div
              class="w-12 h-12 rounded-full border-4 border-blue-100 dark:border-slate-700"
            ></div>
            <div
              class="absolute top-0 left-0 w-12 h-12 rounded-full border-4 border-transparent border-t-blue-500 animate-spin"
            ></div>
          </div>
          <span class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Carregando aprovações...
          </span>
        </div>

        <!-- Empty state -->
        <div
          v-else-if="aprovacoes.length === 0"
          class="flex flex-col items-center justify-center py-12 text-center"
        >
          <div
            class="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-4"
          >
            <i
              class="pi pi-inbox text-2xl text-gray-400 dark:text-gray-500"
            ></i>
          </div>
          <h4 class="text-gray-700 dark:text-gray-300 font-medium mb-1">
            Nenhuma aprovação
          </h4>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Ainda não há aprovações solicitadas
          </p>
        </div>

        <!-- Lista de aprovações -->
        <div
          v-else
          class="space-y-3 max-h-[400px] sm:max-h-96 overflow-auto pr-1"
        >
          <div
            v-for="aprovacao in aprovacoes"
            :key="aprovacao.id"
            class="group relative bg-white dark:bg-slate-800 border rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300"
            :class="{
              'border-yellow-200 dark:border-yellow-700/50':
                aprovacao.status === 'pendente',
              'border-green-200 dark:border-green-700/50':
                aprovacao.status === 'aprovada',
              'border-red-200 dark:border-red-700/50':
                aprovacao.status === 'rejeitada',
              'border-gray-200 dark:border-slate-700':
                aprovacao.status === 'cancelada'
            }"
          >
            <!-- Ribbon de Status -->
            <div
              class="absolute -top-1 -right-1 w-20 h-20 overflow-hidden z-10"
            >
              <div
                class="absolute top-5 -right-6 w-28 h-6 rotate-45 flex items-center justify-center shadow-sm text-[9px] font-bold text-white uppercase tracking-wider"
                :class="{
                  'bg-gradient-to-r from-yellow-400 to-amber-500':
                    aprovacao.status === 'pendente',
                  'bg-gradient-to-r from-green-400 to-emerald-500':
                    aprovacao.status === 'aprovada',
                  'bg-gradient-to-r from-red-400 to-rose-500':
                    aprovacao.status === 'rejeitada',
                  'bg-gradient-to-r from-gray-400 to-gray-500':
                    aprovacao.status === 'cancelada'
                }"
              >
                {{ getStatusLabel(aprovacao.status) }}
              </div>
            </div>

            <!-- Barra lateral colorida -->
            <div
              class="absolute left-0 top-0 bottom-0 w-1"
              :class="{
                'bg-gradient-to-b from-yellow-400 to-amber-500':
                  aprovacao.status === 'pendente',
                'bg-gradient-to-b from-green-400 to-emerald-500':
                  aprovacao.status === 'aprovada',
                'bg-gradient-to-b from-red-400 to-rose-500':
                  aprovacao.status === 'rejeitada',
                'bg-gradient-to-b from-gray-400 to-gray-500':
                  aprovacao.status === 'cancelada'
              }"
            ></div>

            <div class="p-4 pl-5">
              <!-- Avatar e Info do Aprovador -->
              <div class="flex items-start gap-3 pr-16">
                <!-- Avatar -->
                <div class="relative flex-shrink-0">
                  <div
                    v-if="aprovacao.aprovador?.foto_perfil"
                    class="w-12 h-12 rounded-xl overflow-hidden ring-2 ring-offset-2 transition-all shadow-lg"
                    :class="{
                      'ring-yellow-400': aprovacao.status === 'pendente',
                      'ring-green-400': aprovacao.status === 'aprovada',
                      'ring-red-400': aprovacao.status === 'rejeitada',
                      'ring-gray-300': aprovacao.status === 'cancelada'
                    }"
                    v-tooltip.top="aprovacao.aprovador?.nome"
                  >
                    <img
                      :src="aprovacao.aprovador.foto_perfil"
                      :alt="aprovacao.aprovador?.nome"
                      class="w-full h-full object-cover"
                    />
                  </div>
                  <div
                    v-else
                    class="w-12 h-12 rounded-xl flex items-center justify-center text-sm font-bold text-white ring-2 ring-offset-2 transition-all shadow-lg"
                    :class="{
                      'bg-gradient-to-br from-yellow-400 to-amber-600 ring-yellow-400':
                        aprovacao.status === 'pendente',
                      'bg-gradient-to-br from-green-400 to-emerald-600 ring-green-400':
                        aprovacao.status === 'aprovada',
                      'bg-gradient-to-br from-red-400 to-rose-600 ring-red-400':
                        aprovacao.status === 'rejeitada',
                      'bg-gradient-to-br from-gray-400 to-gray-600 ring-gray-300':
                        aprovacao.status === 'cancelada'
                    }"
                    v-tooltip.top="aprovacao.aprovador?.nome"
                  >
                    {{ obterIniciais(aprovacao.aprovador?.nome) }}
                  </div>
                  <!-- Status indicator -->
                  <span
                    class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full border-2 border-white dark:border-slate-800 flex items-center justify-center shadow-sm"
                    :class="{
                      'bg-yellow-400': aprovacao.status === 'pendente',
                      'bg-green-500': aprovacao.status === 'aprovada',
                      'bg-red-500': aprovacao.status === 'rejeitada',
                      'bg-gray-400': aprovacao.status === 'cancelada'
                    }"
                  >
                    <i
                      v-if="aprovacao.status === 'pendente'"
                      class="pi pi-clock !text-[9px] text-white"
                    ></i>
                    <i
                      v-else-if="aprovacao.status === 'aprovada'"
                      class="pi pi-check !text-[9px] text-white"
                    ></i>
                    <i
                      v-else-if="aprovacao.status === 'rejeitada'"
                      class="pi pi-times !text-[9px] text-white"
                    ></i>
                    <i
                      v-else
                      class="pi pi-ban !text-[9px] text-white"
                    ></i>
                  </span>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-2 mb-0.5">
                    <span
                      class="font-bold text-gray-900 dark:text-white truncate text-base"
                    >
                      {{
                        obterNomeSobrenome(aprovacao.aprovador?.nome) ||
                        "Aprovador não encontrado"
                      }}
                    </span>
                  </div>
                  <p
                    class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1"
                  >
                    <i class="pi pi-id-card !text-[10px]"></i>
                    Mat. {{ aprovacao.aprovador?.matricula }}
                  </p>

                  <!-- Observações -->
                  <div
                    v-if="aprovacao.observacoes"
                    class="mt-2 p-2.5 bg-slate-50 dark:bg-slate-700/50 rounded-lg border border-slate-100 dark:border-slate-600/50"
                  >
                    <p
                      class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2"
                    >
                      <span
                        class="font-semibold text-slate-500 dark:text-slate-400"
                      >
                        Obs:
                      </span>
                      {{ aprovacao.observacoes }}
                    </p>
                  </div>

                  <!-- Resposta do aprovador -->
                  <div
                    v-if="aprovacao.resposta_aprovador"
                    class="mt-2 p-2.5 rounded-lg border"
                    :class="
                      aprovacao.status === 'aprovada'
                        ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700/50'
                        : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700/50'
                    "
                  >
                    <p
                      class="text-xs line-clamp-2"
                      :class="
                        aprovacao.status === 'aprovada'
                          ? 'text-green-700 dark:text-green-300'
                          : 'text-red-700 dark:text-red-300'
                      "
                    >
                      <span class="font-semibold">
                        <i
                          :class="
                            aprovacao.status === 'aprovada'
                              ? 'pi pi-check-circle'
                              : 'pi pi-times-circle'
                          "
                          class="!text-[10px] mr-1"
                        ></i>
                        Resposta:
                      </span>
                      {{ aprovacao.resposta_aprovador }}
                    </p>
                  </div>

                  <!-- Datas -->
                  <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-[10px] text-gray-400 dark:text-gray-500"
                  >
                    <span
                      class="inline-flex items-center gap-1 bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded-full"
                    >
                      <i class="pi pi-calendar"></i>
                      {{ formatarData(aprovacao.created_at) }}
                    </span>
                    <span
                      v-if="aprovacao.data_resposta"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full"
                      :class="
                        aprovacao.status === 'aprovada'
                          ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                          : 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
                      "
                    >
                      <i class="pi pi-check-circle"></i>
                      {{ formatarData(aprovacao.data_resposta) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Ações - Movidas para baixo -->
              <div
                v-if="
                  podeEditarAprovacao(aprovacao) ||
                  podeCancelarAprovacao(aprovacao) ||
                  podeAprovar(aprovacao)
                "
                class="flex items-center justify-end gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-slate-700"
              >
                <!-- Botões para quem SOLICITOU a aprovação -->
                <Button
                  v-if="
                    aprovacao.status === 'pendente' &&
                    podeEditarAprovacao(aprovacao)
                  "
                  @click="editarAprovacao(aprovacao)"
                  icon="pi pi-pencil"
                  size="small"
                  severity="secondary"
                  outlined
                  rounded
                  v-tooltip.top="'Editar aprovação'"
                  class="!w-9 !h-9"
                />

                <Button
                  v-if="podeCancelarAprovacao(aprovacao)"
                  @click="cancelarAprovacao(aprovacao)"
                  icon="pi pi-times"
                  size="small"
                  severity="danger"
                  outlined
                  rounded
                  v-tooltip.top="'Cancelar solicitação'"
                  class="!w-9 !h-9"
                />

                <!-- Botões para quem RECEBEU a aprovação -->
                <Button
                  v-if="podeAprovar(aprovacao)"
                  @click="responderAprovacao(aprovacao, 'aprovada')"
                  icon="pi pi-check"
                  size="small"
                  severity="success"
                  rounded
                  outlined
                  v-tooltip.top="'Aprovar'"
                  class="!w-9 !h-9"
                />

                <Button
                  v-if="podeAprovar(aprovacao)"
                  @click="responderAprovacao(aprovacao, 'rejeitada')"
                  icon="pi pi-times"
                  size="small"
                  severity="danger"
                  rounded
                  outlined
                  v-tooltip.top="'Rejeitar'"
                  class="!w-9 !h-9"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Dialog para responder aprovação -->
  <Dialog
    v-model:visible="dialogResposta"
    modal
    :closable="false"
    :style="{ width: '95vw', maxWidth: '450px' }"
    :breakpoints="{ '640px': '95vw' }"
    class="!rounded-2xl overflow-hidden"
    :pt="{
      header: { class: '!hidden' },
      content: { class: '!p-0' },
      footer: { class: '!p-4 !pt-0' }
    }"
  >
    <!-- Header customizado -->
    <div
      class="relative px-5 py-4 text-center"
      :class="
        respostaAprovacao.acao === 'aprovada'
          ? 'bg-gradient-to-br from-emerald-500 to-green-600'
          : 'bg-gradient-to-br from-rose-500 to-red-600'
      "
    >
      <!-- Botão fechar customizado -->
      <button
        @click="dialogResposta = false"
        class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors"
      >
        <i class="pi pi-times text-white text-sm"></i>
      </button>

      <div class="flex justify-center mb-3">
        <div
          class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center"
        >
          <i
            :class="
              respostaAprovacao.acao === 'aprovada'
                ? 'pi pi-check-circle'
                : 'pi pi-times-circle'
            "
            class="text-white !text-xl"
          ></i>
        </div>
      </div>
      <h3 class="text-lg font-bold text-white">
        {{ tituloDialogResposta }}
      </h3>
    </div>

    <!-- Conteúdo -->
    <div class="p-5 space-y-4">
      <!-- Info do aprovador -->
      <div
        class="flex items-center gap-3 p-3 rounded-xl"
        :class="
          respostaAprovacao.acao === 'aprovada'
            ? 'bg-emerald-50 dark:bg-emerald-900/20'
            : 'bg-rose-50 dark:bg-rose-900/20'
        "
      >
        <!-- Avatar com foto ou iniciais -->
        <div
          v-if="respostaAprovacao.aprovacao?.aprovador?.foto_perfil"
          class="w-11 h-11 rounded-xl overflow-hidden ring-2 ring-offset-2"
          :class="
            respostaAprovacao.acao === 'aprovada'
              ? 'ring-emerald-400'
              : 'ring-rose-400'
          "
        >
          <img
            :src="respostaAprovacao.aprovacao.aprovador.foto_perfil"
            :alt="respostaAprovacao.aprovacao.aprovador.nome"
            class="w-full h-full object-cover"
          />
        </div>
        <div
          v-else
          class="w-11 h-11 rounded-xl flex items-center justify-center text-sm font-bold text-white shadow-lg"
          :class="
            respostaAprovacao.acao === 'aprovada'
              ? 'bg-gradient-to-br from-emerald-400 to-emerald-600'
              : 'bg-gradient-to-br from-rose-400 to-rose-600'
          "
        >
          {{ obterIniciais(respostaAprovacao.aprovacao?.aprovador?.nome) }}
        </div>
        <div>
          <p class="font-semibold text-gray-900 dark:text-white text-sm">
            {{
              obterNomeSobrenome(respostaAprovacao.aprovacao?.aprovador?.nome)
            }}
          </p>
          <p
            class="text-xs"
            :class="
              respostaAprovacao.acao === 'aprovada'
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-rose-600 dark:text-rose-400'
            "
          >
            {{
              respostaAprovacao.acao === "aprovada"
                ? "Você está aprovando esta solicitação"
                : "Você está rejeitando esta solicitação"
            }}
          </p>
        </div>
      </div>

      <!-- Campo de resposta -->
      <div class="space-y-2">
        <label
          class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300"
        >
          <i
            class="pi pi-comment text-xs"
            :class="
              respostaAprovacao.acao === 'aprovada'
                ? 'text-emerald-500'
                : 'text-rose-500'
            "
          ></i>
          Resposta
          <span
            v-if="respostaAprovacao.acao === 'rejeitada'"
            class="text-rose-500 font-bold"
          >
            *
          </span>
          <span
            v-else
            class="text-gray-400 text-xs font-normal"
          >
            (opcional)
          </span>
        </label>
        <Textarea
          v-model="respostaAprovacao.comentario"
          rows="3"
          autoResize
          :placeholder="
            respostaAprovacao.acao === 'aprovada'
              ? 'Adicione um comentário (opcional)...'
              : 'Explique o motivo da rejeição...'
          "
          class="w-full !rounded-xl !border-gray-200 dark:!border-slate-600 focus:!ring-2"
          :class="
            respostaAprovacao.acao === 'aprovada'
              ? 'focus:!border-emerald-400 focus:!ring-emerald-100'
              : 'focus:!border-rose-400 focus:!ring-rose-100'
          "
        />
        <p
          v-if="respostaAprovacao.acao === 'rejeitada'"
          class="text-xs text-rose-500 flex items-center gap-1"
        >
          <i class="pi pi-info-circle"></i>
          A resposta é obrigatória para rejeição
        </p>
      </div>
    </div>

    <template #footer>
      <div class="flex gap-2 w-full">
        <Button
          @click="dialogResposta = false"
          label="Cancelar"
          severity="secondary"
          outlined
          class="flex-1 !rounded-xl !h-11"
        />
        <Button
          @click="confirmarResposta"
          :label="labelBotaoResposta"
          :loading="loading"
          :icon="
            respostaAprovacao.acao === 'aprovada'
              ? 'pi pi-check'
              : 'pi pi-times'
          "
          class="flex-1 !rounded-xl !h-11"
          :class="
            respostaAprovacao.acao === 'aprovada'
              ? '!bg-gradient-to-r !from-emerald-500 !to-green-600 !border-0 hover:!from-emerald-600 hover:!to-green-700'
              : '!bg-gradient-to-r !from-rose-500 !to-red-600 !border-0 hover:!from-rose-600 hover:!to-red-700'
          "
        />
      </div>
    </template>
  </Dialog>

  <!-- Dialog para editar aprovação -->
  <Dialog
    v-model:visible="dialogEdicao"
    modal
    header="Editar Aprovação"
    :style="{ width: '95vw', maxWidth: '600px' }"
    :breakpoints="{ '640px': '95vw' }"
    class="!rounded-2xl"
  >
    <div class="space-y-5">
      <div class="space-y-2">
        <label
          class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300"
        >
          <span
            class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-gradient-to-br from-cyan-400 to-cyan-600 shadow-sm"
          >
            <i class="pi pi-user text-white text-xs"></i>
          </span>
          Aprovador
          <span class="text-red-500">*</span>
        </label>
        <Funcionario
          v-model="aprovacaoEdicao.aprovador"
          :retornaObjeto="true"
          placeholder="Buscar funcionário para aprovação..."
        />
      </div>

      <div class="space-y-2">
        <label
          class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300"
        >
          <span
            class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 shadow-sm"
          >
            <i class="pi pi-file-edit text-white text-xs"></i>
          </span>
          Observações
        </label>
        <Textarea
          v-model="aprovacaoEdicao.observacoes"
          rows="3"
          autoResize
          placeholder="Observações sobre a aprovação..."
          class="w-full !rounded-xl !border-gray-300 dark:!border-slate-600"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex flex-col-reverse sm:flex-row gap-2 w-full">
        <Button
          @click="dialogEdicao = false"
          label="Cancelar"
          severity="secondary"
          outlined
          class="w-full sm:w-auto !rounded-xl"
        />
        <Button
          @click="salvarEdicao"
          label="Salvar Alterações"
          icon="pi pi-save"
          :loading="loading"
          class="w-full sm:w-auto !rounded-xl"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from "vue"
import {
  toastSuccess,
  toastError,
  formatarData,
  swalConfirm
} from "@/utils/globalFunctions"
import Funcionario from "@/Components/Componentes/Funcionario.vue"
import Button from "primevue/button"
import Textarea from "primevue/textarea"
import Dialog from "primevue/dialog"

const props = defineProps({
  solicitacaoId: {
    type: [String, Number],
    required: true
  },
  auth: {
    type: Object,
    required: true
  },
  aprovacaoIdRejeitar: {
    type: [String, Number],
    default: null
  }
})

const emits = defineEmits(["atualizar", "aprovacoes-atualizadas"])

// Estados reativos
const loading = ref(false)
const aprovacoes = ref([])
const dialogResposta = ref(false)
const dialogEdicao = ref(false)
const abaAtiva = ref("nova") // Inicia sempre na aba de nova aprovação

// Watch para abrir dialog de rejeição automaticamente
watch(
  () => [props.aprovacaoIdRejeitar, aprovacoes.value],
  ([novoId, listaAprovacoes]) => {
    if (novoId && listaAprovacoes.length > 0) {
      const aprovacao = listaAprovacoes.find((a) => a.id == novoId)
      if (aprovacao && aprovacao.status === "pendente") {
        responderAprovacao(aprovacao, "rejeitada")
      }
    }
  },
  { immediate: true }
)

// Formulário para nova aprovação
const novaAprovacao = reactive({
  aprovador: null,
  observacoes: ""
})

// Formulário para resposta de aprovação
const respostaAprovacao = reactive({
  aprovacao: null,
  acao: null, // 'aprovada' ou 'rejeitada'
  comentario: ""
})

// Formulário para edição de aprovação
const aprovacaoEdicao = reactive({
  id: null,
  aprovador: null,
  observacoes: ""
})

// Computed properties
const podeEnviarAprovacao = computed(() => {
  return novaAprovacao.aprovador
})

const tituloDialogResposta = computed(() => {
  return respostaAprovacao.acao === "aprovada"
    ? "Aprovar Solicitação"
    : "Rejeitar Solicitação"
})

const labelBotaoResposta = computed(() => {
  return respostaAprovacao.acao === "aprovada" ? "Aprovar" : "Rejeitar"
})

const severityBotaoResposta = computed(() => {
  return respostaAprovacao.acao === "aprovada" ? "success" : "danger"
})

// Computed para contadores de aprovações
const aprovacoesStats = computed(() => {
  const stats = {
    pendentes: 0,
    aprovadas: 0,
    rejeitadas: 0,
    canceladas: 0,
    total: aprovacoes.value.length
  }

  aprovacoes.value.forEach((aprovacao) => {
    switch (aprovacao.status) {
      case "pendente":
        stats.pendentes++
        break
      case "aprovada":
        stats.aprovadas++
        break
      case "rejeitada":
        stats.rejeitadas++
        break
      case "cancelada":
        stats.canceladas++
        break
    }
  })

  return stats
})

// Computed para verificar se há problemas nas aprovações
const temProblemasAprovacao = computed(() => {
  return (
    aprovacoesStats.value.pendentes > 0 || aprovacoesStats.value.rejeitadas > 0
  )
})

// Computed para mensagem de alerta de aprovações
const mensagemAlerteAprovacao = computed(() => {
  const stats = aprovacoesStats.value
  const mensagens = []

  if (stats.pendentes > 0) {
    mensagens.push(
      `${stats.pendentes} aprovação${stats.pendentes > 1 ? "ões" : ""} pendente${stats.pendentes > 1 ? "s" : ""}`
    )
  }

  if (stats.rejeitadas > 0) {
    mensagens.push(
      `${stats.rejeitadas} aprovação${stats.rejeitadas > 1 ? "ões" : ""} rejeitada${stats.rejeitadas > 1 ? "s" : ""}`
    )
  }

  return mensagens.join(" e ")
})

// Métodos
onMounted(() => {
  carregarAprovacoes().then(() => {
    // Se já existem aprovações, mostrar o histórico por padrão
    if (aprovacoes.value.length > 0) {
      abaAtiva.value = "historico"
    }
  })
})

async function carregarAprovacoes() {
  try {
    loading.value = true

    const response = await axios.get(
      `/solicitacoes/aprovacoes/${props.solicitacaoId}`
    )
    aprovacoes.value = response.data

    // Emitir evento para o componente pai atualizar seus dados
    emits("aprovacoes-atualizadas", response.data)
  } catch (error) {
    console.error("Erro ao carregar aprovações:", error)
    toastError("Erro ao carregar aprovações")
  } finally {
    loading.value = false
  }
}

async function solicitarAprovacao() {
  if (!podeEnviarAprovacao.value) {
    toastError("Selecione um aprovador")
    return
  }

  try {
    loading.value = true

    const dados = {
      solicitacao_id: props.solicitacaoId,
      aprovador_matricula: novaAprovacao.aprovador.matricula,
      observacoes: novaAprovacao.observacoes
    }

    await axios.post("/solicitacoes/aprovacoes", dados)

    toastSuccess("Aprovação solicitada com sucesso!")
    limparFormulario()
    await carregarAprovacoes()

    // Trocar para a aba de histórico após criar uma aprovação
    if (aprovacoes.value.length > 0) {
      abaAtiva.value = "historico"
    }

    emits("atualizar")
  } catch (error) {
    console.error("Erro ao solicitar aprovação:", error)
    toastError(error.response?.data?.message || "Erro ao solicitar aprovação")
  } finally {
    loading.value = false
  }
}

function limparFormulario() {
  novaAprovacao.aprovador = null
  novaAprovacao.observacoes = ""
}

function podeEditarAprovacao(aprovacao) {
  // Apenas quem solicitou pode editar e apenas se ainda estiver pendente
  return (
    aprovacao.status === "pendente" &&
    aprovacao.solicitante_matricula === props.auth.matricula
  )
}

function podeCancelarAprovacao(aprovacao) {
  // Apenas quem solicitou pode cancelar e apenas se ainda estiver pendente
  return (
    aprovacao.status === "pendente" &&
    aprovacao.solicitante_matricula === props.auth.matricula
  )
}

function podeAprovar(aprovacao) {
  // Apenas o aprovador designado pode aprovar/rejeitar
  return (
    aprovacao.status === "pendente" &&
    aprovacao.aprovador_matricula == props.auth.matricula
  )
}

function editarAprovacao(aprovacao) {
  aprovacaoEdicao.id = aprovacao.id
  aprovacaoEdicao.aprovador = aprovacao.aprovador
  aprovacaoEdicao.observacoes = aprovacao.observacoes || ""
  dialogEdicao.value = true
}

async function salvarEdicao() {
  try {
    loading.value = true

    const dados = {
      aprovador_matricula: aprovacaoEdicao.aprovador.matricula,
      observacoes: aprovacaoEdicao.observacoes
    }

    await axios.post(`/solicitacoes/aprovacoes/${aprovacaoEdicao.id}`, dados)

    toastSuccess("Aprovação atualizada com sucesso!")
    dialogEdicao.value = false
    await carregarAprovacoes()
  } catch (error) {
    console.error("Erro ao atualizar aprovação:", error)
    toastError(error.response?.data?.message || "Erro ao atualizar aprovação")
  } finally {
    loading.value = false
  }
}

async function cancelarAprovacao(aprovacao) {
  const resultado = await swalConfirm(
    "Cancelar Aprovação",
    "Tem certeza que deseja cancelar esta solicitação de aprovação?"
  )

  if (!resultado.isConfirmed) return

  try {
    loading.value = true
    await axios.post(`/solicitacoes/aprovacoes/${aprovacao.id}`)

    toastSuccess("Aprovação cancelada com sucesso!")
    await carregarAprovacoes()

    // Se não restam mais aprovações, voltar para a aba Nova
    if (aprovacoes.value.length === 0) {
      abaAtiva.value = "nova"
    }

    emits("atualizar")
  } catch (error) {
    console.error("Erro ao cancelar aprovação:", error)
    toastError(error.response?.data?.message || "Erro ao cancelar aprovação")
  } finally {
    loading.value = false
  }
}

function responderAprovacao(aprovacao, acao) {
  respostaAprovacao.aprovacao = aprovacao
  respostaAprovacao.acao = acao
  respostaAprovacao.comentario = ""
  dialogResposta.value = true
}

async function confirmarResposta() {
  // Se for rejeição, a resposta é obrigatória
  if (
    respostaAprovacao.acao === "rejeitada" &&
    !respostaAprovacao.comentario.trim()
  ) {
    toastError("Resposta é obrigatória para rejeitar uma aprovação")
    return
  }

  // Para aprovação, a resposta é opcional (não precisa validar)

  try {
    loading.value = true

    const dados = {
      status: respostaAprovacao.acao,
      resposta_observacoes: respostaAprovacao.comentario || null
    }

    await axios.post(
      `/solicitacoes/aprovacoes/${respostaAprovacao.aprovacao.id}/responder`,
      dados
    )

    const msgSucesso =
      respostaAprovacao.acao === "aprovada"
        ? "Aprovação concedida com sucesso!"
        : "Aprovação rejeitada com sucesso!"

    toastSuccess(msgSucesso)
    dialogResposta.value = false
    await carregarAprovacoes()
    emits("atualizar")
  } catch (error) {
    console.error("Erro ao responder aprovação:", error)
    toastError(error.response?.data?.message || "Erro ao responder aprovação")
  } finally {
    loading.value = false
  }
}

// Funções auxiliares para exibição
function getStatusLabel(status) {
  const labels = {
    pendente: "Pendente",
    aprovada: "Aprovada",
    rejeitada: "Rejeitada",
    cancelada: "Cancelada"
  }
  return labels[status] || status
}

function getStatusSeverity(status) {
  const severities = {
    pendente: "warning",
    aprovada: "success",
    rejeitada: "danger",
    cancelada: "secondary"
  }
  return severities[status] || "secondary"
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES AVATAR                         ║
// ╚══════════════════════════════════════════════════════════════╝

// Função para obter iniciais (nome e sobrenome)
function obterIniciais(nome) {
  if (!nome) return "?"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "?"
  if (partes.length === 1) return partes[0].charAt(0).toUpperCase()
  return (
    partes[0].charAt(0) + partes[partes.length - 1].charAt(0)
  ).toUpperCase()
}

// Função para obter nome e sobrenome formatado
function obterNomeSobrenome(nome) {
  if (!nome) return "Não informado"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "Não informado"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

// Expor dados para o componente pai
defineExpose({
  aprovacoesStats,
  temProblemasAprovacao,
  mensagemAlerteAprovacao,
  carregarAprovacoes
})
</script>

<style scoped>
/* Estilos específicos do componente */
.space-y-4 > * + * {
  margin-top: 1rem;
}

.divide-y > * + * {
  border-top: 1px solid #e5e7eb;
}
</style>
