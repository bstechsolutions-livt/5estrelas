<script setup>
import { useDevice } from '@/composables/useDevice'
import FormDesktop from './Form.desktop.vue'
import FormMobile from './Form.mobile.vue'

defineProps({
    department: { type: Object, default: null },
    approvalAreas: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
})

const { isMobile } = useDevice()
</script>

<template>
    <FormMobile v-if="isMobile" v-bind="$props" />
    <FormDesktop v-else v-bind="$props" />
</template>
