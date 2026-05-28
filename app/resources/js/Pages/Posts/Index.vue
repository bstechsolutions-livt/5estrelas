<script setup>
import { useDevice } from '@/composables/useDevice'
import PostsDesktop from './Index.desktop.vue'
import PostsMobile from './Index.mobile.vue'

defineProps({
    posts: Object,
    filters: Object,
})

const { isMobile } = useDevice()
</script>

<template>
    <PostsMobile v-if="isMobile" v-bind="$props" />
    <PostsDesktop v-else v-bind="$props" />
</template>
