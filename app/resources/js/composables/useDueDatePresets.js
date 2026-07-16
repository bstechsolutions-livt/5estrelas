import { ref } from 'vue'

export const DUE_DATE_PRESET_GROUPS = [
    {
        id: 'a_vencer',
        label: 'A vencer',
        hint: 'Vence hoje ou ainda vai vencer',
        presets: [
            { key: 'av_hoje', label: 'Hoje' },
            { key: 'av_amanha', label: 'Amanhã' },
            { key: 'av_semana', label: 'Esta semana' },
            { key: 'av_mes', label: 'Este mês' },
            { key: 'av_mes_proximo', label: 'Mês que vem' },
            { key: 'av_ano', label: 'Este ano' },
        ],
    },
    {
        id: 'vencidos',
        label: 'Já venceram',
        hint: 'Vencimento anterior a hoje',
        presets: [
            { key: 'vc_ontem', label: 'Ontem' },
            { key: 'vc_semana', label: 'Esta semana' },
            { key: 'vc_mes', label: 'Este mês' },
            { key: 'vc_mes_passado', label: 'Mês passado' },
            { key: 'vc_ano', label: 'Este ano' },
            { key: 'vc_todos', label: 'Todos vencidos' },
        ],
    },
]

export const DUE_DATE_PRESETS = DUE_DATE_PRESET_GROUPS.flatMap((g) => g.presets)

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

function addDays(date, days) {
    const d = new Date(date)
    d.setDate(d.getDate() + days)
    return d
}

function mondayOfWeek(date) {
    const day = date.getDay()
    const mondayOffset = day === 0 ? -6 : 1 - day
    return addDays(date, mondayOffset)
}

function sundayOfWeek(monday) {
    return addDays(monday, 6)
}

/** @returns {[string, string]} */
export function duePresetRange(key) {
    const today = todayAtNoon()
    const yesterday = addDays(today, -1)

    switch (key) {
        case 'av_hoje':
            return [toYmd(today), toYmd(today)]
        case 'av_amanha': {
            const tomorrow = addDays(today, 1)
            return [toYmd(tomorrow), toYmd(tomorrow)]
        }
        case 'av_semana':
            return [toYmd(today), toYmd(sundayOfWeek(mondayOfWeek(today)))]
        case 'av_mes': {
            const end = new Date(today.getFullYear(), today.getMonth() + 1, 0, 12)
            return [toYmd(today), toYmd(end)]
        }
        case 'av_mes_proximo': {
            const start = new Date(today.getFullYear(), today.getMonth() + 1, 1, 12)
            const end = new Date(today.getFullYear(), today.getMonth() + 2, 0, 12)
            return [toYmd(start), toYmd(end)]
        }
        case 'av_ano': {
            const end = new Date(today.getFullYear(), 11, 31, 12)
            return [toYmd(today), toYmd(end)]
        }
        case 'vc_ontem':
            return [toYmd(yesterday), toYmd(yesterday)]
        case 'vc_semana':
            return [toYmd(mondayOfWeek(today)), toYmd(yesterday)]
        case 'vc_mes': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1, 12)
            return [toYmd(start), toYmd(yesterday)]
        }
        case 'vc_mes_passado': {
            const start = new Date(today.getFullYear(), today.getMonth() - 1, 1, 12)
            const end = new Date(today.getFullYear(), today.getMonth(), 0, 12)
            return [toYmd(start), toYmd(end)]
        }
        case 'vc_ano': {
            const start = new Date(today.getFullYear(), 0, 1, 12)
            return [toYmd(start), toYmd(yesterday)]
        }
        case 'vc_todos':
            return ['', toYmd(yesterday)]
        default:
            return ['', '']
    }
}

export function detectDuePreset(dueFrom, dueTo) {
    if (!dueFrom && !dueTo) return null
    for (const preset of DUE_DATE_PRESETS) {
        const [from, to] = duePresetRange(preset.key)
        const fromMatch = (from || '') === (dueFrom || '')
        const toMatch = (to || '') === (dueTo || '')
        if (fromMatch && toMatch) {
            return preset.key
        }
    }
    return null
}

