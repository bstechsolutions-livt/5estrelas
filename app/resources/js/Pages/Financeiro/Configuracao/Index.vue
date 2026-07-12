<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    items: { type: Array, default: () => [] },
})

function go(path) {
    router.visit(path)
}
</script>

<template>
    <Head title="Configuração Financeiro" />
    <AppLayout title="Configuração">
        <div class="p-4 md:p-6 max-w-5xl mx-auto space-y-6" dusk="financeiro-configuracao-page">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Configuração</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Parâmetros e regras do módulo financeiro.
                </p>
            </div>

            <div v-if="items.length" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button
                    v-for="item in items"
                    :key="item.key"
                    type="button"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 text-left hover:border-blue-400 dark:hover:border-blue-500 transition-colors group"
                    :dusk="`config-link-${item.key}`"
                    @click="go(item.href)"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors">
                            <i :class="item.icon" class="text-lg" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ item.label }}</div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ item.description }}</p>
                        </div>
                        <i class="pi pi-chevron-right text-slate-300 dark:text-slate-600 group-hover:text-blue-400 transition-colors mt-1" />
                    </div>
                </button>
            </div>

            <div v-else class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-8 text-center text-slate-500">
                Nenhuma configuração disponível para o seu perfil.
            </div>
        </div>
    </AppLayout>
</template>
