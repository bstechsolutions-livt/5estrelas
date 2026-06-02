<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

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
    <AppLayout>
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">
                {{ isEdit ? 'Editar departamento' : 'Novo departamento' }}
            </h1>

            <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!form.errors.name" />
                    <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
                </div>

                <div class="flex items-center gap-3">
                    <ToggleSwitch v-model="form.is_active" />
                    <span class="text-sm text-gray-700">Ativo</span>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button label="Cancelar" severity="secondary" type="button" @click="$inertia.visit('/departamentos')" />
                    <Button :label="isEdit ? 'Salvar' : 'Criar'" type="submit" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
