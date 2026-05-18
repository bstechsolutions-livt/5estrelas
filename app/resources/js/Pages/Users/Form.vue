<script setup>
import { computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
    mode: { type: String, required: true }, // 'create' | 'edit'
    user: Object,
})

const isEdit = computed(() => props.mode === 'edit')

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    is_active: props.user?.is_active ?? true,
})

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

                    <div class="flex items-center gap-3 pt-2">
                        <ToggleSwitch v-model="form.is_active" inputId="active" />
                        <label for="active" class="text-sm font-medium text-gray-700 cursor-pointer">
                            Usuário ativo
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="button" label="Cancelar" severity="secondary" outlined @click="cancel" />
                    <Button type="submit" :label="isEdit ? 'Salvar alterações' : 'Criar usuário'" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
