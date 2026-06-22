<script setup>
import { useDevice } from '@/composables/useDevice'
import ShowDesktop from './Show.desktop.vue'
import ShowMobile from './Show.mobile.vue'

defineProps({
    import: Object,
    transactions: Object,
    counters: Object,
    isConciliador: Boolean,
    filters: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <ShowMobile v-if="isMobile" v-bind="$props" />
    <ShowDesktop v-else v-bind="$props" />
</template>
