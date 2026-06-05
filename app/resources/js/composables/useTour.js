// Shim no-op do useTour (onboarding/driver.js) da intranet Biglar.
// As telas de equipamentos chamam useTour(...). Aqui devolvemos funções vazias
// para não trazer a dependência de tour agora (pode ser implementado depois).
import { ref } from 'vue'

export function useTour() {
    const hasSeenTour = ref(true)
    return {
        startTour: () => {},
        autoStart: () => {},
        hasSeenTour,
        checkIfSeen: () => true,
        endTour: () => {},
        resetTour: () => {},
    }
}
