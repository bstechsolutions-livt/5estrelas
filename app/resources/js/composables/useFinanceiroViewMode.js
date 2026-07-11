import { ref, watch, unref } from 'vue'

/**
 * Modo de visualização financeiro (resumida | detalhada) com persistência em localStorage.
 */
export function useFinanceiroViewMode(storageKey, serverView = 'resumida') {
    const cached = typeof window !== 'undefined' ? localStorage.getItem(storageKey) : null
    const initial = unref(serverView) || cached || 'resumida'
    const viewMode = ref(initial === 'detalhada' ? 'detalhada' : 'resumida')

    watch(
        () => unref(serverView),
        (v) => {
            if (!v) return
            viewMode.value = v === 'detalhada' ? 'detalhada' : 'resumida'
        },
    )

    function persistViewMode(mode) {
        viewMode.value = mode === 'detalhada' ? 'detalhada' : 'resumida'
        localStorage.setItem(storageKey, viewMode.value)
    }

    function withView(filters = {}) {
        return {
            ...filters,
            view: viewMode.value,
        }
    }

    return { viewMode, persistViewMode, withView }
}
