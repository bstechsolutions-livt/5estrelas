<script setup>
import { onMounted } from 'vue';

// Props
const props = defineProps({
    modelValue: {
        type: Object,
        default: null
    }
});
const emit = defineEmits(['update:modelValue']);

onMounted(async () => {

    // DEFINIR FUNÇÃO DE RETORNO DO CÓDIGO DE BARRAS FLUTTER
    window.receberBarCodeFlutter = (barCode) => {
        receberBarCodeFlutter(barCode);
    }

    // VALIDAR SE ESTÁ ABERTO PELO FLUTTER, SE SIM, DECLARA VARIÁVEL COMO TRUE
    if (typeof BarCode !== 'undefined') {
        isFlutter.value = true;
    }

});

function abrirScanner() {

    BarCode.postMessage('');

}
</script>

<template>
    <Button
        v-if="isFlutter"
        @click="abrirScanner()"
        class="mt-3 h-10 ml-auto"
        severity="contrast"
        icon="pi pi-camera"
    ></Button>
</template>