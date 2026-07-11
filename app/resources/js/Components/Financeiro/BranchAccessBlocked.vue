<script setup>
import { Link } from '@inertiajs/vue3'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { useAuth } from '@/composables/useAuth'

const { canAny } = useAuth()
</script>

<template>
    <Message severity="warn" :closable="false" class="mb-4" dusk="no-branch-access-alert">
        <p class="font-medium">Sem acesso a filiais</p>
        <p class="mt-1 text-sm">
            Você não tem permissão para acessar nenhuma filial.
            <template v-if="canAny('usuarios.listar', 'usuarios.editar')">
                Acesse a lista de usuários para gerenciar as filiais liberadas.
            </template>
            <template v-else>
                Solicite ao administrador do sistema a liberação das filiais necessárias.
            </template>
        </p>
        <Link
            v-if="canAny('usuarios.listar', 'usuarios.editar')"
            href="/usuarios"
            class="inline-block mt-3"
        >
            <Button
                label="Gerenciar filiais dos usuários"
                icon="pi pi-users"
                severity="warn"
                size="small"
                outlined
            />
        </Link>
    </Message>
</template>
