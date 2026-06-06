<template>
  <div>
    <InputText
      :value="formattedValue"
      :invalid="invalid"
      @input="onInput"
      @blur="onBlur"
      @focus="onFocus"
      :class="inputClasses"
      class="p-inputtext p-component p-filled p-1 rounded-md shadow-sm"
      :disabled="disabled"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import InputText from 'primevue/inputtext' // Importa o componente InputText do PrimeVue

const props = defineProps({
  modelValue: {
    type: [Number, String],
    required: true
  },
  class: {
    type: [String, Object, Array],
    default: ''
  },
  invalid: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emits = defineEmits(['update:modelValue'])

const inputValue = ref('')

// Formatação do valor como moeda (BRL)
const formattedValue = computed(() => {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(inputValue.value)
})

// Função chamada quando o valor no input muda
const onInput = (event) => {
  const value = event.target.value.replace(/\D/g, '') // Remove caracteres não numéricos
  const floatValue = parseFloat(value) / 100
  
  inputValue.value = isNaN(floatValue) ? 0 : floatValue
  emits('update:modelValue', inputValue.value) // Atualiza o valor emitido
}

// Formatação ao perder o foco (blur)
const onBlur = () => {
  inputValue.value = parseFloat(inputValue.value).toFixed(2)
}

// Formatação ao ganhar o foco (focus)
const onFocus = () => {
  inputValue.value = parseFloat(props.modelValue).toFixed(2)
}

// Sincroniza com o valor de entrada (modelValue)
watch(() => props.modelValue, (newVal) => {
  inputValue.value = parseFloat(newVal).toFixed(2)
}, { immediate: true })

// Classes de estilo para o input
const inputClasses = computed(() => {
  return [
    'border', // Classe padrão
    ...Array.isArray(props.class) ? props.class : [props.class] // Classes adicionais passadas como props
  ]
})
</script>

<style scoped>
/* Estilos adicionais (opcional) */
.p-inputtext {
  width: 100%;
}
</style>
