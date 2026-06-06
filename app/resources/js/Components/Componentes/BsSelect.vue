<script setup>
import { computed } from 'vue';

// Definindo as props com tipos
const props = defineProps({
    modelValue: {
        type: [String, Number, Object],
        required: true,
    },
    label: {
        type: String,
        required: false,
    },
    options: {
        type: Array,
        required: true,
        default: () => [],
    },
    optionLabel: {
        type: String,
        required: false,
        default: null,
    },
    optionValue: {
        type: String,
        required: false,
        default: null, 
    },
    invalid: {
        type: Boolean,
        required: false,
        default: false, 
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false, 
    },
    closeOnSelect: {
        type: Boolean,
        required: false,
        default: true,
    },
});



// Eventos que o componente emite
const emit = defineEmits(['update:modelValue']);

// Propriedade reativa para controlar o v-model
const internalValue = computed({
    get: () => props.modelValue,
    set: (novoValor) => emit('update:modelValue', novoValor),
});


</script>

<template>
    <div class="relative">
        <!-- Exibe a label se for fornecida -->
        <label v-if="label" for="select" 
            class="
                absolute -top-[9px] left-2 z-40 bg-white px-1 select-none
                overflow-hidden max-w-[180px] whitespace-nowrap truncate text-xs
            "
            :class="{
                'text-gray-800' : !invalid,
                'text-red-700' : invalid,
                'bg-gradient-to-t from-gray-200/70 rounded': disabled
                
            }"
        >
            {{ label }}
        </label>

        
        
        <!-- Select vinculado ao internalValue -->
        <select id="select" name="select" v-model="internalValue" :disabled="disabled" 
            class="
                min-w-[250px] w-full  h-[35px] rounded-t
                border ring-0 rounded-md focus:ring-0 hover:ring-0
                transition-all duration-200
                disabled:bg-gray-300/60 
                
            "
            :class="{
                'border-gray-500 focus:border-primaria-dark hover:border-primaria-dark disabled:border-gray-300' : !invalid,
                'border-red-700 focus:border-red-700 hover:border-red-700' : invalid,
                
            }"
        >
            <option v-for="option in options" :key="option[props.optionValue]" :value="props.optionValue ? option[props.optionValue] : option" >
                {{ option[props.optionLabel] }}
            </option>
        </select>
        <div @click="internalValue = null" v-if="(Array.isArray(internalValue) ? internalValue.length > 0 : internalValue) && !disabled && closeOnSelect"
            class="
                absolute cursor-pointer -top-[6px] -right-[1px] p-1 w-3 h-3 bg-red-100 drop-shadow-md shadow-md rounded-full 
                flex items-center justify-center hover:scale-125 transition-all
            "
        >
            <i class="fa-solid fa-xmark fa-xs" style="color: #b30000;"></i>
        </div>

    </div>
</template>
