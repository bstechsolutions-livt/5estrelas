<script setup>
import { useDevice } from '@/composables/useDevice'
import ProfileDesktop from './Index.desktop.vue'
import ProfileMobile from './Index.mobile.vue'

const props = defineProps({
    profile: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <ProfileMobile v-if="isMobile" v-bind="$props" />
    <ProfileDesktop v-else v-bind="$props" />
</template>
