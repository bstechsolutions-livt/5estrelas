<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    targetUser: Object,
    permissions: Object,
    assigned: Array,
})

const page = usePage()
const toast = useToast()

const selected = ref([...props.assigned])

const form = useForm({ permission_ids: selected.value })

const moduleLabels = {
    sistema: 'Sistema',
    usuarios: 'Usuários',
    aparencia: 'Aparência',
    auditoria: 'Auditoria',
    noticias: 'Notícias',
}

function moduleLabel(key) {
    return moduleLabels[key] || key.charAt(0).toUpperCase() + key.slice(1)
}

const wildcardPerm = computed(() => {
    const sistema = props.permissions.sistema || []
    return sistema.find(p => p.key === '*')
})

const hasWildcard = computed(() => {
    return wildcardPerm.value && selected.value.includes(wildcardPerm.value.id)
})

const regularModules = computed(() => Object.keys(props.permissions).filter(m => m !== 'sistema'))

function isChecked(id) {
    return selected.value.includes(id)
}

function toggle(id) {
    if (hasWildcard.value && id !== wildcardPerm.value?.id) return
    const idx = selected.value.indexOf(id)
    if (idx === -1) selected.value.push(id)
    else selected.value.splice(idx, 1)
}

function selectAllModule(module, perms) {
    if (hasWildcard.value) return
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
    <AppLayoutMobile :title="`Permissões`" :show-back="true" :hide-bottom-nav="true">
        <Toast position="top-right" />

        <div class="px-4 pt-3 pb-32">
            <!-- Header com user -->
            <div class="bg-white border border-gray-200 rounded-xl p-3 mb-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-semibold text-gray-700">{{ targetUser.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ targetUser.name }} <span class="text-xs text-gray-400 font-normal">#{{ targetUser.id }}</span></p>
                    <p class="text-xs text-gray-500 truncate">{{ targetUser.email }}</p>
                </div>
            </div>

            <!-- Wildcard (acesso total) -->
            <div
                v-if="wildcardPerm"
                :class="[
                    'rounded-xl border-2 p-4 mb-3 transition-colors',
                    hasWildcard ? 'bg-amber-50 border-amber-300' : 'bg-white border-gray-200'
                ]"
            >
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        :checked="isChecked(wildcardPerm.id)"
                        @change="toggle(wildcardPerm.id)"
                        class="mt-0.5 w-5 h-5 rounded"
                    />
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-shield text-amber-600"></i>
                            <span class="text-sm font-semibold text-gray-800">{{ wildcardPerm.label }}</span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1 leading-snug">
                            {{ wildcardPerm.description || 'Concede acesso total a todas as permissões.' }}
                        </p>
                        <p v-if="hasWildcard" class="text-xs text-amber-700 mt-2 font-medium">
                            ⚠️ Acesso total ativado. Permissões individuais ignoradas.
                        </p>
                    </div>
                </label>
            </div>

            <!-- Módulos regulares -->
            <div
                v-for="module in regularModules"
                :key="module"
                :class="[
                    'rounded-xl border p-4 mb-3 transition-opacity',
                    hasWildcard ? 'bg-gray-50 border-gray-200 opacity-60' : 'bg-white border-gray-200'
                ]"
            >
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        {{ moduleLabel(module) }}
                    </h2>
                    <button
                        v-if="!hasWildcard"
                        type="button"
                        class="text-xs text-blue-600 active:text-blue-800"
                        @click="selectAllModule(module, permissions[module])"
                    >
                        {{ moduleAllSelected(permissions[module]) ? 'Limpar' : 'Marcar todas' }}
                    </button>
                </div>

                <div class="space-y-1">
                    <label
                        v-for="perm in permissions[module]"
                        :key="perm.id"
                        :class="[
                            'flex items-start gap-3 -mx-2 px-2 py-2.5 rounded-lg',
                            hasWildcard ? '' : 'active:bg-gray-100 cursor-pointer'
                        ]"
                    >
                        <input
                            type="checkbox"
                            :checked="hasWildcard || isChecked(perm.id)"
                            :disabled="hasWildcard"
                            @change="toggle(perm.id)"
                            class="mt-0.5 w-5 h-5 rounded flex-shrink-0"
                        />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700">{{ perm.label }}</p>
                            <p v-if="perm.description" class="text-xs text-gray-500 mt-0.5">{{ perm.description }}</p>
                            <code class="text-[10px] text-gray-400">{{ perm.key }}</code>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Footer fixo com ações -->
        <div class="fixed left-0 right-0 bottom-0 bg-white border-t border-gray-200 p-3 flex gap-2 z-30" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button
                @click="cancel"
                class="flex-1 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium"
            >
                Cancelar
            </button>
            <button
                @click="submit"
                :disabled="form.processing"
                class="flex-1 py-3 rounded-lg text-white font-medium disabled:opacity-50"
                :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
            >
                {{ form.processing ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </AppLayoutMobile>
</template>
