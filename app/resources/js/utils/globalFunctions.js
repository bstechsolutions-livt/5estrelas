// Helpers portados da intranet Biglar, usados pelas telas de gestão de contratos.
// Mantém a mesma assinatura para não precisar editar as telas copiadas.
import Swal from 'sweetalert2'

export function formatarCnpj(valor) {
    if (!valor) return ''
    const cnpj = valor.toString().replace(/\D/g, '')
    if (cnpj.length !== 14) return valor
    return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5')
}

export function formatarCpf(cpf) {
    if (!cpf) return ''
    const digits = cpf.replace(/\D/g, '')
    if (digits.length >= 11) {
        return digits.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*$/, '$1.$2.$3-$4')
    }
    return cpf
}

export async function swalConfirm(
    titulo = 'Você tem certeza?',
    texto = 'Essa ação não pode ser desfeita.',
    confirmText = 'Sim',
    cancelText = 'Cancelar',
    options = {},
) {
    const iconType = options.icon || 'warning'
    const confirmColor = options.danger ? '#dc2626' : '#16a34a'
    const gradientStart = options.danger ? '#ef4444' : '#f59e0b'
    const gradientEnd = options.danger ? '#dc2626' : '#d97706'

    const icons = {
        warning: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`,
        trash: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>`,
        question: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`,
    }
    const selectedIcon = icons[iconType] || icons.warning

    return Swal.fire({
        html: `
      <div style="display:flex;flex-direction:column;align-items:center;gap:1rem;padding:.5rem 0;">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,${gradientStart} 0%,${gradientEnd} 100%);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px ${gradientStart}66;">${selectedIcon}</div>
        <div style="text-align:center;font-size:1.1rem;font-weight:600;color:#1e293b;line-height:1.5;">${titulo}</div>
        <div style="text-align:center;font-size:.875rem;color:#64748b;">${texto}</div>
      </div>`,
        showConfirmButton: true,
        showCancelButton: true,
        confirmButtonText: `<i class="pi pi-${options.danger ? 'trash' : 'check'}" style="margin-right:6px;"></i>${confirmText}`,
        cancelButtonText: `<i class="pi pi-times" style="margin-right:6px;"></i>${cancelText}`,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#64748b',
        focusCancel: options.danger || false,
        allowOutsideClick: false,
        allowEscapeKey: true,
        allowEnterKey: true,
    })
}

export async function swalInput(
    titulo = 'Digite um valor',
    inputLabel = '',
    placeholder = 'Digite aqui...',
    confirmText = 'Confirmar',
    cancelText = 'Cancelar',
    options = {},
) {
    const confirmColor = options.danger ? '#dc2626' : '#16a34a'
    const gradientStart = options.danger ? '#ef4444' : '#3b82f6'
    const gradientEnd = options.danger ? '#dc2626' : '#2563eb'
    const editIcon = `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>`

    return Swal.fire({
        html: `
      <div style="display:flex;flex-direction:column;align-items:center;gap:1rem;padding:.5rem 0;">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,${gradientStart} 0%,${gradientEnd} 100%);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px ${gradientStart}66;">${editIcon}</div>
        <div style="text-align:center;font-size:1.1rem;font-weight:600;color:#1e293b;line-height:1.5;">${titulo}</div>
        ${inputLabel ? `<div style="text-align:center;font-size:.875rem;color:#64748b;">${inputLabel}</div>` : ''}
      </div>`,
        input: options.inputType || 'text',
        inputPlaceholder: placeholder,
        inputValidator: (value) => {
            if (!value && options.required !== false) {
                return options.requiredMessage || 'Este campo é obrigatório!'
            }
        },
        showConfirmButton: true,
        showCancelButton: true,
        confirmButtonText: `<i class="pi pi-check" style="margin-right:6px;"></i>${confirmText}`,
        cancelButtonText: `<i class="pi pi-times" style="margin-right:6px;"></i>${cancelText}`,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#64748b',
        allowOutsideClick: false,
    })
}

import { useToast } from 'vue-toastification'

export function toastError(message) {
    useToast().error(message)
}
export function toastWarning(message) {
    useToast().warning(message)
}
export function toastSuccess(message) {
    useToast().success(message)
}

export function swalErro(
    titulo = 'Oops...',
    mensagem = 'Ocorreu um erro inesperado. Tente novamente!',
    icone = 'error',
) {
    return Swal.fire({
        icon: icone,
        title: titulo,
        text: mensagem,
        confirmButtonText: `<i class="pi pi-check" style="margin-right:6px;"></i>OK`,
        confirmButtonColor: '#dc2626',
        allowOutsideClick: false,
        showCancelButton: false,
    })
}

// Detecta se está rodando dentro do WebView do app (Flutter). No web, retorna undefined.
export async function getDevice() {
    const isFlutter = typeof window.flutter_inappwebview !== 'undefined'
    if (!isFlutter) return undefined
    const existente = window.localStorage.getItem('flutter.dispositivo')
    if (existente) return existente
    try {
        const device = await window.flutter_inappwebview.callHandler('getDevice')
        if (device) {
            window.localStorage.setItem('flutter.dispositivo', device)
            return device
        }
    } catch (e) {
        console.error("Canal 'getDevice' não disponível:", e)
    }
    return undefined
}
