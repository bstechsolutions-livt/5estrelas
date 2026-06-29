<script setup>
/**
 * SearchSelect — combobox com busca para o módulo Comercial (Gestão 360º).
 *
 * Substitui os <select>/<input> nativos por um campo com filtro em tempo real,
 * navegação por teclado e (opcional) texto livre. O painel é "teleportado" para
 * o body e posicionado pelo bounding rect do input, evitando recorte dentro de
 * modais (.modal tem overflow-y:auto).
 *
 * Visual: o input usa a classe .form-input do protótipo; o painel usa cores
 * literais do design + var(--app-primary) (white-label) para o destaque.
 *
 * Uso (entidade — valor = slug/id):
 *   <SearchSelect v-model="form.empresa" :options="filiais"
 *     option-value="slug" option-label="nome" option-sub="tag"
 *     dusk="cot-empresa" option-dusk-prefix="cot-empresa-opt" />
 *
 * Uso (texto livre — ex.: nome do cliente em campo string):
 *   <SearchSelect v-model="form.cliente" :options="clientes"
 *     option-value="nome" option-label="nome" option-sub="cidade"
 *     allow-free placeholder="Buscar cliente..." dusk="prop-form-cliente" />
 */
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: '' },
  options: { type: Array, default: () => [] },
  optionValue: { type: String, default: 'value' },
  optionLabel: { type: String, default: 'label' },
  optionSub: { type: String, default: null },
  placeholder: { type: String, default: 'Buscar...' },
  allowFree: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  dusk: { type: String, default: null },
  optionDuskPrefix: { type: String, default: null },
  emptyText: { type: String, default: 'Nenhum resultado' },
  maxItems: { type: Number, default: 100 },
})

const emit = defineEmits(['update:modelValue', 'select', 'change'])

// ─── Helpers de leitura das opções (suporta objeto ou string) ──────────────────
const getVal = (o) => (o == null ? '' : (typeof o === 'object' ? o[props.optionValue] : o))
const getLabel = (o) => (o == null ? '' : (typeof o === 'object' ? o[props.optionLabel] : o))
const getSub = (o) => (props.optionSub && o && typeof o === 'object' ? o[props.optionSub] : null)

// ─── Estado ───────────────────────────────────────────────────────────────────
const open = ref(false)
const dirty = ref(false)          // o usuário digitou desde que abriu?
const text = ref('')              // valor exibido no input
const activeIndex = ref(-1)
const inputRef = ref(null)
const panelRef = ref(null)
const wrapRef = ref(null)
const panelStyle = ref({})

// Rótulo do valor selecionado atual.
const selectedLabel = computed(() => {
  const match = props.options.find((o) => String(getVal(o)) === String(props.modelValue))
  if (match) return getLabel(match)
  // Valor fora da lista (ex.: slug legado) ou texto livre: exibe o próprio valor
  // cru para não "sumir" o dado já gravado.
  if (props.modelValue !== null && props.modelValue !== '') return String(props.modelValue)
  return ''
})

// Sincroniza o texto exibido com o rótulo resolvido (muda quando o valor OU as
// opções mudam) — desde que o campo não esteja em edição.
watch(
  selectedLabel,
  (v) => { if (!open.value) text.value = v || '' },
  { immediate: true },
)

const filtered = computed(() => {
  const q = (dirty.value ? text.value : '').trim().toLowerCase()
  const list = props.options
  const res = !q
    ? list
    : list.filter((o) => {
        const l = String(getLabel(o) ?? '').toLowerCase()
        const s = String(getSub(o) ?? '').toLowerCase()
        return l.includes(q) || s.includes(q)
      })
  return res.slice(0, props.maxItems)
})

function positionPanel() {
  const el = inputRef.value
  if (!el) return
  const r = el.getBoundingClientRect()
  panelStyle.value = {
    position: 'fixed',
    left: `${r.left}px`,
    top: `${r.bottom + 4}px`,
    width: `${r.width}px`,
  }
}

function abrir() {
  if (props.disabled) return
  open.value = true
  dirty.value = false
  activeIndex.value = filtered.value.findIndex((o) => String(getVal(o)) === String(props.modelValue))
  nextTick(() => {
    positionPanel()
    // Seleciona todo o texto para que digitar substitua o valor atual
    // (UX de combobox editável + torna a edição via teclado/automação confiável).
    if (inputRef.value) inputRef.value.select()
  })
  window.addEventListener('scroll', positionPanel, true)
  window.addEventListener('resize', positionPanel)
}

function fechar() {
  open.value = false
  window.removeEventListener('scroll', positionPanel, true)
  window.removeEventListener('resize', positionPanel)
  // Sem texto livre: descarta o que foi digitado e volta ao rótulo selecionado.
  if (!props.allowFree) text.value = selectedLabel.value || ''
}

function onInput(e) {
  dirty.value = true
  text.value = e.target.value
  if (!open.value) abrir()
  activeIndex.value = 0
  // Texto livre reflete imediatamente no v-model (ex.: nome do cliente).
  if (props.allowFree) {
    emit('update:modelValue', text.value)
    emit('change', text.value)
  }
}

function escolher(o) {
  const v = getVal(o)
  emit('update:modelValue', v)
  emit('select', o)
  emit('change', v)
  text.value = getLabel(o) || ''
  dirty.value = false
  fechar()
  nextTick(() => inputRef.value && inputRef.value.blur())
}

