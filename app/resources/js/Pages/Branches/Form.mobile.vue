<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'

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
    <AppLayoutMobile :title="isEdit ? 'Editar filial' : 'Nova filial'" show-back>
        <form @submit.prevent="submit" class="px-4 py-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <InputText v-model="form.name" class="w-full" style="height: 44px" :invalid="!!form.errors.name" />
                <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                <InputText v-model="form.cnpj" class="w-full" style="height: 44px" placeholder="00.000.000/0000-00" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                <InputText v-model="form.code" class="w-full" style="height: 44px" placeholder="Ex: 1, 2, 15..." />
            </div>
            <div class="flex items-center gap-3">
                <ToggleSwitch v-model="form.is_active" />
                <span class="text-sm text-gray-700">Ativo</span>
            </div>
            <button type="submit" :disabled="form.processing"
                class="w-full py-3 rounded-xl text-white font-medium disabled:opacity-50 mt-6"
                :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar' : 'Criar') }}
            </button>
        </form>
    </AppLayoutMobile>
</template>
