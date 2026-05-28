<script setup>
import { useDevice } from '@/composables/useDevice'
import AppearanceDesktop from './Appearance.desktop.vue'
import AppearanceMobile from './Appearance.mobile.vue'

defineProps({
    settings: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <AppearanceMobile v-if="isMobile" v-bind="$props" />
    <AppearanceDesktop v-else v-bind="$props" />
</template>
