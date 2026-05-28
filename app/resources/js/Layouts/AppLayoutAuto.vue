<script setup>
import AppLayout from './AppLayout.vue'
import AppLayoutMobile from './AppLayoutMobile.vue'
import { useDevice } from '@/composables/useDevice'

defineProps({
    title: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
})

const { isMobile } = useDevice()
</script>

<template>
    <AppLayoutMobile v-if="isMobile" :title="title" :show-back="showBack">
        <slot />
    </AppLayoutMobile>
    <AppLayout v-else>
        <slot />
    </AppLayout>
</template>
