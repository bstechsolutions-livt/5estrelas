<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { formatApiDate } from '@/utils/apiDate'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    receivable: Object,
    statusLabels: Object,
})

const { isMobile } = useDevice()

const situacao = computed(() => {
    const code = props.receivable?.senior_situacao_original
    return props.statusLabels?.[code] || code || 'Indefinida'
})

function formatMoney(v) {
    if (v == null) return '—'
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(v) {
    return formatApiDate(v)
}

const Layout = computed(() => isMobile.value ? AppLayoutMobile : AppLayout)
</script>

<template>
    <component :is="Layout" title="Título a Receber">
        <div class="p-4 md:p-6 max-w-4xl mx-auto space-y-4">
            <Button label="Voltar" icon="pi pi-arrow-left" text @click="router.visit('/financeiro/contas-receber')" />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ receivable.title_number }}</h1>
                    <p class="text-surface-600">{{ receivable.customer_name }}</p>
                </div>
                <div class="flex gap-2">
                    <Tag value="Senior ERP" severity="info" />
                    <Tag :value="situacao" severity="secondary" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-surface-0 border border-surface-200 rounded-xl p-4">
                <div>
                    <div class="text-xs text-surface-500">Empresa</div>
                    <div>{{ receivable.empresa_nome || receivable.codemp || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Filial</div>
                    <div>{{ receivable.filial_nome || receivable.codfil || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Emissão</div>
                    <div>{{ formatDate(receivable.issue_date) }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Vencimento</div>
                    <div>{{ formatDate(receivable.due_date) }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Valor original</div>
                    <div class="font-semibold">{{ formatMoney(receivable.amount) }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Saldo em aberto</div>
                    <div class="font-semibold">{{ formatMoney(receivable.open_amount) }}</div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-xs text-surface-500">Descrição / histórico</div>
                    <div>{{ receivable.description || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Conta financeira (ctaFin)</div>
                    <div>{{ receivable.ctafin || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Centro de custo (codCcu)</div>
                    <div>{{ receivable.codccu || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Cliente (codCli)</div>
                    <div>{{ receivable.codcli || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-surface-500">Última sync Senior</div>
                    <div>{{ receivable.senior_synced_at ? formatDate(receivable.senior_synced_at) : '—' }}</div>
                </div>
            </div>

            <div v-if="receivable.rateios?.length" class="bg-surface-0 border border-surface-200 rounded-xl p-4">
                <h2 class="font-medium mb-3">Rateios</h2>
                <div v-for="(r, i) in receivable.rateios" :key="i" class="text-sm border-t border-surface-100 py-2 grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div><span class="text-surface-500">CC:</span> {{ r.codccu || '—' }}</div>
                    <div><span class="text-surface-500">Conta:</span> {{ r.ctafin || '—' }}</div>
                    <div><span class="text-surface-500">%</span> {{ r.perrat ?? '—' }}</div>
                    <div><span class="text-surface-500">Valor</span> {{ formatMoney(r.vlrrat) }}</div>
                </div>
            </div>

            <p class="text-xs text-surface-500">
                Visualização somente leitura. Baixas e alterações devem ser feitas no Senior ERP.
            </p>
        </div>
    </component>
</template>
