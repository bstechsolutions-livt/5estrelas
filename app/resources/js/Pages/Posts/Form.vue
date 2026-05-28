<script setup>
import { useDevice } from '@/composables/useDevice'
import FormDesktop from './Form.desktop.vue'
import FormMobile from './Form.mobile.vue'

defineProps({
    mode: { type: String, required: true },
    post: Object,
    initialType: { type: String, default: 'news' },
})

const { isMobile } = useDevice()
</script>

<template>
    <FormMobile v-if="isMobile" v-bind="$props" />
    <FormDesktop v-else v-bind="$props" />
</template>
