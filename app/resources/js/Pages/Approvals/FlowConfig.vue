<script setup>
import { ref, watch, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
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

const editableLevels = computed(() =>
    props.trails.flatMap(t =>
        t.levels
            .filter(l => !l.from_department && !l.from_finance_department)
            .map(l => ({ id: l.id, default_user_id: l.default_user_id }))
    )
)

const allLevels = ref(editableLevels.value.map(l => ({ ...l })))

watch(editableLevels, (levels) => {
    allLevels.value = levels.map(l => ({ ...l }))
}, { deep: true })

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
                    <p class="text-sm text-gray-500 mt-1">
                        Aprovadores fixos por área. Gestor e diretor do departamento são definidos em
                        <a href="/departamentos" class="text-blue-600 hover:underline">Departamentos</a>.
                    </p>
                </div>
                <Button label="Salvar alterações" icon="pi pi-check" @click="save" />
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 text-xs text-blue-800">
                <p class="font-semibold mb-1">Etapas vindas do departamento de quem envia</p>
                <ul class="list-disc pl-4 space-y-0.5 text-blue-700">
                    <li><strong>Departamento (1ª etapa):</strong> gestor cadastrado no departamento do remetente.</li>
                    <li><strong>Diretoria:</strong> diretor do departamento, se configurado; senão o padrão da área abaixo.</li>
                    <li><strong>Financeiro (auditoria):</strong> qualquer usuário vinculado ao departamento <a href="/departamentos" class="underline">Financeiro</a>.</li>
                    <li><strong>Etapas sem aprovador:</strong> são ignoradas automaticamente (ex.: diretoria vazia na config e no departamento).</li>
                </ul>
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
                                <p class="text-xs font-medium text-gray-700">{{ level.role_label }}</p>
                                <p v-if="level.from_department" class="text-[10px] text-gray-400">Vem do cadastro do departamento</p>
                                <p v-else-if="level.department_fallback" class="text-[10px] text-gray-400">Padrão quando o dept. não tem diretor — vazio pula a etapa</p>
                            </div>

                            <div
                                v-if="level.from_department"
                                class="w-64 text-xs text-gray-600 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2"
                                dusk="level-from-department"
                            >
                                <i class="pi pi-building text-gray-400 mr-1" />
                                Gestor do departamento
                            </div>

                            <div
                                v-else-if="level.from_finance_department"
                                class="w-64 text-xs text-gray-600 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2"
                                dusk="level-from-finance-department"
                            >
                                <i class="pi pi-building text-gray-400 mr-1" />
                                Equipe do dept. Financeiro
                            </div>

                            <Select
                                v-else
                                :modelValue="getUserForLevel(level.id)"
                                @update:modelValue="setUserForLevel(level.id, $event)"
                                :options="users"
                                optionLabel="name"
                                optionValue="id"
                                :placeholder="level.department_fallback ? 'Padrão da área...' : 'Selecione aprovador...'"
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
