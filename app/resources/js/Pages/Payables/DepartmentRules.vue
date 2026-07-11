<script setup>
import { ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    departments: { type: Array, default: () => [] },
    help: { type: Object, default: () => ({}) },
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const form = useForm({
    rules: props.departments.map(d => ({
        department_id: d.id,
        codccu_text: d.codccu_text || '',
        description_text: d.description_text || '',
    })),
})

function save() {
    form.post('/financeiro/contas-pagar/classificacao-departamentos', {
        preserveScroll: true,
    })
}

function deptName(departmentId) {
    return props.departments.find(d => d.id === departmentId)?.name || 'Departamento'
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 3000 })
})
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Classificação' : undefined" :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-24' : 'max-w-5xl mx-auto'" dusk="department-rules-page">
            <div class="mb-6">
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    Classificação por Departamento
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Define como títulos importados da Senior são associados a cada departamento no filtro da Contas a Pagar.
                </p>
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 text-sm text-blue-900 space-y-2">
                <p><strong>O que são essas regras?</strong> Títulos vindos da Senior ainda não têm departamento no workflow. O sistema tenta classificá-los automaticamente usando dois campos da Senior:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li><strong>Centro de custo (codCcu)</strong> — {{ help.codccu }}</li>
                    <li><strong>Observação do título (obsTcp)</strong> — {{ help.description }}</li>
                </ul>
                <p class="text-xs text-blue-700">{{ help.workflow }}</p>
            </div>

            <form @submit.prevent="save" class="space-y-4">
                <div v-for="(rule, index) in form.rules" :key="rule.department_id"
                    class="bg-white rounded-xl border border-gray-100 p-4" :dusk="`dept-rule-${rule.department_id}`">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ deptName(rule.department_id) }}</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Centros de custo (codCcu)</label>
                            <Textarea v-model="form.rules[index].codccu_text" rows="4" class="w-full text-sm"
                                placeholder="Um código por linha&#10;Ex: 2363&#10;2566" />
                            <p class="text-[11px] text-gray-400 mt-1">Códigos numéricos da Senior. Deixe vazio se não usar.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Palavras na descrição (obsTcp)</label>
                            <Textarea v-model="form.rules[index].description_text" rows="4" class="w-full text-sm"
                                placeholder="Uma palavra ou trecho por linha&#10;Ex: GFD&#10;TRCT&#10;MANUTEN" />
                            <p class="text-[11px] text-gray-400 mt-1">Corresponde ao campo obsTcp da Senior (coluna Descrição no Hub).</p>
                        </div>
                    </div>
                </div>

                <div :class="isMobile ? 'fixed bottom-16 left-0 right-0 px-4 z-20' : 'flex justify-end pt-2'">
                    <Button type="submit" label="Salvar regras" icon="pi pi-save" :loading="form.processing"
                        class="w-full md:w-auto" dusk="save-department-rules" />
                </div>
            </form>
        </div>
    </component>
</template>