function limpar() {
  emit('update:modelValue', props.allowFree ? '' : null)
  emit('change', null)
  text.value = ''
  dirty.value = false
  nextTick(() => inputRef.value && inputRef.value.focus())
}

// ─── Teclado ────────────────────────────────────────────────────────────────
function onKeydown(e) {
  if (['ArrowDown', 'ArrowUp'].includes(e.key)) {
    if (!open.value) abrir()
    e.preventDefault()
    const n = filtered.value.length
    if (!n) return
    activeIndex.value = e.key === 'ArrowDown'
      ? (activeIndex.value + 1) % n
      : (activeIndex.value - 1 + n) % n
  } else if (e.key === 'Enter') {
    if (open.value && filtered.value[activeIndex.value]) {
      e.preventDefault()
      escolher(filtered.value[activeIndex.value])
    }
  } else if (e.key === 'Escape') {
    if (open.value) { e.preventDefault(); fechar() }
  }
}

// Fecha ao clicar fora (input + painel teleportado).
function onDocMousedown(e) {
  if (!open.value) return
  const inWrap = wrapRef.value && wrapRef.value.contains(e.target)
  const inPanel = panelRef.value && panelRef.value.contains(e.target)
  if (!inWrap && !inPanel) fechar()
}
if (typeof document !== 'undefined') document.addEventListener('mousedown', onDocMousedown)

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') document.removeEventListener('mousedown', onDocMousedown)
  window.removeEventListener('scroll', positionPanel, true)
  window.removeEventListener('resize', positionPanel)
})

const optionDusk = (o) => (props.optionDuskPrefix ? `${props.optionDuskPrefix}-${getVal(o)}` : null)
</script>

<template>
  <div ref="wrapRef" class="bs-ss" role="combobox" :aria-expanded="open">
    <div class="bs-ss-field">
      <input
        ref="inputRef"
        type="text"
        class="form-input bs-ss-input"
        :class="{ 'bs-ss-clearable': clearable }"
        :value="text"
        :placeholder="placeholder"
        :disabled="disabled"
        :dusk="dusk"
        autocomplete="off"
        role="searchbox"
        aria-autocomplete="list"
        @focus="abrir"
        @input="onInput"
        @keydown="onKeydown"
      >
      <button
        v-if="clearable && (modelValue !== null && modelValue !== '' )"
        type="button"
        class="bs-ss-clear"
        tabindex="-1"
        aria-label="Limpar"
        :dusk="dusk ? dusk + '-clear' : null"
        @click="limpar"
      >×</button>
      <span class="bs-ss-caret" :class="{ up: open }" aria-hidden="true">▾</span>
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        class="bs-ss-panel"
        :style="panelStyle"
        role="listbox"
      >
        <button
          v-for="(o, i) in filtered"
          :key="getVal(o) ?? i"
          type="button"
          class="bs-ss-option"
          :class="{ active: i === activeIndex, selected: String(getVal(o)) === String(modelValue) }"
          :dusk="optionDusk(o)"
          role="option"
          :aria-selected="String(getVal(o)) === String(modelValue)"
          @mouseenter="activeIndex = i"
          @click="escolher(o)"
        >
          <span class="bs-ss-opt-label">{{ getLabel(o) }}</span>
          <span v-if="getSub(o)" class="bs-ss-opt-sub">{{ getSub(o) }}</span>
        </button>
        <div v-if="!filtered.length" class="bs-ss-empty">{{ emptyText }}</div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.bs-ss { position: relative; width: 100%; }
.bs-ss-field { position: relative; display: flex; align-items: center; }
.bs-ss-input { width: 100%; padding-right: 46px; }
.bs-ss-clearable { padding-right: 46px; }
.bs-ss-clear {
  position: absolute; right: 26px; top: 50%; transform: translateY(-50%);
  border: none; background: transparent; cursor: pointer;
  color: #8A96A8; font-size: 18px; line-height: 1; padding: 0 4px;
}
.bs-ss-clear:hover { color: #C0392B; }
.bs-ss-caret {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  color: #8A96A8; font-size: 11px; pointer-events: none; transition: transform 0.15s;
}
.bs-ss-caret.up { transform: translateY(-50%) rotate(180deg); }
</style>

<style>
/* Painel teleportado para o body (fora do .g360) — cores literais do design +
   var(--app-primary) global para o destaque white-label. */
.bs-ss-panel {
  z-index: 2000;
  max-height: 260px;
  overflow-y: auto;
  background: #FFFFFF;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 8px;
  box-shadow: 0 12px 36px rgba(0, 0, 0, 0.16);
  padding: 4px;
  font-family: 'Calibri', 'Inter', 'Segoe UI', Arial, sans-serif;
}
.bs-ss-option {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: left;
  border: none;
  background: transparent;
  border-radius: 6px;
  padding: 9px 12px;
  cursor: pointer;
  font-family: inherit;
}
.bs-ss-option:hover,
.bs-ss-option.active {
  background: color-mix(in srgb, var(--app-primary, #B8922A) 12%, transparent);
}
.bs-ss-option.selected { background: color-mix(in srgb, var(--app-primary, #B8922A) 18%, transparent); }
.bs-ss-opt-label { font-size: 13px; font-weight: 600; color: #1A2333; }
.bs-ss-opt-sub { font-size: 11px; color: #8A96A8; }
.bs-ss-empty { padding: 14px; text-align: center; color: #8A96A8; font-size: 12px; }
</style>
