<template>
    <div>
      <input
        type="text"
        :value="formattedValue"
        @input="onInput"
        @blur="onBlur"
        @focus="onFocus"
        :class="inputClasses"
        class="border-gray-300 border p-1 rounded-md shadow-sm"
      />
    </div>
  </template>
  
  <script setup>
  import { ref, computed, watch } from 'vue'
  
  const props = defineProps({
    modelValue: {
      type: [Number, String],
      required: true
    },
    class: {
      type: [String, Object, Array],
      default: ''
    }
  })
  
  const emits = defineEmits(['update:modelValue'])
  
  const inputValue = ref('')
  
  const formattedValue = computed(() => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(inputValue.value)
  })
  
  const onInput = (event) => {
    const value = event.target.value.replace(/\D/g, '')
    const floatValue = parseFloat(value) / 100
  
    inputValue.value = isNaN(floatValue) ? 0 : floatValue
    emits('update:modelValue', inputValue.value)
  }
  
  const onBlur = () => {
    inputValue.value = parseFloat(inputValue.value).toFixed(2)
  }
  
  const onFocus = () => {
    inputValue.value = parseFloat(props.modelValue).toFixed(2)
  }
  
  watch(() => props.modelValue, (newVal) => {
    inputValue.value = parseFloat(newVal).toFixed(2)
  }, { immediate: true })
  
  const inputClasses = computed(() => {
    return [
      'border', // Classe padrão
      ...Array.isArray(props.class) ? props.class : [props.class] // Classes adicionais passadas como props
    ]
  })
  </script>
  