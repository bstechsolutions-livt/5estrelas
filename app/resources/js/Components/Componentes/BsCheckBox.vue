<script setup>
import { computed, ref } from "vue"

const props = defineProps({
  label: {
    type: String,
    required: false,
    default: null
  },
  severity: {
    type: String,
    required: false,
    default: ""
  },
  size: {
    type: String,
    required: false,
    default: "md"
  },
  labelSize: {
    type: String,
    required: false,
    default: "md"
  },
  //Se utilizar um v-for, usar a KEY obrigatoriamente
  index: {
    required: false,
    default: null
  },
  modelValue: {
    type: String,
    required: true
  },
  disabled: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    required: false
  }
})

const emit = defineEmits(["update:modelValue"])

const internalValue = computed({
  get: () => props.modelValue,
  set: (novoValor) => emit("update:modelValue", novoValor)
})

// Função para determinar o tamanho baseado no size
const getSizeClass = () => {
  switch (props.size) {
    case "xs":
      return "h-2 w-2"
    case "sm":
      return "h-3 w-3"
    case "lg":
      return "h-5 w-5 "
    case "xl":
      return "h-7 w-7"
    default:
      return "h-4 w-4"
  }
}

//Função para determinar o tamanho da label
const getLabelSize = () => {
  switch (props.labelSize) {
    case "xs":
      return "text-xs"
    case "sm":
      return "text-[14px]"
    case "lg":
      return "text-lg"
    case "xl":
      return "text-xl"
    default:
      return "text-[16px]"
  }
}

// Função para determinar a cor baseada no severity
const getSeverityClass = () => {
  switch (props.severity) {
    case "primary":
      return "focus:checked:bg-primaria checked:bg-primaria checked:hover:bg-primaria-hover"
    case "success":
      return "focus:checked:bg-green-600 checked:bg-green-600 checked:hover:bg-green-700 dark:focus:checked:bg-green-500 dark:checked:bg-green-500 dark:checked:hover:bg-green-600"
    case "info":
      return "focus:checked:bg-blue-600 checked:bg-blue-600 checked:hover:bg-blue-700 dark:focus:checked:bg-blue-500 dark:checked:bg-blue-500 dark:checked:hover:bg-blue-600"
    case "warning":
      return "focus:checked:bg-yellow-500 checked:bg-yellow-500 checked:hover:bg-yellow-600 dark:focus:checked:bg-yellow-400 dark:checked:bg-yellow-400 dark:checked:hover:bg-yellow-500"
    case "help":
      return "focus:checked:bg-purple-600 checked:bg-purple-600 checked:hover:bg-purple-700 dark:focus:checked:bg-purple-500 dark:checked:bg-purple-500 dark:checked:hover:bg-purple-600"
    case "secondary":
      return "focus:checked:bg-gray-600 checked:bg-gray-600 checked:hover:bg-gray-700 dark:focus:checked:bg-gray-500 dark:checked:bg-gray-500 dark:checked:hover:bg-gray-600"
    default:
      return "focus:checked:bg-primaria checked:bg-primaria checked:hover:bg-primaria-hover"
  }
}
</script>
<template>
  <div class="flex flex-row items-center justify-center space-x-2 select-none">
    <input
      v-model="internalValue"
      :id="index ? index : 'check'"
      type="checkbox"
      :disabled="disabled"
      :title="title"
      class="rounded-[3px] border-gray-400 dark:border-gray-600 focus:outline-none focus:ring-0 focus:ring-transparent disabled:bg-gray-200/80 dark:disabled:bg-gray-700/80 disabled:hover:cursor-not-allowed"
      :class="[getSizeClass(), getSeverityClass()]"
    />
    <label
      v-if="label"
      :for="index ? index : 'check'"
      class="cursor-pointer capitalize text-gray-700 dark:text-gray-300 font-medium"
      :class="[getLabelSize()]"
    >
      {{ label }}
    </label>
  </div>
</template>
