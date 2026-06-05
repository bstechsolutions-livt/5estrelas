<script setup>
// Adaptador: telas portadas da intranet Biglar (gestao-contratos) importam
// este layout. Aqui ele apenas embrulha o AppLayout padrão do 5 Estrelas,
// para que as telas rodem sem precisar editar imports.
import AppLayout from '@/Layouts/AppLayout.vue'
</script>

<template>
    <AppLayout>
        <slot />
    </AppLayout>
</template>
