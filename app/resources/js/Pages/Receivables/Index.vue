<script setup>
import { useDevice } from '@/composables/useDevice'
import ReceivablesDesktop from './Index.desktop.vue'
import ReceivablesMobile from './Index.mobile.vue'

defineProps({
    receivables: Object,
    totals: Object,
    filters: Object,
    empresas: Array,
    statusOptions: Object,
    lockedBranches: { type: Array, default: () => [] },
    noBranchAccess: { type: Boolean, default: false },
})

const { isMobile } = useDevice()
</script>

<template>
    <ReceivablesMobile v-if="isMobile" v-bind="$props" />
    <ReceivablesDesktop v-else v-bind="$props" />
</template>
