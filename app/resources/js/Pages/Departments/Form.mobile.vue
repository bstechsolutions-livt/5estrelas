<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Select from 'primevue/select'

const props = defineProps({
    department: { type: Object, default: null },
    approvalAreas: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
})

const isEdit = !!props.department

const form = useForm({
    name: props.department?.name || '',
    is_active: props.department?.is_active ?? true,
    area_key: props.department?.area_key || null,
    manager_id: props.department?.manager_id || null,
    director_id: props.department?.director_id || null,
})

function submit() {
    if (isEdit) {
        form.put(`/departamentos/${props.department.id}`)
    } else {
        form.post('/departamentos')
    }
}
</script>

<template>
    <AppLayoutMobile :title="isEdit ? 'Editar departamento' : 'Novo departamento'" show-back>
        <form @submit.prevent="submit" class="px-4 py-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <InputText v-model="form.name" class="w-full" style="height: 44px" :invalid="!!form.errors.name" />
                <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
            </div>

            <div class="flex items-center gap-3">
                <ToggleSwitch v-model="form.is_active" />
                <span class="text-sm text-gray-700">Ativo</span>
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-4">
                <h2 class="text-sm font-semibold text-gray-800">Aprovação financeira</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Área de aprovação</label>
                    <Select
                        v-model="form.area_key"
                        :options="approvalAreas"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Área do fluxo..."
                        showClear
                        filter
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gestor / Head</label>
                    <Select
                        v-model="form.manager_id"
                        :options="users"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="1ª etapa..."
                        showClear
                        filter
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diretor</label>
                    <Select
                        v-model="form.director_id"
                        :options="users"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Opcional"
                        showClear
                        filter
                        class="w-full"
                    />
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 rounded-xl text-white font-medium disabled:opacity-50 mt-6"
                :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
            >
                {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar' : 'Criar') }}
            </button>
        </form>
    </AppLayoutMobile>
</template>
