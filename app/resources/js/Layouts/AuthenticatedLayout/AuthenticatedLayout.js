// Adaptador (shim) do "AuthenticatedLayout.js" da intranet Biglar.
// As telas portadas de gestao-contratos importam `* as layoutJs` e chamam
// `layoutJs.setPaginaNova(...)`. Aqui reproduzimos só o necessário como no-op
// para não precisar editar as telas copiadas.
import { ref } from 'vue'

export const paginaNova = ref(false)
export const isLoading = ref(false)
export const isMobile = ref(false)
export const permissoes = ref([])

export function setPaginaNova(isNova = true) {
    paginaNova.value = isNova
}

// No-ops para qualquer outra função de layout que telas portadas possam chamar
export function abrirDocumentacoes() {}
export function toggleMenu() {}
export function closeMobileMenu() {}
export function navegarEFecharMenu() {}
