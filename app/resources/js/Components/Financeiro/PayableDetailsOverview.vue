<script setup>
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import PayableFieldOriginLabel from '@/Components/Financeiro/PayableFieldOriginLabel.vue'

defineProps({
    payable: { type: Object, required: true },
    fieldOrigins: { type: Object, default: null },
    naturezaGasto: { type: String, default: null },
    centroCusto: { type: String, default: null },
    contaFinanceira: { type: String, default: null },
    departamentoTitulo: { type: String, default: null },
    canManagePriority: { type: Boolean, default: false },
    priorityForm: { type: Object, required: true },
    priorityOptions: { type: Array, default: () => [] },
    priorityColors: { type: Object, default: () => ({}) },
    slaAlertClass: { type: String, default: '' },
    formatDate: { type: Function, required: true },
})

defineEmits(['save-priority'])
</script>

<template>
    <section
        class="bg-white rounded-xl border border-gray-100 overflow-hidden"
        dusk="payable-overview"
    >
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
            <div class="p-4 sm:p-5 min-w-0">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="pi pi-info-circle text-gray-400"></i>
                    Informações
                </h3>
                <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 gap-y-4 text-sm">
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Empresa" field="empresa_nome" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Empresa</p><p class="text-gray-800">{{ payable.empresa_nome || '—' }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Filial" field="filial_nome" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Filial</p><p class="text-gray-800">{{ payable.filial_label || payable.filial_nome || '—' }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Natureza de gasto" field="codntg" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Natureza de gasto</p><p class="text-gray-800">{{ naturezaGasto || '—' }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Centro de custo" field="codccu" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Centro de custo</p><p class="text-gray-800 text-xs" :class="centroCusto && !payable.centro_custo_nome ? 'font-mono' : ''">{{ centroCusto || '—' }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Conta financeira" field="ctafin" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Conta financeira</p><p class="text-gray-800 text-xs" :class="contaFinanceira && !payable.conta_financeira_nome ? 'font-mono' : ''">{{ contaFinanceira || '—' }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Departamento" field="department_nome" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Departamento</p><p class="text-gray-800">{{ departamentoTitulo || '—' }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="Emissão" field="issue_date" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">Emissão</p><p class="text-gray-800">{{ formatDate(payable.issue_date) }}</p></div>
                    <div><PayableFieldOriginLabel v-if="fieldOrigins" label="CNPJ" field="supplier_cnpj" :field-origins="fieldOrigins" /><p v-else class="text-xs text-gray-500">CNPJ</p><p class="text-gray-800 font-mono text-xs">{{ payable.supplier_cnpj || '—' }}</p></div>
                    <div dusk="payable-lancado-por">
                        <PayableFieldOriginLabel v-if="fieldOrigins" label="Lançado por (Senior)" field="launcher_nome" :field-origins="fieldOrigins" />
                        <p v-else class="text-xs text-gray-500">Lançado por (Senior)</p>
                        <p class="text-gray-800">{{ payable.launcher_label || '—' }}</p>
                    </div>
                    <div class="col-span-2 xl:col-span-3 border-t border-gray-100 pt-3">
                        <PayableFieldOriginLabel v-if="fieldOrigins" label="Fornecedor" field="supplier_name" :field-origins="fieldOrigins" />
                        <p v-else class="text-xs text-gray-500">Fornecedor</p>
                        <p class="text-gray-800 font-medium">{{ payable.supplier_display_name || payable.supplier_name || '—' }}</p>
                    </div>
                </div>
            </div>

            <aside class="p-4 sm:p-5 border-t lg:border-t-0 lg:border-l border-gray-100 bg-gray-50/40" dusk="payable-priority-section">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-1.5">
                    <i class="pi pi-bolt text-amber-500"></i>
                    Prioridade de pagamento
                    <i
                        v-if="fieldOrigins?.payment_priority === 'hub'"
                        class="pi pi-pencil text-[10px] text-blue-400"
                        title="Prioridade definida na intranet Hub"
                    />
                </h3>

                <template v-if="canManagePriority && payable.status !== 'encerrado'">
                    <div class="space-y-3">
                        <div>
                            <PayableFieldOriginLabel v-if="fieldOrigins" label="Prioridade" field="payment_priority" :field-origins="fieldOrigins" class="block mb-1" />
                            <label v-else class="block text-xs font-medium text-gray-500 mb-1">Prioridade</label>
                            <Select v-model="priorityForm.payment_priority" :options="priorityOptions" option-label="label" option-value="value" class="w-full" />
                        </div>
                        <div>
                            <PayableFieldOriginLabel v-if="fieldOrigins" label="Prazo (SLA)" field="payment_sla_date" :field-origins="fieldOrigins" class="block mb-1" />
                            <label v-else class="block text-xs font-medium text-gray-500 mb-1">Prazo (SLA)</label>
                            <DatePicker v-model="priorityForm.payment_sla_date" date-format="dd/mm/yy" placeholder="dd/mm/aaaa" class="w-full" show-icon />
                        </div>
                        <Button label="Salvar prioridade" icon="pi pi-save" size="small" class="w-full" :loading="priorityForm.processing" @click="$emit('save-priority')" />
                    </div>
                </template>
                <template v-else>
                    <div class="text-sm space-y-3">
                        <div>
                            <PayableFieldOriginLabel v-if="fieldOrigins" label="Prioridade" field="payment_priority" :field-origins="fieldOrigins" />
                            <p v-else class="text-xs text-gray-500">Prioridade</p>
                            <Tag v-if="payable.payment_priority" :value="payable.priority_label" :severity="priorityColors[payable.payment_priority]" />
                            <p v-else class="text-gray-400">Não definida</p>
                        </div>
                        <div v-if="payable.payment_sla_date">
                            <PayableFieldOriginLabel v-if="fieldOrigins" label="Prazo (SLA)" field="payment_sla_date" :field-origins="fieldOrigins" />
                            <p v-else class="text-xs text-gray-500">Prazo (SLA)</p>
                            <p :class="slaAlertClass">{{ formatDate(payable.payment_sla_date) }}</p>
                        </div>
                        <div v-if="payable.priority_setter">
                            <p class="text-xs text-gray-500">Definida por</p>
                            <p class="text-gray-800">{{ payable.priority_setter.name }}</p>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </section>
</template>
