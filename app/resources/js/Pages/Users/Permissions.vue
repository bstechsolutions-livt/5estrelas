<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    targetUser: Object,
    permissions: Object, // grouped by module
    assigned: Array,     // ids assigned
})

const page = usePage()
const toast = useToast()

const selected = ref([...props.assigned])

const form = useForm({
    permission_ids: selected.value,
})

const moduleLabels = {
    sistema: 'Sistema',
    usuarios: 'Usuários',
    aparencia: 'Aparência',
}

function moduleLabel(key) {
    return moduleLabels[key] || key.charAt(0).toUpperCase() + key.slice(1)
}

// Identifica a permissão curinga (*)
const wildcardPerm = computed(() => {
    const sistema = props.permissions.sistema || []
    return sistema.find(p => p.key === '*')
})

const hasWildcard = computed(() => {
    return wildcardPerm.value && selected.value.includes(wildcardPerm.value.id)
})

// Módulos que NÃO sejam "sistema" (a wildcard fica em destaque separado)
const regularModules = computed(() => {
    return Object.keys(props.permissions).filter(m => m !== 'sistema')
})

function isChecked(id) {
    return selected.value.includes(id)
}

function toggle(id) {
    const idx = selected.value.indexOf(id)
    if (idx === -1) selected.value.push(id)
    else selected.value.splice(idx, 1)
}

function selectAllModule(module, perms) {
    if (hasWildcard.value) return // bloqueado quando admin
    const ids = perms.map(p => p.id)
    const allSelected = ids.every(id => selected.value.includes(id))
    if (allSelected) {
        selected.value = selected.value.filter(id => !ids.includes(id))
    } else {
        ids.forEach(id => {
            if (!selected.value.includes(id)) selected.value.push(id)
        })
    }
}

function moduleAllSelected(perms) {
    return perms.every(p => selected.value.includes(p.id))
}

function submit() {
    form.permission_ids = selected.value
    form.put(`/usuarios/${props.targetUser.id}/permissoes`, {
        preserveScroll: true,
    })
}

function cancel() {
    router.visit('/usuarios')
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

        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">Permissões de {{ targetUser.name }}</h1>
                <p class="text-gray-500 text-sm mt-1">{{ targetUser.email }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Acesso total (curinga *) - destaque especial -->
                <div
                    v-if="wildcardPerm"
                    :class="[
                        'rounded-xl border-2 p-6 transition-colors',
                        hasWildcard ? 'bg-amber-50 border-amber-300' : 'bg-white border-gray-200'
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <Checkbox
                            :model-value="isChecked(wildcardPerm.id)"
                            :binary="true"
                            @update:model-value="toggle(wildcardPerm.id)"
                            input-id="wildcard-perm"
                        />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-shield text-amber-600"></i>
                                <label for="wildcard-perm" class="text-base font-semibold text-gray-800 cursor-pointer">
                                    {{ wildcardPerm.label }}
                                </label>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">
                                {{ wildcardPerm.description || 'Concede acesso total a todas as permissões do sistema.' }}
                            </p>
                            <p v-if="hasWildcard" class="text-xs text-amber-700 mt-2 font-medium">
                                ⚠️ Este usuário tem acesso total. Permissões individuais abaixo ficam ignoradas.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Permissões individuais por módulo -->
                <div
                    v-for="module in regularModules"
                    :key="module"
                    :class="[
                        'rounded-xl border p-6 transition-opacity',
                        hasWildcard ? 'bg-gray-50 border-gray-200 opacity-60' : 'bg-white border-gray-200'
                    ]"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            {{ moduleLabel(module) }}
                        </h2>
                        <button
                            v-if="!hasWildcard"
                            type="button"
                            class="text-xs text-blue-600 hover:underline"
                            @click="selectAllModule(module, permissions[module])"
                        >
                            {{ moduleAllSelected(permissions[module]) ? 'Desmarcar todas' : 'Marcar todas' }}
                        </button>
                    </div>

                    <div class="space-y-3">
                        <label
                            v-for="perm in permissions[module]"
                            :key="perm.id"
                            :class="[
                                'flex items-start gap-3 -mx-2 px-2 py-2 rounded transition-colors',
                                hasWildcard ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50'
                            ]"
                        >
                            <Checkbox
                                :model-value="hasWildcard || isChecked(perm.id)"
                                :binary="true"
                                :disabled="hasWildcard"
                                @update:model-value="toggle(perm.id)"
                                :input-id="`perm-${perm.id}`"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700">{{ perm.label }}</p>
                                <p v-if="perm.description" class="text-xs text-gray-500 mt-0.5">{{ perm.description }}</p>
                                <code class="text-xs text-gray-400">{{ perm.key }}</code>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="button" label="Cancelar" severity="secondary" outlined @click="cancel" />
                    <Button type="submit" label="Salvar permissões" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
