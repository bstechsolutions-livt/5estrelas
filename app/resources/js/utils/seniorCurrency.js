/**
 * Moeda do título Senior (campo codMoe / coluna codmoe).
 * Cadastro do cliente 5estrelas: 01 = Real, 03 = Dólar.
 */
const SENIOR_CURRENCY = {
    '01': { code: 'BRL', symbol: 'R$', label: 'Real' },
    '1': { code: 'BRL', symbol: 'R$', label: 'Real' },
    '03': { code: 'USD', symbol: 'US$', label: 'Dólar' },
    '3': { code: 'USD', symbol: 'US$', label: 'Dólar' },
}

export function resolveSeniorCurrency(codMoe) {
    const key = String(codMoe ?? '').trim()
    if (!key) {
        return SENIOR_CURRENCY['01']
    }
    return SENIOR_CURRENCY[key] || {
        code: 'BRL',
        symbol: `moe ${key}`,
        label: `Moeda ${key}`,
    }
}

/**
 * Formata valor do título na moeda Senior (codMoe).
 * Fallback: Real (BRL).
 */
export function formatPayableMoney(amount, codMoe = null) {
    const currency = resolveSeniorCurrency(codMoe)
    try {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: currency.code,
            currencyDisplay: 'symbol',
        }).format(Number(amount) || 0)
    } catch {
        const n = Number(amount) || 0
        return `${currency.symbol} ${n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    }
}
