<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Message from 'primevue/message'

const props = defineProps({
    accounts: Object,
    filters: Object,
    typeOptions: Object,
    isInterimSource: { type: Boolean, default: true },
})

const search = ref(props.filters?.search || '')
const accountType = ref(props.filters?.account_type || null)

const typeList = [
    { label: 'Todos os tipos', value: null },
    ...Object.entries(props.typeOptions || {}).map(([value, label]) => ({ label, value })),
]

function applyFilters() {
    router.get('/financeiro/plano-de-contas', {
        search: search.value || undefined,
        account_type: accountType.value || undefined,
    }, { preserveState: true, replace: true })
}
</script>

<template>
    <AppLayout title="Plano de Contas">
        <div class="p-4 md:p-6 space-y-4">
            <div>
                <h1 class="text-2xl font-semibold">Plano de Contas</h1>
                <p class="text-sm text-surface-500">Base para DRE e conciliação futura com contas a receber.</p>
            </div>

            <Message v-if="isInterimSource" severity="warn" :closable="false">
                Fonte interim: códigos distintos de <strong>ctaFin</strong> e <strong>codCcu</strong> extraídos de Contas a Pagar e Contas a Receber.
                Integração completa com o serviço Senior <code>com_senior_g5_co_mct_ctb_planocontacontabil</code> pendente de parametrização EASYTECH.
            </Message>

            <div class="flex flex-wrap gap-3 items-end bg-surface-0 border border-surface-200 rounded-xl p-4">
                <div class="flex flex-col gap-1 min-w-[200px]">
                    <label class="text-xs text-surface-500">Busca</label>
                    <InputText v-model="search" placeholder="Código ou descrição" @keyup.enter="applyFilters" />
                </div>
                <div class="flex flex-col gap-1 min-w-[200px]">
                    <label class="text-xs text-surface-500">Tipo</label>
                    <Select v-model="accountType" :options="typeList" option-label="label" option-value="value" />
                </div>
                <Button label="Filtrar" icon="pi pi-search" @click="applyFilters" />
            </div>

            <DataTable :value="accounts.data" paginator :rows="accounts.per_page || 50" :total-records="accounts.total || 0" data-key="id" class="text-sm">
                <Column field="code" header="Código" />
                <Column field="description" header="Descrição">
                    <template #body="{ data }">{{ data.description || '—' }}</template>
                </Column>
                <Column header="Tipo">
                    <template #body="{ data }">
                        <Tag :value="typeOptions?.[data.account_type] || data.account_type" severity="secondary" />
                    </template>
                </Column>
                <Column field="empresa_nome" header="Empresa">
                    <template #body="{ data }">{{ data.empresa_nome || '—' }}</template>
                </Column>
                <Column header="Origem">
                    <template #body="{ data }">
                        <Tag :value="data.source === 'derived' ? 'Derivado CP/CR' : 'Senior'" :severity="data.source === 'derived' ? 'warn' : 'info'" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AppLayout>
</template>
