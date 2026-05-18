import { computed, watchEffect } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Converte hex para RGB
function hexToRgb(hex) {
    const h = hex.replace('#', '')
    return {
        r: parseInt(h.substring(0, 2), 16),
        g: parseInt(h.substring(2, 4), 16),
        b: parseInt(h.substring(4, 6), 16),
    }
}

// Converte RGB para hex
function rgbToHex(r, g, b) {
    const toHex = (c) => Math.max(0, Math.min(255, Math.round(c))).toString(16).padStart(2, '0')
    return '#' + toHex(r) + toHex(g) + toHex(b)
}

// Mistura uma cor com branco (lighten) ou preto (darken)
// amount: 0 a 1, 0 = cor original, 1 = totalmente branco/preto
function mix(hex, target, amount) {
    const c = hexToRgb(hex)
    const t = hexToRgb(target)
    return rgbToHex(
        c.r + (t.r - c.r) * amount,
        c.g + (t.g - c.g) * amount,
        c.b + (t.b - c.b) * amount,
    )
}

const lighten = (hex, amount) => mix(hex, '#ffffff', amount)
const darken = (hex, amount) => mix(hex, '#000000', amount)

// Calcula luminância relativa (0 = preto, 1 = branco)
function getLuminance(hex) {
    const { r, g, b } = hexToRgb(hex)
    const [rs, gs, bs] = [r, g, b].map(c => {
        c = c / 255
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
    })
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs
}

// Retorna #ffffff ou #000000 dependendo do contraste com a cor de fundo
function getContrastColor(hex) {
    return getLuminance(hex) > 0.5 ? '#000000' : '#ffffff'
}

// Gera paleta completa (50 a 950) a partir de uma cor base, similar ao Tailwind
function generatePalette(baseHex) {
    return {
        50:  lighten(baseHex, 0.95),
        100: lighten(baseHex, 0.85),
        200: lighten(baseHex, 0.70),
        300: lighten(baseHex, 0.50),
        400: lighten(baseHex, 0.25),
        500: baseHex,
        600: darken(baseHex, 0.10),
        700: darken(baseHex, 0.25),
        800: darken(baseHex, 0.40),
        900: darken(baseHex, 0.55),
        950: darken(baseHex, 0.70),
    }
}

export function useTheme() {
    const page = usePage()
    const theme = computed(() => page.props.theme || {})

    watchEffect(() => {
        if (typeof document === 'undefined') return
        const t = theme.value
        if (!t) return

        const root = document.documentElement

        if (t.primary_color) {
            const palette = generatePalette(t.primary_color)
            const primaryContrast = getContrastColor(t.primary_color)

            // Variáveis customizadas do app
            root.style.setProperty('--app-primary', t.primary_color)
            root.style.setProperty('--app-primary-hover', palette[600])
            root.style.setProperty('--app-primary-active', palette[700])
            root.style.setProperty('--app-primary-text', primaryContrast)

            // Variáveis do PrimeVue (Aura usa estas)
            Object.entries(palette).forEach(([shade, color]) => {
                root.style.setProperty(`--p-primary-${shade}`, color)
            })
            root.style.setProperty('--p-primary-color', palette[500])
            root.style.setProperty('--p-primary-contrast-color', primaryContrast)
            root.style.setProperty('--p-primary-hover-color', palette[600])
            root.style.setProperty('--p-primary-active-color', palette[700])

            // Botões PrimeVue
            root.style.setProperty('--p-button-primary-background', palette[500])
            root.style.setProperty('--p-button-primary-hover-background', palette[600])
            root.style.setProperty('--p-button-primary-active-background', palette[700])
            root.style.setProperty('--p-button-primary-border-color', palette[500])
            root.style.setProperty('--p-button-primary-hover-border-color', palette[600])
            root.style.setProperty('--p-button-primary-active-border-color', palette[700])
            root.style.setProperty('--p-button-primary-color', primaryContrast)
            root.style.setProperty('--p-button-primary-hover-color', primaryContrast)
            root.style.setProperty('--p-button-primary-active-color', primaryContrast)
        }

        if (t.secondary_color) {
            const secondaryContrast = getContrastColor(t.secondary_color)
            root.style.setProperty('--sidebar-bg', t.secondary_color)
            root.style.setProperty('--app-secondary', t.secondary_color)
            root.style.setProperty('--app-secondary-text', secondaryContrast)
            // Texto secundário (subtítulo, hover) com 60% de opacidade do contraste
            root.style.setProperty('--app-secondary-text-muted', secondaryContrast === '#ffffff' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)')
        }

        if (t.favicon_url) {
            let link = document.querySelector("link[rel~='icon']")
            if (!link) {
                link = document.createElement('link')
                link.rel = 'icon'
                document.head.appendChild(link)
            }
            link.href = t.favicon_url
        }

        if (t.app_name) {
            document.title = t.app_name
        }
    })

    return { theme }
}
