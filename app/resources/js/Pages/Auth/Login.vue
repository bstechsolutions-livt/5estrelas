<script setup>
import { useForm } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'

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
    <div class="min-h-screen flex items-center justify-center bg-gray-900 px-4"
         style="background: linear-gradient(135deg, #1e1e2d 0%, #1a1a2e 50%, #16213e 100%);">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-500 mb-4">
                    <span class="text-white font-bold text-2xl">5E</span>
                </div>
                <h1 class="text-2xl font-bold text-white">5 Estrelas</h1>
                <p class="text-gray-400 text-sm mt-1">Acesse sua conta</p>
            </div>

            <!-- Login form -->
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
                    severity="primary"
                />

                <div v-if="form.errors.email && form.errors.email.includes('credentials')"
                     class="text-center">
                    <small class="text-red-500 text-xs">Credenciais inválidas</small>
                </div>
            </form>
        </div>
    </div>
</template>
