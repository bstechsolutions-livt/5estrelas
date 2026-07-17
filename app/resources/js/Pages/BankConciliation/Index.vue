<script setup>
import { useDevice } from '@/composables/useDevice'
import IndexDesktop from './Index.desktop.vue'
import IndexMobile from './Index.mobile.vue'

defineProps({
    imports: Object,
    isConciliador: Boolean,
    bankAccounts: { type: Array, default: () => [] },
})

const { isMobile } = useDevice()
</script>

<template>
    <IndexMobile v-if="isMobile" v-bind="$props" />
    <IndexDesktop v-else v-bind="$props" />
</template>
