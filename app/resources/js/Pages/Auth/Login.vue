<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import { useTheme } from '@/composables/useTheme'

const { theme } = useTheme()

const appName = computed(() => theme.value?.app_name || '5 Estrelas')
const logoUrl = computed(() => theme.value?.logo_url)
const logoMobileUrl = computed(() => theme.value?.logo_mobile_url || theme.value?.logo_url)
const loginBgUrl = computed(() => theme.value?.login_bg_url)
const loginBgMobileUrl = computed(() => theme.value?.login_bg_mobile_url || theme.value?.login_bg_url)
const primaryColor = computed(() => theme.value?.primary_color || '#3b82f6')
const secondaryColor = computed(() => theme.value?.secondary_color || '#1e1e2d')

const initials = computed(() => {
    const parts = appName.value.split(' ').filter(Boolean)
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
    return appName.value.substring(0, 2).toUpperCase()
})

// Background responsivo: usa media query CSS via gradient + background-image com url-set
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
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <div :class="['min-h-screen flex items-center justify-center px-4', hasLoginBg ? 'login-bg' : '']" :style="bgStyle">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div
                    v-if="!logoUrl"
                    class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
                    :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text, #ffffff)' }"
                >
                    <span class="font-bold text-2xl">{{ initials }}</span>
                </div>
                <img
                    v-else
                    :src="logoUrl"
                    :alt="appName"
                    class="inline-block h-24 mb-4 object-contain"
                />
                <template v-if="!logoUrl">
                    <h1 class="text-2xl font-bold text-white drop-shadow-lg">{{ appName }}</h1>
                    <p class="text-gray-200 text-sm mt-1 drop-shadow">Acesse sua conta</p>
                </template>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-xl p-8 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                    <InputText
                        v-model="form.email"
                        type="email"
                        placeholder="seu@email.com"
                        class="w-full"
                        :invalid="!!form.errors.email"
                    />
                    <small v-if="form.errors.email" class="text-red-500 text-xs mt-1">
                        {{ form.errors.email }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                    <Password
                        v-model="form.password"
                        placeholder="••••••••"
                        :feedback="false"
                        toggleMask
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!form.errors.password"
                    />
                    <small v-if="form.errors.password" class="text-red-500 text-xs mt-1">
                        {{ form.errors.password }}
                    </small>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.remember" :binary="true" inputId="remember" />
                        <label for="remember" class="text-sm text-gray-600 cursor-pointer">
                            Lembrar-me
                        </label>
                    </div>
                </div>

                <Button
                    type="submit"
                    label="Entrar"
                    :loading="form.processing"
                    class="w-full"
                />
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
</style>
