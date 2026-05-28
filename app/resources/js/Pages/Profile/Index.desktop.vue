<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
    profile: Object,
})

const page = usePage()
const toast = useToast()
const confirm = useConfirm()

const avatarPreview = ref(props.profile.avatar_url)

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
    avatar: null,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const initials = computed(() => {
    const parts = (props.profile.name || '').split(' ').filter(Boolean)
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
    return (props.profile.name || '?').substring(0, 2).toUpperCase()
})

function handleAvatarFile(event) {
    const file = event.target.files[0]
    if (!file) return
    profileForm.avatar = file
    const reader = new FileReader()
    reader.onload = (e) => {
        avatarPreview.value = e.target.result
    }
    reader.readAsDataURL(file)
}

function submitProfile() {
    profileForm.post('/perfil', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            profileForm.avatar = null
        },
    })
}

function submitPassword() {
    passwordForm.put('/perfil/senha', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
        },
    })
}

function confirmRemoveAvatar() {
    confirm.require({
        message: 'Deseja remover sua foto de perfil?',
        header: 'Remover foto',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Remover', severity: 'danger' },
        accept: () => {
            router.delete('/perfil/avatar', {
                preserveScroll: true,
                onSuccess: () => {
                    avatarPreview.value = null
                },
            })
        },
    })
}

function logout() {
    router.post('/logout')
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
})
</script>

<template>
    <AppLayout>
        <Toast position="top-right" />
        <ConfirmDialog />

        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">Meu perfil</h1>
                <p class="text-gray-500 text-sm mt-1">Gerencie suas informações pessoais e segurança</p>
            </div>

            <div class="space-y-6">
                <!-- Informações pessoais -->
                <form @submit.prevent="submitProfile" class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Informações pessoais
                        </h2>
                        <span class="text-xs text-gray-500">ID: <span class="font-mono font-semibold text-gray-700">#{{ profile.id }}</span></span>
                    </div>

                    <div class="flex items-start gap-6 mb-6">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-24 h-24 rounded-full overflow-hidden border-2 border-gray-200 flex items-center justify-center" :style="{ backgroundColor: avatarPreview ? 'transparent' : 'var(--app-primary, #3b82f6)' }">
                                <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar" class="w-full h-full object-cover" />
                                <span v-else class="text-white font-bold text-2xl">{{ initials }}</span>
                            </div>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                ref="avatarInput"
                                @change="handleAvatarFile"
                            />
                            <div class="flex flex-col gap-1 items-center">
                                <button
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="$refs.avatarInput.click()"
                                >
                                    {{ avatarPreview ? 'Trocar foto' : 'Adicionar foto' }}
                                </button>
                                <button
                                    v-if="avatarPreview && profile.avatar_url"
                                    type="button"
                                    class="text-xs text-red-500 hover:underline"
                                    @click="confirmRemoveAvatar"
                                >
                                    Remover foto
                                </button>
                            </div>
                            <small class="text-xs text-gray-400 text-center">JPG, PNG ou WebP — máx 10MB</small>
                        </div>

                        <div class="flex-1 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nome</label>
                                <InputText
                                    v-model="profileForm.name"
                                    class="w-full"
                                    :invalid="!!profileForm.errors.name"
                                />
                                <small v-if="profileForm.errors.name" class="text-red-500 text-xs mt-1 block">{{ profileForm.errors.name }}</small>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                                <InputText
                                    v-model="profileForm.email"
                                    type="email"
                                    class="w-full"
                                    :invalid="!!profileForm.errors.email"
                                />
                                <small v-if="profileForm.errors.email" class="text-red-500 text-xs mt-1 block">{{ profileForm.errors.email }}</small>
                            </div>

                            <small v-if="profileForm.errors.avatar" class="text-red-500 text-xs block">{{ profileForm.errors.avatar }}</small>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            label="Salvar alterações"
                            icon="pi pi-check"
                            :loading="profileForm.processing"
                        />
                    </div>
                </form>

                <!-- Trocar senha -->
                <form @submit.prevent="submitPassword" class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-5">
                        Trocar senha
                    </h2>

                    <div class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Senha atual</label>
                            <Password
                                v-model="passwordForm.current_password"
                                :feedback="false"
                                toggleMask
                                class="w-full"
                                inputClass="w-full"
                                :invalid="!!passwordForm.errors.current_password"
                            />
                            <small v-if="passwordForm.errors.current_password" class="text-red-500 text-xs mt-1 block">{{ passwordForm.errors.current_password }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nova senha</label>
                            <Password
                                v-model="passwordForm.password"
                                :feedback="true"
                                toggleMask
                                class="w-full"
                                inputClass="w-full"
                                :invalid="!!passwordForm.errors.password"
                                promptLabel="Digite a nova senha"
                                weakLabel="Fraca"
                                mediumLabel="Média"
                                strongLabel="Forte"
                            />
                            <small v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1 block">{{ passwordForm.errors.password }}</small>
                            <small v-else class="text-gray-500 text-xs mt-1 block">Mínimo 8 caracteres, diferente da senha atual</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmar nova senha</label>
                            <Password
                                v-model="passwordForm.password_confirmation"
                                :feedback="false"
                                toggleMask
                                class="w-full"
                                inputClass="w-full"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <Button
                            type="submit"
                            label="Atualizar senha"
                            icon="pi pi-key"
                            :loading="passwordForm.processing"
                        />
                    </div>
                </form>

                <!-- Conta -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-5">
                        Conta
                    </h2>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Status</span>
                            <Tag :value="profile.is_active ? 'Ativo' : 'Inativo'" :severity="profile.is_active ? 'success' : 'secondary'" />
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Permissões</span>
                            <span class="text-gray-700 font-medium">
                                {{ profile.permissions.includes('*') ? 'Acesso total (admin)' : `${profile.permissions.length} permissão(ões)` }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-5 flex justify-end">
                        <Button
                            label="Sair da conta"
                            icon="pi pi-sign-out"
                            severity="danger"
                            outlined
                            @click="logout"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
