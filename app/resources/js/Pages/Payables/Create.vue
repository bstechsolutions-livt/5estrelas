<script setup>
import { computed, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { useDevice } from '@/composables/useDevice'
import { parseApiDate, toApiDateString } from '@/utils/apiDate'

const props = defineProps({
    filiais: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    canChangeDepartment: { type: Boolean, default: true },
    lockedDepartment: { type: Object, default: null },
    defaultFilial: { type: String, default: null },
    defaultDueDate: { type: String, default: null },
    priorityOptions: { type: Object, default: () => ({}) },
    noBranchAccess: { type: Boolean, default: false },
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const form = useForm({
    title_number: '',
    nickname: '',
    supplier_name: '',
    supplier_cnpj: '',
    amount: null,
    due_date: props.defaultDueDate ? parseApiDate(props.defaultDueDate) : null,
    issue_date: null,
    description: '',
    category: '',
    filial: props.defaultFilial || null,
    department_id: props.canChangeDepartment
        ? null
        : (props.lockedDepartment?.id ?? null),
    codfor: null,
    codntg: null,
    codccu: '',
    ctafin: null,
    ctared: null,
    codtns: '',
    codtpt: '',
    payment_priority: null,
    requester_comment: '',
})

const filialList = computed(() =>
    (props.filiais || []).map(f => ({ label: f.label, value: f.value }))
)

const departmentList = computed(() =>
    (props.departments || []).map(d => ({ label: d.name, value: d.id }))
)

const priorityList = computed(() =>
    Object.entries(props.priorityOptions || {}).map(([value, label]) => ({ value, label }))
)

const canSubmit = computed(() =>
    !props.noBranchAccess
    && !!form.supplier_name?.trim()
    && form.amount > 0
    && !!form.filial
)

function submit() {
    form
        .transform((data) => ({
            ...data,
            title_number: data.title_number?.trim() || null,
            nickname: data.nickname?.trim() || null,
            supplier_name: data.supplier_name?.trim(),
            supplier_cnpj: data.supplier_cnpj?.trim() || null,
            description: data.description?.trim() || null,
            category: data.category?.trim() || null,
            codccu: data.codccu?.trim() || null,
            codtns: data.codtns?.trim() || null,
            codtpt: data.codtpt?.trim() || null,
            due_date: data.due_date ? toApiDateString(data.due_date) : null,
            issue_date: data.issue_date ? toApiDateString(data.issue_date) : null,
            requester_comment: data.requester_comment?.trim() || null,
        }))
        .post('/financeiro/contas-pagar')
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 4000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout"
        :title="isMobile ? 'Lançar título' : undefined"
        :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-28' : 'max-w-3xl mx-auto space-y-6'">
            <div>
                <button
                    type="button"
                    class="text-sm text-blue-600 hover:underline mb-2 inline-flex items-center gap-1 cursor-pointer bg-transparent border-0 p-0"
                    @click="router.visit('/financeiro/contas-pagar')"
                >
                    <i class="pi pi-arrow-left text-xs"></i> Voltar para Contas a Pagar
                </button>
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    Lançar título
                </h1>
                <p class="text-sm text-gray-500 mt-1 max-w-2xl">
                    Crie um título de contas a pagar na intranet (Hub). A integração com a Senior fica para depois —
                    o título entra como <strong>Pendente</strong> para anexar documentos e seguir o fluxo normal.
                </p>
            </div>

            <BranchAccessBlocked v-if="noBranchAccess" />

            <form v-else @submit.prevent="submit" class="space-y-4" dusk="payable-create-form">
                <!-- Identificação -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Identificação</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Número, apelido e classificação operacional.</p>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Número do título</label>
                            <InputText v-model="form.title_number" class="w-full" placeholder="Opcional" :invalid="!!form.errors.title_number" />
                            <small v-if="form.errors.title_number" class="text-red-500 text-xs mt-1 block">{{ form.errors.title_number }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Apelido</label>
                            <InputText v-model="form.nickname" class="w-full" placeholder="Opcional — facilita a busca" :invalid="!!form.errors.nickname" />
                            <small v-if="form.errors.nickname" class="text-red-500 text-xs mt-1 block">{{ form.errors.nickname }}</small>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Filial <span class="text-red-500">*</span></label>
                            <Select
                                v-model="form.filial"
                                :options="filialList"
                                option-label="label"
                                option-value="value"
                                placeholder="Selecione a filial..."
                                filter
                                class="w-full"
                                dusk="payable-create-filial"
                                :invalid="!!form.errors.filial"
                            />
                            <small v-if="form.errors.filial" class="text-red-500 text-xs mt-1 block">{{ form.errors.filial }}</small>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Departamento</label>
                            <Select
                                v-if="canChangeDepartment"
                                v-model="form.department_id"
                                :options="departmentList"
                                option-label="label"
                                option-value="value"
                                placeholder="Opcional"
                                show-clear
                                filter
                                class="w-full"
                                :invalid="!!form.errors.department_id"
                            />
                            <div v-else class="h-[38px] px-3 flex items-center rounded-md border border-gray-200 bg-gray-50 text-sm text-gray-700">
                                {{ lockedDepartment?.name || 'Sem departamento vinculado' }}
                            </div>
                            <small v-if="form.errors.department_id" class="text-red-500 text-xs mt-1 block">{{ form.errors.department_id }}</small>
                        </div>
                    </div>
                </div>

                <!-- Valores e datas -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Valores e datas</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Sem vencimento informado, o sistema aplica +3 dias úteis (regra dos títulos manuais).
                        </p>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Valor <span class="text-red-500">*</span></label>
                            <InputNumber
                                v-model="form.amount"
                                mode="currency"
                                currency="BRL"
                                locale="pt-BR"
                                class="w-full"
                                input-class="w-full"
                                dusk="payable-create-amount"
                                :invalid="!!form.errors.amount"
                            />
                            <small v-if="form.errors.amount" class="text-red-500 text-xs mt-1 block">{{ form.errors.amount }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Prioridade</label>
                            <Select
                                v-model="form.payment_priority"
                                :options="priorityList"
                                option-label="label"
                                option-value="value"
                                placeholder="Normal (padrão do fluxo)"
                                show-clear
                                class="w-full"
                            />
                            <small v-if="form.errors.payment_priority" class="text-red-500 text-xs mt-1 block">{{ form.errors.payment_priority }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento</label>
                            <DatePicker
                                v-model="form.due_date"
                                date-format="dd/mm/yy"
                                show-icon
                                class="w-full"
                                input-class="w-full"
                                dusk="payable-create-due-date"
                                :invalid="!!form.errors.due_date"
                            />
                            <small v-if="form.errors.due_date" class="text-red-500 text-xs mt-1 block">{{ form.errors.due_date }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Emissão</label>
                            <DatePicker
                                v-model="form.issue_date"
                                date-format="dd/mm/yy"
                                show-icon
                                class="w-full"
                                input-class="w-full"
                                :invalid="!!form.errors.issue_date"
                            />
                            <small v-if="form.errors.issue_date" class="text-red-500 text-xs mt-1 block">{{ form.errors.issue_date }}</small>
                        </div>
                    </div>
                </div>

                <!-- Fornecedor -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Fornecedor</h2>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nome <span class="text-red-500">*</span></label>
                            <InputText
                                v-model="form.supplier_name"
                                class="w-full"
                                placeholder="Razão social ou nome fantasia"
                                dusk="payable-create-supplier"
                                :invalid="!!form.errors.supplier_name"
                            />
                            <small v-if="form.errors.supplier_name" class="text-red-500 text-xs mt-1 block">{{ form.errors.supplier_name }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">CNPJ / CPF</label>
                            <InputText v-model="form.supplier_cnpj" class="w-full" placeholder="Opcional" :invalid="!!form.errors.supplier_cnpj" />
                            <small v-if="form.errors.supplier_cnpj" class="text-red-500 text-xs mt-1 block">{{ form.errors.supplier_cnpj }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Código fornecedor (Senior)</label>
                            <InputNumber v-model="form.codfor" class="w-full" input-class="w-full" :use-grouping="false" :min="1" placeholder="Opcional" />
                            <small v-if="form.errors.codfor" class="text-red-500 text-xs mt-1 block">{{ form.errors.codfor }}</small>
                        </div>
                    </div>
                </div>

                <!-- Classificação -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Classificação contábil</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Campos alinhados ao cabeçalho Senior — opcionais no lançamento Hub.</p>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Natureza de gasto</label>
                            <InputNumber v-model="form.codntg" class="w-full" input-class="w-full" :use-grouping="false" :min="0" placeholder="codNtg" />
                            <small v-if="form.errors.codntg" class="text-red-500 text-xs mt-1 block">{{ form.errors.codntg }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Centro de custo</label>
                            <InputText v-model="form.codccu" class="w-full" placeholder="codCcu" :invalid="!!form.errors.codccu" />
                            <small v-if="form.errors.codccu" class="text-red-500 text-xs mt-1 block">{{ form.errors.codccu }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Conta financeira</label>
                            <InputNumber v-model="form.ctafin" class="w-full" input-class="w-full" :use-grouping="false" :min="0" placeholder="ctaFin" />
                            <small v-if="form.errors.ctafin" class="text-red-500 text-xs mt-1 block">{{ form.errors.ctafin }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Conta reduzida</label>
                            <InputNumber v-model="form.ctared" class="w-full" input-class="w-full" :use-grouping="false" :min="0" placeholder="ctaRed" />
                            <small v-if="form.errors.ctared" class="text-red-500 text-xs mt-1 block">{{ form.errors.ctared }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Transação (codTns)</label>
                            <InputText v-model="form.codtns" class="w-full" placeholder="Opcional" :invalid="!!form.errors.codtns" />
                            <small v-if="form.errors.codtns" class="text-red-500 text-xs mt-1 block">{{ form.errors.codtns }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de título (codTpt)</label>
                            <InputText v-model="form.codtpt" class="w-full" placeholder="Opcional" :invalid="!!form.errors.codtpt" />
                            <small v-if="form.errors.codtpt" class="text-red-500 text-xs mt-1 block">{{ form.errors.codtpt }}</small>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Categoria</label>
                            <InputText v-model="form.category" class="w-full" placeholder="Opcional — preenchida automaticamente se houver codTns" :invalid="!!form.errors.category" />
                            <small v-if="form.errors.category" class="text-red-500 text-xs mt-1 block">{{ form.errors.category }}</small>
                        </div>
                    </div>
                </div>

                <!-- Observações -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Observações</h2>
                    </div>
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Observação do título</label>
                            <Textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full"
                                placeholder="Observação do título (máx. 255 caracteres)"
                                :invalid="!!form.errors.description"
                            />
                            <small v-if="form.errors.description" class="text-red-500 text-xs mt-1 block">{{ form.errors.description }}</small>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Comentário do solicitante</label>
                            <Textarea
                                v-model="form.requester_comment"
                                rows="3"
                                class="w-full"
                                placeholder="Fica fixo no topo da timeline do título"
                                dusk="payable-create-requester-comment"
                                :invalid="!!form.errors.requester_comment"
                            />
                            <p class="text-[11px] text-gray-400 mt-1">Visível para todos os aprovadores, fixado no histórico.</p>
                            <small v-if="form.errors.requester_comment" class="text-red-500 text-xs mt-1 block">{{ form.errors.requester_comment }}</small>
                        </div>
                    </div>
                </div>

                <div :class="isMobile ? 'fixed bottom-0 inset-x-0 p-4 bg-white border-t border-gray-100 flex gap-2' : 'flex justify-end gap-3 pt-2'">
                    <Button
                        label="Cancelar"
                        severity="secondary"
                        type="button"
                        class="flex-1 sm:flex-none"
                        @click="router.visit('/financeiro/contas-pagar')"
                    />
                    <Button
                        label="Lançar título"
                        type="submit"
                        icon="pi pi-plus"
                        class="flex-1 sm:flex-none"
                        dusk="payable-create-submit"
                        :loading="form.processing"
                        :disabled="!canSubmit || form.processing"
                    />
                </div>
            </form>
        </div>
    </component>
</template>
