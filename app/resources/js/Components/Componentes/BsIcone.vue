<script setup>
import { computed } from 'vue';
import ProgressSpinner from 'primevue/progressspinner';

const props = defineProps({

    label: {
        type: String,
        required: false,
    },
    severity: {
        type: String,
        required: false,
    },
    size: {
        type: String,
        required: false,
        default: 'md',
    },
    fluid: {
        type: Boolean,
        required: false,
        default: false,
    },
    loading: {
        type: Boolean,
        required: false,
        default: false,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    icone: {
        type: String,
        required: false,
    },
    button: {
        type: Boolean,
        required: false,
        default: false
    },
    rounded: {
        type: String,
        required: false, 
        default: 'md'
    },
    iconPosition: {
        type: String,
        required: false, 
        default: 'left'
    }
});

// Função para determinar a cor baseada na severity
const getSeverityClass = () => {
    switch (props.severity) {
        case 'success':
            return 'bg-green-700 text-white hover:bg-green-700/85';
        case 'danger':
            return 'bg-red-700 text-white hover:bg-red-700/85';
        case 'warning':
            return 'bg-yellow-600 text-white hover:bg-yellow-600/85';
        case 'info':
            return 'bg-blue-600 text-white hover:bg-blue-600/85';
        case 'contrast':
            return 'bg-gray-900 text-white hover:bg-gray-900/85';
        default:
            return 'bg-primaria text-white hover:bg-primaria/85';
    }
};

const getArredondamento = () => {
    switch (props.rounded) {
        case 'sm':
            return 'rounded-sm';
        case 'lg':
            return 'rounded-lg';
        case 'xl':
            return 'rounded-xl';
        case 'full':
            return 'rounded-full';
        default:
            return 'rounded-md';
    }
}

// Função para determinar o tamanho baseado no size
const getSizeClass = () => {
    switch (props.size) {
        case 'xs':
            return 'h-[20px] text-[11px] min-w-[70px]';
        case 'sm':
            return 'h-[30px] text-[12px] min-w-[80px]';
        case 'lg':
            return 'h-[50px] text-[16px] min-w-[100px]';
        case 'xl':
            return 'h-[60px] text-[18px] min-w-[120px]';
        default:
            return 'h-[40px] text-[14px] min-w-[90px]';
    }
};

const getIcon = () => {
    switch (props.icone) {
        case 'arrow-up':
            return 'fas fa-arrow-up';
        case 'plus':
            return 'fas fa-plus';
        case 'arrow-left':
            return 'fas fa-arrow-left text-xs';
        case 'arrow-right':
            return 'fas fa-arrow-right text-xs';
        case 'play':
            return 'fas fa-play';
        case 'pause':
            return 'fas fa-pause';
        case 'stop':
            return 'fas fa-stop';
        case 'check':
            return 'fas fa-check';
        case 'trash':
            return 'fas fa-trash';
        case 'pencil':
            return 'fas fa-pencil';
    }
}

</script>

<template>
    <div class="flex items-center select-none justify-center" :class="[
        getSeverityClass(),
        getArredondamento(),
        fluid ? 'w-full' : '',
        button ? 'cursor-pointer justify-center items-center transition-all ' : '',
        label ? 'space-x-2' : ''
        
    ] ">

        <i v-if="iconPosition == 'left'" :class="getIcon()"></i>


        <div v-if="label">
            {{ label }}
        </div>

        <i v-if="iconPosition == 'right'" :class="getIcon()"></i>


    </div>


</template>

<style scoped>
/* Animação de progresso infinito */
@keyframes loadingAnimation {
    0% {
        width: 0%;
    }
    50% {
        width: 100%;
    }
    100% {
        width: 0%;
    }
}

/* Aplica a animação somente quando loading for true */
.loading-bar {
    animation: loadingAnimation 2s ease-in-out infinite;
}
</style>
