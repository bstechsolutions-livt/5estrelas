<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

const props = defineProps({ branch: { type: Object, default: null } })
const isEdit = !!props.branch

const form = useForm({
    name: props.branch?.name || '',
    cnpj: props.branch?.cnpj || '',
    code: props.branch?.code || '',
    is_active: props.branch?.is_active ?? true,
})

function submit() {
    if (isEdit) form.put(`/filiais/${props.branch.id}`)
    else form.post('/filiais')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">{{ isEdit ? 'Editar filial' : 'Nova filial' }}</h1>
            <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!form.errors.name" />
                    <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                        <InputText v-model="form.cnpj" class="w-full" placeholder="00.000.000/0000-00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                        <InputText v-model="form.code" class="w-full" placeholder="Ex: 1, 2, 15..." />
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <ToggleSwitch v-model="form.is_active" />
                    <span class="text-sm text-gray-700">Ativo</span>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <Button label="Cancelar" severity="secondary" type="button" @click="$inertia.visit('/filiais')" />
                    <Button :label="isEdit ? 'Salvar' : 'Criar'" type="submit" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
