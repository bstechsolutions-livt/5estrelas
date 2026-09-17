<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    status: { type: Object, required: true },
    payers: { type: Array, default: () => [] },
    can_manage: { type: Boolean, default: false },
})

const page = usePage()
const toast = useToast()
const busy = ref({})

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.add({ severity: 'success', summary: 'Open Finance', detail: flash.success, life: 4000 })
        }
        if (flash?.error) {
            toast.add({ severity: 'error', summary: 'Open Finance', detail: flash.error, life: 6000 })
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

function tecnospeedSeverity(payer) {
    if (payer.tecnospeed_state === 'registered') return 'success'
    if (payer.tecnospeed_state === 'missing') return 'warn'
    if (payer.tecnospeed_state === 'ip_blocked') return 'danger'
    return 'secondary'
}

function openFinanceSeverity(payer) {
    if (payer.statement_activated === true) return 'success'
    if (payer.statement_activated === false) return 'warn'
    return 'secondary'
}

function isBusy(cnpj) {
    return !!busy.value[cnpj]
}

function setBusy(cnpj, value) {
    busy.value = { ...busy.value, [cnpj]: value }
}

function retry() {
    router.reload({ preserveScroll: true })
}

function verify(payer) {
    setBusy(payer.cpf_cnpj, true)
    router.post('/open-finance/payers/verify', { cpf_cnpj: payer.cpf_cnpj }, {
        preserveScroll: true,
        onFinish: () => setBusy(payer.cpf_cnpj, false),
    })
}

function ensure(payer) {
    setBusy(payer.cpf_cnpj, true)
    router.post('/open-finance/payers/ensure', { cpf_cnpj: payer.cpf_cnpj }, {
        preserveScroll: true,
        onFinish: () => setBusy(payer.cpf_cnpj, false),
    })
}

function formatCheckedAt(iso) {
    if (!iso) return 'Ainda não verificado'
    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return 'Ainda não verificado'
    return date.toLocaleString('pt-BR')
}
</script>

<template>
    <Head title="Open Finance" />
    <AppLayout title="Open Finance">
        <Toast />
        <div class="w-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-4 md:py-6 space-y-6" dusk="open-finance-page">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Open Finance</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Status da integração técnica com a TecnoSpeed (autenticação, comunicação e pagadores).
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
                    <Button
                        type="button"
                        label="Liberar IP na TecnoSpeed"
                        icon="pi pi-external-link"
                        severity="warn"
                    />
                </a>
            </div>

            <section class="space-y-4" dusk="open-finance-payers">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Pagadores Open Finance</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        CNPJs das empresas e filiais do sistema. Verificar consulta a TecnoSpeed; cadastrar/ativar
                        só acontece quando você solicita.
                    </p>
                </div>

                <div
                    v-if="payers.length === 0"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 text-sm text-slate-500 dark:text-slate-400"
                    dusk="open-finance-payers-empty"
                >
                    Nenhum CNPJ elegível encontrado nas empresas/filiais ativas.
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <article
                        v-for="payer in payers"
                        :key="payer.cpf_cnpj"
                        class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4"
                        :dusk="'open-finance-payer-' + payer.cpf_cnpj"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ payer.company_label }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ payer.name }}</div>
                                <div class="text-sm font-mono text-slate-600 dark:text-slate-300 mt-2" dusk="open-finance-payer-cnpj">
                                    {{ payer.cpf_cnpj_masked }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Tag :value="payer.tecnospeed_state_label" :severity="tecnospeedSeverity(payer)" />
                                <Tag :value="'Open Finance: ' + payer.open_finance_label" :severity="openFinanceSeverity(payer)" />
                            </div>
                        </div>

                        <p class="text-sm text-slate-600 dark:text-slate-300 m-0">
                            {{ payer.last_status_label }}
                            <template v-if="payer.last_checked_at">
                                <span class="text-slate-400"> · </span>
                                {{ formatCheckedAt(payer.last_checked_at) }}
                            </template>
                        </p>

                        <p
                            v-if="payer.missing_address"
                            class="text-xs text-amber-700 dark:text-amber-300 m-0"
                        >
                            Cadastro local sem endereço completo (bairro, cidade, UF e CEP). A verificação ainda é possível; o cadastro remoto fica bloqueado até o sync da Senior preencher esses dados.
                        </p>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <Button
                                label="Verificar na TecnoSpeed"
                                icon="pi pi-search"
                                severity="secondary"
                                outlined
                                size="small"
                                :loading="isBusy(payer.cpf_cnpj)"
                                :disabled="isBusy(payer.cpf_cnpj)"
                                dusk="open-finance-payer-verify"
                                @click="verify(payer)"
                            />
                            <Button
                                v-if="can_manage"
                                label="Cadastrar/Ativar Open Finance"
                                icon="pi pi-check"
                                size="small"
                                :loading="isBusy(payer.cpf_cnpj)"
                                :disabled="isBusy(payer.cpf_cnpj)"
                                dusk="open-finance-payer-ensure"
                                @click="ensure(payer)"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
