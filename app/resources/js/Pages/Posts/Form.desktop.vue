<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
    mode: { type: String, required: true },
    post: Object,
    initialType: { type: String, default: 'news' },
})

const isEdit = computed(() => props.mode === 'edit')

const typeOptions = [
    { label: 'Destaque (story)', value: 'highlight' },
    { label: 'Notícia (feed)', value: 'news' },
]

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
    aspectClass: 'aspect-square',
} : {
    aspect: '4:5 (vertical, estilo Instagram feed)',
    res: '1080x1350px',
    max: '10MB',
    aspectClass: '',
})
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">
                    {{ isEdit ? 'Editar postagem' : 'Nova postagem' }}
                </h1>
                <p class="text-gray-500 text-sm mt-1">
                    {{ isHighlight ? 'Destaques aparecem como stories no topo do dashboard' : 'Notícias aparecem no feed lateral do dashboard' }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                        <SelectButton v-model="form.type" :options="typeOptions" option-label="label" option-value="value" :allow-empty="false" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Título</label>
                        <InputText v-model="form.title" placeholder="Ex: Comunicado interno - Dia das Mães" class="w-full" :invalid="!!form.errors.title" />
                        <small v-if="form.errors.title" class="text-red-500 text-xs">{{ form.errors.title }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Conteúdo <span v-if="isHighlight" class="text-xs text-gray-500 font-normal">(opcional - texto que aparece sobre a imagem)</span>
                        </label>
                        <Textarea v-model="form.content" rows="5" class="w-full" :invalid="!!form.errors.content" />
                        <small v-if="form.errors.content" class="text-red-500 text-xs">{{ form.errors.content }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Imagem</label>
                        <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center hover:border-gray-300 transition-colors">
                            <div :class="['w-full max-w-sm flex items-center justify-center bg-gray-50 rounded mb-3 overflow-hidden', imageInfo.aspectClass]" :style="!imageInfo.aspectClass ? 'aspect-ratio: 4/5;' : ''">
                                <img v-if="imagePreview" :src="imagePreview" alt="Preview" class="w-full h-full object-cover" />
                                <i v-else class="pi pi-image text-3xl text-gray-300"></i>
                            </div>
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" ref="imgInput" @change="handleImage" />
                            <button type="button" class="text-xs text-blue-600 hover:underline" @click="$refs.imgInput.click()">
                                {{ imagePreview ? 'Trocar imagem' : 'Selecionar imagem' }}
                            </button>
                        </div>
                        <div class="mt-2 space-y-0.5">
                            <small class="text-xs text-gray-500 block"><strong>Proporção:</strong> {{ imageInfo.aspect }}</small>
                            <small class="text-xs text-gray-500 block"><strong>Resolução:</strong> {{ imageInfo.res }}</small>
                            <small class="text-xs text-gray-500 block"><strong>Formato:</strong> JPG, PNG, WebP — máx {{ imageInfo.max }}</small>
                        </div>
                        <small v-if="form.errors.image" class="text-red-500 text-xs block mt-1">{{ form.errors.image }}</small>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Publicação</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Publicar a partir de</label>
                            <DatePicker v-model="form.published_at" show-time hour-format="24" date-format="dd/mm/yy" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Expira em <span class="text-xs text-gray-500 font-normal">(opcional)</span></label>
                            <DatePicker v-model="form.expires_at" show-time hour-format="24" date-format="dd/mm/yy" class="w-full" show-button-bar />
                            <small v-if="form.errors.expires_at" class="text-red-500 text-xs">{{ form.errors.expires_at }}</small>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <ToggleSwitch v-model="form.is_active" inputId="active" />
                        <label for="active" class="text-sm font-medium text-gray-700 cursor-pointer">Ativa</label>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="button" label="Cancelar" severity="secondary" outlined @click="cancel" />
                    <Button type="submit" :label="isEdit ? 'Salvar alterações' : 'Publicar'" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
