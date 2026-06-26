<script setup>
import { ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    trails: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    areas: { type: Object, default: () => ({}) },
})

const toast = useToast()
const page = usePage()

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.add({ severity: 'success', summary: 'Salvo', detail: flash.success, life: 3000 })
    if (flash?.error) toast.add({ severity: 'error', summary: 'Erro', detail: flash.error, life: 5000 })
}, { deep: true })

// Flatten all levels for the form
const allLevels = ref(
    props.trails.flatMap(t => t.levels.map(l => ({ id: l.id, default_user_id: l.default_user_id })))
)

function save() {
    const form = useForm({ levels: allLevels.value })
    form.post('/financeiro/fluxos-aprovacao', { preserveScroll: true })
}

function getUserForLevel(levelId) {
    return allLevels.value.find(l => l.id === levelId)?.default_user_id || null
}

function setUserForLevel(levelId, userId) {
    const item = allLevels.value.find(l => l.id === levelId)
    if (item) item.default_user_id = userId
}
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Configurar Fluxos de Aprovação</h1>
                    <p class="text-sm text-gray-500 mt-1">Defina quem aprova em cada nível, por área de origem.</p>
                </div>
                <Button label="Salvar alterações" icon="pi pi-check" @click="save" />
            </div>

            <div class="space-y-6">
                <div v-for="trail in trails" :key="trail.area" class="bg-white rounded-xl border border-gray-100 p-5">
                    <h3 class="text-sm font-bold text-gray-800 mb-1">{{ trail.area_label }}</h3>
                    <p class="text-xs text-gray-400 mb-4">Área: {{ trail.area }}</p>

                    <div class="space-y-3">
                        <div v-for="level in trail.levels" :key="level.id" class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0">
                                {{ level.order }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-500">{{ level.role_label }}</p>
                            </div>
                            <Select
                                :modelValue="getUserForLevel(level.id)"
                                @update:modelValue="setUserForLevel(level.id, $event)"
                                :options="users"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Selecione aprovador..."
                                filter
                                showClear
                                class="w-64"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <Button label="Salvar alterações" icon="pi pi-check" @click="save" />
            </div>
        </div>
    </AppLayout>
</template>
