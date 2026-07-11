export const PAYABLE_SORT_OPTIONS = [
    { label: 'Prioridade + vencimento (padrão)', value: 'default' },
    { label: 'Vencimento — mais antigo primeiro', value: 'due_date:asc' },
    { label: 'Vencimento — mais recente primeiro', value: 'due_date:desc' },
    { label: 'Valor — menor primeiro', value: 'amount:asc' },
    { label: 'Valor — maior primeiro', value: 'amount:desc' },
    { label: 'Fornecedor — A → Z', value: 'supplier_name:asc' },
    { label: 'Fornecedor — Z → A', value: 'supplier_name:desc' },
    { label: 'Nº título — crescente', value: 'title_number:asc' },
    { label: 'Nº título — decrescente', value: 'title_number:desc' },
    { label: 'SLA — mais antigo primeiro', value: 'payment_sla_date:asc' },
    { label: 'SLA — mais recente primeiro', value: 'payment_sla_date:desc' },
]

export function parseSortValue(value) {
    if (!value || value === 'default') {
        return { sort: 'default', dir: null }
    }
    const [sort, dir] = String(value).split(':')
    return { sort, dir: dir === 'desc' ? 'desc' : 'asc' }
}

export function toSortValue(sort, dir) {
    if (!sort || sort === 'default') {
        return 'default'
    }
    return `${sort}:${dir === 'desc' ? 'desc' : 'asc'}`
}

export function sortQueryFromValue(value) {
    const { sort, dir } = parseSortValue(value)
    if (sort === 'default') {
        return {}
    }
    return { sort, dir }
}

export function sortValueFromQuery(sort, dir) {
    return toSortValue(sort, dir)
}

export function tableSortState(value) {
    const { sort, dir } = parseSortValue(value)
    if (sort === 'default') {
        return { field: null, order: 0 }
    }
    return { field: sort, order: dir === 'asc' ? 1 : -1 }
}

export function sortValueFromTable(field, order) {
    if (!field || order === 0 || order === null || order === undefined) {
        return 'default'
    }
    return `${field}:${order === 1 ? 'asc' : 'desc'}`
}
