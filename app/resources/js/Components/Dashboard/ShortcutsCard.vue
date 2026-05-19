<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
    shortcuts: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
})

const configOpen = ref(false)

const selectedKeys = ref([])

function openConfig() {
    selectedKeys.value = props.shortcuts.map(s => s.key)
    configOpen.value = true
}

function toggle(key) {
    const idx = selectedKeys.value.indexOf(key)
    if (idx === -1) selectedKeys.value.push(key)
    else selectedKeys.value.splice(idx, 1)
}

const form = useForm({ menu_keys: [] })

function save() {
    form.menu_keys = selectedKeys.value
    form.put('/perfil/atalhos', {
        preserveScroll: true,
        onSuccess: () => {
            configOpen.value = false
        },
    })
}

function navigate(href) {
    router.visit(href)
}
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Acesso Rápido
            </h2>
            <button @click="openConfig" class="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <i class="pi pi-cog text-sm"></i>
            </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <button
                v-for="s in shortcuts"
                :key="s.key"
                @click="navigate(s.href)"
                class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all text-center"
            >
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)', color: 'var(--app-primary-text, #ffffff)' }">
                    <i :class="[s.icon, 'text-base']"></i>
                </div>
                <span class="text-xs text-gray-700 leading-tight line-clamp-2">{{ s.label }}</span>
            </button>

            <button
                @click="openConfig"
                class="flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-gray-400 transition-colors text-gray-400 hover:text-gray-600"
                style="min-height: 100px;"
            >
                <i class="pi pi-plus text-xl"></i>
                <span class="text-xs">Adicionar</span>
            </button>
        </div>

        <p v-if="!shortcuts.length" class="text-xs text-gray-400 text-center mt-3">
            Você ainda não configurou atalhos. Clique no botão acima para adicionar.
        </p>

        <Dialog v-model:visible="configOpen" modal header="Configurar atalhos" :style="{ width: '480px', maxWidth: '95vw' }">
            <p class="text-sm text-gray-500 mb-4">Selecione quais atalhos você quer ver no painel.</p>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <label
                    v-for="opt in menuOptions"
                    :key="opt.key"
                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer"
                >
                    <Checkbox
                        :model-value="selectedKeys.includes(opt.key)"
                        :binary="true"
                        @update:model-value="toggle(opt.key)"
                    />
                    <i :class="[opt.icon, 'text-gray-500']"></i>
                    <span class="text-sm text-gray-700">{{ opt.label }}</span>
                </label>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <Button label="Cancelar" severity="secondary" outlined @click="configOpen = false" />
                <Button label="Salvar" icon="pi pi-check" :loading="form.processing" @click="save" />
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
