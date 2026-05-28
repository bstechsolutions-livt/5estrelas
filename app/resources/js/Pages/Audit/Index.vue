<script setup>
import { useDevice } from '@/composables/useDevice'
import AuditDesktop from './Index.desktop.vue'
import AuditMobile from './Index.mobile.vue'

defineProps({
    logs: Object,
    filters: Object,
    options: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <AuditMobile v-if="isMobile" v-bind="$props" />
    <AuditDesktop v-else v-bind="$props" />
</template>
