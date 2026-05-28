<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import ColorPicker from 'primevue/colorpicker'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    settings: Object,
})

const page = usePage()
const toast = useToast()

const form = useForm({
    app_name: props.settings.app_name || '',
    primary_color: (props.settings.primary_color || '#3b82f6').replace('#', ''),
    secondary_color: (props.settings.secondary_color || '#1e1e2d').replace('#', ''),
    logo: null,
    logo_mobile: null,
    favicon: null,
    login_bg: null,
    login_bg_mobile: null,
})

const previews = ref({
    logo: props.settings.logo_url,
    logo_mobile: props.settings.logo_mobile_url,
    favicon: props.settings.favicon_url,
    login_bg: props.settings.login_bg_url,
    login_bg_mobile: props.settings.login_bg_mobile_url,
})

const hexErrors = ref({ primary_color: false, secondary_color: false })

function onHexInput(event, field) {
    let value = event.target.value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase()
    if (value.length > 6) value = value.substring(0, 6)
    event.target.value = value

    if (value.length === 6) {
        form[field] = value
        hexErrors.value[field] = false
    } else if (value.length > 0 && value.length < 6) {
        hexErrors.value[field] = true
    } else {
        hexErrors.value[field] = false
    }
}

function onHexBlur(field) {
    const value = form[field].replace('#', '')
    if (value.length !== 6) {
        form[field] = (props.settings[field] || (field === 'primary_color' ? '#3b82f6' : '#1e1e2d')).replace('#', '')
        hexErrors.value[field] = false
    }
}

function handleFile(event, field) {
    const file = event.target.files[0]
    if (!file) return
    form[field] = file
    const reader = new FileReader()
    reader.onload = (e) => { previews.value[field] = e.target.result }
    reader.readAsDataURL(file)
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
})

function submit() {
    form.transform((data) => ({
        ...data,
        primary_color: '#' + data.primary_color.replace('#', ''),
        secondary_color: '#' + data.secondary_color.replace('#', ''),
    })).post('/settings/aparencia', {
        forceFormData: true,
        preserveScroll: true,
    })
}

function cancel() {
    router.visit('/dashboard')
}

const primaryHex = computed(() => '#' + form.primary_color.replace('#', ''))
const secondaryHex = computed(() => '#' + form.secondary_color.replace('#', ''))

const uploadConfigs = [
    { field: 'logo', label: 'Logo (desktop)', aspect: '1:1', res: '256x256 ou 512x512px', max: '20MB', accept: 'image/png,image/jpeg,image/webp,image/svg+xml', aspectClass: 'aspect-square', size: 'sm' },
    { field: 'logo_mobile', label: 'Logo (mobile)', aspect: '1:1 (compacto)', res: '128x128 ou 256x256px', max: '20MB', accept: 'image/png,image/jpeg,image/webp,image/svg+xml', aspectClass: 'aspect-square', size: 'sm', optional: true },
    { field: 'favicon', label: 'Favicon', aspect: '1:1', res: '32x32 ou 64x64px', max: '5MB', accept: 'image/png,image/svg+xml,image/x-icon,image/webp', aspectClass: 'aspect-square', size: 'xs' },
    { field: 'login_bg', label: 'Fundo do login (desktop)', aspect: '16:9', res: '1920x1080px', max: '30MB', accept: 'image/png,image/jpeg,image/webp', aspectClass: 'aspect-video', size: 'lg' },
    { field: 'login_bg_mobile', label: 'Fundo do login (mobile)', aspect: '9:16', res: '1080x1920px', max: '30MB', accept: 'image/png,image/jpeg,image/webp', aspectStyle: 'aspect-ratio: 9/16;', size: 'md', optional: true },
]
</script>

