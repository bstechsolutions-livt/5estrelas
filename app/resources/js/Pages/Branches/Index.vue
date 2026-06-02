<script setup>
import { useDevice } from '@/composables/useDevice'
import BranchesDesktop from './Index.desktop.vue'
import BranchesMobile from './Index.mobile.vue'

defineProps({ branches: Object, filters: Object })
const { isMobile } = useDevice()
</script>

<template>
    <BranchesMobile v-if="isMobile" v-bind="$props" />
    <BranchesDesktop v-else v-bind="$props" />
</template>
