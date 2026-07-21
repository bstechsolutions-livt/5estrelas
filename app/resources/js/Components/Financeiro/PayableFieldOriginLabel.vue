<script setup>
defineProps({
    label: { type: String, required: true },
    field: { type: String, required: true },
    /** Mantido por compatibilidade; ícones de origem não são mais exibidos no detalhe. */
    fieldOrigins: { type: Object, default: null },
})
</script>

<template>
    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
        <span>{{ label }}</span>
    </span>
</template>
