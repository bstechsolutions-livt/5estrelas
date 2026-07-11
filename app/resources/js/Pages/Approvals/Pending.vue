<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    payables: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
})

const { isMobile } = useDevice()

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}
function goToPayable(id) {
    router.visit(`/financeiro/contas-pagar/${id}`)
}
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Dependências' : undefined">
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-4xl mx-auto'">
            <div class="mb-6">
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    Minhas Dependências
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Títulos aguardando sua aprovação no fluxo do Contas a Pagar.
                </p>
            </div>

            <div v-if="payables.length === 0" class="text-center py-16 text-gray-400">
                <i class="pi pi-check-circle text-4xl mb-3 block"></i>
                <p class="text-lg font-medium">Nenhuma pendência</p>
                <p class="text-sm">Todos os títulos sob sua responsabilidade já foram aprovados.</p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="p in payables" :key="p.id"
                    class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-sm transition-shadow cursor-pointer"
                    @click="goToPayable(p.id)">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ p.supplier_name || p.title_number }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Título: {{ p.title_number }} · Venc: {{ formatDate(p.due_date) }}
                                <span v-if="p.empresa_nome">· {{ p.empresa_nome }}</span>
                            </p>
                            <p v-if="p.preparer" class="text-[10px] text-gray-400 mt-1">
                                Enviado por {{ p.preparer.name }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-800">{{ formatMoney(p.amount) }}</p>
                            <Tag value="Aguardando" severity="warn" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
