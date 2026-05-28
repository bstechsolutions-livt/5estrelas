<script setup>
import { computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import { useTheme } from '@/composables/useTheme'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    token: String,
    email: String,
})

const { theme } = useTheme()
const { isMobile } = useDevice()

const appName = computed(() => theme.value?.app_name || '5 Estrelas')
const logoUrl = computed(() => theme.value?.logo_url)
const loginBgUrl = computed(() => theme.value?.login_bg_url)
const loginBgMobileUrl = computed(() => theme.value?.login_bg_mobile_url || theme.value?.login_bg_url)
const primaryColor = computed(() => theme.value?.primary_color || '#3b82f6')
const secondaryColor = computed(() => theme.value?.secondary_color || '#1e1e2d')

const initials = computed(() => {
    const parts = appName.value.split(' ').filter(Boolean)
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
    return appName.value.substring(0, 2).toUpperCase()
})

const bgStyle = computed(() => {
    const desktop = loginBgUrl.value
    const mobile = loginBgMobileUrl.value
    if (desktop || mobile) {
        return {
            '--login-bg-desktop': desktop ? `url(${desktop})` : 'none',
            '--login-bg-mobile': mobile ? `url(${mobile})` : (desktop ? `url(${desktop})` : 'none'),
        }
    }
    return {
        background: `linear-gradient(135deg, ${secondaryColor.value} 0%, #1a1a2e 50%, #16213e 100%)`,
    }
})

const hasLoginBg = computed(() => !!(loginBgUrl.value || loginBgMobileUrl.value))

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/redefinir-senha', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <!-- Mobile -->
    <div v-if="isMobile" :class="['min-h-screen flex flex-col px-5 pb-8', hasLoginBg ? 'login-bg' : '']" :style="bgStyle">
        <div class="flex-1 flex flex-col justify-center pt-12">
            <div class="text-center mb-8">
                <div
                    v-if="!logoUrl"
                    class="inline-flex items-center justify-center w-20 h-20 rounded-3xl mb-4 shadow-lg"
                    :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text, #ffffff)' }"
                >
                    <span class="font-bold text-3xl">{{ initials }}</span>
                </div>
                <img v-else :src="logoUrl" :alt="appName" class="inline-block h-20 mb-4 object-contain drop-shadow-lg" />
            </div>

            <div class="space-y-3">
                <h1 class="text-xl font-semibold text-white drop-shadow">Redefinir senha</h1>
                <p class="text-sm text-white/80 mb-3">Defina uma nova senha para acessar sua conta.</p>

                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="text-xs text-white/70 mb-1 block">E-mail</label>
                        <InputText
                            v-model="form.email"
                            type="email"
                            class="w-full mobile-input"
                            :invalid="!!form.errors.email"
                            readonly
                        />
                        <small v-if="form.errors.email" class="text-red-300 text-xs mt-1 block px-2">{{ form.errors.email }}</small>
                    </div>

                    <div>
                        <label class="text-xs text-white/70 mb-1 block">Nova senha</label>
                        <Password
                            v-model="form.password"
                            placeholder="Mínimo 8 caracteres"
                            :feedback="true"
                            toggleMask
                            class="w-full mobile-password"
                            inputClass="w-full mobile-input"
                            :invalid="!!form.errors.password"
                            promptLabel="Digite a nova senha"
                            weakLabel="Fraca"
                            mediumLabel="Média"
                            strongLabel="Forte"
                        />
                        <small v-if="form.errors.password" class="text-red-300 text-xs mt-1 block px-2">{{ form.errors.password }}</small>
                    </div>

                    <div>
                        <label class="text-xs text-white/70 mb-1 block">Confirmar senha</label>
                        <Password
                            v-model="form.password_confirmation"
                            placeholder="Repita a senha"
                            :feedback="false"
                            toggleMask
                            class="w-full mobile-password"
                            inputClass="w-full mobile-input"
                        />
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-3">
            <Button
                type="button"
                label="Redefinir e entrar"
                icon="pi pi-check"
                :loading="form.processing"
                class="w-full mobile-submit"
                @click="submit"
            />
            <Link href="/login" class="block text-center text-sm text-white/80 hover:text-white">
                ← Voltar ao login
            </Link>
        </div>
    </div>

    <!-- Desktop -->
    <div v-else :class="['min-h-screen flex items-center justify-center px-4', hasLoginBg ? 'login-bg' : '']" :style="bgStyle">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    v-if="!logoUrl"
                    class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
                    :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text, #ffffff)' }"
                >
                    <span class="font-bold text-2xl">{{ initials }}</span>
                </div>
                <img v-else :src="logoUrl" :alt="appName" class="inline-block h-24 mb-4 object-contain" />
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-xl p-8 space-y-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-1">Redefinir senha</h2>
                    <p class="text-sm text-gray-500">Defina uma nova senha para acessar sua conta.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                    <InputText v-model="form.email" type="email" class="w-full" :invalid="!!form.errors.email" readonly />
                    <small v-if="form.errors.email" class="text-red-500 text-xs mt-1 block">{{ form.errors.email }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nova senha</label>
                    <Password v-model="form.password" :feedback="true" toggleMask class="w-full" inputClass="w-full" :invalid="!!form.errors.password" promptLabel="Digite a nova senha" weakLabel="Fraca" mediumLabel="Média" strongLabel="Forte" />
                    <small v-if="form.errors.password" class="text-red-500 text-xs mt-1 block">{{ form.errors.password }}</small>
                    <small v-else class="text-gray-500 text-xs mt-1 block">Mínimo 8 caracteres</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmar senha</label>
                    <Password v-model="form.password_confirmation" :feedback="false" toggleMask class="w-full" inputClass="w-full" />
                </div>

                <Button type="submit" label="Redefinir e entrar" icon="pi pi-check" :loading="form.processing" class="w-full" />

                <div class="text-center pt-2">
                    <Link href="/login" class="text-sm text-gray-600 hover:underline">
                        ← Voltar ao login
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.login-bg {
    background-image: var(--login-bg-mobile);
    background-size: cover;
    background-position: center;
}
@media (min-width: 768px) {
    .login-bg {
        background-image: var(--login-bg-desktop);
    }
}

:deep(.mobile-input) {
    background-color: rgba(255, 255, 255, 0.95) !important;
    border: 0 !important;
    height: 52px !important;
    font-size: 16px !important;
    border-radius: 12px !important;
}

:deep(.mobile-password .p-password-input) {
    height: 52px !important;
    font-size: 16px !important;
    border-radius: 12px !important;
    background-color: rgba(255, 255, 255, 0.95) !important;
    border: 0 !important;
}

.mobile-submit {
    height: 54px !important;
    font-size: 16px !important;
    border-radius: 12px !important;
}
</style>
