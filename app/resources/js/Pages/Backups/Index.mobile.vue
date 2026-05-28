<script setup>
import { ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'

const props = defineProps({
    backups: Array,
    config: Object,
})

const page = usePage()
const toast = useToast()
const runForm = useForm({})

function runBackup() {
    runForm.post('/backups/run', {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['backups'] })
        },
    })
}

function downloadBackup(filename) {
    window.location.href = `/backups/${encodeURIComponent(filename)}/download`
}

const actionsOpen = ref(false)
const selected = ref(null)

function openActions(b) {
    selected.value = b
    actionsOpen.value = true
}

function confirmDelete() {
    if (!selected.value) return
    actionsOpen.value = false
    confirmOpen.value = true
}

const confirmOpen = ref(false)

function doDelete() {
    if (!selected.value) return
    router.delete(`/backups/${encodeURIComponent(selected.value.name)}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false
            selected.value = null
        },
    })
}

function formatDate(iso) {
    const d = new Date(iso)
    return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'OK', detail: msg, life: 4000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
</script>

<template>
    <AppLayoutMobile title="Backups">
        <Toast position="top-center" />

        <div class="px-4 py-3">
            <p class="text-xs text-gray-500 leading-relaxed">
                Agenda: <strong class="text-gray-700">{{ config.schedule }}</strong> ·
                retenção <strong class="text-gray-700">{{ config.retention_days }} dias</strong>
            </p>
        </div>

        <div v-if="backups.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
            <i class="pi pi-database text-4xl text-gray-300 block mb-3"></i>
            Nenhum backup ainda.
            <p class="mt-1 text-xs">Toque no botão abaixo para criar um agora.</p>
        </div>

        <div v-else class="px-4 space-y-2 pb-24">
            <button
                v-for="b in backups"
                :key="b.name"
                @click="openActions(b)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 text-left active:bg-gray-50 flex items-center gap-3"
            >
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <i class="pi pi-database text-blue-500"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ b.name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ formatDate(b.created_at) }} · {{ b.size_human }}
                    </p>
                </div>
                <i class="pi pi-chevron-right text-gray-300 text-xs"></i>
            </button>
        </div>

        <!-- Botão fixo "Executar agora" -->
        <div class="fixed left-0 right-0 bottom-16 z-30 px-4 pb-3" style="padding-bottom: calc(env(safe-area-inset-bottom) + 64px);">
            <button
                @click="runBackup"
                :disabled="runForm.processing"
                class="w-full h-12 rounded-xl text-white font-medium shadow-lg active:scale-95 transition-transform disabled:opacity-50 flex items-center justify-center gap-2"
                :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
            >
                <i :class="['pi', runForm.processing ? 'pi-spin pi-spinner' : 'pi-play']"></i>
                {{ runForm.processing ? 'Executando...' : 'Executar agora' }}
            </button>
        </div>

        <!-- Bottom sheet de ações -->
        <BottomSheet v-model="actionsOpen" :title="selected?.name">
            <div v-if="selected" class="space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-500">Tamanho</p>
                        <p class="font-medium text-gray-800">{{ selected.size_human }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Data</p>
                        <p class="font-medium text-gray-800">{{ formatDate(selected.created_at) }}</p>
                    </div>
                </div>

                <button
                    @click="downloadBackup(selected.name); actionsOpen = false"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-lg text-white font-medium"
                    :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
                >
                    <i class="pi pi-download"></i> Baixar
                </button>
                <button
                    @click="confirmDelete"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-lg border border-red-200 text-red-600 font-medium active:bg-red-50"
                >
                    <i class="pi pi-trash"></i> Excluir
                </button>
            </div>
        </BottomSheet>

        <!-- Confirmação de exclusão -->
        <BottomSheet v-model="confirmOpen" title="Excluir backup?">
            <p class="text-sm text-gray-600 mb-4">
                Esta ação não pode ser desfeita. O arquivo
                <strong class="text-gray-800">{{ selected?.name }}</strong>
                será removido do servidor.
            </p>
            <div class="flex gap-2">
                <button
                    @click="confirmOpen = false"
                    class="flex-1 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium"
                >
                    Cancelar
                </button>
                <button
                    @click="doDelete"
                    class="flex-1 py-3 rounded-lg bg-red-500 text-white font-medium active:bg-red-600"
                >
                    Excluir
                </button>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
