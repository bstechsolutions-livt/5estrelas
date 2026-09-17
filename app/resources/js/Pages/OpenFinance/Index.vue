<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    status: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    can_manage: { type: Boolean, default: false },
    p2_blocked: { type: Boolean, default: false },
    p2_block_message: { type: String, default: null },
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()
const busy = ref({})
const wizardOpen = ref(false)
const wizardStep = ref(1)
const wizardLoading = ref(false)
const wizardAccount = ref(null)
const wizardPreview = ref(null)

const form = reactive({
    bank_account_id: null,
    branch_id: null,
    name: '',
    cpf_cnpj: '',
    email: '',
    zipcode: '',
    street: '',
    address_number: '',
    address_complement: '',
    neighborhood: '',
    city: '',
    state: '',
})

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.add({ severity: 'success', summary: 'Open Finance', detail: flash.success, life: 4500 })
        }
        if (flash?.error) {
            toast.add({ severity: 'error', summary: 'Open Finance', detail: flash.error, life: 7000 })
        }
        if (flash?.warning) {
            toast.add({ severity: 'warn', summary: 'Open Finance', detail: flash.warning, life: 7000 })
        }
    },
    { immediate: true, deep: true },
)

const configuredSeverity = computed(() => (props.status.configured ? 'success' : 'warn'))
const configuredLabel = computed(() => (props.status.configured ? 'Configurada' : 'Não configurada'))

const connectionSeverity = computed(() => {
    if (props.status.connected) return 'success'
    if (!props.status.configured) return 'secondary'
    return 'danger'
})

const connectionLabel = computed(() => {
    if (props.status.connected) return 'Conexão OK'
    if (!props.status.configured) return 'Aguardando configuração'
    return 'Falha na comunicação'
})

const messageClass = computed(() => {
    if (props.status.connected) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200'
    }
    if (!props.status.configured) {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200'
    }
    return 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200'
})

const branchOptions = computed(() => {
    return (wizardPreview.value?.branches ?? []).map((branch) => ({
        label: `${branch.display_name} · ${branch.cnpj_masked}`,
        value: branch.id,
    }))
})

function statusSeverity(status) {
    return {
        conectada: 'success',
        aguardando_autorizacao: 'warn',
        account_ready: 'info',
        payer_ready: 'info',
        pending_data: 'warn',
        erro: 'danger',
        not_configured: 'secondary',
    }[status] || 'secondary'
}

function isBusy(key) {
    return !!busy.value[key]
}

function setBusy(key, value) {
    busy.value = { ...busy.value, [key]: value }
}

function retry() {
    router.reload({ preserveScroll: true })
}

function actionLabel(account) {
    if (account.actions.includes('authorize')) return 'Autorizar no banco'
    if (account.actions.includes('continue')) return 'Continuar configuração'
    if (account.status === 'conectada') return 'Verificar conexão'
    return 'Configurar Open Finance'
}

async function openWizard(account) {
    if (!props.can_manage) return
    if (props.p2_blocked) {
        toast.add({
            severity: 'warn',
            summary: 'Open Finance',
            detail: props.p2_block_message || props.status.message,
            life: 6000,
        })
        return
    }

    wizardAccount.value = account
    wizardStep.value = 1
    wizardLoading.value = true
    wizardOpen.value = true
    try {
        const { data } = await axios.get(`/open-finance/accounts/${account.id}/preview`)
        wizardPreview.value = data
        form.bank_account_id = account.id
        form.branch_id = data.suggested_branch_id
        applyPayer(data.payer || {})
        if (form.branch_id) wizardStep.value = 2
    } catch (error) {
        wizardOpen.value = false
        toast.add({
            severity: 'error',
            summary: 'Open Finance',
            detail: error.response?.data?.message || 'Não foi possível carregar os dados da conta.',
            life: 6000,
        })
    } finally {
        wizardLoading.value = false
    }
}

function applyPayer(payer) {
    form.name = payer.name || ''
    form.cpf_cnpj = payer.cpf_cnpj || ''
    form.email = payer.email || ''
    form.zipcode = payer.zipcode || ''
    form.street = payer.street || ''
    form.address_number = payer.address_number || ''
    form.address_complement = payer.address_complement || ''
    form.neighborhood = payer.neighborhood || ''
    form.city = payer.city || ''
    form.state = payer.state || ''
}

async function onBranchChange(branchId) {
    form.branch_id = branchId
    const selected = (wizardPreview.value?.branches ?? []).find((item) => item.id === branchId)
    if (selected && !form.name) form.name = selected.name
    if (selected && !form.cpf_cnpj) form.cpf_cnpj = selected.cnpj || ''
}

function nextStep() {
    if (wizardStep.value === 1) {
        wizardStep.value = 2
        return
    }
    if (wizardStep.value === 2 && !form.branch_id) {
        toast.add({ severity: 'warn', summary: 'Open Finance', detail: 'Escolha a empresa/filial deste pagador.', life: 4000 })
        return
    }
    wizardStep.value = 3
}

