<script setup>
import { ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    trails: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    approverTypes: { type: Object, default: () => ({}) },
    compositeAreas: { type: Array, default: () => [] },
})

const toast = useToast()
const page = usePage()

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.add({ severity: 'success', summary: 'Salvo', detail: flash.success, life: 3000 })
    if (flash?.error) toast.add({ severity: 'error', summary: 'Erro', detail: flash.error, life: 5000 })
}, { deep: true })

const localTrails = ref(cloneTrails(props.trails))
const deletedAreas = ref([])

watch(() => props.trails, (t) => { localTrails.value = cloneTrails(t) }, { deep: true })

function cloneTrails(trails) {
    return trails.map(t => ({
        ...t,
        levels: t.levels.map(l => ({ ...l })),
        overrides: (t.overrides || []).map(o => ({ ...o })),
    }))
}

const typeOptions = Object.entries(props.approverTypes).map(([value, label]) => ({ value, label }))

function addFlow() {
    const key = `area_${Date.now()}`
    localTrails.value.push({
        area: key,
        area_label: 'Novo fluxo',
        is_composite: false,
        levels: [
            { id: null, order: 1, role_label: 'Departamento', approver_type: 'gestor_depto', default_user_id: null, approver_department_id: null },
        ],
        overrides: [],
    })
}

function removeFlow(index) {
    const trail = localTrails.value[index]
    if (trail.is_composite) return
    if (!confirm(`Excluir o fluxo "${trail.area_label}"?`)) return
    if (!trail.area.startsWith('area_')) {
        deletedAreas.value.push(trail.area)
    }
    localTrails.value.splice(index, 1)
}

function addLevel(trail) {
    const order = trail.levels.length + 1
    trail.levels.push({
        id: null,
        order,
        role_label: `Etapa ${order}`,
        approver_type: 'usuario',
        default_user_id: null,
        approver_department_id: null,
    })
}

function removeLevel(trail, index) {
    trail.levels.splice(index, 1)
    trail.levels.forEach((l, i) => { l.order = i + 1 })
}

function moveLevel(trail, index, dir) {
    const target = index + dir
    if (target < 0 || target >= trail.levels.length) return
    const tmp = trail.levels[index]
    trail.levels[index] = trail.levels[target]
    trail.levels[target] = tmp
    trail.levels.forEach((l, i) => { l.order = i + 1 })
}

function onTypeChange(level) {
    if (level.approver_type !== 'usuario' && level.approver_type !== 'diretor_depto') {
        level.default_user_id = null
    }
    if (level.approver_type !== 'departamento') {
        level.approver_department_id = null
    }
}

function needsUser(type) {
    return type === 'usuario' || type === 'diretor_depto'
}

function needsDepartment(type) {
    return type === 'departamento'
}

function isReadonlyType(type) {
    return type === 'gestor_depto' || type === 'dept_financeiro'
}

function addOverride(trail) {
    if (!trail.overrides) trail.overrides = []
    const firstOrder = trail.levels[0]?.order ?? 1
    trail.overrides.push({
        id: null,
        step_order: firstOrder,
        label: '',
        codccu_text: '',
        title_patterns_text: '',
        approver_user_id: null,
        priority: 0,
        is_active: true,
    })
}

function removeOverride(trail, index) {
    trail.overrides.splice(index, 1)
}

function stepOptions(trail) {
    return trail.levels.map(l => ({
        value: l.order,
        label: `${l.order} — ${l.role_label}`,
    }))
}

