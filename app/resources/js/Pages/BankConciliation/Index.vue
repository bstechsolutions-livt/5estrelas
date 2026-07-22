<script setup>
import { useDevice } from '@/composables/useDevice'
import IndexDesktop from './Index.desktop.vue'
import IndexMobile from './Index.mobile.vue'

defineProps({
    isConciliador: Boolean,
    bankAccounts: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    dayReport: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    importResults: { type: Array, default: null },
})

const { isMobile } = useDevice()
</script>

<template>
    <IndexMobile v-if="isMobile" v-bind="$props" />
    <IndexDesktop v-else v-bind="$props" />
</template>
