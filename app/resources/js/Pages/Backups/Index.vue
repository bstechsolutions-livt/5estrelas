<script setup>
import { useDevice } from '@/composables/useDevice'
import BackupsDesktop from './Index.desktop.vue'
import BackupsMobile from './Index.mobile.vue'

defineProps({
    backups: Array,
    config: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <BackupsMobile v-if="isMobile" v-bind="$props" />
    <BackupsDesktop v-else v-bind="$props" />
</template>
