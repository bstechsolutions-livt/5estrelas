import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useAuth() {
    const page = usePage()
    const user = computed(() => page.props.auth?.user)
    const permissions = computed(() => user.value?.permissions || [])

    function can(permission) {
        const list = permissions.value
        return list.includes('*') || list.includes(permission)
    }

    function canAny(...keys) {
        return keys.some(k => can(k))
    }

    function canAll(...keys) {
        return keys.every(k => can(k))
    }

    return { user, permissions, can, canAny, canAll }
}
