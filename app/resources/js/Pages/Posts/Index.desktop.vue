<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import SelectButton from 'primevue/selectbutton'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
    posts: Object,
    filters: Object,
})

const page = usePage()
const toast = useToast()
const confirm = useConfirm()

const typeOptions = [
    { label: 'Notícias', value: 'news' },
    { label: 'Destaques', value: 'highlight' },
]
const type = ref(props.filters.type || 'news')

watch(type, (val) => {
    router.get('/noticias', { type: val }, { preserveState: true, replace: true })
})

function onPage(event) {
    router.get('/noticias', { type: type.value, per_page: event.rows, page: event.page + 1 }, { preserveState: true })
}

function goCreate() {
    router.visit(`/noticias/criar?type=${type.value}`)
}
function goEdit(id) {
    router.visit(`/noticias/${id}/editar`)
}

function toggleActive(p) {
    router.post(`/noticias/${p.id}/toggle-active`, {}, { preserveScroll: true })
}

function confirmDelete(p) {
    confirm.require({
        message: `Excluir "${p.title}"? Esta ação não pode ser desfeita.`,
        header: 'Confirmação',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Excluir', severity: 'danger' },
        accept: () => router.delete(`/noticias/${p.id}`, { preserveScroll: true }),
    })
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function statusInfo(p) {
    const now = new Date()
    if (!p.is_active) return { label: 'Inativo', severity: 'secondary' }
    if (p.expires_at && new Date(p.expires_at) < now) return { label: 'Expirado', severity: 'warn' }
    if (p.published_at && new Date(p.published_at) > now) return { label: 'Agendado', severity: 'info' }
    return { label: 'Ativo', severity: 'success' }
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
})
</script>

<template>
    <AppLayout>
        <Toast position="top-right" />
        <ConfirmDialog />

        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Notícias e destaques</h1>
                    <p class="text-gray-500 text-sm mt-1">Gerencie o conteúdo do feed</p>
                </div>
                <Button label="Nova postagem" icon="pi pi-plus" @click="goCreate" />
            </div>

            <div class="mb-4">
                <SelectButton v-model="type" :options="typeOptions" option-label="label" option-value="value" />
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <DataTable
                    :value="posts.data"
                    lazy
                    paginator
                    :rows="posts.per_page"
                    :total-records="posts.total"
                    :first="(posts.current_page - 1) * posts.per_page"
                    :rows-per-page-options="[10, 25, 50]"
                    @page="onPage"
                    striped-rows
                    data-key="id"
                >
                    <Column header="Imagem" style="width: 80px">
                        <template #body="{ data }">
                            <div class="w-12 h-12 rounded bg-gray-100 overflow-hidden flex items-center justify-center">
                                <img v-if="data.image_url" :src="data.image_url" class="w-full h-full object-cover" />
                                <i v-else class="pi pi-image text-gray-300"></i>
                            </div>
                        </template>
                    </Column>
                    <Column field="title" header="Título" />
                    <Column header="Status" style="width: 120px">
                        <template #body="{ data }">
                            <Tag :value="statusInfo(data).label" :severity="statusInfo(data).severity" />
                        </template>
                    </Column>
                    <Column header="Publicação" style="width: 160px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ formatDate(data.published_at) }}</span>
                        </template>
                    </Column>
                    <Column header="Engajamento" style="width: 140px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <span><i class="pi pi-heart-fill text-red-400 text-[10px]"></i> {{ data.likes_count }}</span>
                                <span><i class="pi pi-comment text-blue-400 text-[10px]"></i> {{ data.comments_count }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Ações" style="width: 180px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Button icon="pi pi-pencil" severity="secondary" text rounded title="Editar" @click="goEdit(data.id)" />
                                <Button :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check-circle'" severity="secondary" text rounded :title="data.is_active ? 'Inativar' : 'Ativar'" @click="toggleActive(data)" />
                                <Button icon="pi pi-trash" severity="danger" text rounded title="Excluir" @click="confirmDelete(data)" />
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhuma postagem encontrada.</div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
