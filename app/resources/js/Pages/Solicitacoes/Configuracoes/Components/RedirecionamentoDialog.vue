<script setup>
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import ToggleSwitch from "primevue/toggleswitch"
import Select from "primevue/select"
import { computed } from "vue"

const props = defineProps({
  modelValue: Boolean,
  assunto: Object,
  departamentos: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(["update:modelValue", "update:assunto", "save"])

const form = computed({
  get: () => props.assunto,
  set: (value) => emit("update:assunto", value)
})

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value)
})

const assuntosFiltrados = computed(() => {
  if (!form.value.redirect_departamento) return []
  const depto = props.departamentos.find(
    (d) => d.nome === form.value.redirect_departamento
  )
  return depto ? depto.assuntos : []
})

function onSave() {
  form.value.redirect = true
  dialogVisible.value = false
  emit("save")
}

function onCancel() {
  dialogVisible.value = false
  Object.assign(form.value, {
    redirect: false,
    redirect_mensagem: "",
    redirect_mensagem_sim: "",
    redirect_mensagem_nao: "",
    redirect_nao: false,
    redirect_departamento: "",
    redirect_assunto_id: null
  })
}

const disabledSave = computed(() => {
  return (
    !form.value.redirect_mensagem ||
    !form.value.redirect_mensagem_sim ||
    !form.value.redirect_departamento ||
    !form.value.redirect_assunto_id ||
    (form.value.redirect_nao && !form.value.redirect_mensagem_nao)
  )
})
</script>

<template>
  <Dialog
    v-model:visible="dialogVisible"
    :closable="false"
    modal
    class="w-[34rem] rounded-2xl shadow-2xl"
    :draggable="false"
  >
    <template #header>
      <div class="flex justify-between w-full items-center">
        <span class="font-semibold text-slate-600 text-lg">
          Redirecionamento
        </span>
        <i
          class="pi pi-times cursor-pointer !text-lg hover:text-red-600"
          @click="onCancel"
          v-tooltip.bottom="'Fechar sem salvar'"
        />
      </div>
    </template>
    <div class="flex flex-col gap-6 p-2">
      <!-- Mensagem -->
      <div class="flex flex-col gap-2">
        <label class="text-sm font-medium text-gray-700">
          Mensagem principal
        </label>
        <InputText
          v-model="form.redirect_mensagem"
          class="w-full"
          placeholder="Digite a mensagem exibida..."
        />
      </div>

      <!-- Botões de resposta -->
      <div class="flex gap-4">
        <div class="flex-1 flex flex-col gap-2">
          <label class="text-sm font-medium text-gray-700">Texto (Sim)</label>
          <InputText
            v-model="form.redirect_mensagem_sim"
            placeholder="Ex: Continuar"
            class="w-full mt-1"
          />
        </div>

        <div class="flex-1 flex flex-col gap-2 relative">
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-gray-700">Texto (Não)</label>
            <ToggleSwitch v-model="form.redirect_nao" />
          </div>
          <InputText
            v-model="form.redirect_mensagem_nao"
            :disabled="!form.redirect_nao"
            placeholder="Ex: Cancelar"
            class="w-full"
          />
        </div>
      </div>

      <!-- Seletores -->
      <div class="flex flex-col gap-4 border-t pt-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-gray-700">Departamento</label>
          <Select
            v-model="form.redirect_departamento"
            :options="props.departamentos"
            option-label="nome"
            option-value="nome"
            placeholder="Selecione um departamento"
            class="w-full"
          />
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-gray-700">Assunto</label>
          <Select
            v-model="form.redirect_assunto_id"
            :options="assuntosFiltrados"
            option-label="assunto"
            option-value="id"
            placeholder="Selecione um assunto"
            class="w-full"
            :disabled="!form.redirect_departamento"
          />
        </div>
      </div>
    </div>

    <!-- Footer -->
    <template #footer>
      <div class="flex justify-end gap-3 w-full border-t pt-3">
        <Button
          outlined
          label="Cancelar"
          icon="pi pi-times"
          severity="danger"
          @click="onCancel()"
        />
        <Button
          label="Salvar"
          outlined
          severity="success"
          icon="pi pi-save"
          :disabled="disabledSave"
          @click="onSave()"
        />
      </div>
    </template>
  </Dialog>
</template>
