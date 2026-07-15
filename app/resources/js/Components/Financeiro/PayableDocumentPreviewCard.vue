<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import Tag from 'primevue/tag'

const props = defineProps({
    doc: { type: Object, required: true },
    typeLabel: { type: String, default: 'Documento' },
    canRemove: { type: Boolean, default: false },
    /** Variante compacta para visualização em lista. */
    dense: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'remove'])

const rootEl = ref(null)
const shouldLoad = ref(false)
let observer = null

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
}

function isImage(doc) {
    return doc.mime_type?.startsWith('image/')
}

function isPdf(doc) {
    return doc.mime_type === 'application/pdf'
}

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') {
        shouldLoad.value = true
        return
    }
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                shouldLoad.value = true
                observer?.disconnect()
                observer = null
            }
        },
        { rootMargin: '120px', threshold: 0.01 },
    )
    if (rootEl.value) observer.observe(rootEl.value)
})

onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
})
</script>

<template>
    <!-- Lista: linha compacta sem iframe/img até abrir -->
    <article
        v-if="dense"
        ref="rootEl"
        class="flex items-center gap-3 px-3 py-2 border border-gray-100 rounded-lg bg-white hover:border-blue-200 transition-colors"
        dusk="payable-doc-preview-row"
    >
        <button
            type="button"
            class="shrink-0 w-10 h-10 rounded-md bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-400 overflow-hidden"
            @click="emit('open', doc)"
        >
            <img
                v-if="shouldLoad && isImage(doc)"
                :src="doc.url"
                :alt="doc.name"
                class="w-full h-full object-cover"
                loading="lazy"
            />
            <i v-else-if="isPdf(doc)" class="pi pi-file-pdf text-red-400"></i>
            <i v-else class="pi pi-file text-lg"></i>
        </button>
        <button type="button" class="min-w-0 flex-1 text-left" @click="emit('open', doc)">
            <div class="flex items-center gap-2 mb-0.5">
                <Tag :value="typeLabel" severity="secondary" class="!text-[10px]" />
            </div>
            <p class="text-xs text-gray-800 truncate font-medium" :title="doc.name">{{ doc.name }}</p>
            <p class="text-[10px] text-gray-400">{{ formatSize(doc.size) }} · {{ doc.uploader?.name || '—' }}</p>
        </button>
        <button type="button" class="text-xs text-blue-600 hover:underline shrink-0 font-medium" @click="emit('open', doc)">
            Ampliar
        </button>
        <a :href="doc.url" :download="doc.name" class="text-gray-400 hover:text-blue-600 p-1 shrink-0" title="Baixar">
            <i class="pi pi-download text-xs"></i>
        </a>
        <button v-if="canRemove" type="button" class="text-red-400 hover:text-red-600 p-1 shrink-0" title="Remover" @click="emit('remove', doc.id)">
            <i class="pi pi-trash text-xs"></i>
        </button>
    </article>

    <!-- Card: miniatura lazy (Intersection Observer) -->
    <article
        v-else
        ref="rootEl"
        class="border border-gray-100 rounded-lg overflow-hidden bg-gray-50"
        dusk="payable-doc-preview-card"
    >
        <div class="px-2 py-1.5 bg-white border-b border-gray-100 flex items-center justify-between gap-2">
            <Tag :value="typeLabel" severity="secondary" class="!text-[10px]" />
            <div class="flex items-center gap-1">
                <button type="button" class="text-[10px] text-blue-600 hover:underline" @click="emit('open', doc)">Ampliar</button>
                <a :href="doc.url" :download="doc.name" class="text-gray-400 hover:text-blue-600 p-1" title="Baixar">
                    <i class="pi pi-download text-xs"></i>
                </a>
                <button v-if="canRemove" type="button" class="text-red-400 hover:text-red-600 p-1" title="Remover" @click="emit('remove', doc.id)">
                    <i class="pi pi-trash text-xs"></i>
                </button>
            </div>
        </div>
        <button type="button" class="block w-full text-left" @click="emit('open', doc)">
            <template v-if="shouldLoad">
                <img v-if="isImage(doc)" :src="doc.url" :alt="doc.name" class="w-full max-h-52 object-contain bg-white" loading="lazy" />
                <iframe v-else-if="isPdf(doc)" :src="doc.url" class="w-full h-52 bg-white border-0 pointer-events-none" :title="doc.name" />
                <div v-else class="h-28 flex items-center justify-center text-gray-400 bg-white">
                    <i class="pi pi-file text-3xl"></i>
                </div>
            </template>
            <div v-else class="h-52 flex flex-col items-center justify-center gap-2 text-gray-300 bg-white">
                <i class="pi pi-image text-2xl"></i>
                <span class="text-[10px]">Miniatura sob demanda</span>
            </div>
        </button>
        <div class="px-2 py-1.5 bg-white border-t border-gray-100">
            <p class="text-[11px] text-gray-700 truncate" :title="doc.name">{{ doc.name }}</p>
            <p class="text-[10px] text-gray-400">{{ formatSize(doc.size) }} · {{ doc.uploader?.name || '—' }}</p>
        </div>
    </article>
</template>
