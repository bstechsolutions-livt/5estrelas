<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
    shortcuts: { type: Array, default: () => [] },
})

const emit = defineEmits(['configure'])

const page = usePage()

const MAX_SHORTCUTS = 4

const userSlots = computed(() => {
    return (props.shortcuts || [])
        .filter(s => s.key !== 'dashboard')
        .slice(0, MAX_SHORTCUTS)
})

const items = computed(() => {
    const list = [
        { key: 'home', label: 'Início', icon: 'pi pi-home', href: '/dashboard' },
    ]

    userSlots.value.forEach(s => {
        list.push({ key: s.key, label: s.label, icon: s.icon, href: s.href })
    })

    list.push({ key: 'configure', label: 'Editar', icon: 'pi pi-cog', action: 'configure' })

    return list
})

function isActive(href) {
    if (!href) return false
    return page.url === href || page.url.startsWith(href + '/')
}

function onTap(item) {
    if (item.action === 'configure') {
        emit('configure')
        return
    }
    if (item.href) router.visit(item.href)
}
</script>

<template>
    <nav class="bottom-nav fixed left-0 right-0 bottom-0 z-40 flex border-t border-gray-200 bg-white" style="padding-bottom: env(safe-area-inset-bottom);">
        <button
            v-for="item in items"
            :key="item.key"
            @click="onTap(item)"
            :class="[
                'flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 transition-colors',
                isActive(item.href) ? 'text-[var(--app-primary)]' : 'text-gray-500',
                item.action === 'configure' ? 'opacity-70' : ''
            ]"
        >
            <i :class="[item.icon, 'text-lg']"></i>
            <span class="text-[10px] font-medium leading-tight line-clamp-1">{{ item.label }}</span>
        </button>
    </nav>
</template>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
