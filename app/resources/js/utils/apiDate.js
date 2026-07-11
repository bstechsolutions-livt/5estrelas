/** Converte data da API (YYYY-MM-DD ou ISO) para Date local, sem shift de timezone. */
export function parseApiDate(val) {
    if (!val) return null
    if (val instanceof Date) {
        return Number.isNaN(val.getTime()) ? null : val
    }
    const s = String(val).trim()
    const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (iso) {
        const dt = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]), 12, 0, 0)
        return Number.isNaN(dt.getTime()) ? null : dt
    }
    const dt = new Date(s.includes('T') ? s : `${s}T12:00:00`)
    return Number.isNaN(dt.getTime()) ? null : dt
}

/** Formata data-only da API em pt-BR (ex.: 2026-07-10 → 10/07/2026). */
export function formatApiDate(val) {
    const dt = parseApiDate(val)
    if (!dt) return '—'
    return dt.toLocaleDateString('pt-BR')
}
