<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: String,
  label: String,
  error: String,
  maxLength: Number,
  disabled: Boolean,
  inputClass: String
});

const emit = defineEmits(['update:modelValue']);

const inputValue = ref(props.modelValue);

// Watch to keep inputValue in sync with modelValue prop
watch(() => props.modelValue, (newValue) => {
  inputValue.value = newValue;
});

const handleInput = (event) => {
  if (!props.maxLength || event.target.value.length <= props.maxLength) {
    emit('update:modelValue', event.target.value);
  }
};
</script>

<template>
  <div class="flex flex-col">
    <label for="input" class="text-gray-700 font-semibold">{{ label }}</label>
    <input
      v-model="inputValue"
      @input="handleInput"
      :maxlength="maxLength"
      :disabled="disabled"
      type="text"
      name="input"
      id="input"
      class="px-2 py-1 w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-200 disabled:cursor-not-allowed"
      :class="[inputClass,{'bg-gray-50':disabled}]"
      :placeholder="$slots.default ? $slots.default()[0].children : ''"
    />
    <span v-if="error" class="text-red-500 text-sm">{{ error }}</span>
  </div>
</template>
