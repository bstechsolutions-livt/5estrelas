<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import Button from 'primevue/button'

const page = usePage()
const impersonator = computed(() => page.props.auth?.impersonator)
const user = computed(() => page.props.auth?.user)

function leave() {
    router.post('/impersonacao/sair')
}
</script>

<template>
    <div
        v-if="impersonator"
        class="bg-amber-500 text-amber-950 px-4 py-2 flex flex-wrap items-center justify-between gap-2 text-sm shrink-0"
    >
        <div class="flex items-center gap-2 min-w-0">
            <i class="pi pi-eye shrink-0"></i>
            <span class="min-w-0">
                Você está vendo o sistema como <strong>{{ user?.name }}</strong>
                <span class="hidden sm:inline">(logado originalmente como {{ impersonator.name }})</span>
            </span>
        </div>
        <Button
            label="Voltar ao meu usuário"
            size="small"
            severity="contrast"
            class="shrink-0"
            @click="leave"
        />
    </div>
</template>
