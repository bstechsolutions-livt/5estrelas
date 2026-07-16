<script setup>
import { computed, ref, watch, nextTick, onBeforeUnmount } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    visible: { type: Boolean, default: false },
    /** Documento único (compat Contas a Pagar / open de 1 item). */
    doc: { type: Object, default: null },
    /** Lista completa do título — scroll contínuo no viewer. */
    docs: { type: Array, default: null },
    /** Id do doc clicado para scroll inicial. */
    initialDocId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:visible'])

const { isMobile } = useDevice()

const dialogVisible = computed({
    get: () => props.visible,
    set: (v) => emit('update:visible', v),
})

const documentList = computed(() => {
    if (Array.isArray(props.docs) && props.docs.length) {
        return props.docs
    }
    return props.doc ? [props.doc] : []
})

const isMulti = computed(() => documentList.value.length > 1)

const title = computed(() => {
    if (isMulti.value) {
        return `Documentos (${documentList.value.length})`
    }
    return documentList.value[0]?.name || 'Documento'
})

const scrollRoot = ref(null)
const loadedIds = ref(new Set())
let observer = null

function isImage(doc) {
    return doc?.mime_type?.startsWith('image/')
}

function isPdf(doc) {
    return doc?.mime_type === 'application/pdf'
}

function markLoaded(id) {
    if (loadedIds.value.has(id)) return
    const next = new Set(loadedIds.value)
    next.add(id)
    loadedIds.value = next
}

function disconnectObserver() {
    if (observer) {
        observer.disconnect()
        observer = null
    }
}

function setupLazyLoad() {
    disconnectObserver()
    const root = scrollRoot.value
    if (!root) return

    // No multi, carrega sob demanda; no single, libera já.
    if (!isMulti.value) {
        documentList.value.forEach((d) => markLoaded(d.id))
        return
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue
                const id = entry.target.getAttribute('data-doc-id')
                if (id != null) markLoaded(Number(id) || id)
            }
        },
        { root, rootMargin: '200px 0px', threshold: 0.01 },
    )

    root.querySelectorAll('[data-doc-id]').forEach((el) => observer.observe(el))
}

async function scrollToInitial() {
    await nextTick()
    const root = scrollRoot.value
    if (!root || !props.initialDocId) return
    const target = root.querySelector(`[data-doc-id="${props.initialDocId}"]`)
    if (target) {
        target.scrollIntoView({ block: 'start' })
        markLoaded(props.initialDocId)
    }
}

watch(
    () => [props.visible, documentList.value.map((d) => d.id).join(','), props.initialDocId],
    async ([visible]) => {
        if (!visible) {
            disconnectObserver()
            loadedIds.value = new Set()
            return
        }
        await nextTick()
        setupLazyLoad()
        await scrollToInitial()
        // Garante o doc clicado (e o primeiro) prontos.
        const first = documentList.value[0]
        if (first) markLoaded(first.id)
        if (props.initialDocId) markLoaded(props.initialDocId)
    },
)

