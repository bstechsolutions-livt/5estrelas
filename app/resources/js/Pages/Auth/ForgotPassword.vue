<script setup>
import { computed, watch } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useTheme } from '@/composables/useTheme'

const { theme } = useTheme()
const page = usePage()

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
    email: '',
})

function submit() {
    form.post('/esqueci-senha', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}

const successMessage = computed(() => page.props.flash?.success)
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
                </template>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-xl p-8 space-y-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-1">Esqueci minha senha</h2>
                    <p class="text-sm text-gray-500">Informe seu e-mail e enviaremos um link para redefinir sua senha.</p>
                </div>

                <Message v-if="successMessage" severity="success" :closable="false">
                    {{ successMessage }}
                </Message>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                    <InputText
                        v-model="form.email"
                        type="email"
                        placeholder="seu@email.com"
                        class="w-full"
                        :invalid="!!form.errors.email"
                    />
                    <small v-if="form.errors.email" class="text-red-500 text-xs mt-1 block">
                        {{ form.errors.email }}
                    </small>
                </div>

                <Button
                    type="submit"
                    label="Enviar link"
                    icon="pi pi-send"
                    :loading="form.processing"
                    class="w-full"
                />

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
</style>
