<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
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
    reader.onload = (e) => { avatarPreview.value = e.target.result }
    reader.readAsDataURL(file)
}

function submitProfile() {
    profileForm.post('/perfil', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { profileForm.avatar = null },
    })
}

function submitPassword() {
    passwordForm.put('/perfil/senha', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
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
                onSuccess: () => { avatarPreview.value = null },
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
    <AppLayoutMobile title="Meu perfil">
        <Toast position="top-right" />
        <ConfirmDialog />

        <div class="space-y-3 pt-3">
            <!-- Card avatar centralizado -->
            <div class="bg-white px-4 py-6 flex flex-col items-center border-y border-gray-200">
                <div class="w-24 h-24 rounded-full overflow-hidden border-2 border-gray-200 flex items-center justify-center mb-3"
                     :style="{ backgroundColor: avatarPreview ? 'transparent' : 'var(--app-primary, #3b82f6)' }">
                    <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar" class="w-full h-full object-cover" />
                    <span v-else class="text-white font-bold text-3xl">{{ initials }}</span>
                </div>

                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    ref="avatarInput"
                    @change="handleAvatarFile"
                />

                <div class="flex gap-2">
                    <button
                        class="text-sm px-3 py-1.5 rounded-md text-blue-600 active:bg-blue-50"
                        @click="$refs.avatarInput.click()"
                    >
                        {{ avatarPreview ? 'Trocar foto' : 'Adicionar foto' }}
                    </button>
                    <button
                        v-if="avatarPreview && profile.avatar_url"
                        class="text-sm px-3 py-1.5 rounded-md text-red-500 active:bg-red-50"
                        @click="confirmRemoveAvatar"
                    >
                        Remover
                    </button>
                </div>
                <small class="text-xs text-gray-400 mt-1">JPG, PNG ou WebP — máx 10MB</small>
            </div>

            <!-- Form dados pessoais -->
            <form @submit.prevent="submitProfile" class="bg-white p-4 border-y border-gray-200 space-y-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Informações pessoais</h2>

                <div class="text-xs text-gray-500">
                    ID: <span class="font-mono font-semibold text-gray-700">#{{ profile.id }}</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                    <InputText
                        v-model="profileForm.name"
                        class="w-full"
                        :invalid="!!profileForm.errors.name"
                        style="height: 44px"
                    />
                    <small v-if="profileForm.errors.name" class="text-red-500 text-xs mt-1 block">{{ profileForm.errors.name }}</small>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-mail</label>
                    <InputText
                        v-model="profileForm.email"
                        type="email"
                        class="w-full"
                        :invalid="!!profileForm.errors.email"
                        style="height: 44px"
                    />
                    <small v-if="profileForm.errors.email" class="text-red-500 text-xs mt-1 block">{{ profileForm.errors.email }}</small>
                </div>

                <Button
                    type="submit"
                    label="Salvar alterações"
                    icon="pi pi-check"
                    :loading="profileForm.processing"
                    class="w-full"
                    style="height: 48px"
                />
            </form>

            <!-- Trocar senha -->
            <form @submit.prevent="submitPassword" class="bg-white p-4 border-y border-gray-200 space-y-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Trocar senha</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Senha atual</label>
                    <Password
                        v-model="passwordForm.current_password"
                        :feedback="false"
                        toggleMask
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!passwordForm.errors.current_password"
                        :pt="{ pcInputText: { root: { style: 'height: 44px; width: 100%' } } }"
                    />
                    <small v-if="passwordForm.errors.current_password" class="text-red-500 text-xs mt-1 block">{{ passwordForm.errors.current_password }}</small>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nova senha</label>
                    <Password
                        v-model="passwordForm.password"
                        :feedback="true"
                        toggleMask
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!passwordForm.errors.password"
                        :pt="{ pcInputText: { root: { style: 'height: 44px; width: 100%' } } }"
                    />
                    <small v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1 block">{{ passwordForm.errors.password }}</small>
                    <small v-else class="text-gray-500 text-xs mt-1 block">Mínimo 8 caracteres</small>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Confirmar nova senha</label>
                    <Password
                        v-model="passwordForm.password_confirmation"
                        :feedback="false"
                        toggleMask
                        class="w-full"
                        inputClass="w-full"
                        :pt="{ pcInputText: { root: { style: 'height: 44px; width: 100%' } } }"
                    />
                </div>

                <Button
                    type="submit"
                    label="Atualizar senha"
                    icon="pi pi-key"
                    :loading="passwordForm.processing"
                    class="w-full"
                    style="height: 48px"
                />
            </form>

            <!-- Sair -->
            <div class="px-4 pt-2">
                <Button
                    label="Sair da conta"
                    icon="pi pi-sign-out"
                    severity="danger"
                    outlined
                    class="w-full"
                    @click="logout"
                    style="height: 48px"
                />
            </div>
        </div>
    </AppLayoutMobile>
</template>
