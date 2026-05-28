<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
    mode: { type: String, required: true },
    post: Object,
    initialType: { type: String, default: 'news' },
})

const isEdit = computed(() => props.mode === 'edit')

const imagePreview = ref(props.post?.image_url || null)

const form = useForm({
    type: props.post?.type || props.initialType,
    title: props.post?.title || '',
    content: props.post?.content || '',
    image: null,
    published_at: props.post?.published_at ? new Date(props.post.published_at) : new Date(),
    expires_at: props.post?.expires_at ? new Date(props.post.expires_at) : null,
    is_active: props.post?.is_active ?? true,
})

function handleImage(e) {
    const file = e.target.files[0]
    if (!file) return
    form.image = file
    const reader = new FileReader()
    reader.onload = (ev) => { imagePreview.value = ev.target.result }
    reader.readAsDataURL(file)
}

function submit() {
    const data = {
        type: form.type,
        title: form.title,
        content: form.content,
        image: form.image,
        published_at: form.published_at ? toDateTimeString(form.published_at) : null,
        expires_at: form.expires_at ? toDateTimeString(form.expires_at) : null,
        is_active: form.is_active,
    }

    if (isEdit.value) {
        form.transform(() => ({ ...data, _method: 'put' }))
            .post(`/noticias/${props.post.id}`, { forceFormData: true })
    } else {
        form.transform(() => data).post('/noticias', { forceFormData: true })
    }
}

function toDateTimeString(d) {
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
}

function cancel() {
    router.visit(`/noticias?type=${form.type}`)
}

const isHighlight = computed(() => form.type === 'highlight')
const imageInfo = computed(() => isHighlight.value ? {
    aspect: '1:1 (quadrado)',
    res: '512x512px ou 1080x1080px',
    max: '5MB',
} : {
    aspect: '4:5 (vertical)',
    res: '1080x1350px',
    max: '10MB',
})
</script>

<template>
    <AppLayoutMobile :title="isEdit ? 'Editar postagem' : 'Nova postagem'" :show-back="true" :hide-bottom-nav="true">
        <form @submit.prevent="submit" class="pt-3 pb-32">
            <!-- Tipo (toggle Notícia / Destaque) -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Tipo</h2>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        :class="['py-3 rounded-lg text-sm font-medium border-2 transition-colors',
                            form.type === 'news' ? 'border-[var(--app-primary)] text-[var(--app-primary)] bg-blue-50' : 'border-gray-200 text-gray-600']"
                        @click="form.type = 'news'"
                    >
                        <i class="pi pi-comments block mb-1"></i>
                        Notícia (feed)
                    </button>
                    <button
                        type="button"
                        :class="['py-3 rounded-lg text-sm font-medium border-2 transition-colors',
                            form.type === 'highlight' ? 'border-[var(--app-primary)] text-[var(--app-primary)] bg-blue-50' : 'border-gray-200 text-gray-600']"
                        @click="form.type = 'highlight'"
                    >
                        <i class="pi pi-star block mb-1"></i>
                        Destaque (story)
                    </button>
                </div>
            </div>

            <!-- Título e conteúdo -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Título</label>
                    <InputText v-model="form.title" placeholder="Ex: Comunicado interno" class="w-full" :invalid="!!form.errors.title" style="height: 44px" />
                    <small v-if="form.errors.title" class="text-red-500 text-xs mt-1 block">{{ form.errors.title }}</small>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Conteúdo
                        <span v-if="isHighlight" class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <Textarea v-model="form.content" rows="5" class="w-full" :invalid="!!form.errors.content" />
                    <small v-if="form.errors.content" class="text-red-500 text-xs mt-1 block">{{ form.errors.content }}</small>
                </div>
            </div>

            <!-- Imagem -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Imagem</h2>
                <div class="flex flex-col items-center">
                    <div :class="['w-full bg-gray-50 rounded-lg overflow-hidden flex items-center justify-center mb-3 border-2 border-dashed border-gray-200',
                                  isHighlight ? 'aspect-square' : '']" :style="!isHighlight ? 'aspect-ratio: 4/5;' : ''">
                        <img v-if="imagePreview" :src="imagePreview" alt="Preview" class="w-full h-full object-cover" />
                        <i v-else class="pi pi-image text-3xl text-gray-300"></i>
                    </div>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" ref="imgInput" @change="handleImage" />
                    <button type="button" class="text-sm text-blue-600 active:text-blue-800" @click="$refs.imgInput.click()">
                        {{ imagePreview ? 'Trocar imagem' : 'Selecionar imagem' }}
                    </button>
                </div>
                <div class="mt-3 space-y-0.5">
                    <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> {{ imageInfo.aspect }}</small>
                    <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> {{ imageInfo.res }}</small>
                    <small class="text-xs text-gray-500 block"><strong>Formato:</strong> JPG, PNG, WebP — máx {{ imageInfo.max }}</small>
                </div>
                <small v-if="form.errors.image" class="text-red-500 text-xs block mt-1">{{ form.errors.image }}</small>
            </div>

            <!-- Publicação -->
            <div class="bg-white border-y border-gray-200 p-4 mb-3 space-y-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Publicação</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Publicar a partir de</label>
                    <DatePicker v-model="form.published_at" show-time hour-format="24" date-format="dd/mm/yy" class="w-full" :pt="{ pcInputText: { root: { style: 'height: 44px; width: 100%' } } }" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Expira em
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <DatePicker v-model="form.expires_at" show-time hour-format="24" date-format="dd/mm/yy" class="w-full" show-button-bar :pt="{ pcInputText: { root: { style: 'height: 44px; width: 100%' } } }" />
                    <small v-if="form.errors.expires_at" class="text-red-500 text-xs">{{ form.errors.expires_at }}</small>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <ToggleSwitch v-model="form.is_active" inputId="active" />
                    <label for="active" class="text-sm font-medium text-gray-700 cursor-pointer">Ativa</label>
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
                {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar' : 'Publicar') }}
            </button>
        </div>
    </AppLayoutMobile>
</template>
