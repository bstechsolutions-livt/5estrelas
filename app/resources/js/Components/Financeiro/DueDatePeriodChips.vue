<script setup>
import { DUE_DATE_PRESET_GROUPS } from '@/composables/useDueDatePresets'

defineProps({
    /** Chave do preset ativo (ex.: av_hoje) ou null */
    activeKey: { type: String, default: null },
    /** Densidade visual: desktop (CP) ou compacta (mobile / Assinaturas) */
    compact: { type: Boolean, default: false },
    /** Exibe o título "Período de vencimento" */
    showTitle: { type: Boolean, default: true },
})

const emit = defineEmits(['select'])

function chipClass(key, groupId, activeKey) {
    const active = activeKey === key
    if (groupId === 'vencidos') {
        return active
            ? 'bg-amber-600 text-white border-amber-600'
            : 'bg-white text-amber-800 border-amber-200 hover:border-amber-400'
    }
    return active
        ? 'bg-blue-600 text-white border-blue-600'
        : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-700'
}
</script>

<template>
    <div class="space-y-3">
        <p
            v-if="showTitle"
            :class="compact
                ? 'text-xs font-semibold text-gray-600'
                : 'text-[11px] font-semibold uppercase tracking-wide text-gray-500'"
        >
            Período de vencimento
        </p>

        <div
            v-for="group in DUE_DATE_PRESET_GROUPS"
            :key="group.id"
            :class="[
                'rounded-lg border',
                compact ? 'px-2 py-2' : 'px-3 py-2.5',
                group.id === 'vencidos'
                    ? (compact ? 'border-amber-200 bg-amber-50/70' : 'border-amber-200 bg-amber-50/60')
                    : (compact ? 'border-blue-100 bg-white' : 'border-blue-100 bg-white/80'),
            ]"
        >
            <div
                v-if="!compact"
                class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 mb-2"
            >
                <span
                    :class="[
                        'text-xs font-semibold',
                        group.id === 'vencidos' ? 'text-amber-800' : 'text-blue-800',
                    ]"
                >
                    {{ group.label }}
                </span>
                <span class="text-[11px] text-gray-500">{{ group.hint }}</span>
            </div>
            <p
                v-else
                :class="[
                    'text-[11px] font-semibold mb-1.5',
                    group.id === 'vencidos' ? 'text-amber-800' : 'text-blue-800',
                ]"
            >
                {{ group.label }}
            </p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="preset in group.presets"
                    :key="preset.key"
                    type="button"
                    :class="[
                        'rounded-full font-medium transition-colors border',
                        compact ? 'px-2 py-1 text-[11px]' : 'px-2.5 py-1 text-xs',
                        chipClass(preset.key, group.id, activeKey),
                    ]"
                    @click="emit('select', preset.key)"
                >
                    {{ preset.label }}
                </button>
            </div>
        </div>
    </div>
</template>
