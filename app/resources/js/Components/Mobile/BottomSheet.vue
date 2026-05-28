<script setup>
import { watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

function close() {
    emit('update:modelValue', false)
}

watch(() => props.modelValue, (val) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = val ? 'hidden' : ''
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition name="bs-overlay">
            <div v-if="modelValue" class="fixed inset-0 z-[80] bg-black/40" @click="close"></div>
        </Transition>
        <Transition name="bs-sheet">
            <div
                v-if="modelValue"
                class="fixed left-0 right-0 bottom-0 z-[81] bg-white rounded-t-2xl shadow-xl flex flex-col"
                style="max-height: 90vh; padding-bottom: env(safe-area-inset-bottom);"
                @click.stop
            >
                <div class="flex justify-center pt-3 pb-1">
                    <div class="w-10 h-1 rounded-full bg-gray-300"></div>
                </div>
                <div v-if="title" class="px-4 py-2 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800">{{ title }}</h3>
                    <button @click="close" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="pi pi-times text-sm"></i>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.bs-overlay-enter-active, .bs-overlay-leave-active { transition: opacity 0.25s ease; }
.bs-overlay-enter-from, .bs-overlay-leave-to { opacity: 0; }
.bs-sheet-enter-active, .bs-sheet-leave-active { transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); }
.bs-sheet-enter-from, .bs-sheet-leave-to { transform: translateY(100%); }
</style>
