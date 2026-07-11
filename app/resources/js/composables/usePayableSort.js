export const PAYABLE_SORT_GROUPS = [
    {
        label: 'Padrão',
        items: [
            { label: 'Prioridade + vencimento', value: 'default' },
        ],
    },
    {
        label: 'Datas',
        items: [
            { label: 'Vencimento — mais antigo', value: 'due_date:asc' },
            { label: 'Vencimento — mais recente', value: 'due_date:desc' },
            { label: 'SLA — mais antigo', value: 'payment_sla_date:asc' },
            { label: 'SLA — mais recente', value: 'payment_sla_date:desc' },
        ],
    },
    {
        label: 'Valores',
        items: [
            { label: 'Valor — menor primeiro', value: 'amount:asc' },
            { label: 'Valor — maior primeiro', value: 'amount:desc' },
        ],
    },
    {
        label: 'Identificação',
        items: [
            { label: 'Nº título — crescente', value: 'title_number:asc' },
            { label: 'Nº título — decrescente', value: 'title_number:desc' },
            { label: 'Apelido — A → Z', value: 'nickname:asc' },
            { label: 'Apelido — Z → A', value: 'nickname:desc' },
            { label: 'Fornecedor — A → Z', value: 'supplier_name:asc' },
            { label: 'Fornecedor — Z → A', value: 'supplier_name:desc' },
            { label: 'Empresa — A → Z', value: 'codemp:asc' },
            { label: 'Empresa — Z → A', value: 'codemp:desc' },
            { label: 'Departamento — A → Z', value: 'department_nome:asc' },
            { label: 'Departamento — Z → A', value: 'department_nome:desc' },
            { label: 'Descrição — A → Z', value: 'description:asc' },
            { label: 'Descrição — Z → A', value: 'description:desc' },
            { label: 'Prioridade — urgente primeiro', value: 'payment_priority:desc' },
            { label: 'Prioridade — normal primeiro', value: 'payment_priority:asc' },
        ],
    },
]

export const PAYABLE_SORT_OPTIONS = PAYABLE_SORT_GROUPS.flatMap((g) => g.items)

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