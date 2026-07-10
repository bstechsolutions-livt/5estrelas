<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Select from 'primevue/select'
import Button from 'primevue/button'

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

                <div class="border-t border-gray-100 pt-5 space-y-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800">Aprovação financeira</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            Gestor = 1ª aprovação (gerência). Diretor = etapa diretoria. Configure conforme o organograma.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Área de aprovação</label>
                        <Select
                            v-model="form.area_key"
                            :options="approvalAreas"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Selecione a área do fluxo..."
                            showClear
                            filter
                            class="w-full"
                        />
                        <small v-if="form.errors.area_key" class="text-red-500 text-xs mt-1 block">{{ form.errors.area_key }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gerência / Head (1ª aprovação)</label>
                        <Select
                            v-model="form.manager_id"
                            :options="users"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Quem aprova primeiro nesta área..."
                            showClear
                            filter
                            class="w-full"
                        />
                        <small class="text-gray-400 text-xs mt-1 block">Ex.: Erismar (Compras/Matriz), Cilas (Filiais), Leiliane (Comercial).</small>
                        <small v-if="form.errors.manager_id" class="text-red-500 text-xs mt-1 block">{{ form.errors.manager_id }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Diretor (etapa diretoria)</label>
                        <Select
                            v-model="form.director_id"
                            :options="users"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Opcional — usa o padrão da área se vazio"
                            showClear
                            filter
                            class="w-full"
                        />
                        <small v-if="form.errors.director_id" class="text-red-500 text-xs mt-1 block">{{ form.errors.director_id }}</small>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button label="Cancelar" severity="secondary" type="button" @click="$inertia.visit('/departamentos')" />
                    <Button :label="isEdit ? 'Salvar' : 'Criar'" type="submit" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