function submitWizard() {
    wizardLoading.value = true
    router.post('/open-finance/connections', { ...form }, {
        preserveScroll: true,
        onFinish: () => {
            wizardLoading.value = false
        },
        onSuccess: () => {
            wizardOpen.value = false
        },
    })
}

async function authorize(account) {
    if (!props.can_manage || !account.connection_id) return
    setBusy(account.id, true)
    try {
        const { data } = await axios.get(`/open-finance/connections/${account.connection_id}/authorization-link`)
        if (data?.url) {
            window.open(data.url, '_blank', 'noopener')
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Open Finance',
            detail: error.response?.data?.message || 'Não foi possível abrir a autorização.',
            life: 6000,
        })
    } finally {
        setBusy(account.id, false)
    }
}

function verify(account) {
    if (!props.can_manage || !account.connection_id) return
    setBusy(account.id, true)
    router.post(`/open-finance/connections/${account.connection_id}/verify`, {}, {
        preserveScroll: true,
        onFinish: () => setBusy(account.id, false),
    })
}

function primaryAction(account) {
    if (account.actions.includes('authorize')) {
        authorize(account)
        return
    }
    if (account.actions.includes('verify') && account.status === 'conectada') {
        verify(account)
        return
    }
    openWizard(account)
}

function bankLine(account) {
    const parts = []
    if (account.bank_code) parts.push(account.bank_code + (account.bank_name ? ` ${account.bank_name}` : ''))
    if (account.agency) parts.push(`Ag ${account.agency}`)
    if (account.account_masked) parts.push(`CC ${account.account_masked}`)
    return parts.join(' · ') || '—'
}
</script>

