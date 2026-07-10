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
    approvalAreas: { type: Object, default: () => ({}) },
})

function areaLabel(key) {
    if (!key) return '—'
    return props.approvalAreas[key] || key
}

const search = ref(props.filters?.search || '')
let timer = null

const hoverUsers = ref(null)
const hoverStyle = ref({ top: '0px', left: '0px' })
let hideTimer = null

function showUserList(event, dept) {
    if (!dept.users?.length) return
    clearTimeout(hideTimer)
    const rect = event.currentTarget.getBoundingClientRect()
    hoverStyle.value = {
        top: `${rect.bottom + 6}px`,
        left: `${rect.left}px`,
    }
    hoverUsers.value = dept.users
}

function scheduleHideUserList() {
    hideTimer = setTimeout(() => {
        hoverUsers.value = null
    }, 120)
}

function keepUserList() {
    clearTimeout(hideTimer)
}

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
                    <Column header="Área aprovação" style="min-width: 180px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ areaLabel(data.area_key) }}</span>
                        </template>
                    </Column>
                    <Column header="Gestor" style="min-width: 140px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ data.manager?.name || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Usuários" sortable style="width: 120px">
                        <template #body="{ data }">
                            <span
                                class="inline-block min-w-[1.5rem]"
                                :class="data.users_count ? 'cursor-default border-b border-dotted border-gray-400 text-gray-800' : 'text-gray-400'"
                                @mouseenter="showUserList($event, data)"
                                @mouseleave="scheduleHideUserList"
                            >
                                {{ data.users_count }}
                            </span>
                        </template>
                    </Column>
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

        <Teleport to="body">
            <div
                v-if="hoverUsers?.length"
                class="fixed z-[9999] min-w-[10rem] max-w-xs max-h-56 overflow-y-auto rounded-lg bg-gray-900 text-white text-xs py-2 px-3 shadow-lg"
                :style="hoverStyle"
                @mouseenter="keepUserList"
                @mouseleave="scheduleHideUserList"
            >
                <p v-for="user in hoverUsers" :key="user.id" class="leading-5 py-0.5">
                    {{ user.name }}
                </p>
            </div>
        </Teleport>
    </AppLayout>
</template>
