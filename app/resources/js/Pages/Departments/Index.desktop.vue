<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

const props = defineProps({
    departments: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
let timer = null

watch(search, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/departamentos', { search: val || undefined }, { preserveState: true, replace: true })
    }, 300)
})

function goCreate() { router.visit('/departamentos/criar') }
function goEdit(id) { router.visit(`/departamentos/${id}/editar`) }
function confirmDelete(dept) {
    if (confirm(`Excluir "${dept.name}"? Usuários vinculados ficarão sem departamento.`)) {
        router.delete(`/departamentos/${dept.id}`, { preserveScroll: true })
    }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Departamentos</h1>
                    <p class="text-sm text-gray-500 mt-1">Gerencie os departamentos da empresa.</p>
                </div>
                <Button label="Novo departamento" icon="pi pi-plus" @click="goCreate" />
            </div>

            <div class="mb-4">
                <InputText v-model="search" placeholder="Buscar..." class="w-full max-w-xs" />
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable :value="departments.data" striped-rows>
                    <Column field="name" header="Nome" sortable />
                    <Column field="users_count" header="Usuários" sortable style="width: 120px" />
                    <Column field="is_active" header="Status" style="width: 100px">
                        <template #body="{ data }">
                            <Tag :value="data.is_active ? 'Ativo' : 'Inativo'" :severity="data.is_active ? 'success' : 'secondary'" />
                        </template>
                    </Column>
                    <Column header="Ações" style="width: 150px">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <Button icon="pi pi-pencil" severity="secondary" text rounded title="Editar" @click="goEdit(data.id)" />
                                <Button icon="pi pi-trash" severity="danger" text rounded title="Excluir" @click="confirmDelete(data)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhum departamento cadastrado.</div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
