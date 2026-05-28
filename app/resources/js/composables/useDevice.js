import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useWindowSize } from '@vueuse/core'

export function useDevice() {
    const page = usePage()
    const { width } = useWindowSize()

    const isMobileApp = computed(() => page.props.is_mobile_app === true)
    const isSmallScreen = computed(() => width.value < 1024)

    // Mobile = é o app Flutter OU tela pequena
    const isMobile = computed(() => isMobileApp.value || isSmallScreen.value)
    const isDesktop = computed(() => !isMobile.value)

    return { isMobileApp, isSmallScreen, isMobile, isDesktop }
}
