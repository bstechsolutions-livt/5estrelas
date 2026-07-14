<script setup>
import { computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'

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
    representatives: (props.representatives || []).map((r) => ({
        id: r.id || null,
        representative_id: r.representative_id,
        starts_at: toDate(r.starts_at),
        ends_at: toDate(r.ends_at),
        reason: r.reason || '',
        scopes: r.scopes?.length ? [...r.scopes] : ['financeiro.aprovacao'],
        is_active: r.is_active ?? true,
        currently_active: r.currently_active ?? false,
    })),
})

const branchOptions = computed(() =>
    (props.branches || []).map(b => ({ label: b.name, value: b.id })),
)

const departmentOptions = computed(() => [
    { label: 'Sem departamento', value: null },
    ...props.departments.map(d => ({ label: d.name, value: d.id })),
])

const candidateOptions = computed(() =>
    (props.representativeCandidates || []).map(u => ({
        label: `${u.name} (${u.email})`,
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
        currently_active: false,
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
    <AppLayout>
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">
                    {{ isEdit ? 'Editar usuário' : 'Novo usuário' }}
                </h1>
                <p class="text-gray-500 text-sm mt-1">
                    {{ isEdit ? 'Atualize os dados do usuário' : 'Cadastre um novo usuário no sistema' }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nome</label>
                        <InputText
                            v-model="form.name"
                            placeholder="Nome completo"
                            class="w-full"
                            :invalid="!!form.errors.name"
                        />
                        <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                        <InputText
                            v-model="form.email"
                            type="email"
                            placeholder="email@exemplo.com"
                            class="w-full"
                            :invalid="!!form.errors.email"
                        />
                        <small v-if="form.errors.email" class="text-red-500 text-xs mt-1 block">{{ form.errors.email }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Senha
                            <span v-if="isEdit" class="text-xs text-gray-500 font-normal">(deixe em branco para manter a atual)</span>
                        </label>
                        <Password
                            v-model="form.password"
                            placeholder="Mínimo 8 caracteres"
                            :feedback="false"
                            toggleMask
                            class="w-full"
                            inputClass="w-full"
                            :invalid="!!form.errors.password"
                        />
                        <small v-if="form.errors.password" class="text-red-500 text-xs mt-1 block">{{ form.errors.password }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Departamento</label>
                        <Select
                            v-model="form.department_id"
                            :options="departmentOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Selecione..."
                            class="w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Código usuário Senior (codUsu)</label>
                        <InputText
                            v-model="form.senior_cod_usu"
                            type="number"
                            min="1"
                            placeholder="Ex.: 42"
                            class="w-full"
                            :invalid="!!form.errors.senior_cod_usu"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            Usado para classificar títulos CP pelo usuário que lançou na Senior. Preencha manualmente se o backfill automático não encontrar.
                        </p>
                        <small v-if="form.errors.senior_cod_usu" class="text-red-500 text-xs mt-1 block">{{ form.errors.senior_cod_usu }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Filiais liberadas</label>
                        <MultiSelect
                            v-model="form.branch_ids"
                            :options="branchOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Selecione as filiais liberadas"
                            display="chip"
                            filter
                            class="w-full"
                            dusk="user-branch-ids"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            Selecione as filiais que o usuário pode acessar no financeiro e solicitações. Vazio = sem permissão para nenhuma filial.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <ToggleSwitch v-model="form.is_active" inputId="active" />
                        <label for="active" class="text-sm font-medium text-gray-700 cursor-pointer">
                            Usuário ativo
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-800">Representantes</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Quem pode agir em nome deste usuário no período informado. Hoje vale para aprovar no Financeiro; outros módulos entram depois no mesmo cadastro.
                            </p>
                        </div>
                        <Button type="button" label="Adicionar" icon="pi pi-plus" size="small" outlined @click="addRepresentative" />
                    </div>

                    <div v-if="!form.representatives.length" class="text-sm text-gray-500 border border-dashed border-gray-200 rounded-lg px-4 py-6 text-center">
                        Nenhum representante cadastrado.
                    </div>

                    <div
                        v-for="(rep, index) in form.representatives"
                        :key="rep.id || `new-${index}`"
                        class="border border-gray-200 rounded-lg p-4 space-y-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">Representante {{ index + 1 }}</span>
                                <Tag v-if="rep.currently_active" value="Vigente agora" severity="success" />
                            </div>
                            <Button type="button" icon="pi pi-trash" severity="danger" text rounded @click="removeRepresentative(index)" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Pessoa</label>
                            <Select
                                v-model="rep.representative_id"
                                :options="candidateOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Selecione o representante"
                                filter
                                class="w-full"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Início</label>
                                <DatePicker v-model="rep.starts_at" date-format="dd/mm/yy" class="w-full" show-icon />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fim</label>
                                <DatePicker v-model="rep.ends_at" date-format="dd/mm/yy" class="w-full" show-icon :min-date="rep.starts_at || undefined" />
                                <p class="text-xs text-gray-500 mt-1">Vazio = sem data final.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Escopos</label>
                            <MultiSelect
                                v-model="rep.scopes"
                                :options="scopeOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Onde o representante pode agir"
                                display="chip"
                                class="w-full"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Motivo</label>
                            <Textarea v-model="rep.reason" rows="2" class="w-full" placeholder="Ex.: férias, viagem, afastamento..." />
                        </div>

                        <div class="flex items-center gap-3">
                            <ToggleSwitch v-model="rep.is_active" :inputId="`rep-active-${index}`" />
                            <label :for="`rep-active-${index}`" class="text-sm font-medium text-gray-700 cursor-pointer">Ativo</label>
                        </div>
                    </div>

                    <small v-if="form.errors.representatives" class="text-red-500 text-xs block">{{ form.errors.representatives }}</small>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="button" label="Cancelar" severity="secondary" outlined @click="cancel" />
                    <Button type="submit" :label="isEdit ? 'Salvar alterações' : 'Criar usuário'" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
