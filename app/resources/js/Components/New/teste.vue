<template>
    <div class="scanner-container">
      <div v-if="scanning" class="video-container">
        <video ref="videoElement" autoplay></video>
      </div>
      <div v-if="!scanning" class="flex justify-center">
        <button @click="startScanning" class="bg-blue-600 text-white p-2 rounded-md">Abrir Câmera para Escanear</button>
      </div>
      <div v-if="codigoBarras" class="mt-4">
        <p class="text-lg">Código de Barras: {{ codigoBarras }}</p>
      </div>
      <div v-if="scanning" class="flex justify-center mt-4">
        <button @click="stopScanning" class="bg-red-600 text-white p-2 rounded-md">Parar Escaneamento</button>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, onUnmounted } from 'vue';
  import { BrowserMultiFormatReader, NotFoundException } from '@zxing/library';
  
  // Variáveis de estado
  const scanning = ref(false);
  const codigoBarras = ref('');
  const videoElement = ref(null); // Referência para o elemento de vídeo
  const codeReader = new BrowserMultiFormatReader();
  
  // Função para iniciar o escaneamento
  const startScanning = () => {
    scanning.value = true;
  
    // Iniciar a captura da câmera
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then((stream) => {
        videoElement.value.srcObject = stream;
        videoElement.value.setAttribute('playsinline', true); // Para iOS
        videoElement.value.play();
        scan(); // Iniciar o escaneamento contínuo
      })
      .catch((err) => {
        console.error('Erro ao acessar a câmera:', err);
        scanning.value = false;
      });
  };
  
  // Função para escanear continuamente
  const scan = () => {
    codeReader.decodeFromVideoDevice(null, videoElement.value, (result, err) => {
      if (result) {
        console.log('Código de barras detectado:', result.text);
        codigoBarras.value = result.text;
        stopScanning(); // Parar escaneamento após detectar um código
      } else if (err) {
        if (err instanceof NotFoundException) {
          // Isso indica que nenhum código foi encontrado no quadro atual
          console.log('Nenhum código de barras detectado no quadro.');
        } else {
          console.error('Erro ao escanear:', err);
        }
      }
    });
  };
  
  // Função para parar o escaneamento
  const stopScanning = () => {
    scanning.value = false;
    codeReader.reset(); // Para a leitura de vídeo
    if (videoElement.value.srcObject) {
      const stream = videoElement.value.srcObject;
      const tracks = stream.getTracks();
      tracks.forEach(track => track.stop());
    }
    videoElement.value.srcObject = null;
  };
  
  // Limpeza ao desmontar o componente
  onUnmounted(() => {
    stopScanning();
  });
  </script>
  
  <style scoped>
  .scanner-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  
  .video-container {
    width: 100%;
    max-width: 500px;
    height: auto;
    overflow: hidden;
    border: 2px solid #ccc;
    border-radius: 10px;
  }
  
  video {
    width: 100%;
    height: auto;
  }
  </style>
  