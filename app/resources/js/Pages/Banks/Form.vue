<script setup>
import { computed, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    account: { type: Object, default: null },
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()
const isEditing = computed(() => !!props.account?.id)

const form = useForm({
    name: props.account?.name ?? '',
    bank_code: props.account?.bank_code ?? '',
    bank_name: props.account?.bank_name ?? '',
    agency: props.account?.agency ?? '',
    account_number: props.account?.account_number ?? '',
    account_digit: props.account?.account_digit ?? '',
    opening_balance: props.account?.opening_balance ?? null,
    opening_balance_date: props.account?.opening_balance_date ?? '',
})

function submit() {
    if (isEditing.value) {
        form.put(`/financeiro/bancos/${props.account.id}`)
    } else {
        form.post('/financeiro/bancos')
    }
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
        :title="isMobile ? (isEditing ? 'Editar conta' : 'Nova conta') : undefined"
        :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-28' : 'max-w-2xl mx-auto space-y-6'">
            <div>
                <button type="button" class="text-xs text-blue-600 hover:underline mb-2 inline-flex items-center gap-1"
                    @click="router.visit('/financeiro/bancos')">
                    <i class="pi pi-arrow-left text-[10px]"></i> Voltar
                </button>
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    {{ isEditing ? 'Editar conta bancária' : 'Nova conta bancária' }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Preencha banco, agência e conta para o OFX sugerir esta conta na importação.
                </p>
            </div>

            <div v-if="account?.from_senior" class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-900">
                <p class="font-medium mb-1">Origem Senior (somente leitura)</p>
                <p class="text-xs">
                    Conta interna <strong>{{ account.senior_num_cco }}</strong>
                    · Empresa {{ account.senior_codemp }}
                    <span v-if="account.senior_codfil !== null && account.senior_codfil !== undefined">
                        · Filial {{ account.senior_codfil }}
                    </span>
                </p>
                <p v-if="account.senior_descricao" class="text-xs mt-1">Descrição Senior: {{ account.senior_descricao }}</p>
            </div>

            <form class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome na intranet</label>
                    <InputText v-model="form.name" class="w-full" placeholder="Ex: MATRIZ BRB 050" />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Código banco (FEBRABAN)</label>
                        <InputText v-model="form.bank_code" class="w-full" placeholder="070" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nome do banco</label>
                        <InputText v-model="form.bank_name" class="w-full" placeholder="Banco de Brasília" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Agência</label>
                        <InputText v-model="form.agency" class="w-full" placeholder="0001" />
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Conta</label>
                            <InputText v-model="form.account_number" class="w-full" placeholder="0460001329" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dígito</label>
                            <InputText v-model="form.account_digit" class="w-full" placeholder="0" />
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <h2 class="text-sm font-semibold text-gray-700">Saldo inicial</h2>
                    <p class="text-xs text-gray-500 mt-1 mb-3">
                        Informe o saldo e a data de referência. Após a importação de um OFX, a listagem passa a mostrar o saldo final do extrato mais recente.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Valor</label>
                            <InputNumber
                                v-model="form.opening_balance"
                                mode="currency"
                                currency="BRL"
                                locale="pt-BR"
                                class="w-full"
                                input-class="w-full"
                            />
                            <p v-if="form.errors.opening_balance" class="text-xs text-red-500 mt-1">
                                {{ form.errors.opening_balance }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Data de referência</label>
                            <input
                                v-model="form.opening_balance_date"
                                type="date"
                                class="w-full h-10 rounded-md border border-gray-300 px-3 text-sm text-gray-700"
                            />
                            <p v-if="form.errors.opening_balance_date" class="text-xs text-red-500 mt-1">
                                {{ form.errors.opening_balance_date }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <Button type="submit" :label="isEditing ? 'Salvar' : 'Cadastrar'" icon="pi pi-check"
                        :loading="form.processing" />
                    <Button type="button" label="Cancelar" severity="secondary" outlined
                        @click="router.visit('/financeiro/bancos')" />
                </div>
            </form>
        </div>
    </component>
</template>