<template>
    <Head title="Open Finance" />
    <component :is="isMobile ? AppLayoutMobile : AppLayout" title="Open Finance">
        <Toast />
        <div class="w-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-4 md:py-6 space-y-6" dusk="open-finance-page">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Open Finance</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Conexão técnica com a TecnoSpeed, pagador, conta bancária e consentimento Open Finance.
                    </p>
                </div>
                <Button
                    label="Verificar novamente"
                    icon="pi pi-refresh"
                    severity="secondary"
                    outlined
                    dusk="open-finance-retry"
                    @click="retry"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Integração</div>
                    <div class="mt-3" dusk="open-finance-configured">
                        <Tag :value="configuredLabel" :severity="configuredSeverity" />
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Ambiente</div>
                    <div class="mt-3 font-semibold text-slate-800 dark:text-slate-100" dusk="open-finance-environment">
                        {{ status.environment_label }}
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Comunicação</div>
                    <div class="mt-3" dusk="open-finance-connected">
                        <Tag :value="connectionLabel" :severity="connectionSeverity" />
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border p-5 text-sm leading-relaxed space-y-4"
                :class="messageClass"
                dusk="open-finance-message"
            >
                <p class="m-0">{{ status.message }}</p>
                <a
                    v-if="status.show_ip_release && status.ip_release_url"
                    :href="status.ip_release_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex"
                    dusk="open-finance-ip-release"
                >
                    <Button type="button" label="Liberar IP na TecnoSpeed" icon="pi pi-external-link" severity="warn" />
                </a>
            </div>

            <section class="space-y-4" dusk="open-finance-accounts">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Contas Open Finance</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Contas bancárias já cadastradas no sistema. O Open Finance cria um vínculo com a TecnoSpeed, sem duplicar o cadastro.
                    </p>
                </div>

                <div
                    v-if="p2_blocked && p2_block_message"
                    class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200 p-4 text-sm"
                    dusk="open-finance-p2-blocked"
                >
                    {{ p2_block_message }}
                </div>

                <div
                    v-if="accounts.length === 0"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 text-sm text-slate-500 dark:text-slate-400"
                    dusk="open-finance-accounts-empty"
                >
                    Nenhuma conta bancária cadastrada. Cadastre a conta em Bancos antes de configurar o Open Finance.
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <article
                        v-for="account in accounts"
                        :key="account.id"
                        class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4"
                        :dusk="'open-finance-account-' + account.id"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ account.name }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ bankLine(account) }}</div>
                                <div class="text-sm text-slate-600 dark:text-slate-300 mt-2">
                                    Empresa/Pagador: {{ account.empresa || 'Não definida' }}
                                </div>
                            </div>
                            <Tag
                                :value="account.status_label"
                                :severity="statusSeverity(account.status)"
                                :dusk="'open-finance-account-status-' + account.id"
                            />
                        </div>

                        <p v-if="account.last_error_message" class="text-sm text-red-700 dark:text-red-300 m-0">
                            {{ account.last_error_message }}
                        </p>
                        <p v-if="account.status === 'aguardando_autorizacao'" class="text-sm text-slate-600 dark:text-slate-300 m-0">
                            Conta preparada. Autorize o acesso no banco.
                        </p>
                        <p v-if="account.status === 'conectada'" class="text-sm text-emerald-700 dark:text-emerald-300 m-0">
                            Conectada. O conector Open Finance foi confirmado.
                        </p>

                        <div v-if="can_manage" class="flex flex-col sm:flex-row gap-2">
                            <Button
                                v-if="account.status !== 'conectada'"
                                :label="actionLabel(account)"
                                :icon="account.actions.includes('authorize') ? 'pi pi-external-link' : 'pi pi-link'"
                                size="small"
                                :loading="isBusy(account.id)"
                                :disabled="isBusy(account.id) || p2_blocked"
                                dusk="open-finance-account-primary"
                                @click="primaryAction(account)"
                            />
                            <Button
                                v-if="account.actions.includes('verify')"
                                label="Verificar conexão"
                                icon="pi pi-search"
                                :severity="account.status === 'conectada' ? 'success' : 'secondary'"
                                :outlined="account.status !== 'conectada'"
                                size="small"
                                :loading="isBusy(account.id)"
                                :disabled="isBusy(account.id) || p2_blocked"
                                dusk="open-finance-account-verify"
                                @click="verify(account)"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <Dialog
            v-model:visible="wizardOpen"
            modal
            :header="wizardStep === 3 ? 'Dados do pagador' : wizardStep === 2 ? 'Empresa / Pagador' : 'Conta bancária'"
            :style="{ width: isMobile ? '96vw' : '34rem' }"
            dusk="open-finance-wizard"
        >
            <div v-if="wizardLoading && !wizardPreview" class="text-sm text-slate-500">Carregando…</div>
            <div v-else class="space-y-4">
                <div v-if="wizardStep === 1" class="space-y-2 text-sm">
                    <p class="m-0 text-slate-500">Será usado o cadastro bancário já existente. Nenhum novo cadastro mestre será criado.</p>
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3">
                        <div class="font-semibold">{{ wizardAccount?.name }}</div>
                        <div class="text-slate-500 mt-1">{{ bankLine(wizardAccount || {}) }}</div>
                    </div>
                </div>

                <div v-if="wizardStep === 2" class="space-y-3">
                    <p v-if="wizardPreview?.match === 'exact' || wizardPreview?.match === 'unique_company'" class="text-sm text-slate-500 m-0">
                        Empresa pré-selecionada pela correspondência Senior da conta. Confira antes de continuar.
                    </p>
                    <p v-else class="text-sm text-amber-700 dark:text-amber-300 m-0">
                        Não foi possível associar a empresa automaticamente. Escolha a filial/Branch correspondente.
                    </p>
                    <label class="block text-sm font-medium">Empresa / Branch</label>
                    <Select
                        v-model="form.branch_id"
                        :options="branchOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        placeholder="Selecione a empresa"
                        dusk="open-finance-branch"
                        @update:model-value="onBranchChange"
                    />
                </div>

                <div v-if="wizardStep === 3" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Razão social</label>
                        <InputText v-model="form.name" class="w-full" dusk="open-finance-payer-name" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">CNPJ</label>
                        <InputText v-model="form.cpf_cnpj" class="w-full" dusk="open-finance-payer-cnpj" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">E-mail (opcional)</label>
                        <InputText v-model="form.email" class="w-full" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Logradouro</label>
                        <InputText v-model="form.street" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Número</label>
                        <InputText v-model="form.address_number" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Complemento</label>
                        <InputText v-model="form.address_complement" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Bairro</label>
                        <InputText v-model="form.neighborhood" class="w-full" dusk="open-finance-payer-neighborhood" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cidade</label>
                        <InputText v-model="form.city" class="w-full" dusk="open-finance-payer-city" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">UF</label>
                        <InputText v-model="form.state" class="w-full" maxlength="2" dusk="open-finance-payer-state" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">CEP</label>
                        <InputText v-model="form.zipcode" class="w-full" dusk="open-finance-payer-zipcode" />
                    </div>
                    <p class="sm:col-span-2 text-xs text-slate-500 m-0">
                        Endereço não é inventado pelo sistema. Preencha o que estiver em branco antes de enviar à TecnoSpeed.
                    </p>
                </div>
            </div>

            <template #footer>
                <Button v-if="wizardStep > 1" label="Voltar" severity="secondary" text :disabled="wizardLoading" @click="wizardStep -= 1" />
                <Button v-if="wizardStep < 3" label="Continuar" :disabled="wizardLoading" dusk="open-finance-wizard-next" @click="nextStep" />
                <Button
                    v-else
                    label="Preparar conexão"
                    :loading="wizardLoading"
                    dusk="open-finance-wizard-submit"
                    @click="submitWizard"
                />
            </template>
        </Dialog>
    </component>
</template>
