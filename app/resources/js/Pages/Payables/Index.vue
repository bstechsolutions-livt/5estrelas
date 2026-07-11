<script setup>
import { useDevice } from '@/composables/useDevice'
import PayablesDesktop from './Index.desktop.vue'
import PayablesMobile from './Index.mobile.vue'

defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    empresas: Array,
    departments: Array,
    branches: Array,
    statusOptions: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <PayablesMobile v-if="isMobile" v-bind="$props" />
    <PayablesDesktop v-else v-bind="$props" />
</template>
