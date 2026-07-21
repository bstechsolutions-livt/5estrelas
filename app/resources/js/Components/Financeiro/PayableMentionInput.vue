<script setup>
import { ref, watch, nextTick, onMounted } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    mentionableUsers: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const editorRef = ref(null)
const mentionOpen = ref(false)
const mentionQuery = ref('')
const mentionFiltered = ref([])
const empty = ref(true)

function syncEmpty() {
    const el = editorRef.value
    if (!el) {
        empty.value = true
        return
    }
    empty.value = (el.textContent || '').trim() === ''
}

function serializeEditor() {
    const el = editorRef.value
    if (!el) return ''
    let out = ''
    el.childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            out += node.textContent || ''
            return
        }
        if (node.nodeType === Node.ELEMENT_NODE) {
            const id = node.getAttribute('data-mention-id')
            const name = node.getAttribute('data-mention-name')
            if (id && name) {
                out += `@[${name}](id:${id})`
                return
            }
            out += node.textContent || ''
        }
    })
    return out
}

function emitValue() {
    emit('update:modelValue', serializeEditor())
    syncEmpty()
}

function getTextBeforeCaret() {
    const sel = window.getSelection()
    if (!sel || !sel.rangeCount || !editorRef.value) return ''
    const range = sel.getRangeAt(0).cloneRange()
    range.selectNodeContents(editorRef.value)
    range.setEnd(sel.focusNode, sel.focusOffset)
    return range.toString()
}

function updateMentionQuery() {
    const before = getTextBeforeCaret()
    const match = before.match(/@([^\s@]*)$/)
    if (match) {
        mentionQuery.value = match[1] || ''
        const q = mentionQuery.value.toLowerCase()
        const list = props.mentionableUsers || []
        mentionFiltered.value = (q
            ? list.filter((u) => u.name?.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q))
            : list
        ).slice(0, 8)
        mentionOpen.value = mentionFiltered.value.length > 0
    } else {
        mentionOpen.value = false
        mentionQuery.value = ''
        mentionFiltered.value = []
    }
}

function onInput() {
    updateMentionQuery()
    emitValue()
}

function onKeydown(e) {
    if (e.key === 'Escape' && mentionOpen.value) {
        mentionOpen.value = false
        e.preventDefault()
    }
}

function deleteQueryBeforeCaret() {
    const sel = window.getSelection()
    if (!sel || !sel.rangeCount) return
    const node = sel.focusNode
    if (!node || node.nodeType !== Node.TEXT_NODE) return
    const text = node.textContent || ''
    const offset = sel.focusOffset
    const before = text.slice(0, offset)
    const match = before.match(/@([^\s@]*)$/)
    if (!match) return
    const start = offset - match[0].length
    node.textContent = before.slice(0, start) + text.slice(offset)
    const range = document.createRange()
    range.setStart(node, start)
    range.collapse(true)
    sel.removeAllRanges()
    sel.addRange(range)
}

function insertMention(user) {
    const el = editorRef.value
    if (!el) return
    el.focus()
    deleteQueryBeforeCaret()

    const chip = document.createElement('span')
    chip.className = 'mention-chip'
    chip.contentEditable = 'false'
    chip.setAttribute('data-mention-id', String(user.id))
    chip.setAttribute('data-mention-name', user.name)
    chip.textContent = `@${user.name}`

    const sel = window.getSelection()
    if (sel && sel.rangeCount) {
        const range = sel.getRangeAt(0)
        range.deleteContents()
        range.insertNode(chip)
        const space = document.createTextNode(' ')
        range.setStartAfter(chip)
        range.insertNode(space)
        range.setStartAfter(space)
        range.collapse(true)
        sel.removeAllRanges()
        sel.addRange(range)
    } else {
        el.appendChild(chip)
        el.appendChild(document.createTextNode(' '))
    }

    mentionOpen.value = false
    mentionQuery.value = ''
    mentionFiltered.value = []
    emitValue()
}

function clearEditor() {
    const el = editorRef.value
    if (!el) return
    el.innerHTML = ''
    syncEmpty()
}

watch(() => props.modelValue, (val) => {
    if (!val && editorRef.value && serializeEditor() !== '') {
        clearEditor()
    }
})

onMounted(() => {
    nextTick(syncEmpty)
})

defineExpose({ clearEditor, focus: () => editorRef.value?.focus() })
</script>

<template>
    <div class="relative">
        <div
            ref="editorRef"
            class="mention-editor w-full min-h-[4.5rem] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            dusk="approval-comment"
            @input="onInput"
            @keydown="onKeydown"
            @keyup="updateMentionQuery"
            @click="updateMentionQuery"
        />
        <span
            v-if="empty"
            class="pointer-events-none absolute left-3 top-2 text-sm text-gray-400"
        >
            {{ placeholder }}
        </span>
        <div
            v-if="mentionOpen && mentionFiltered.length"
            class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto"
            dusk="mention-suggestions"
        >
            <button
                v-for="u in mentionFiltered"
                :key="u.id"
                type="button"
                class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 cursor-pointer"
                @mousedown.prevent="insertMention(u)"
            >
                <span class="font-medium text-gray-800">{{ u.name }}</span>
                <span class="text-xs text-gray-400 ml-2">{{ u.email }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.mention-editor :deep(.mention-chip),
.mention-editor .mention-chip {
    display: inline-flex;
    align-items: center;
    padding: 0 0.4rem;
    margin: 0 0.1rem;
    border-radius: 9999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 600;
    font-size: 0.75rem;
    line-height: 1.4;
    vertical-align: baseline;
    user-select: all;
}
.mention-editor:empty:before {
    content: none;
}
</style>
