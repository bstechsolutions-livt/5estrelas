/** Resumo compacto das filiais liberadas (evita 15 tags na listagem). */
export function branchAccessSummary(branches, totalActive = null) {
    const list = branches || []
    const names = list.map(b => b.display_name || b.name)

    if (list.length === 0) {
        return { label: 'Nenhuma', severity: 'secondary', title: '' }
    }

    if (totalActive && list.length >= totalActive) {
        return {
            label: `Todas (${list.length})`,
            severity: 'info',
            title: names.join('\n'),
        }
    }

    if (list.length === 1) {
        return { label: names[0], severity: 'info', title: '' }
    }

    if (list.length === 2) {
        return { label: names.join(', '), severity: 'info', title: '' }
    }

    return {
        label: `${list.length} filiais`,
        severity: 'info',
        title: names.join('\n'),
    }
}