<template>
    <AppLayoutMobile title="Aparência" :show-back="true" :hide-bottom-nav="true">
        <Toast position="top-right" />

        <form @submit.prevent="submit" class="pt-3 pb-32">
            <!-- Identidade -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Identidade</h2>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome do sistema</label>
                    <InputText v-model="form.app_name" placeholder="Ex: 5 Estrelas" class="w-full" :invalid="!!form.errors.app_name" style="height: 44px" />
                    <small v-if="form.errors.app_name" class="text-red-500 text-xs mt-1 block">{{ form.errors.app_name }}</small>
                </div>
            </div>

            <!-- Cores -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3 space-y-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Cores</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cor primária</label>
                    <div class="flex items-center gap-3">
                        <ColorPicker v-model="form.primary_color" format="hex" />
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-mono">#</span>
                            <input
                                type="text"
                                :value="form.primary_color.replace('#', '').toUpperCase()"
                                @input="onHexInput($event, 'primary_color')"
                                @blur="onHexBlur('primary_color')"
                                maxlength="6"
                                placeholder="3B82F6"
                                class="w-full border border-gray-300 rounded-md pl-7 pr-3 py-2 text-sm font-mono uppercase focus:outline-none focus:border-blue-500"
                                :class="{ 'border-red-500': hexErrors.primary_color }"
                                style="height: 44px"
                            />
                        </div>
                    </div>
                    <small v-if="hexErrors.primary_color" class="text-xs text-red-500 mt-1 block">Hex inválido</small>
                    <small v-else class="text-xs text-gray-500 mt-1 block">Botões, ativos, destaques</small>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cor secundária (sidebar)</label>
                    <div class="flex items-center gap-3">
                        <ColorPicker v-model="form.secondary_color" format="hex" />
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-mono">#</span>
                            <input
                                type="text"
                                :value="form.secondary_color.replace('#', '').toUpperCase()"
                                @input="onHexInput($event, 'secondary_color')"
                                @blur="onHexBlur('secondary_color')"
                                maxlength="6"
                                placeholder="1E1E2D"
                                class="w-full border border-gray-300 rounded-md pl-7 pr-3 py-2 text-sm font-mono uppercase focus:outline-none focus:border-blue-500"
                                :class="{ 'border-red-500': hexErrors.secondary_color }"
                                style="height: 44px"
                            />
                        </div>
                    </div>
                    <small v-if="hexErrors.secondary_color" class="text-xs text-red-500 mt-1 block">Hex inválido</small>
                    <small v-else class="text-xs text-gray-500 mt-1 block">Fundo da sidebar e header</small>
                </div>
            </div>

            <!-- Imagens -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3 space-y-5">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Imagens</h2>

                <div v-for="cfg in uploadConfigs" :key="cfg.field">
                    <label class="block text-xs font-medium text-gray-600 mb-2">
                        {{ cfg.label }}
                        <span v-if="cfg.optional" class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <div class="border-2 border-dashed border-gray-200 rounded-lg p-3 flex flex-col items-center">
                        <div
                            :class="[
                                'bg-gray-50 rounded overflow-hidden flex items-center justify-center mb-2',
                                cfg.size === 'xs' ? 'w-20 h-20' : '',
                                cfg.size === 'sm' ? 'w-32 h-32' : '',
                                cfg.size === 'md' ? 'w-32' : '',
                                cfg.size === 'lg' ? 'w-full' : '',
                                cfg.aspectClass || ''
                            ]"
                            :style="cfg.aspectStyle"
                        >
                            <img v-if="previews[cfg.field]" :src="previews[cfg.field]" :alt="cfg.label" class="w-full h-full object-cover" />
                            <i v-else class="pi pi-image text-2xl text-gray-300"></i>
                        </div>
                        <input
                            type="file"
                            :accept="cfg.accept"
                            class="hidden"
                            :ref="cfg.field + 'Input'"
                            @change="handleFile($event, cfg.field)"
                        />
                        <button
                            type="button"
                            class="text-sm text-blue-600 active:text-blue-800"
                            @click="$refs[cfg.field + 'Input'][0].click()"
                        >
                            {{ previews[cfg.field] ? 'Trocar' : 'Selecionar' }}
                        </button>
                    </div>
                    <div class="mt-1 space-y-0.5">
                        <small class="text-[11px] text-gray-500 block"><strong>Proporção:</strong> {{ cfg.aspect }}</small>
                        <small class="text-[11px] text-gray-500 block"><strong>Resolução:</strong> {{ cfg.res }}</small>
                        <small class="text-[11px] text-gray-500 block"><strong>Máx:</strong> {{ cfg.max }}</small>
                    </div>
                    <small v-if="form.errors[cfg.field]" class="text-red-500 text-xs mt-1 block">{{ form.errors[cfg.field] }}</small>
                </div>
            </div>
        </form>

        <!-- Footer fixo -->
        <div class="fixed left-0 right-0 bottom-0 bg-white border-t border-gray-200 p-3 flex gap-2 z-30" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button @click="cancel" class="flex-1 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium">
                Cancelar
            </button>
            <button
                @click="submit"
                :disabled="form.processing"
                class="flex-1 py-3 rounded-lg text-white font-medium disabled:opacity-50"
                :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
            >
                {{ form.processing ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </AppLayoutMobile>
</template>
