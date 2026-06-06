<template>
    <Loader :loading="loading"></Loader>
    <div class="w-full">
        <InputText v-if="!apenasCodigoBarras" label="Consultar Produto" :disabled="false" inputClass="cursor-pointer"
            @click="abrirDialog">
            {{ produtoSelecionado != null ? produtoSelecionado.codprod + '-' + produtoSelecionado.descricao :
            'Selecionar Produto' }}
        </InputText>

        <!-- Câmera para escanear código de barras -->
        <div v-else class="flex flex-col items-center justify-center w-full bg-white rounded-md hd:hidden">
            <Button v-if="isFlutter" @click="abrirScanner" class="h-10" severity="contrast" icon="pi pi-camera"></Button>
            <Button v-else @click="abrirScannerWeb" class="h-10 " severity="contrast" icon="pi pi-camera"></Button>

        </div>

        <Dialog v-model:visible="showModalProdutos" modal="true" header="Consulta Produtos" position="top"
            @hide="limparConsultaProdutos()">

            <div class="flex flex-col p-2 bg-white rounded-md w-80 hd:w-full">
                <!-- Filtros e inputs -->
                <div class="flex flex-col w-full space-y-1 lg:flex-row lg:space-x-1 lg:space-y-0">
                    <div class="flex items-end justify-center space-x-2">
                        <div class="flex flex-col w-full">
                            <label for="codprod">Código</label>
                            <input type="text" @keydown.enter="buscarProdutos()" required v-model="filtroProduto.codprod"
                                class="rounded-md lg:w-36" autofocus>
                        </div>
                        <div class="hd:hidden">
                            <Button v-if="isFlutter" @click="abrirScanner" class="h-10" severity="contrast"
                                icon="pi pi-camera">
                            </Button>
                            <Button v-else @click="abrirScannerWeb" class="h-10" severity="contrast" icon="pi pi-camera"></Button>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <label for="descricao">Descrição</label>
                        <input type="text" @keydown.enter="buscarProdutos()" required v-model="filtroProduto.descricao"
                            class="rounded-md lg:w-96">
                    </div>
                </div>

                <!-- Câmera para escanear código de barras -->
                <div class="flex flex-col items-center justify-center bg-white rounded-mdw-full">

                </div>
                <Message class="mt-2" v-if="erroProduto == true" severity="error">Produto não encontrado.</Message>
                <div class="flex flex-row w-full mt-4 space-x-1">
                    <button @click="buscarProdutos()" :disabled="loading"
                        :class="{ 'bg-blue-600': loading, 'bg-blue-800': !loading }"
                        class="w-full h-10 p-2 font-bold text-white rounded-md select-none">
                        {{ loading ? 'Buscando' : 'Atualizar' }}
                    </button>
                    <button @click="showModalProdutos = false"
                        class="w-full h-10 p-2 font-bold text-white bg-gray-800 rounded-md select-none">Fechar</button>
                </div>

                <!-- Tabela de produtos -->
                <div v-if="produtos.length > 0"
                    class="relative max-w-xs p-4 mt-5 overflow-x-auto bg-white border border-black rounded-md lg:max-w-4xl">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="px-1 text-start">Código</th>
                                <th class="px-1 text-start">Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(produto, index) in produtos" :key="index" @click="selecionarProduto(produto)"
                                class="border border-black rounded-md cursor-pointer hover:bg-yellow-100">
                                <td class="px-1 text-sm">{{ produto.codprod }}</td>
                                <td class="px-1 text-sm">{{ produto.descricao }}</td>
                                <td class="px-1 text-sm">{{ produto.unidade }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </Dialog>

        <!-- Dialog para o scanner web -->
        <Dialog v-model:visible="showModalScanner" modal="true" header="Escanear Código de Barras" @hide="stopScanning">
            <div class="scanner-container">
            <div v-if="scanning" class="video-container">
                <video ref="videoElement" autoplay></video>
            </div>
            <div v-if="codigoBarras" class="mt-4">
                <p class="text-lg">Código de Barras: {{ codigoBarras }}</p>
            </div>
            <div v-if="scanning" class="flex justify-center mt-4">
                <button @click="stopScanning" class="p-2 text-white bg-red-600 rounded-md">Parar Escaneamento</button>
            </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, inject } from 'vue';
