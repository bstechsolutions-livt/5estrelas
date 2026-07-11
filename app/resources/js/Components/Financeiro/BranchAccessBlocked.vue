<script setup>
import Message from 'primevue/message'
</script>

<template>
    <Message severity="warn" :closable="false" class="mb-4" dusk="no-branch-access-alert">
        <p class="font-medium">Sem acesso a filiais</p>
        <p class="mt-1 text-sm">
            Você não tem permissão para acessar nenhuma filial.
            Solicite ao administrador do sistema a liberação das filiais necessárias.
        </p>
    </Message>
</template>
