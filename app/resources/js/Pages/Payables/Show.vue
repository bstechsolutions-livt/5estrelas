<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import { useDevice } from '@/composables/useDevice'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'

const props = defineProps({
    payable: Object,
    statusLabels: Object,
    statusColors: Object,
})

const { isMobile } = useDevice()
const page = usePage()
const authUser = page.props.auth?.user

const commentForm = useForm({ body: '' })
const showRejectDialog = ref(false)
const rejectForm = useForm({ reason: '' })

function submitComment() {
    commentForm.post(`/financeiro/contas-pagar/${props.payable.id}/comentarios`, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    })
}

function uploadDoc(event) {
    const file = event.files?.[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    router.post(`/financeiro/contas-pagar/${props.payable.id}/documentos`, formData, {
        preserveScroll: true,
        forceFormData: true,
    })
}

function removeDoc(docId) {
    router.delete(`/financeiro/contas-pagar/${props.payable.id}/documentos/${docId}`, { preserveScroll: true })
}

function sendForApproval() {
    router.post(`/financeiro/contas-pagar/${props.payable.id}/enviar-aprovacao`, {}, { preserveScroll: true })
}

function approve() {
    router.post(`/financeiro/contas-pagar/${props.payable.id}/aprovar`, {}, { preserveScroll: true })
}

function reject() {
    rejectForm.post(`/financeiro/contas-pagar/${props.payable.id}/reprovar`, {
        preserveScroll: true,
        onSuccess: () => { showRejectDialog.value = false; rejectForm.reset() },
    })
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}
function formatDateTime(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

const canPrepare = ['pendente', 'em_preparacao', 'reprovado'].includes(props.payable.status)
const canApprove = props.payable.status === 'aguardando_aprovacao'

function goBack() {
    window.history.back()
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Detalhe' : undefined" :show-back="isMobile">
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-5xl mx-auto'">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <button @click="goBack" class="text-sm text-blue-600 hover:underline mb-2 flex items-center gap-1 cursor-pointer">
                        <i class="pi pi-arrow-left text-xs"></i> Voltar para lista
                    </button>
                    <div class="flex items-center gap-3">
                        <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                            {{ payable.supplier_name }}
                        </h1>
                        <Tag :value="statusLabels[payable.status]" :severity="statusColors[payable.status]" />
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        Título: {{ payable.title_number || '—' }} · Vencimento: {{ formatDate(payable.due_date) }}
                    </p>
                </div>
                <p class="text-xl font-bold text-gray-800">{{ formatMoney(payable.amount) }}</p>
            </div>

            <div :class="isMobile ? 'space-y-4' : (canPrepare || canApprove) ? 'grid grid-cols-3 gap-6' : ''">
                <!-- Coluna principal -->
                <div :class="isMobile ? '' : (canPrepare || canApprove) ? 'col-span-2 space-y-4' : 'space-y-4'">
                    <!-- Info -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Informações</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><p class="text-xs text-gray-500">Filial</p><p class="text-gray-800">{{ payable.branch?.name || '—' }}</p></div>
                            <div><p class="text-xs text-gray-500">Categoria</p><p class="text-gray-800">{{ payable.category || '—' }}</p></div>
                            <div><p class="text-xs text-gray-500">Emissão</p><p class="text-gray-800">{{ formatDate(payable.issue_date) }}</p></div>
                            <div><p class="text-xs text-gray-500">CNPJ</p><p class="text-gray-800 font-mono text-xs">{{ payable.supplier_cnpj || '—' }}</p></div>
                            <div class="col-span-2" v-if="payable.description"><p class="text-xs text-gray-500">Descrição</p><p class="text-gray-800">{{ payable.description }}</p></div>
                        </div>
                    </div>

                    <!-- Documentos -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Documentos ({{ payable.documents?.length || 0 }})</h3>
                        <div v-if="payable.documents?.length" class="space-y-2 mb-3">
                            <div v-for="doc in payable.documents" :key="doc.id"
                                class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i class="pi pi-file text-gray-400"></i>
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-800 truncate">{{ doc.name }}</p>
                                        <p class="text-[11px] text-gray-400">{{ formatSize(doc.size) }} · {{ doc.uploader?.name }}</p>
                                    </div>
                                </div>
                                <button v-if="canPrepare" @click="removeDoc(doc.id)" class="text-red-400 hover:text-red-600 text-xs p-1">
                                    <i class="pi pi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-sm text-gray-400 mb-3">Nenhum documento anexado.</div>
                        <div v-if="canPrepare">
                            <FileUpload mode="basic" :auto="true" choose-label="Anexar documento" accept="*/*"
                                :max-file-size="10485760" @select="uploadDoc" class="w-full" />
                        </div>
                    </div>

                    <!-- Timeline/Comentários -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Timeline</h3>
                        <div class="space-y-3 mb-4 max-h-80 overflow-y-auto">
                            <div v-for="c in payable.comments" :key="c.id" class="flex gap-2">
                                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 text-xs font-semibold text-blue-600">
                                    {{ c.user?.name?.charAt(0) || 'S' }}
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium text-gray-700">{{ c.user?.name || 'Sistema' }}</span>
                                        · {{ formatDateTime(c.created_at) }}
                                    </p>
                                    <p :class="['text-sm mt-0.5', c.type === 'rejection' ? 'text-red-600' : c.type === 'approval' ? 'text-green-600' : 'text-gray-700']">
                                        {{ c.body }}
                                    </p>
                                </div>
                            </div>
                            <p v-if="!payable.comments?.length" class="text-sm text-gray-400">Nenhuma atividade.</p>
                        </div>

                        <!-- Form de comentário -->
                        <form v-if="canPrepare || canApprove" @submit.prevent="submitComment" class="flex gap-2">
                            <Textarea v-model="commentForm.body" placeholder="Escreva um comentário..." rows="2" class="flex-1" />
                            <Button type="submit" icon="pi pi-send" :loading="commentForm.processing" :disabled="!commentForm.body.trim()" />
                        </form>
                    </div>
                </div>

                <!-- Sidebar de ações -->
                <div v-if="canPrepare || canApprove" class="space-y-4">
                    <!-- Ações do preparador -->
                    <div v-if="canPrepare" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Ações</h3>
                        <Button label="Enviar para Aprovação" icon="pi pi-send" class="w-full" @click="sendForApproval" />
                    </div>

                    <!-- Ações do aprovador -->
                    <div v-if="canApprove" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Aprovação</h3>
                        <div class="space-y-2">
                            <Button label="Aprovar" icon="pi pi-check" severity="success" class="w-full" @click="approve" />
                            <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined class="w-full" @click="showRejectDialog = true" />
                        </div>
                    </div>

                    <!-- Dialog de reprovação -->
                    <div v-if="showRejectDialog" class="bg-white rounded-xl border border-red-200 p-4">
                        <h3 class="text-sm font-semibold text-red-700 mb-2">Motivo da reprovação</h3>
                        <Textarea v-model="rejectForm.reason" placeholder="Justifique..." rows="3" class="w-full mb-2" />
                        <div class="flex gap-2">
                            <Button label="Cancelar" severity="secondary" size="small" @click="showRejectDialog = false" class="flex-1" />
                            <Button label="Confirmar" severity="danger" size="small" @click="reject" :disabled="!rejectForm.reason.trim()" class="flex-1" />
                        </div>
                    </div>

                    <!-- Info lateral -->
                    <div v-if="payable.preparer || payable.approver || payable.approved_at" class="bg-white rounded-xl border border-gray-100 p-4 text-sm">
                        <div v-if="payable.preparer" class="mb-2">
                            <p class="text-xs text-gray-500">Preparado por</p>
                            <p class="text-gray-800">{{ payable.preparer.name }}</p>
                        </div>
                        <div v-if="payable.approver" class="mb-2">
                            <p class="text-xs text-gray-500">{{ payable.status === 'aprovado' ? 'Aprovado por' : 'Reprovado por' }}</p>
                            <p class="text-gray-800">{{ payable.approver.name }}</p>
                        </div>
                        <div v-if="payable.approved_at">
                            <p class="text-xs text-gray-500">Data da decisão</p>
                            <p class="text-gray-800">{{ formatDateTime(payable.approved_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