import InputText from './InputText.vue';
import Dialog from 'primevue/dialog';
import { BrowserMultiFormatReader, NotFoundException } from '@zxing/library';
import Loader from '../Loader.vue';

// Props
const props = defineProps({
    modelValue: {
        type: Object,
        default: null
    },
    filial: {
        type: Number,
        default: 91
    },
    importados: {
        type: Boolean
    },
    apenasCodigoBarras: {
        type: Boolean,
        default: false
    }
});

const swal = inject('$swal');
const emit = defineEmits(['update:modelValue']);

// Variáveis e estados
const showModalProdutos = ref(false);
const produtoSelecionado = ref(props.modelValue || null);
const filtroProduto = ref({
    codprod: '',
    descricao: '',
    barcode: ''
});
const produtos = ref([]);
const isFlutter = ref(window.flutter_inappwebview !== "undefined");
const loading = ref(false);
const erroProduto = ref(false);
const videoElement = ref(null);
const codeReader = new BrowserMultiFormatReader();
const scanning = ref(false);
const showModalScanner = ref(false);

// Funções
// Função para abrir o scanner web
function abrirScannerWeb() {
    showModalScanner.value = true;
    startScanning();
}

// Função para iniciar o escaneamento
const startScanning = () => {
    scanning.value = true;
    navigator.mediaDevices
        .getUserMedia({ video: { facingMode: 'environment' } })
        .then((stream) => {
        videoElement.value.srcObject = stream;
        videoElement.value.setAttribute('playsinline', true); // Para iOS
        videoElement.value.play();
        scan();
        })
        .catch((err) => {
        console.error('Erro ao acessar a câmera:', err);
        scanning.value = false;
        });
};

// Função para escanear continuamente
async function scan(){
    loading.value = true;
    codeReader.decodeFromVideoDevice(null, videoElement.value, async (result, err) => {
        if (result) {
        filtroProduto.value.barcode = result.text; // Usar o código escaneado para buscar produtos
        stopScanning();
        await buscarProdutos();
        if (produtos.value.length == 1) {
            selecionarProduto(produtos.value[0]);
        }
        } else if (err instanceof NotFoundException) {
        console.log('Nenhum código de barras detectado no quadro.');
        } else {
        console.error('Erro ao escanear:', err);
        }
    });
    loading.value = false;
};

// Função para parar o escaneamento
const stopScanning = () => {
    scanning.value = false;
    codeReader.reset();
    if (videoElement.value && videoElement.value.srcObject) {
        const stream = videoElement.value.srcObject;
        const tracks = stream.getTracks();
        tracks.forEach((track) => track.stop());
    }
    if (videoElement.value) {
        videoElement.value.srcObject = null;
    }
    showModalScanner.value = false;
};

const limparConsultaProdutos = () => {
    filtroProduto.value.codprod = '';
    filtroProduto.value.descricao = '';
    erroProduto.value = null;
    produtos.value = [];
}

async function abrirScanner() {
    try {
        const response = await window.flutter_inappwebview.callHandler('Barcode');
        if(response) {
            receberBarCodeFlutter(response);
        }
    } catch (error) {
        console.log(error);
        
    }
}

async function receberBarCodeFlutter(barCodeFlutter) {
    filtroProduto.value.barcode = barCodeFlutter;
    await buscarProdutos();
    if (produtos.value.length == 1) {
        selecionarProduto(produtos.value[0]);
    }
}

const abrirDialog = () => {
    showModalProdutos.value = true;
};

const buscarProdutos = async () => {
    produtos.value = [];
    loading.value = true;
    filtroProduto.value.filial = props.filial;
    filtroProduto.value.importados = props.importados;
    const response = await axios.post('/util/produtos', filtroProduto.value);
    produtos.value = response.data;
    if(produtos.value.length == 0){
        //showModalProdutos.value = false;
        loading.value = false;
        erroProduto.value = true;
    } else {
        erroProduto.value = false;
    }
    loading.value = false;
};

const selecionarProduto = (produto) => {
    produtoSelecionado.value = produto;
    emit('update:modelValue', produto);
    showModalProdutos.value = false;
};

watch(() => props.modelValue, (newValue) => {
    produtoSelecionado.value = newValue;
});

onMounted(async () => {

});
</script>

<style scoped>
.scanner-container {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.video-container {
  width: 100%;
  max-width: 300px;
  height: 300px;
  background-color: black;
  border-radius: 8px;
  overflow: hidden;
}
</style>