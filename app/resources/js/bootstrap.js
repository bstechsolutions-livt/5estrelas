import axios from 'axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Pega o CSRF token do meta tag
const tokenMeta = document.head.querySelector('meta[name="csrf-token"]')
if (tokenMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.content
}

// Reverb (WebSocket)
window.Pusher = Pusher

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': tokenMeta?.content,
        },
    },
})

// ─────────────────────────────────────────────────────────────────
// Interceptador global de links internos → navegação SPA (Inertia)
// Telas portadas (ex: gestão de contratos) usam <a href="/pagina/..."> nos
// breadcrumbs. Link HTML puro recarrega a página inteira. Aqui interceptamos
// cliques em links internos e roteamos pelo Inertia, sem precisar editar cada
// tela. Arquivos (/storage), externos, target=_blank, download e cliques com
// modificadores (ctrl/cmd/abrir em nova aba) continuam com comportamento padrão.
// ─────────────────────────────────────────────────────────────────
import { router } from '@inertiajs/vue3'

document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0) return
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return

    const a = e.target.closest('a')
    if (!a) return

    const href = a.getAttribute('href')
    if (!href) return

    // Ignora: nova aba, download, âncoras, mailto/tel, externos
    if (a.target && a.target !== '' && a.target !== '_self') return
    if (a.hasAttribute('download')) return
    if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return

    // Só links internos relativos (mesma origem), exceto arquivos em /storage
    if (!href.startsWith('/') || href.startsWith('//')) return
    if (href.startsWith('/storage')) return

    e.preventDefault()
    router.visit(href)
})
