<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

const props = defineProps({
    branch: { type: Object, default: null },
    empresaOptions: { type: Array, default: () => [] },
    seniorFiliais: { type: Object, default: () => ({}) },
})

const isEdit = !!props.branch

const form = useForm({
    name: props.branch?.name || '',
    apelido: props.branch?.apelido || '',
    cnpj: props.branch?.cnpj || '',
    code: props.branch?.code || '',
    cod_emp: props.branch?.cod_emp ?? null,
    cod_fil: props.branch?.cod_fil ?? null,
    is_active: props.branch?.is_active ?? true,
})

const filialOptions = computed(() => {
    if (!form.cod_emp) return []
    return props.seniorFiliais[String(form.cod_emp)] || []
})

function onEmpresaChange() {
    form.cod_fil = null
}

function submit() {
    if (isEdit) form.put(`/filiais/${props.branch.id}`)
    else form.post('/filiais')
}
</script>

<template>
    <AppLayout>
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">{{ isEdit ? 'Editar filial' : 'Nova filial' }}</h1>
            <p class="text-sm text-gray-500 mb-6">Vincule a filial operacional à empresa do grupo (Senior) e defina o apelido exibido na intranet.</p>
            <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Empresa (grupo)</label>
                        <Select v-model="form.cod_emp" :options="empresaOptions" option-label="label" option-value="value"
                            placeholder="Selecione a empresa" class="w-full" show-clear @change="onEmpresaChange" />
                        <small v-if="form.errors.cod_emp" class="text-red-500 text-xs mt-1 block">{{ form.errors.cod_emp }}</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filial Senior (cod_fil)</label>
                        <Select v-model="form.cod_fil" :options="filialOptions" option-label="label" option-value="value"
                            placeholder="Selecione a filial" class="w-full" show-clear :disabled="!form.cod_emp" />
                        <small v-if="form.errors.cod_fil" class="text-red-500 text-xs mt-1 block">{{ form.errors.cod_fil }}</small>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Apelido da filial</label>
                    <InputText v-model="form.apelido" class="w-full" placeholder="Ex: 5 ESTRELAS GO" />
                    <small class="text-gray-400 text-xs mt-1 block">Nome curto exibido nas telas (Contas a Pagar, etc.).</small>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!form.errors.name" />
                    <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                        <InputText v-model="form.cnpj" class="w-full" placeholder="00.000.000/0000-00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código interno</label>
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
