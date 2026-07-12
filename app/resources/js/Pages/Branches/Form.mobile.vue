<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'

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
    <AppLayoutMobile :title="isEdit ? 'Editar filial' : 'Nova filial'" show-back>
        <form @submit.prevent="submit" class="px-4 py-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Empresa (grupo)</label>
                <Select v-model="form.cod_emp" :options="empresaOptions" option-label="label" option-value="value"
                    placeholder="Selecione" class="w-full" show-clear @change="onEmpresaChange" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filial Senior</label>
                <Select v-model="form.cod_fil" :options="filialOptions" option-label="label" option-value="value"
                    placeholder="Selecione" class="w-full" show-clear :disabled="!form.cod_emp" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Apelido da filial</label>
                <InputText v-model="form.apelido" class="w-full" style="height: 44px" placeholder="Ex: 5 ESTRELAS GO" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                <InputText v-model="form.name" class="w-full" style="height: 44px" :invalid="!!form.errors.name" />
                <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">{{ form.errors.name }}</small>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                <InputText v-model="form.cnpj" class="w-full" style="height: 44px" placeholder="00.000.000/0000-00" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código interno</label>
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
