<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const firstName = computed(() => user.value?.name?.split(' ')[0] || 'Usuário')
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl font-semibold text-gray-800">
                Olá, {{ firstName }} 👋
            </h1>
            <p class="text-gray-500 text-sm mt-1">Bem-vindo ao painel de gestão</p>
        </div>
    </AppLayout>
</template>
