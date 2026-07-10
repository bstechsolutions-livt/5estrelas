<script setup>
defineProps({
    preview: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <div class="space-y-3">
        <div v-if="!preview.ok" class="bg-red-50 border border-red-200 rounded-lg p-3" dusk="approval-preview-error">
            <p class="text-sm font-semibold text-red-800 flex items-center gap-2">
                <i class="pi pi-exclamation-triangle" />
                Não é possível enviar para aprovação
            </p>
            <ul class="mt-2 space-y-1">
                <li v-for="(err, i) in preview.errors" :key="i" class="text-xs text-red-700">{{ err }}</li>
            </ul>
        </div>

        <template v-else>
            <div class="text-xs text-gray-600 space-y-0.5">
                <p><span class="font-medium text-gray-700">Seu departamento:</span> {{ preview.department?.name }}</p>
                <p v-if="preview.area_label"><span class="font-medium text-gray-700">Fluxo:</span> {{ preview.area_label }}</p>
            </div>

            <div>
                <p class="text-xs font-medium text-gray-700 mb-2">Sequência de aprovação</p>
                <div class="space-y-1.5 border-l-2 border-gray-100 pl-3">
                    <div v-for="step in preview.steps" :key="step.order" class="flex items-start gap-2">
                        <span class="text-[10px] font-bold text-gray-400 w-4 shrink-0">{{ step.order }}</span>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-800">{{ step.role_label }}</p>
                            <p class="text-[11px] text-gray-500">{{ step.assignee_name || '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
