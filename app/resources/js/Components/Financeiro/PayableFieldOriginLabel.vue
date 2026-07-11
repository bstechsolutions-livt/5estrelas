<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    field: { type: String, required: true },
    fieldOrigins: { type: Object, default: null },
})

const origin = computed(() => props.fieldOrigins?.[props.field] ?? null)

const tooltip = computed(() => {
    if (origin.value === 'senior') {
        return 'Preenchido automaticamente pela Senior (ERP)'
    }
    if (origin.value === 'hub') {
        return 'Preenchido ou editável na intranet Hub'
    }
    return ''
})
</script>

<template>
    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
        <span>{{ label }}</span>
        <i
            v-if="origin === 'senior'"
            class="pi pi-cloud-download text-[10px] text-slate-400"
            :title="tooltip"
            :aria-label="tooltip"
            dusk="field-origin-senior"
        />
        <i
            v-else-if="origin === 'hub'"
            class="pi pi-pencil text-[10px] text-blue-400"
            :title="tooltip"
            :aria-label="tooltip"
            dusk="field-origin-hub"
        />
    </span>
</template>
