<script setup>
import { ref, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    roles: { type: Array, default: () => [] },
    availableUsers: { type: Array, default: () => [] },
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

// userId selecionado por papel (role => id)
const selectedByRole = ref({})
const removing = ref(null)

function roleObj(role) {
    return props.roles.find((r) => r.role === role)
}

// Usuários ainda não associados ao papel (evita duplicar na UI)
function availableFor(role) {
    const assigned = new Set((roleObj(role)?.users || []).map((u) => u.id))
    return (props.availableUsers || []).filter((u) => !assigned.has(u.id))
}

// Forms por papel para adicionar
const forms = {}
function getForm(role) {
    if (!forms[role]) {
        forms[role] = useForm({ role, user_id: null })
    }
    return forms[role]
}

function addUser(role) {
    const userId = selectedByRole.value[role]
    if (!userId) return
    const form = getForm(role)
    form.role = role
    form.user_id = userId
    form.post('/financeiro/contas-pagar/alcada', {
        preserveScroll: true,
        onSuccess: () => {
            selectedByRole.value[role] = null
            form.reset()
        },
    })
}

function removeUser(role, userId) {
    removing.value = `${role}-${userId}`
    router.delete(`/financeiro/contas-pagar/alcada/${role}/${userId}`, {
        preserveScroll: true,
        onFinish: () => { removing.value = null },
    })
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 3000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Alçada' : undefined" :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-4xl mx-auto'" dusk="alcada-page">
            <div class="mb-6">
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    Alçada — Contas a Pagar
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Defina quem ocupa cada papel do fluxo de pagamento. As alterações valem imediatamente.
                </p>
            </div>

            <div class="space-y-4">
                <div v-for="r in roles" :key="r.role" :dusk="`alcada-role-${r.role}`"
                    class="bg-white rounded-xl border border-gray-100 p-4">
                    <div class="mb-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ r.label }}</h3>
                        <p class="text-xs text-gray-500">{{ r.description }}</p>
                    </div>

                    <!-- Responsáveis associados -->
                    <div v-if="r.users.length" class="space-y-2 mb-3">
                        <div v-for="u in r.users" :key="u.id"
                            class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-800 truncate">
                                    {{ u.name }}
                                    <span v-if="!u.is_active" class="text-[11px] text-amber-600 ml-1">(inativo)</span>
                                </p>
                                <p class="text-[11px] text-gray-400 truncate">{{ u.email }}</p>
                            </div>
                            <Button :dusk="`alcada-remove-${r.role}-${u.id}`"
                                icon="pi pi-trash" severity="danger" text rounded size="small"
                                :loading="removing === `${r.role}-${u.id}`"
                                @click="removeUser(r.role, u.id)" title="Remover" />
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 italic mb-3">Sem responsável definido.</div>

                    <!-- Adicionar responsável -->
                    <div class="flex gap-2 items-center">
                        <Select
                            :dusk="`alcada-select-${r.role}`"
                            v-model="selectedByRole[r.role]"
                            :options="availableFor(r.role)"
                            optionLabel="name"
                            optionValue="id"
                            filter
                            placeholder="Selecione um usuário..."
                            class="flex-1"
                        />
                        <Button label="Adicionar" icon="pi pi-plus" size="small"
                            :dusk="`alcada-add-${r.role}`"
                            :loading="getForm(r.role).processing"
                            :disabled="!selectedByRole[r.role]"
                            @click="addUser(r.role)" />
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
