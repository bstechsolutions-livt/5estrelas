<template>
    <div>
      <label :for="id" class="file-input-label">
        <div
          class="relative border border-gray-300 bg-white rounded-md shadow-sm px-4 py-2 flex items-center justify-center cursor-pointer w-full"
        >
          <span class="mr-2">{{ !selectedFile ? 'Escolher Arquivo' : 'Trocar Arquivo' }}</span>
          <input
            type="file"
            :id="id"
            :class="className + ' sr-only'"
            @change="handleFileChange"
          />
        </div>
      </label>
      <div v-if="selectedFile" class="mt-2 text-xs text-center text-gray-600">
        {{ selectedFile.name }}
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, watch } from 'vue';
  
  const props = defineProps({
    modelValue: {
      type: File,
      required: false,
    },
  });
  
  const emit = defineEmits(['update:modelValue']);
  
  const id = 'file-input';
  const className = '';
  
  const selectedFile = ref(props.modelValue);
  
  watch(() => props.modelValue, (newValue) => {
    selectedFile.value = newValue;
  });
  
  function handleFileChange(event) {
    const file = event.target.files[0];
    selectedFile.value = file;
    emit('update:modelValue', file);
  }
  </script>
  