function save() {
    const form = useForm({
        trails: localTrails.value
            .filter(t => !t.is_composite)
            .map(t => ({
                area: t.area,
                area_label: t.area_label,
                levels: t.levels.map(l => ({
                    id: l.id,
                    order: l.order,
                    role_label: l.role_label,
                    approver_type: l.approver_type,
                    default_user_id: l.default_user_id,
                    approver_department_id: l.approver_department_id,
                })),
                overrides: (t.overrides || []).map(o => ({
                    id: o.id,
                    step_order: o.step_order,
                    label: o.label || null,
                    codccu_text: o.codccu_text || '',
                    title_patterns_text: o.title_patterns_text || '',
                    approver_user_id: o.approver_user_id,
                    priority: o.priority ?? 0,
                    is_active: o.is_active !== false,
                })),
            })),
        deleted_areas: deletedAreas.value,
    })
    form.post('/financeiro/fluxos-aprovacao', { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Configurar Fluxos de Aprovação</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Crie fluxos por área, defina etapas e escolha o tipo de aprovador.
                        Gestor e diretor do departamento do remetente vêm do cadastro em
                        <a href="/departamentos" class="text-blue-600 hover:underline">Departamentos</a>.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button label="Novo fluxo" icon="pi pi-plus" severity="secondary" outlined @click="addFlow" />
                    <Button label="Salvar alterações" icon="pi pi-check" @click="save" />
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 text-xs text-blue-800">
                <p class="font-semibold mb-1">Tipos de aprovador</p>
                <ul class="list-disc pl-4 space-y-0.5 text-blue-700">
                    <li><strong>Gestor do departamento:</strong> gestor do dept. de quem envia (sem seleção).</li>
                    <li><strong>Diretor do departamento:</strong> diretor do dept. de quem envia; pode ter usuário de fallback.</li>
                    <li><strong>Equipe do Financeiro:</strong> qualquer usuário do dept. Financeiro.</li>
                    <li><strong>Usuário específico:</strong> selecione a pessoa.</li>
                    <li><strong>Departamento específico:</strong> gestor ou equipe do dept. escolhido.</li>
                    <li>Etapas sem aprovador configurado são ignoradas automaticamente (exceto gestor e financeiro).</li>
                    <li><strong>Exceções:</strong> por centro de custo ou padrão no título, troca o aprovador de uma etapa; o restante do fluxo permanece igual.</li>
                </ul>
            </div>

            <div class="space-y-6">
                <div v-for="(trail, tIdx) in localTrails" :key="trail.area" class="bg-white rounded-xl border border-gray-100 p-5">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="flex-1">
                            <InputText
                                v-if="!trail.is_composite"
                                v-model="trail.area_label"
                                class="w-full text-sm font-bold"
                                placeholder="Nome do fluxo"
                            />
                            <h3 v-else class="text-sm font-bold text-gray-800">{{ trail.area_label }}</h3>
                            <p class="text-xs text-gray-400 mt-1">
                                Área: <code>{{ trail.area }}</code>
                                <span v-if="trail.is_composite" class="ml-2 text-amber-600">(composto — não editável)</span>
                            </p>
                        </div>
                        <Button
                            v-if="!trail.is_composite"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            rounded
                            @click="removeFlow(tIdx)"
                            v-tooltip.left="'Excluir fluxo'"
                        />
                    </div>

                    <div v-if="trail.is_composite" class="text-xs text-gray-500 italic">
                        Este fluxo combina outras trilhas automaticamente no envio.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="(level, lIdx) in trail.levels" :key="`${trail.area}-${lIdx}`" class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <Button icon="pi pi-arrow-up" text rounded size="small" :disabled="lIdx === 0" @click="moveLevel(trail, lIdx, -1)" />
                                <Button icon="pi pi-arrow-down" text rounded size="small" :disabled="lIdx === trail.levels.length - 1" @click="moveLevel(trail, lIdx, 1)" />
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">
                                    {{ level.order }}
                                </div>
                            </div>

                            <InputText v-model="level.role_label" class="flex-1 min-w-[140px] text-xs" placeholder="Nome da etapa" />

                            <Select
                                v-model="level.approver_type"
                                :options="typeOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-52"
                                @change="onTypeChange(level)"
                            />

                            <div v-if="isReadonlyType(level.approver_type)" class="w-56 text-xs text-gray-600 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2">
                                <i class="pi pi-building text-gray-400 mr-1" />
                                {{ approverTypes[level.approver_type] }}
                            </div>

                            <Select
                                v-else-if="needsUser(level.approver_type)"
                                v-model="level.default_user_id"
                                :options="users"
                                optionLabel="name"
                                optionValue="id"
                                :placeholder="level.approver_type === 'diretor_depto' ? 'Fallback (opcional)' : 'Selecione o usuário'"
                                filter
                                showClear
                                class="w-56"
                            />

                            <Select
                                v-else-if="needsDepartment(level.approver_type)"
                                v-model="level.approver_department_id"
                                :options="departments"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Selecione o departamento"
                                filter
                                showClear
                                class="w-56"
                            />

                            <Button icon="pi pi-times" text rounded severity="danger" size="small" @click="removeLevel(trail, lIdx)" />
                        </div>

                        <Button label="Adicionar etapa" icon="pi pi-plus" text size="small" @click="addLevel(trail)" />

                        <div class="mt-5 pt-4 border-t border-dashed border-gray-200">
                            <p class="text-xs font-semibold text-gray-700 mb-1">Exceções por centro de custo / título</p>
                            <p class="text-xs text-gray-400 mb-3">
                                Quando o título bater, substitui o aprovador da etapa escolhida. Demais etapas seguem o fluxo normal.
                                Informe ao menos um centro de custo (um por linha) ou padrão no título.
                            </p>

                            <div v-if="!trail.overrides?.length" class="text-xs text-gray-400 italic mb-2">
                                Nenhuma exceção — só o fluxo padrão acima.
                            </div>

                            <div
                                v-for="(rule, rIdx) in trail.overrides"
                                :key="`ov-${trail.area}-${rIdx}`"
                                class="mb-3 p-3 rounded-lg border border-amber-100 bg-amber-50/40 space-y-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Select
                                        v-model="rule.step_order"
                                        :options="stepOptions(trail)"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Etapa"
                                        class="w-44"
                                    />
                                    <InputText v-model="rule.label" class="flex-1 min-w-[120px] text-xs" placeholder="Rótulo (opcional)" />
                                    <Select
                                        v-model="rule.approver_user_id"
                                        :options="users"
                                        optionLabel="name"
                                        optionValue="id"
                                        placeholder="Aprovador alternativo"
                                        filter
                                        class="w-52"
                                    />
                                    <InputText v-model.number="rule.priority" type="number" class="w-20 text-xs" placeholder="Prior." title="Maior = avalia primeiro" />
                                    <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                        <Checkbox v-model="rule.is_active" binary />
                                        Ativa
                                    </label>
                                    <Button icon="pi pi-times" text rounded severity="danger" size="small" @click="removeOverride(trail, rIdx)" />
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-1">Centros de custo (codCcu)</label>
                                        <Textarea v-model="rule.codccu_text" rows="3" class="w-full text-xs" placeholder="6289&#10;2559" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-1">Padrão no título / observação</label>
                                        <Textarea v-model="rule.title_patterns_text" rows="3" class="w-full text-xs" placeholder="58 03&#10;OBRA" />
                                    </div>
                                </div>
                            </div>

                            <Button label="Adicionar exceção" icon="pi pi-sliders-h" text size="small" @click="addOverride(trail)" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button label="Novo fluxo" icon="pi pi-plus" severity="secondary" outlined @click="addFlow" />
                <Button label="Salvar alterações" icon="pi pi-check" @click="save" />
            </div>
        </div>
    </AppLayout>
</template>
