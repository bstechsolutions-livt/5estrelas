import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

const items = ref([])
const unreadCount = ref(0)
const loading = ref(false)
const loadingMore = ref(false)
const hasMore = ref(false)
const lastIncoming = ref(null)
const wsConnected = ref(false) // pra UI mostrar status se quiser
const PAGE_SIZE = 20
const POLL_INTERVAL_MS = 30000

let pollHandle = null
let activeRefs = 0
let echoChannel = null
let echoUserId = null
let pusherConn = null
let stateHandler = null

async function fetchList() {
    loading.value = true
    try {
        const { data } = await axios.get('/notificacoes', {
            params: { limit: PAGE_SIZE },
            headers: { Accept: 'application/json' },
        })
        items.value = data.items || []
        unreadCount.value = data.unread_count || 0
        hasMore.value = (data.items || []).length === PAGE_SIZE
    } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('Falha ao buscar notificações', e)
    } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (loadingMore.value || !hasMore.value || items.value.length === 0) return
    loadingMore.value = true
    try {
        const last = items.value[items.value.length - 1]
        const { data } = await axios.get('/notificacoes', {
            params: { limit: PAGE_SIZE, before_id: last.id },
            headers: { Accept: 'application/json' },
        })
        const newItems = data.items || []
        items.value.push(...newItems)
        hasMore.value = newItems.length === PAGE_SIZE
    } catch (e) {
        // ignora
    } finally {
        loadingMore.value = false
    }
}

async function refreshCount() {
    try {
        const { data } = await axios.get('/notificacoes/contador', {
            headers: { Accept: 'application/json' },
        })
        unreadCount.value = data.unread_count || 0
    } catch (e) {
        // silencioso
    }
}

async function markRead(id) {
    try {
        await axios.post(`/notificacoes/${id}/lida`)
        const it = items.value.find(n => n.id === id)
        if (it && !it.read_at) {
            it.read_at = new Date().toISOString()
            unreadCount.value = Math.max(0, unreadCount.value - 1)
        }
    } catch (e) {
        // ignora
    }
}

async function markAllRead() {
    try {
        await axios.post('/notificacoes/marcar-todas')
        const now = new Date().toISOString()
        items.value.forEach(n => { if (!n.read_at) n.read_at = now })
        unreadCount.value = 0
    } catch (e) {
        // ignora
    }
}

async function destroy(id) {
    try {
        await axios.delete(`/notificacoes/${id}`)
        const idx = items.value.findIndex(n => n.id === id)
        if (idx >= 0) {
            const removed = items.value[idx]
            items.value.splice(idx, 1)
            if (!removed.read_at) {
                unreadCount.value = Math.max(0, unreadCount.value - 1)
            }
        }
    } catch (e) {
        // ignora
    }
}

function startPolling() {
    if (pollHandle) return
    pollHandle = setInterval(() => {
        refreshCount()
    }, POLL_INTERVAL_MS)
}

function stopPolling() {
    if (pollHandle) {
        clearInterval(pollHandle)
        pollHandle = null
    }
}

function bindConnectionState() {
    if (!window.Echo || !window.Echo.connector?.pusher) return

    pusherConn = window.Echo.connector.pusher.connection
    if (!pusherConn) return

    stateHandler = (states) => {
        // states: { previous, current }
        const current = states?.current || pusherConn.state
        if (current === 'connected') {
            wsConnected.value = true
            // Quando o WS conecta, dispensa o polling
            stopPolling()
            // Atualiza o contador na hora pra refletir tempo offline
            refreshCount()
        } else if (current === 'disconnected' || current === 'unavailable' || current === 'failed') {
            wsConnected.value = false
            // Quando perde a conexão, ativa o polling como fallback
            startPolling()
        }
    }

    pusherConn.bind('state_change', stateHandler)

    // Estado inicial
    if (pusherConn.state === 'connected') {
        wsConnected.value = true
        stopPolling()
    } else {
        wsConnected.value = false
        startPolling()
    }
}

function unbindConnectionState() {
    if (pusherConn && stateHandler) {
        try { pusherConn.unbind('state_change', stateHandler) } catch (e) {}
    }
    pusherConn = null
    stateHandler = null
}

function subscribeRealtime(userId) {
    if (!window.Echo || !userId) return
    if (echoUserId === userId && echoChannel) return

    if (echoChannel && echoUserId !== userId) {
        try { window.Echo.leave(`App.Models.User.${echoUserId}`) } catch (e) {}
        echoChannel = null
    }

    echoUserId = userId
    echoChannel = window.Echo.private(`App.Models.User.${userId}`)
    echoChannel.listen('.NotificationCreated', (payload) => {
        const n = payload?.notification
        if (!n) return
        if (!items.value.find(x => x.id === n.id)) {
            items.value.unshift(n)
            if (!n.read_at) unreadCount.value += 1
            lastIncoming.value = n
        }
    })

    bindConnectionState()
}

function unsubscribeRealtime() {
    unbindConnectionState()
    if (window.Echo && echoUserId) {
        try { window.Echo.leave(`App.Models.User.${echoUserId}`) } catch (e) {}
    }
    echoChannel = null
    echoUserId = null
}

export function useNotifications() {
    onMounted(() => {
        activeRefs++
        if (activeRefs === 1) {
            refreshCount()

            const page = usePage()
            const userId = page.props.auth?.user?.id
            if (userId) {
                subscribeRealtime(userId)
                // Polling só vai ligar via bindConnectionState() se WS falhar
            } else {
                // Sem WS: só polling
                startPolling()
            }
        }
    })
    onBeforeUnmount(() => {
        activeRefs = Math.max(0, activeRefs - 1)
        if (activeRefs === 0) {
            stopPolling()
            unsubscribeRealtime()
        }
    })

    return {
        items,
        unreadCount,
        loading,
        loadingMore,
        hasMore,
        lastIncoming,
        wsConnected,
        fetchList,
        loadMore,
        refreshCount,
        markRead,
        markAllRead,
        destroy,
    }
}
