<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import ColorPicker from 'primevue/colorpicker'
import Button from 'primevue/button'
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
    reader.onload = (e) => {
        previews.value[field] = e.target.result
    }
    reader.readAsDataURL(file)
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) {
        toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
    }
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

const primaryHex = computed(() => '#' + form.primary_color.replace('#', ''))
const secondaryHex = computed(() => '#' + form.secondary_color.replace('#', ''))
</script>

<template>
    <AppLayout>
        <Toast position="top-right" />

        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">Aparência</h1>
                <p class="text-gray-500 text-sm mt-1">Personalize as cores, logo e identidade visual do sistema</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Identidade -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Identidade
                    </h2>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nome do sistema</label>
                        <InputText
                            v-model="form.app_name"
                            placeholder="Ex: 5 Estrelas"
                            class="w-full max-w-md"
                            :invalid="!!form.errors.app_name"
                        />
                        <small v-if="form.errors.app_name" class="text-red-500 text-xs mt-1 block">
                            {{ form.errors.app_name }}
                        </small>
                    </div>
                </div>

                <!-- Cores -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Cores
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Cor primária</label>
                            <div class="flex items-center gap-3">
                                <ColorPicker v-model="form.primary_color" format="hex" />
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-mono">#</span>
                                    <input
                                        type="text"
                                        :value="form.primary_color.replace('#', '').toUpperCase()"
                                        @input="onHexInput($event, 'primary_color')"
                                        @blur="onHexBlur('primary_color')"
                                        maxlength="6"
                                        placeholder="3B82F6"
                                        class="border border-gray-300 rounded-md pl-7 pr-3 py-2 text-sm font-mono uppercase w-32 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        :class="{ 'border-red-500': hexErrors.primary_color }"
                                    />
                                </div>
                            </div>
                            <small v-if="hexErrors.primary_color" class="text-xs text-red-500 mt-1 block">Hex inválido (use 6 caracteres: 0-9, A-F)</small>
                            <small v-else class="text-xs text-gray-500 mt-1 block">Botões, ativos, destaques</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Cor secundária (sidebar)</label>
                            <div class="flex items-center gap-3">
                                <ColorPicker v-model="form.secondary_color" format="hex" />
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-mono">#</span>
                                    <input
                                        type="text"
                                        :value="form.secondary_color.replace('#', '').toUpperCase()"
                                        @input="onHexInput($event, 'secondary_color')"
                                        @blur="onHexBlur('secondary_color')"
                                        maxlength="6"
                                        placeholder="1E1E2D"
                                        class="border border-gray-300 rounded-md pl-7 pr-3 py-2 text-sm font-mono uppercase w-32 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        :class="{ 'border-red-500': hexErrors.secondary_color }"
                                    />
                                </div>
                            </div>
                            <small v-if="hexErrors.secondary_color" class="text-xs text-red-500 mt-1 block">Hex inválido (use 6 caracteres: 0-9, A-F)</small>
                            <small v-else class="text-xs text-gray-500 mt-1 block">Fundo da sidebar e header</small>
                        </div>
                    </div>
                </div>

                <!-- Imagens -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Logo e Favicon
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Logo desktop -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Logo (desktop)</label>
                            <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center justify-center hover:border-gray-300 transition-colors" style="min-height: 180px;">
                                <div class="w-32 h-32 flex items-center justify-center bg-gray-50 rounded mb-3 overflow-hidden">
                                    <img v-if="previews.logo" :src="previews.logo" alt="Logo preview" class="max-w-full max-h-full object-contain" />
                                    <i v-else class="pi pi-image text-3xl text-gray-300"></i>
                                </div>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    class="hidden"
                                    ref="logoInput"
                                    @change="handleFile($event, 'logo')"
                                />
                                <button
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="$refs.logoInput.click()"
                                >
                                    {{ previews.logo ? 'Trocar' : 'Selecionar' }}
                                </button>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> 1:1 (quadrado)</small>
                                <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> 256x256px ou 512x512px</small>
                                <small class="text-xs text-gray-500 block"><strong>Formato:</strong> PNG, JPG, SVG, WebP — máx 20MB</small>
                            </div>
                            <small v-if="form.errors.logo" class="text-red-500 text-xs mt-1 block">{{ form.errors.logo }}</small>
                        </div>

                        <!-- Logo mobile -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Logo (mobile)</label>
                            <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center justify-center hover:border-gray-300 transition-colors" style="min-height: 180px;">
                                <div class="w-32 h-32 flex items-center justify-center bg-gray-50 rounded mb-3 overflow-hidden">
                                    <img v-if="previews.logo_mobile" :src="previews.logo_mobile" alt="Logo mobile preview" class="max-w-full max-h-full object-contain" />
                                    <i v-else class="pi pi-mobile text-3xl text-gray-300"></i>
                                </div>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    class="hidden"
                                    ref="logoMobileInput"
                                    @change="handleFile($event, 'logo_mobile')"
                                />
                                <button
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="$refs.logoMobileInput.click()"
                                >
                                    {{ previews.logo_mobile ? 'Trocar' : 'Selecionar' }}
                                </button>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> 1:1 (compacta/símbolo)</small>
                                <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> 128x128px ou 256x256px</small>
                                <small class="text-xs text-gray-500 block"><strong>Formato:</strong> PNG, JPG, SVG, WebP — máx 20MB</small>
                                <small class="text-xs text-gray-400 italic block">Opcional. Se vazio, usa o logo desktop.</small>
                            </div>
                            <small v-if="form.errors.logo_mobile" class="text-red-500 text-xs mt-1 block">{{ form.errors.logo_mobile }}</small>
                        </div>

                        <!-- Favicon -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Favicon</label>
                            <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center justify-center hover:border-gray-300 transition-colors" style="min-height: 180px;">
                                <div class="w-32 h-32 flex items-center justify-center bg-gray-50 rounded mb-3 overflow-hidden">
                                    <img v-if="previews.favicon" :src="previews.favicon" alt="Favicon preview" class="w-16 h-16 object-contain" />
                                    <i v-else class="pi pi-bookmark text-3xl text-gray-300"></i>
                                </div>
                                <input
                                    type="file"
                                    accept="image/png,image/svg+xml,image/x-icon,image/webp"
                                    class="hidden"
                                    ref="faviconInput"
                                    @change="handleFile($event, 'favicon')"
                                />
                                <button
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="$refs.faviconInput.click()"
                                >
                                    {{ previews.favicon ? 'Trocar' : 'Selecionar' }}
                                </button>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> 1:1 (quadrado)</small>
                                <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> 32x32px ou 64x64px</small>
                                <small class="text-xs text-gray-500 block"><strong>Formato:</strong> PNG, ICO, SVG — máx 5MB</small>
                            </div>
                            <small v-if="form.errors.favicon" class="text-red-500 text-xs mt-1 block">{{ form.errors.favicon }}</small>
                        </div>
                    </div>
                </div>

                <!-- Fundos do login -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Fundo da tela de login
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Login bg desktop -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fundo desktop</label>
                            <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center justify-center hover:border-gray-300 transition-colors" style="min-height: 180px;">
                                <div class="w-full aspect-video flex items-center justify-center bg-gray-50 rounded mb-3 overflow-hidden">
                                    <img v-if="previews.login_bg" :src="previews.login_bg" alt="Login bg preview" class="w-full h-full object-cover" />
                                    <i v-else class="pi pi-image text-3xl text-gray-300"></i>
                                </div>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="hidden"
                                    ref="bgInput"
                                    @change="handleFile($event, 'login_bg')"
                                />
                                <button
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="$refs.bgInput.click()"
                                >
                                    {{ previews.login_bg ? 'Trocar' : 'Selecionar' }}
                                </button>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> 16:9 (widescreen)</small>
                                <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> 1920x1080px (Full HD)</small>
                                <small class="text-xs text-gray-500 block"><strong>Formato:</strong> PNG, JPG, WebP — máx 30MB</small>
                            </div>
                            <small v-if="form.errors.login_bg" class="text-red-500 text-xs mt-1 block">{{ form.errors.login_bg }}</small>
                        </div>

                        <!-- Login bg mobile -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fundo mobile</label>
                            <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center justify-center hover:border-gray-300 transition-colors" style="min-height: 180px;">
                                <div class="w-32 flex items-center justify-center bg-gray-50 rounded mb-3 overflow-hidden" style="aspect-ratio: 9/16;">
                                    <img v-if="previews.login_bg_mobile" :src="previews.login_bg_mobile" alt="Login bg mobile preview" class="w-full h-full object-cover" />
                                    <i v-else class="pi pi-mobile text-3xl text-gray-300"></i>
                                </div>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="hidden"
                                    ref="bgMobileInput"
                                    @change="handleFile($event, 'login_bg_mobile')"
                                />
                                <button
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="$refs.bgMobileInput.click()"
                                >
                                    {{ previews.login_bg_mobile ? 'Trocar' : 'Selecionar' }}
                                </button>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> 9:16 (retrato)</small>
                                <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> 1080x1920px</small>
                                <small class="text-xs text-gray-500 block"><strong>Formato:</strong> PNG, JPG, WebP — máx 30MB</small>
                                <small class="text-xs text-gray-400 italic block">Opcional. Se vazio, usa o fundo desktop.</small>
                            </div>
                            <small v-if="form.errors.login_bg_mobile" class="text-red-500 text-xs mt-1 block">{{ form.errors.login_bg_mobile }}</small>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-3">
                    <Button
                        type="submit"
                        label="Salvar alterações"
                        icon="pi pi-check"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
