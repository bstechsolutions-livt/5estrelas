<script setup>
const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  text: {
    type: String,
    default: "Carregando"
  },
  subtext: {
    type: String,
    default: "Aguarde um momento..."
  }
})
</script>

<template>
  <Transition name="fade">
    <div
      v-if="loading"
      class="fixed inset-0 z-[10000] flex items-center justify-center pointer-events-none"
    >
      <div
        class="flex flex-col items-center gap-5 bg-white dark:bg-slate-800 px-10 py-8 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 pointer-events-auto"
      >
        <!-- Spinner animado -->
        <div class="relative">
          <div
            class="w-16 h-16 rounded-full border-4 border-blue-100 dark:border-slate-700"
          ></div>
          <div
            class="absolute top-0 left-0 w-16 h-16 rounded-full border-4 border-transparent border-t-blue-500 dark:border-t-blue-400 animate-spin"
          ></div>
          <div
            class="absolute top-1.5 left-1.5 w-[52px] h-[52px] rounded-full border-4 border-transparent border-t-indigo-400 dark:border-t-indigo-500 animate-spin"
            style="animation-duration: 1.5s; animation-direction: reverse"
          ></div>
        </div>
        <!-- Texto -->
        <div class="text-center">
          <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
            {{ text }}
          </p>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ subtext }}
          </p>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* Transição de fade */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
