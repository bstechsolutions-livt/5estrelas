<script setup>
import { useDevice } from '@/composables/useDevice'
import FormDesktop from './Form.desktop.vue'
import FormMobile from './Form.mobile.vue'

defineProps({ branch: { type: Object, default: null }, empresaOptions: { type: Array, default: () => [] }, seniorFiliais: { type: Object, default: () => ({}) } })
const { isMobile } = useDevice()
</script>

<template>
    <FormMobile v-if="isMobile" v-bind="$props" />
    <FormDesktop v-else v-bind="$props" />
</template>
