<script setup>
import { useDevice } from '@/composables/useDevice'
import DashboardDesktop from '@/Pages/Dashboard.desktop.vue'
import DashboardMobile from '@/Pages/Dashboard.mobile.vue'

defineProps({
    highlights: { type: Array, default: () => [] },
    news: { type: Object, default: () => ({ data: [] }) },
    shortcuts: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
})

const { isMobile } = useDevice()
</script>

<template>
    <DashboardMobile v-if="isMobile" v-bind="$props" />
    <DashboardDesktop v-else v-bind="$props" />
</template>
