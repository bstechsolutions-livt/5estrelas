<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAuth } from '@/composables/useAuth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

import { branchAccessSummary } from '@/utils/branchAccessLabel'

const props = defineProps({
    users: Object,
    filters: Object,
    totalBranches: { type: Number, default: 0 },
})

const page = usePage()
const toast = useToast()
const confirm = useConfirm()
const { can, user: authUser } = useAuth()

const isImpersonating = computed(() => !!page.props.auth?.impersonator)

const search = ref(props.filters?.search || '')

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/usuarios', { search: val, per_page: props.filters.per_page }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

function onPage(event) {
    router.get('/usuarios', {
        search: search.value,
        per_page: event.rows,
        page: event.page + 1,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function goCreate() { router.visit('/usuarios/criar') }
function goEdit(id) { router.visit(`/usuarios/${id}/editar`) }
function goPermissions(id) { router.visit(`/usuarios/${id}/permissoes`) }

function impersonate(u) {
    confirm.require({
        message: `Entrar no sistema como "${u.name}"? Você verá tudo exatamente como este usuário.`,
        header: 'Entrar como usuário',
        icon: 'pi pi-user',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Entrar como', severity: 'warn' },
        accept: () => {
            router.post(`/usuarios/${u.id}/impersonar`)
        },
    })
}

function toggleActive(u) {
    router.post(`/usuarios/${u.id}/toggle-active`, {}, { preserveScroll: true })
}

function unlockUser(u) {
    router.post(`/usuarios/${u.id}/unlock`, {}, { preserveScroll: true })
}

function confirmDelete(u) {
    confirm.require({
        message: `Excluir o usuário "${u.name}"? Esta ação não pode ser desfeita.`,
        header: 'Confirmação',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Excluir', severity: 'danger' },
        accept: () => {
            router.delete(`/usuarios/${u.id}`, { preserveScroll: true })
        },
    })
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
})
</script>

<template>
    <AppLayout>
        <Toast position="top-right" />
        <ConfirmDialog />

        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Usuários</h1>
                    <p class="text-gray-500 text-sm mt-1">Gerencie usuários e suas permissões</p>
                </div>
                <Button
                    v-if="can('usuarios.criar')"
                    label="Novo usuário"
                    icon="pi pi-plus"
                    @click="goCreate"
                />
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="mb-4">
                    <div class="relative max-w-md">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <InputText
                            v-model="search"
                            placeholder="Buscar por nome ou e-mail..."
                            class="w-full pl-9"
                        />
                    </div>
                </div>

                <DataTable
                    :value="users.data"
                    lazy
                    paginator
                    :rows="users.per_page"
                    :total-records="users.total"
                    :first="(users.current_page - 1) * users.per_page"
                    :rows-per-page-options="[10, 25, 50]"
                    @page="onPage"
                    striped-rows
                    data-key="id"
                    responsive-layout="scroll"
                >
                    <Column field="id" header="ID" style="width: 70px">
                        <template #body="{ data }">
                            <span class="text-xs font-mono text-gray-500">#{{ data.id }}</span>
                        </template>
                    </Column>
                    <Column field="name" header="Nome">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                    <span class="text-xs font-semibold text-gray-700">{{ data.name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <span>{{ data.name }}</span>
                                <i
                                    v-if="!data.senior_cod_usu"
                                    class="pi pi-exclamation-triangle text-amber-500 text-sm shrink-0"
                                    v-tooltip.top="'Código usuário Senior não cadastrado'"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column field="email" header="E-mail" />
                    <Column header="Departamento">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-600">{{ data.department?.name || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Filiais" style="width: 140px">
                        <template #body="{ data }">
                            <template v-if="data.branches?.length">
                                <Tag
                                    :value="branchAccessSummary(data.branches, totalBranches).label"
                                    :severity="branchAccessSummary(data.branches, totalBranches).severity"
                                    class="text-xs whitespace-nowrap max-w-[130px] truncate"
                                    :title="branchAccessSummary(data.branches, totalBranches).title || undefined"
                                />
                            </template>
                            <span v-else class="text-xs text-gray-400">Nenhuma</span>
                        </template>
                    </Column>
                    <Column field="is_active" header="Status">
                        <template #body="{ data }">
                            <Tag :value="data.is_active ? 'Ativo' : 'Inativo'" :severity="data.is_active ? 'success' : 'secondary'" />
                        </template>
                    </Column>
                    <Column header="Ações" style="width: 220px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Button
                                    v-if="can('usuarios.impersonar') && !isImpersonating && data.is_active && data.id !== authUser?.id"
                                    icon="pi pi-sign-in"
                                    severity="warn"
                                    text
                                    rounded
                                    title="Entrar como este usuário"
                                    @click="impersonate(data)"
                                />
                                <Button
                                    v-if="can('usuarios.editar')"
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    text
                                    rounded
                                    title="Editar"
                                    @click="goEdit(data.id)"
                                />
                                <Button
                                    v-if="can('usuarios.gerenciar_permissoes')"
                                    icon="pi pi-key"
                                    severity="secondary"
                                    text
                                    rounded
                                    title="Permissões"
                                    @click="goPermissions(data.id)"
                                />
                                <Button
                                    v-if="can('usuarios.editar') && data.id !== authUser?.id"
                                    :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
                                    severity="secondary"
                                    text
                                    rounded
                                    :title="data.is_active ? 'Desativar' : 'Ativar'"
                                    @click="toggleActive(data)"
                                />
                                <Button
                                    v-if="can('usuarios.editar') && data.locked_until"
                                    icon="pi pi-unlock"
                                    severity="warn"
                                    text
                                    rounded
                                    title="Desbloquear conta"
                                    @click="unlockUser(data)"
                                />
                                <Button
                                    v-if="can('usuarios.excluir') && data.id !== authUser?.id"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    title="Excluir"
                                    @click="confirmDelete(data)"
                                />
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="text-center py-8 text-gray-500">
                            Nenhum usuário encontrado.
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