export function duePresetGroupId(key) {
    if (!key) return null
    return key.startsWith('vc_') ? 'vencidos' : key.startsWith('av_') ? 'a_vencer' : null
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

    function presetChipClass(key, groupId) {
        const active = duePreset.value === key
        if (groupId === 'vencidos') {
            return active
                ? 'bg-amber-600 text-white border-amber-600'
                : 'bg-white text-amber-800 border-amber-200 hover:border-amber-400'
        }
        return active
            ? 'bg-blue-600 text-white border-blue-600'
            : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-700'
    }

    return {
        duePreset,
        applyDuePreset,
        clearDuePreset,
        onDueDateManualChange,
        presetChipClass,
    }
}

/** Presets de data de emissão (histórico → hoje). */
export const ISSUE_DATE_PRESET_GROUPS = [
    {
        id: 'emissao',
        label: 'Data de emissão',
        hint: 'Quando o título foi emitido',
        presets: [
            { key: 'em_hoje', label: 'Hoje' },
            { key: 'em_ontem', label: 'Ontem' },
            { key: 'em_semana', label: 'Esta semana' },
            { key: 'em_mes', label: 'Este mês' },
            { key: 'em_mes_passado', label: 'Mês passado' },
            { key: 'em_ano', label: 'Este ano' },
        ],
    },
]

export const ISSUE_DATE_PRESETS = ISSUE_DATE_PRESET_GROUPS.flatMap((g) => g.presets)

/** @returns {[string, string]} */
export function issuePresetRange(key) {
    const today = todayAtNoon()
    const yesterday = addDays(today, -1)

    switch (key) {
        case 'em_hoje':
            return [toYmd(today), toYmd(today)]
        case 'em_ontem':
            return [toYmd(yesterday), toYmd(yesterday)]
        case 'em_semana':
            return [toYmd(mondayOfWeek(today)), toYmd(today)]
        case 'em_mes': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1, 12)
            return [toYmd(start), toYmd(today)]
        }
        case 'em_mes_passado': {
            const start = new Date(today.getFullYear(), today.getMonth() - 1, 1, 12)
            const end = new Date(today.getFullYear(), today.getMonth(), 0, 12)
            return [toYmd(start), toYmd(end)]
        }
        case 'em_ano': {
            const start = new Date(today.getFullYear(), 0, 1, 12)
            return [toYmd(start), toYmd(today)]
        }
        default:
            return ['', '']
    }
}

export function detectIssuePreset(issueFrom, issueTo) {
    if (!issueFrom && !issueTo) return null
    for (const preset of ISSUE_DATE_PRESETS) {
        const [from, to] = issuePresetRange(preset.key)
        if ((from || '') === (issueFrom || '') && (to || '') === (issueTo || '')) {
            return preset.key
        }
    }
    return null
}

export function useIssueDatePresets(issueFrom, issueTo) {
    const issuePreset = ref(detectIssuePreset(issueFrom.value, issueTo.value))

    function applyIssuePreset(key) {
        const [from, to] = issuePresetRange(key)
        issueFrom.value = from
        issueTo.value = to
        issuePreset.value = key
    }

    function clearIssuePreset() {
        issuePreset.value = null
    }

    function onIssueDateManualChange() {
        issuePreset.value = detectIssuePreset(issueFrom.value, issueTo.value)
    }

    function issuePresetChipClass(key) {
        const active = issuePreset.value === key
        return active
            ? 'bg-emerald-600 text-white border-emerald-600'
            : 'bg-white text-emerald-800 border-emerald-200 hover:border-emerald-400'
    }

    return {
        issuePreset,
        applyIssuePreset,
        clearIssuePreset,
        onIssueDateManualChange,
        issuePresetChipClass,
    }
}
