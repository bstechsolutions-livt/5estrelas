<script setup>
import { useDevice } from '@/composables/useDevice'
import UsersDesktop from './Index.desktop.vue'
import UsersMobile from './Index.mobile.vue'

defineProps({
    users: Object,
    filters: Object,
    totalBranches: { type: Number, default: 0 },
})

const { isMobile } = useDevice()
</script>

<template>
    <UsersMobile v-if="isMobile" v-bind="$props" />
    <UsersDesktop v-else v-bind="$props" />
</template>
