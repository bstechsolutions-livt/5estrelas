import { ref } from 'vue'

export const DUE_DATE_PRESETS = [
    { key: 'hoje', label: 'Vencem hoje' },
    { key: 'amanha', label: 'Amanhã' },
    { key: 'semana', label: 'Esta semana' },
    { key: 'mes', label: 'Este mês' },
    { key: 'mes_proximo', label: 'Mês que vem' },
    { key: 'ano', label: 'Este ano' },
]

function pad(n) {
    return String(n).padStart(2, '0')
}

export function toYmd(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function todayAtNoon() {
    const d = new Date()
    d.setHours(12, 0, 0, 0)
    return d
}

/** @returns {[string, string]} */
export function duePresetRange(key) {
    const today = todayAtNoon()

    switch (key) {
        case 'hoje':
            return [toYmd(today), toYmd(today)]
        case 'amanha': {
            const next = new Date(today)
            next.setDate(next.getDate() + 1)
            return [toYmd(next), toYmd(next)]
        }
        case 'semana': {
            const day = today.getDay()
            const mondayOffset = day === 0 ? -6 : 1 - day
            const monday = new Date(today)
            monday.setDate(today.getDate() + mondayOffset)
            const sunday = new Date(monday)
            sunday.setDate(monday.getDate() + 6)
            return [toYmd(monday), toYmd(sunday)]
        }
        case 'mes': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1, 12)
            const end = new Date(today.getFullYear(), today.getMonth() + 1, 0, 12)
            return [toYmd(start), toYmd(end)]
        }
        case 'mes_proximo': {
            const start = new Date(today.getFullYear(), today.getMonth() + 1, 1, 12)
            const end = new Date(today.getFullYear(), today.getMonth() + 2, 0, 12)
            return [toYmd(start), toYmd(end)]
        }
        case 'ano': {
            const start = new Date(today.getFullYear(), 0, 1, 12)
            const end = new Date(today.getFullYear(), 11, 31, 12)
            return [toYmd(start), toYmd(end)]
        }
        default:
            return ['', '']
    }
}

export function detectDuePreset(dueFrom, dueTo) {
    if (!dueFrom && !dueTo) return null
    for (const preset of DUE_DATE_PRESETS) {
        const [from, to] = duePresetRange(preset.key)
        if (dueFrom === from && dueTo === to) {
            return preset.key
        }
    }
    return null
}

export function useDueDatePresets(dueFrom, dueTo) {
    const duePreset = ref(detectDuePreset(dueFrom.value, dueTo.value))

    function applyDuePreset(key) {
        const [from, to] = duePresetRange(key)
        dueFrom.value = from
        dueTo.value = to
        duePreset.value = key
    }

    function clearDuePreset() {
        duePreset.value = null
    }

    function onDueDateManualChange() {
        duePreset.value = detectDuePreset(dueFrom.value, dueTo.value)
    }

    return {
        duePreset,
        applyDuePreset,
        clearDuePreset,
        onDueDateManualChange,
    }
}
