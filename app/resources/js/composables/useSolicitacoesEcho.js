import { ref, onMounted, onUnmounted } from "vue"
import { toastInfo } from "@/utils/globalFunctions"

/**
 * Composable para gerenciar conexões Echo/Reverb em Solicitações
 *
 * @example
 * // Em um componente Vue:
 * const { escutarDepartamento, escutarSolicitacao, escutarUsuario } = useSolicitacoesEcho()
 *
 * onMounted(() => {
 *   escutarDepartamento('TI', {
 *     onCriada: (data) => console.log('Nova solicitação!', data),
 *     onAtualizada: (data) => console.log('Atualizada!', data),
 *     filtroAtual: (solicitacao) => true, // Retorna true se a solicitação está no filtro atual
 *   })
 * })
 */
export function useSolicitacoesEcho() {
  const canaisAtivos = ref([])
  const conectado = ref(false)
  const erroConexao = ref(null)

  /**
   * Sanitiza nome do departamento para usar como canal Reverb
   * Remove espaços, acentos e caracteres especiais
   */
  const sanitizarNomeCanal = (nome) => {
    if (!nome) return ""
    // Converter para minúsculas
    let sanitizado = nome.toLowerCase()
    // Remover acentos
    sanitizado = sanitizado.normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    // Substituir espaços e caracteres especiais por underscore
    sanitizado = sanitizado.replace(/[^a-z0-9]+/g, "_")
    // Remover underscores duplicados e das pontas
    sanitizado = sanitizado.replace(/_+/g, "_").replace(/^_|_$/g, "")
    return sanitizado
  }

  /**
   * Verifica se o Echo está disponível
   */
  const echoDisponivel = () => {
    if (!window.Echo) {
      console.warn("[SolicitacoesEcho] window.Echo não está disponível")
      return false
    }
    return true
  }

  /**
   * Escuta eventos de um departamento específico
   *
   * @param {string} departamento - Nome do departamento
   * @param {Object} callbacks - Callbacks para os eventos
   * @param {Function} callbacks.onCriada - Callback quando nova solicitação é criada
   * @param {Function} callbacks.onAtualizada - Callback quando solicitação é atualizada
   * @param {Function} [callbacks.filtroAtual] - Função que recebe a solicitação e retorna true se está no filtro atual.
   *   Quando fornecida e retorna false para evento `criada`, exibe toast informando nova solicitação fora do filtro.
   */
  const escutarDepartamento = (departamento, callbacks = {}) => {
    if (!echoDisponivel()) return null

    const departamentoSanitizado = sanitizarNomeCanal(departamento)
    const canalNome = `public.intranet.solicitacoes.departamento.${departamentoSanitizado}`

    try {
      const canal = window.Echo.channel(canalNome)

      // Evento: nova solicitação criada
      canal.listen(".criada", (data) => {
        // Verificar se a solicitação está fora do filtro atual
        if (callbacks.filtroAtual && data.solicitacao) {
          const estaNoFiltro = callbacks.filtroAtual(data.solicitacao)
          if (!estaNoFiltro) {
            // Notificar via toast que há nova solicitação fora do filtro
            const id = data.solicitacao.id || ""
            toastInfo(`Nova solicitação #${id} recebida (fora do filtro atual)`)
          }
        }

        if (callbacks.onCriada) {
          callbacks.onCriada(data)
        }
      })

      // Evento: solicitação atualizada
      canal.listen(".atualizada", (data) => {
        if (callbacks.onAtualizada) {
          callbacks.onAtualizada(data)
        }
      })

      canaisAtivos.value.push(canalNome)
      conectado.value = true

      return canal
    } catch (error) {
      erroConexao.value = error.message
      return null
    }
  }

  /**
   * Escuta eventos de uma solicitação específica
   *
   * @param {number} solicitacaoId - ID da solicitação
   * @param {Object} callbacks - Callbacks para os eventos
   * @param {Function} callbacks.onAtualizada - Callback quando atualizada
   * @param {Function} callbacks.onComentario - Callback quando novo comentário
   */
  const escutarSolicitacao = (solicitacaoId, callbacks = {}) => {
    if (!echoDisponivel()) return null

    const canalNome = `public.intranet.solicitacoes.item.${solicitacaoId}`

    try {
      const canal = window.Echo.channel(canalNome)

      if (callbacks.onAtualizada) {
        canal.listen(".atualizada", (data) => {
          callbacks.onAtualizada(data)
        })
      }

      if (callbacks.onComentario) {
        canal.listen(".comentario", (data) => {
          callbacks.onComentario(data)
        })
      }

      if (callbacks.onComentarioExcluido) {
        canal.listen(".comentario_excluido", (data) => {
          callbacks.onComentarioExcluido(data)
        })
      }

      canaisAtivos.value.push(canalNome)
      conectado.value = true

      return canal
    } catch (error) {
      erroConexao.value = error.message
      return null
    }
  }

  /**
   * Escuta notificações pessoais do usuário
   *
   * @param {string} matricula - Matrícula do usuário
   * @param {Function} onNotificacao - Callback quando recebe notificação
   */
  const escutarUsuario = (matricula, onNotificacao) => {
    if (!echoDisponivel()) return null

    const canalNome = `public.intranet.solicitacoes.usuario.${matricula}`

    try {
      const canal = window.Echo.channel(canalNome)

      // Escutar evento de notificação
      canal.listen(".notificacao", (data) => {
        if (onNotificacao) {
          onNotificacao(data)
        }
      })

      // Escutar também evento de atualização (para Minhas Solicitações)
      canal.listen(".atualizada", (data) => {
        if (onNotificacao) {
          onNotificacao(data)
        }
      })

      canaisAtivos.value.push(canalNome)
      conectado.value = true

      return canal
    } catch (error) {
      erroConexao.value = error.message
      return null
    }
  }

  /**
   * Desconecta de um canal específico
   *
   * @param {string} canalNome - Nome do canal
   */
  const sairDoCanal = (canalNome) => {
    if (!echoDisponivel()) return

    try {
      window.Echo.leaveChannel(canalNome)
      canaisAtivos.value = canaisAtivos.value.filter((c) => c !== canalNome)
    } catch (error) {
      // Erro silencioso
    }
  }

  /**
   * Desconecta de todos os canais ativos
   */
  const desconectarTodos = () => {
    if (!echoDisponivel()) return

    canaisAtivos.value.forEach((canalNome) => {
      try {
        window.Echo.leaveChannel(canalNome)
      } catch (error) {
        // Erro silencioso
      }
    })

    canaisAtivos.value = []
    conectado.value = false
  }

  // Cleanup automático ao desmontar componente
  onUnmounted(() => {
    desconectarTodos()
  })

  return {
    // Estado
    canaisAtivos,
    conectado,
    erroConexao,

    // Métodos
    escutarDepartamento,
    escutarSolicitacao,
    escutarUsuario,
    sairDoCanal,
    desconectarTodos
  }
}
