<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
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
function confirmDelete(b) {
    if (confirm(`Excluir "${b.name}"?`)) {
        router.delete(`/filiais/${b.id}`, { preserveScroll: true })
    }
}

function formatCnpj(raw) {
    const d = (raw || '').toString().replace(/\D/g, '').padStart(14, '0')
    if (d.length !== 14) return raw
    return `${d.slice(0,2)}.${d.slice(2,5)}.${d.slice(5,8)}/${d.slice(8,12)}-${d.slice(12)}`
}
</script>

<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Filiais</h1>
                    <p class="text-sm text-gray-500 mt-1">Empresas e filiais do grupo.</p>
                </div>
                <Button label="Nova filial" icon="pi pi-plus" @click="goCreate" />
            </div>

            <div class="mb-4">
                <InputText v-model="search" placeholder="Buscar por nome, CNPJ ou código..." class="w-full max-w-sm" />
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable :value="branches.data" striped-rows>
                    <Column field="code" header="Código" style="width: 80px" sortable />
                    <Column field="name" header="Nome" sortable />
                    <Column field="cnpj" header="CNPJ" style="width: 200px">
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{ formatCnpj(data.cnpj) }}</span>
                        </template>
                    </Column>
                    <Column field="users_count" header="Usuários" style="width: 100px" sortable />
                    <Column field="is_active" header="Status" style="width: 90px">
                        <template #body="{ data }">
                            <Tag :value="data.is_active ? 'Ativo' : 'Inativo'" :severity="data.is_active ? 'success' : 'secondary'" />
                        </template>
                    </Column>
                    <Column header="Ações" style="width: 120px">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <Button icon="pi pi-pencil" severity="secondary" text rounded @click="goEdit(data.id)" />
                                <Button icon="pi pi-trash" severity="danger" text rounded @click="confirmDelete(data)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhuma filial cadastrada.</div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
