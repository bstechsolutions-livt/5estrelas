<script setup>
import { computed } from 'vue';
import ProgressSpinner from 'primevue/progressspinner';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: false,
    },
    severity: {
        type: String,
        required: false,
        default: '',
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
    class: {
        type: String,
        required: false,
        default: ''
    }
});

// Função para determinar a cor baseada na severity
const getSeverityClass = () => {
    switch (props.severity) {
        case 'success':
            return 'bg-green-700 text-white hover:bg-green-700/85';
        case 'danger':
            return 'bg-red-700 text-white  hover:bg-red-700/85';
        case 'warning':
            return 'bg-yellow-600 text-white  hover:bg-yellow-600/85';
        case 'info':
            return 'bg-blue-600 text-white hover:bg-blue-600/85';
        case 'contrast':
            return 'bg-gray-800 text-white hover:bg-gray-800/85';
        case 'neutral':
            return 'bg-gray-500/80 text-white font-bold hover:bg-gray-500/70';
        default:
            return 'bg-primaria text-white hover:bg-primaria/85';
    }
};

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
</script>

<template>
    
    <div class="flex items-center">
        
        <button @click="submitButton" :disabled="loading || disabled" class="w-full"
            :class="[
                getSeverityClass(),
                getSizeClass(),
                props.class,
                loading ? 'bg-opacity-85 cursor-not-allowed' : 'cursor-pointer',
                disabled ? 'rounded-md px-2 opacity-65 hover:cursor-not-allowed transition-all flex items-center justify-center' : 'rounded-md px-2 transition-all flex items-center justify-center relative'
            ]"
        >
            {{ loading == true ? '' : label ? label : ''}}

            <div v-if="loading" class=" flex items-center w-full">
                    <ProgressSpinner style="width: 25px; height: 25px" strokeWidth="8" fill="transparent"
                        animationDuration=".5s" aria-label="Custom ProgressSpinner" />
            </div>


        </button>

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
