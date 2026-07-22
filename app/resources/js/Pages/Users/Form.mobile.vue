<script setup>
import { computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'

const props = defineProps({
    mode: { type: String, required: true },
    user: Object,
    departments: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    representativeCandidates: { type: Array, default: () => [] },
    representatives: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.mode === 'edit')

function toDate(value) {
    if (!value) return null
    if (value instanceof Date) return value
    const [y, m, d] = String(value).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, d)
}

function toIsoDate(value) {
    if (!value) return null
    const d = value instanceof Date ? value : toDate(value)
    if (!d || Number.isNaN(d.getTime())) return null
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    is_active: props.user?.is_active ?? true,
    department_id: props.user?.department_id || null,
    senior_cod_usu: props.user?.senior_cod_usu || null,
    branch_ids: props.user?.branch_ids || [],
    extra_department_ids: props.user?.extra_department_ids || [],
    representatives: (props.representatives || []).map((r) => ({
        id: r.id || null,
        representative_id: r.representative_id,
        starts_at: toDate(r.starts_at),
        ends_at: toDate(r.ends_at),
        reason: r.reason || '',
        scopes: r.scopes?.length ? [...r.scopes] : ['financeiro.aprovacao'],
        is_active: r.is_active ?? true,
    })),
})

const branchOptions = computed(() =>
    (props.branches || []).map(b => ({ label: b.name, value: b.id })),
)

const extraDepartmentOptions = computed(() =>
    (props.departments || [])
        .filter(d => d.id !== form.department_id)
        .map(d => ({ label: d.name, value: d.id })),
)

const departmentOptions = computed(() => [
    { label: 'Sem departamento', value: null },
    ...props.departments.map(d => ({ label: d.name, value: d.id })),
])

const candidateOptions = computed(() =>
    (props.representativeCandidates || []).map(u => ({
        label: `${u.name}`,
        value: u.id,
    })),
)

function addRepresentative() {
    form.representatives.push({
        id: null,
        representative_id: null,
        starts_at: new Date(),
        ends_at: null,
        reason: '',
        scopes: ['financeiro.aprovacao'],
        is_active: true,
    })
}

function removeRepresentative(index) {
    form.representatives.splice(index, 1)
}

function submit() {
    const payload = {
        ...form.data(),
        representatives: form.representatives.map((r) => ({
            id: r.id,
            representative_id: r.representative_id,
            starts_at: toIsoDate(r.starts_at),
            ends_at: toIsoDate(r.ends_at),
            reason: r.reason || null,
            scopes: r.scopes?.length ? r.scopes : ['financeiro.aprovacao'],
            is_active: r.is_active ?? true,
        })),
    }

    if (isEdit.value) {
        form.transform(() => payload).put(`/usuarios/${props.user.id}`)
    } else {
        form.transform(() => payload).post('/usuarios')
    }
}

function cancel() {
    router.visit('/usuarios')
}
</script>

