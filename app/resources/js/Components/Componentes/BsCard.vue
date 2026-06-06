<script setup>

const props = defineProps({
    label: {
        type: String,
        required: false,
        default: null
    },
    noPadding: {
        type: Boolean,
        required: false,
        default: false
    },
});

</script>

<template>

    <div class="bg-white shadow-md drop-shadow-md relative w-full h-full rounded-xl border overflow-hidden" :class="noPadding ? '' : 'p-2'">
        <div class="absolute -top-2 left-2 z-40 text-sm px-2">
            {{ props.label }}
        </div>
        <slot />
    </div>
    
</template>