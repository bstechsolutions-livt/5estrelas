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

const props = defineProps({
    mode: { type: String, required: true },
    user: Object,
    departments: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.mode === 'edit')

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    is_active: props.user?.is_active ?? true,
    department_id: props.user?.department_id || null,
    branch_ids: props.user?.branch_ids || [],
})

const branchOptions = computed(() =>
    (props.branches || []).map(b => ({ label: b.name, value: b.id })),
)

const departmentOptions = computed(() => [
    { label: 'Sem departamento', value: null },
    ...props.departments.map(d => ({ label: d.name, value: d.id })),
])

function submit() {
    if (isEdit.value) {
        form.put(`/usuarios/${props.user.id}`)
    } else {
        form.post('/usuarios')
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
                    <label class="block text-xs font-medium text-gray-600 mb-1">Filiais liberadas</label>
                    <MultiSelect
                        v-model="form.branch_ids"
                        :options="branchOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Todas (sem restrição)"
                        display="chip"
                        filter
                        class="w-full"
                    />
                    <p class="text-[11px] text-gray-500 mt-1">Vazio = todas. Selecione para restringir o financeiro.</p>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <ToggleSwitch v-model="form.is_active" inputId="active" />
                    <label for="active" class="text-sm font-medium text-gray-700 cursor-pointer">Usuário ativo</label>
                </div>
            </div>
        </form>

        <!-- Botões fixos no rodapé -->
        <div class="fixed left-0 right-0 bottom-0 bg-white border-t border-gray-200 p-3 flex gap-2 z-30" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <Button type="button" label="Cancelar" severity="secondary" outlined class="flex-1" @click="cancel" style="height: 48px" />
            <Button type="button" :label="isEdit ? 'Salvar' : 'Criar'" icon="pi pi-check" :loading="form.processing" class="flex-1" @click="submit" style="height: 48px" />
        </div>
    </AppLayoutMobile>
</template>