<template>
    <AppLayoutMobile :title="isEdit ? 'Editar usuário' : 'Novo usuário'" :show-back="true" :hide-bottom-nav="true">
        <form @submit.prevent="submit" class="space-y-3 pt-3 pb-32">
            <div class="bg-white px-4 py-4 border-y border-gray-200 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                    <InputText v-model="form.name" placeholder="Nome completo" class="w-full" :invalid="!!form.errors.name" style="height: 44px" />
                    <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-mail</label>
                    <InputText v-model="form.email" type="email" placeholder="email@exemplo.com" class="w-full" :invalid="!!form.errors.email" style="height: 44px" />
                    <small v-if="form.errors.email" class="text-red-500 text-xs mt-1 block">{{ form.errors.email }}</small>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Senha
                        <span v-if="isEdit" class="text-gray-400 font-normal">(deixe em branco para manter)</span>
                    </label>
                    <Password
                        v-model="form.password"
                        placeholder="Mínimo 8 caracteres"
                        :feedback="false"
                        toggleMask
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!form.errors.password"
                        :pt="{ pcInputText: { root: { style: 'height: 44px; width: 100%' } } }"
                    />
                    <small v-if="form.errors.password" class="text-red-500 text-xs mt-1 block">{{ form.errors.password }}</small>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Departamento</label>
                    <Select v-model="form.department_id" :options="departmentOptions" option-label="label" option-value="value" placeholder="Selecione..." class="w-full" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cód. usuário Senior</label>
                    <InputText
                        v-model="form.senior_cod_usu"
                        type="number"
                        min="1"
                        placeholder="codUsu"
                        class="w-full"
                        :invalid="!!form.errors.senior_cod_usu"
                        style="height: 44px"
                    />
                    <small v-if="form.errors.senior_cod_usu" class="text-red-500 text-xs mt-1 block">{{ form.errors.senior_cod_usu }}</small>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Filiais liberadas</label>
                    <MultiSelect
                        v-model="form.branch_ids"
                        :options="branchOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Selecione as filiais"
                        display="chip"
                        filter
                        class="w-full"
                    />
                    <p class="text-[11px] text-gray-500 mt-1">Vazio = sem permissão para nenhuma filial.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Departamentos extras (Financeiro)</label>
                    <MultiSelect
                        v-model="form.extra_department_ids"
                        :options="extraDepartmentOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Além do principal"
                        display="chip"
                        filter
                        class="w-full"
                        dusk="user-extra-department-ids"
                    />
                    <p class="text-[11px] text-gray-500 mt-1">Libera visão no Contas a Pagar / Borderôs além do departamento principal.</p>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <ToggleSwitch v-model="form.is_active" inputId="active" />
                    <label for="active" class="text-sm font-medium text-gray-700 cursor-pointer">Usuário ativo</label>
                </div>
            </div>

            <div class="bg-white px-4 py-4 border-y border-gray-200 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Representantes</p>
                        <p class="text-[11px] text-gray-500">Quem age em nome deste usuário (ex.: aprovar no financeiro).</p>
                    </div>
                    <Button type="button" icon="pi pi-plus" rounded text @click="addRepresentative" />
                </div>

                <div
                    v-for="(rep, index) in form.representatives"
                    :key="rep.id || `m-${index}`"
                    class="border border-gray-200 rounded-lg p-3 space-y-2"
                >
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-medium text-gray-600">Representante {{ index + 1 }}</span>
                        <Button type="button" icon="pi pi-trash" severity="danger" text rounded size="small" @click="removeRepresentative(index)" />
                    </div>
                    <Select v-model="rep.representative_id" :options="candidateOptions" option-label="label" option-value="value" placeholder="Pessoa" filter class="w-full" />
                    <div class="grid grid-cols-2 gap-2">
                        <DatePicker v-model="rep.starts_at" date-format="dd/mm/yy" placeholder="Início" class="w-full" show-icon />
                        <DatePicker v-model="rep.ends_at" date-format="dd/mm/yy" placeholder="Fim" class="w-full" show-icon />
                    </div>
                    <MultiSelect v-model="rep.scopes" :options="scopeOptions" option-label="label" option-value="value" placeholder="Escopos" display="chip" class="w-full" />
                    <Textarea v-model="rep.reason" rows="2" class="w-full" placeholder="Motivo" />
                </div>
            </div>
        </form>

        <div class="fixed left-0 right-0 bottom-0 bg-white border-t border-gray-200 p-3 flex gap-2 z-30" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <Button type="button" label="Cancelar" severity="secondary" outlined class="flex-1" @click="cancel" style="height: 48px" />
            <Button type="button" :label="isEdit ? 'Salvar' : 'Criar'" icon="pi pi-check" :loading="form.processing" class="flex-1" @click="submit" style="height: 48px" />
        </div>
    </AppLayoutMobile>
</template>
