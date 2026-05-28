<script setup>
import { useDevice } from '@/composables/useDevice'
import PermissionsDesktop from './Permissions.desktop.vue'
import PermissionsMobile from './Permissions.mobile.vue'

defineProps({
    targetUser: Object,
    permissions: Object,
    assigned: Array,
})

const { isMobile } = useDevice()
</script>

<template>
    <PermissionsMobile v-if="isMobile" v-bind="$props" />
    <PermissionsDesktop v-else v-bind="$props" />
</template>
