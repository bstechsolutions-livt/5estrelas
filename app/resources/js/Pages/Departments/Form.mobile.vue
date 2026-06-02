<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
    department: { type: Object, default: null },
})

const isEdit = !!props.department

const form = useForm({
    name: props.department?.name || '',
    is_active: props.department?.is_active ?? true,
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
