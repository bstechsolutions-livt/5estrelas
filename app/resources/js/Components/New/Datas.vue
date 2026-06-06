<template>
  <div class="flex flex-row items-center space-x-2">
    <div class="flex" :class="{'flex-col space-x-0': eixo === 'y', 'flex-row space-x-1': eixo === 'x'}">
      <label for="dataInicio" class="text-gray-700 font-semibold">De</label>
      <input
        type="date"
        :value="dataIni"
        @input="handleDataInicio"
        id="dataInicio"
        name="dataInicio"
        class="px-2 py-0 text-md w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>
    <div class="flex" :class="{'flex-col space-x-0': eixo === 'y', 'flex-row space-x-1': eixo === 'x'}">
      <label for="dataFim" class="text-gray-700 font-semibold">até</label>
      <input
        type="date"
        :value="dataFim"
        @input="handleDataFim"
        id="dataFim"
        name="dataFim"
        class="px-2 py-0 text-md w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>
    <span v-if="error" class="text-red-500 text-sm">{{ error }}</span>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  dataInicio: String,
  dataFim: String,
  eixo: {
    type: String,
    default: 'x'
  },
  error: String,
});

const emit = defineEmits(['update:dataInicio', 'update:dataFim']);

const dataIni = ref(props.dataInicio || '');
const dataFim = ref(props.dataFim || '');

const handleDataInicio = (event) => {
  dataIni.value = event.target.value;
  emit('update:dataInicio', event.target.value);
};

const handleDataFim = (event) => {
  dataFim.value = event.target.value;
  emit('update:dataFim', event.target.value);
};
</script>