onBeforeUnmount(() => disconnectObserver())
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :dismissable-mask="true"
        :close-on-escape="true"
        :pt="{
            mask: { class: 'backdrop-blur-[1px]' },
            root: {
                class: [
                    '!overflow-hidden !p-0 !shadow-2xl',
                    isMobile
                        ? '!m-0 !w-screen !max-w-none !h-[100dvh] !max-h-[100dvh] !rounded-none'
                        : '!w-[92vw] !max-w-[1100px] !max-h-[min(92dvh,900px)] !rounded-xl',
                ].join(' '),
            },
        }"
    >
        <template #container="{ closeCallback }">
            <div
                class="flex flex-col bg-white w-full overflow-hidden"
                :class="isMobile ? 'h-[100dvh] max-h-[100dvh]' : 'max-h-[min(92dvh,900px)]'"
            >
                <div class="shrink-0 flex items-center gap-2 px-3 sm:px-4 py-3 border-b border-gray-100 min-w-0 bg-white">
                    <Button
                        label="Voltar"
                        icon="pi pi-arrow-left"
                        severity="secondary"
                        outlined
                        size="small"
                        class="!font-semibold shrink-0"
                        dusk="doc-viewer-close"
                        @click="closeCallback"
                    />
                    <h3
                        class="m-0 flex-1 min-w-0 text-sm sm:text-base font-semibold text-gray-800 truncate"
                        :title="title"
                    >
                        {{ title }}
                    </h3>
                    <button
                        type="button"
                        class="shrink-0 w-9 h-9 rounded-md flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-700 border border-gray-200"
                        aria-label="Fechar"
                        @click="closeCallback"
                    >
                        <i class="pi pi-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <div
                    ref="scrollRoot"
                    dusk="doc-viewer"
                    class="min-h-0 flex-1 overflow-y-auto bg-gray-100"
                    :class="isMobile ? '' : 'max-h-[min(70dvh,720px)]'"
                >
                    <section
                        v-for="(item, idx) in documentList"
                        :key="item.id"
                        :data-doc-id="item.id"
                        class="border-b border-gray-200 last:border-b-0 bg-white"
                    >
                        <div
                            v-if="isMulti"
                            class="sticky top-0 z-10 px-3 py-2 bg-gray-50/95 backdrop-blur border-b border-gray-100 flex items-center justify-between gap-2"
                        >
                            <p class="text-xs sm:text-sm font-medium text-gray-700 truncate min-w-0" :title="item.name">
                                <span class="text-gray-400 mr-1">{{ idx + 1 }}/{{ documentList.length }}</span>
                                {{ item.name }}
                            </p>
                            <a
                                :href="item.url"
                                :download="item.name"
                                class="text-xs text-blue-600 hover:underline shrink-0"
                            >
                                Baixar
                            </a>
                        </div>

                        <div class="min-h-[50vh] sm:min-h-[60vh] flex flex-col bg-gray-50">
                            <template v-if="loadedIds.has(item.id)">
                                <iframe
                                    v-if="isPdf(item)"
                                    :src="item.url"
                                    class="w-full flex-1 min-h-[50vh] sm:min-h-[60vh] border-0 bg-white"
                                    :title="item.name || 'Visualização do documento'"
                                />
                                <div
                                    v-else-if="isImage(item)"
                                    class="flex-1 min-h-[50vh] overflow-auto flex items-center justify-center p-2"
                                >
                                    <img
                                        :src="item.url"
                                        :alt="item.name"
                                        class="max-w-full max-h-[70vh] object-contain"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="flex-1 min-h-[40vh] flex flex-col items-center justify-center text-center px-4 py-10 text-gray-500"
                                >
                                    <i class="pi pi-file text-5xl mb-4 text-gray-300"></i>
                                    <p class="mb-4 text-sm">Pré-visualização não disponível para este tipo de arquivo.</p>
                                    <a
                                        :href="item.url"
                                        :download="item.name"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"
                                    >
                                        <i class="pi pi-download"></i> Baixar arquivo
                                    </a>
                                </div>
                            </template>
                            <div
                                v-else
                                class="flex-1 min-h-[50vh] flex flex-col items-center justify-center text-gray-400 gap-2"
                            >
                                <i class="pi pi-spin pi-spinner text-2xl"></i>
                                <span class="text-xs">Carregando…</span>
                            </div>
                        </div>
                    </section>
                </div>

                <div
                    class="shrink-0 flex items-center justify-between gap-2 px-3 sm:px-4 py-3 border-t border-gray-200 bg-white shadow-[0_-4px_12px_rgba(0,0,0,0.04)]"
                    style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
                >
                    <Button
                        label="Voltar"
                        icon="pi pi-arrow-left"
                        severity="contrast"
                        class="!font-semibold !px-4"
                        dusk="doc-viewer-close-footer"
                        @click="closeCallback"
                    />
                    <a
                        v-if="!isMulti && documentList[0]"
                        :href="documentList[0].url"
                        :download="documentList[0].name"
                        class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-blue-700 hover:underline shrink-0"
                    >
                        <i class="pi pi-download"></i> Baixar
                    </a>
                    <span v-else class="text-xs text-gray-500">
                        Role para ver todos os documentos
                    </span>
                </div>
            </div>
        </template>
    </Dialog>
</template>
