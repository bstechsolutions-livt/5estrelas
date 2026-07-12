<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

const props = defineProps({ branches: Object, filters: Object })
const search = ref(props.filters?.search || '')
let timer = null

watch(search, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/filiais', { search: val || undefined }, { preserveState: true, replace: true })
    }, 300)
})

function goCreate() { router.visit('/filiais/criar') }
function goEdit(id) { router.visit(`/filiais/${id}/editar`) }

const actionsOpen = ref(false)
const selected = ref(null)

function openActions(b) { selected.value = b; actionsOpen.value = true }
function confirmDelete() {
    actionsOpen.value = false
    if (selected.value) router.delete(`/filiais/${selected.value.id}`, { preserveScroll: true })
}
</script>

<template>
    <AppLayoutMobile title="Filiais">
        <div class="px-4 pt-3 pb-2">
            <InputText v-model="search" placeholder="Buscar filial..." class="w-full" style="height: 44px" />
        </div>

        <div v-if="branches.data.length" class="px-4 space-y-2 pb-24">
            <button v-for="b in branches.data" :key="b.id" @click="openActions(b)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 text-left active:bg-gray-50 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <i class="pi pi-map-marker text-blue-500"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ b.apelido || b.display_name || b.name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 truncate">
                        <span v-if="b.empresa_apelido">{{ b.empresa_apelido }}</span>
                        <span v-if="b.empresa_apelido && b.cod_emp"> · </span>
                        <span v-if="b.cod_emp && b.cod_fil">Senior {{ b.cod_emp }}/{{ b.cod_fil }}</span>
                        <span v-else-if="!b.empresa_apelido">Cód: {{ b.code || '—' }}</span>
                        · {{ b.users_count }} usuário(s)
                    </p>
                </div>
                <Tag :value="b.is_active ? 'Ativo' : 'Inativo'" :severity="b.is_active ? 'success' : 'secondary'" />
            </button>
        </div>
        <div v-else class="text-center py-12 text-gray-400 text-sm">Nenhuma filial.</div>

        <button @click="goCreate"
            class="fixed right-4 bottom-20 w-14 h-14 rounded-full shadow-lg flex items-center justify-center text-white"
            :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
            <i class="pi pi-plus text-lg"></i>
        </button>

        <BottomSheet v-model="actionsOpen" :title="selected?.name || ''">
            <div class="space-y-1">
                <button @click="goEdit(selected?.id); actionsOpen = false" class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-gray-100 text-left">
                    <i class="pi pi-pencil text-gray-500"></i><span class="text-sm">Editar</span>
                </button>
                <button @click="confirmDelete" class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-red-50 text-left text-red-600">
                    <i class="pi pi-trash"></i><span class="text-sm">Excluir</span>
                </button>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
