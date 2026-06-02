<script setup>
import { useDevice } from '@/composables/useDevice'
import DepartmentsDesktop from './Index.desktop.vue'
import DepartmentsMobile from './Index.mobile.vue'

defineProps({
    departments: Object,
    filters: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <DepartmentsMobile v-if="isMobile" v-bind="$props" />
    <DepartmentsDesktop v-else v-bind="$props" />
</template>
