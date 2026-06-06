/**
 * Composable para gerenciar preferências do usuário no servidor
 *
 * PRINCÍPIO: Salvar apenas IDs/códigos simples, rehidratar na leitura
 * Isso evita problemas de dados stale e inconsistências
 */

import axios from "axios"

// Cache local para evitar requisições repetidas na mesma sessão
const cache = new Map()

/**
 * Hook para usar preferências do usuário
 */
export function useUserPreferences() {
  /**
   * Buscar uma preferência
   * @param {string} chave - Chave da preferência (ex: 'solicitacoes.lista.filtroMinhas')
   * @param {any} defaultValue - Valor padrão se não existir
   * @returns {Promise<any>}
   */
  async function get(chave, defaultValue = null) {
    // Verificar cache primeiro
    if (cache.has(chave)) {
      return cache.get(chave)
    }

    try {
      const response = await axios.get(`/user-preferences/${chave}`)
      const valor = response.data?.valor ?? defaultValue

      // Salvar no cache
      cache.set(chave, valor)

      return valor
    } catch (error) {
      console.warn(`⚠️ Erro ao buscar preferência ${chave}:`, error)
      return defaultValue
    }
  }

  /**
   * Salvar uma preferência
   * @param {string} chave - Chave da preferência
   * @param {any} valor - Valor a ser salvo
   * @returns {Promise<boolean>}
   */
  async function set(chave, valor) {
    try {
      await axios.post(`/user-preferences/${chave}`, { valor })

      // Atualizar cache
      cache.set(chave, valor)

      return true
    } catch (error) {
      console.error(`❌ Erro ao salvar preferência ${chave}:`, error)
      return false
    }
  }

  /**
   * Remover uma preferência
   * @param {string} chave - Chave da preferência
   * @returns {Promise<boolean>}
   */
  async function remove(chave) {
    try {
      await axios.delete(`/user-preferences/${chave}`)

      // Remover do cache
      cache.delete(chave)

      return true
    } catch (error) {
      console.warn(`⚠️ Erro ao remover preferência ${chave}:`, error)
      return false
    }
  }

  /**
   * Buscar múltiplas preferências de uma vez
   * @param {string[]} chaves - Array de chaves
   * @returns {Promise<Object>}
   */
  async function getMany(chaves) {
    // Separar chaves que estão no cache das que precisam buscar
    const chavesNaoCache = chaves.filter((c) => !cache.has(c))
    const resultado = {}

    // Pegar do cache o que já tem
    chaves.forEach((chave) => {
      if (cache.has(chave)) {
        resultado[chave] = cache.get(chave)
      }
    })

    // Se todas já estão no cache, retornar
    if (chavesNaoCache.length === 0) {
      return resultado
    }

    try {
      const response = await axios.post("/user-preferences/get-many", {
        chaves: chavesNaoCache
      })

      // Adicionar ao resultado e ao cache
      Object.entries(response.data || {}).forEach(([chave, valor]) => {
        resultado[chave] = valor
        cache.set(chave, valor)
      })

      return resultado
    } catch (error) {
      console.warn("⚠️ Erro ao buscar múltiplas preferências:", error)
      return resultado
    }
  }

  /**
   * Limpar o cache local (útil após logout ou para forçar recarregamento)
   */
  function clearCache() {
    cache.clear()
  }

  /**
   * Limpar cache de uma chave específica (força buscar do servidor na próxima vez)
   */
  function clearCacheKey(chave) {
    cache.delete(chave)
  }

  /**
   * Forçar recarregamento de uma preferência (ignora cache)
   */
  async function getForceRefresh(chave, defaultValue = null) {
    cache.delete(chave)
    return get(chave, defaultValue)
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // FILTRO DE SOLICITAÇÕES - Funções específicas para serialização/deserialização
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Serializa o filtro para salvar no banco
   * PRINCÍPIO: Salvar APENAS identificadores simples, nunca objetos complexos
   *
   * @param {Object} filtro - Objeto de filtro completo da UI
   * @returns {Object} - Objeto serializado com apenas IDs
   */
  function serializarFiltro(filtro) {
    if (!filtro || typeof filtro !== "object") return null

    return {
      // Identificador único da busca
      id: filtro.id || null,

      // Arrays simples de valores primitivos
      prioridades: Array.isArray(filtro.prioridades)
        ? [...filtro.prioridades]
        : [],
      situacoes: Array.isArray(filtro.situacoes) ? [...filtro.situacoes] : [],

      // Departamento: salvar apenas o código identificador
      departamentoCodigo: filtro.departamento?.condicao1 || null,

      // Filiais: array de códigos (strings)
      filiais: Array.isArray(filtro.filiais)
        ? filtro.filiais.filter((f) => f != null).map((f) => String(f))
        : [],

      // Assuntos: apenas IDs
      assuntosIds: Array.isArray(filtro.assuntos)
        ? filtro.assuntos.filter((a) => a?.id != null).map((a) => a.id)
        : [],

      // Responsáveis: apenas matrículas (incluindo 'nao_atribuido')
      responsaveisMatriculas: Array.isArray(filtro.responsavel)
        ? filtro.responsavel
            .filter((r) => r != null)
            .map((r) => (typeof r === "object" ? r.matricula : r))
        : [],

      // Solicitante: apenas matrícula
      solicitanteMatricula: filtro.solicitante?.matricula || null,

      // Datas: strings ISO
      dataIni: filtro.dataIni || null,
      dataFim: filtro.dataFim || null,
      dataAltIni: filtro.dataAltIni || null,
      dataAltFim: filtro.dataAltFim || null,

      // Configurações de visualização
      porPagina: filtro.porPagina || 10,
      isResponsavel: Boolean(filtro.isResponsavel),

      // Versão do schema para futuras migrações
      _version: 2
    }
  }

  /**
   * Deserializa o filtro salvo e rehidrata com dados atuais
   *
   * @param {Object} filtroSalvo - Objeto serializado do banco
   * @param {Object} contexto - Dados atuais para rehidratação
   * @param {Array} contexto.departamentos - Lista de departamentos disponíveis
   * @param {Array} contexto.filiais - Lista de filiais disponíveis [{code, name}]
   * @param {Number} contexto.matriculaLogado - Matrícula do usuário logado
   * @param {String} contexto.areaatuacaoLogado - Área de atuação do usuário logado
   * @returns {Object} - Filtro rehidratado pronto para usar na UI
   */
  function deserializarFiltro(filtroSalvo, contexto) {
    const {
      departamentos = [],
      filiais = [],
      matriculaLogado,
      areaatuacaoLogado
    } = contexto

    // Se não tem filtro salvo, retorna null
    if (!filtroSalvo || typeof filtroSalvo !== "object") {
      return null
    }

    // Migrar filtros antigos (sem _version)
    if (!filtroSalvo._version) {
      return migrarFiltroAntigo(filtroSalvo, contexto)
    }

    // Rehidratar departamento
    let departamento = null
    if (filtroSalvo.departamentoCodigo) {
      departamento = departamentos.find(
        (d) => d.condicao1 === filtroSalvo.departamentoCodigo
      )
    }

    // Se departamento não existe mais, usar o do usuário ou o primeiro
    if (!departamento && departamentos.length > 0) {
      departamento =
        departamentos.find((d) => d.condicao1 === areaatuacaoLogado) ||
        departamentos[0]
    }

    // Rehidratar assuntos do departamento
    let assuntos = []
    if (
      departamento &&
      Array.isArray(filtroSalvo.assuntosIds) &&
      filtroSalvo.assuntosIds.length > 0
    ) {
      const assuntosDisponiveis = departamento.assuntos || []

      assuntos = filtroSalvo.assuntosIds
        .map((id) => assuntosDisponiveis.find((a) => a.id === id))
        .filter(Boolean) // Remove assuntos que não existem mais
        .filter((assunto) => {
          // Verificar permissão de ver o assunto
          if (!assunto.responsaveis || assunto.responsaveis.length === 0) {
            return true
          }
          return assunto.responsaveis.some(
            (r) => r.matricula == matriculaLogado
          )
        })
    }

    // Rehidratar filiais - validar contra lista disponível
    let filiaisValidadas = []
    if (
      Array.isArray(filtroSalvo.filiais) &&
      filtroSalvo.filiais.length > 0 &&
      filiais.length > 0
    ) {
      const codigosDisponiveis = new Set(filiais.map((f) => String(f.code)))
      filiaisValidadas = filtroSalvo.filiais
        .map((f) => String(f))
        .filter((f) => codigosDisponiveis.has(f))
    }

    // Rehidratar responsáveis do departamento
    // IMPORTANTE: retornar apenas matrículas (escalares), pois o MultiSelect usa option-value="matricula"
    let responsaveis = []
    if (
      Array.isArray(filtroSalvo.responsaveisMatriculas) &&
      filtroSalvo.responsaveisMatriculas.length > 0
    ) {
      const responsaveisDisponiveis = departamento?.responsaveis || []

      responsaveis = filtroSalvo.responsaveisMatriculas.filter((matricula) => {
        if (matricula === "nao_atribuido") return true
        return responsaveisDisponiveis.some((r) => r.matricula == matricula)
      })
    }

    // Rehidratar solicitante (mantém apenas matrícula, UI busca o resto se precisar)
    let solicitante = null
    if (filtroSalvo.solicitanteMatricula) {
      solicitante = { matricula: filtroSalvo.solicitanteMatricula }
    }

    return {
      id: filtroSalvo.id || null,
      prioridades: filtroSalvo.prioridades || [],
      situacoes: (filtroSalvo.situacoes || []).filter(
        (s) => s !== "finalizada" && s !== "cancelada"
      ),
      departamento,
      filiais: filiaisValidadas,
      assuntos,
      responsavel: responsaveis,
      solicitante,
      dataIni: filtroSalvo.dataIni || null,
      dataFim: filtroSalvo.dataFim || null,
      dataAltIni: filtroSalvo.dataAltIni || null,
      dataAltFim: filtroSalvo.dataAltFim || null,
      porPagina: filtroSalvo.porPagina || 10,
      pagina: 1, // Sempre começa na página 1
      isResponsavel: Boolean(filtroSalvo.isResponsavel),
      ordenacao: [{ field: "id", order: -1 }] // Sempre ordenação padrão
    }
  }

  /**
   * Migra filtros salvos no formato antigo (v1) para o novo formato
   */
  function migrarFiltroAntigo(filtroAntigo, contexto) {
    const {
      departamentos = [],
      filiais = [],
      matriculaLogado,
      areaatuacaoLogado
    } = contexto

    // Tentar extrair dados do formato antigo
    const departamentoCodigo = filtroAntigo.departamento?.condicao1 || null

    // Encontrar departamento
    let departamento = departamentoCodigo
      ? departamentos.find((d) => d.condicao1 === departamentoCodigo)
      : null

    if (!departamento && departamentos.length > 0) {
      departamento =
        departamentos.find((d) => d.condicao1 === areaatuacaoLogado) ||
        departamentos[0]
    }

    // Extrair IDs de assuntos do formato antigo
    const assuntosIds = Array.isArray(filtroAntigo.assuntos)
      ? filtroAntigo.assuntos.filter((a) => a?.id != null).map((a) => a.id)
      : []

    // Rehidratar assuntos
    let assuntos = []
    if (departamento && assuntosIds.length > 0) {
      const assuntosDisponiveis = departamento.assuntos || []
      assuntos = assuntosIds
        .map((id) => assuntosDisponiveis.find((a) => a.id === id))
        .filter(Boolean)
        .filter((assunto) => {
          if (!assunto.responsaveis || assunto.responsaveis.length === 0)
            return true
          return assunto.responsaveis.some(
            (r) => r.matricula == matriculaLogado
          )
        })
    }

    // Validar filiais
    let filiaisValidadas = []
    if (
      Array.isArray(filtroAntigo.filiais) &&
      filtroAntigo.filiais.length > 0 &&
      filiais.length > 0
    ) {
      const codigosDisponiveis = new Set(filiais.map((f) => String(f.code)))
      filiaisValidadas = filtroAntigo.filiais
        .map((f) =>
          typeof f === "object" ? String(f.code || f.codigo) : String(f)
        )
        .filter((f) => f && codigosDisponiveis.has(f))
    }

    // Extrair matrículas de responsáveis
    // IMPORTANTE: retornar apenas matrículas (escalares), pois o MultiSelect usa option-value="matricula"
    let responsaveis = []
    if (Array.isArray(filtroAntigo.responsavel)) {
      const responsaveisDisponiveis = departamento?.responsaveis || []
      responsaveis = filtroAntigo.responsavel
        .map((r) => {
          if (r === "nao_atribuido") return "nao_atribuido"
          return typeof r === "object" ? r.matricula : r
        })
        .filter((matricula) => {
          if (matricula === "nao_atribuido") return true
          return responsaveisDisponiveis.some(
            (resp) => resp.matricula == matricula
          )
        })
    }

    return {
      id: filtroAntigo.id || null,
      prioridades: filtroAntigo.prioridades || [],
      situacoes: (filtroAntigo.situacoes || []).filter(
        (s) => s !== "finalizada" && s !== "cancelada"
      ),
      departamento,
      filiais: filiaisValidadas,
      assuntos,
      responsavel: responsaveis,
      solicitante: filtroAntigo.solicitante || null,
      dataIni: filtroAntigo.dataIni || null,
      dataFim: filtroAntigo.dataFim || null,
      dataAltIni: filtroAntigo.dataAltIni || null,
      dataAltFim: filtroAntigo.dataAltFim || null,
      porPagina: filtroAntigo.porPagina || 10,
      pagina: 1,
      isResponsavel: Boolean(filtroAntigo.isResponsavel),
      ordenacao: [{ field: "id", order: -1 }]
    }
  }

  // Manter compatibilidade com código antigo (deprecated)
  function sanitizeFiltro(data) {
    console.warn("⚠️ sanitizeFiltro está deprecated. Use serializarFiltro.")
    return serializarFiltro(data)
  }

  return {
    // Funções genéricas
    get,
    set,
    remove,
    getMany,
    clearCache,
    clearCacheKey,
    getForceRefresh,

    // Funções específicas para filtros de solicitações
    serializarFiltro,
    deserializarFiltro,

    // Deprecated (manter compatibilidade)
    sanitizeFiltro
  }
}

// Export default para uso direto
export default useUserPreferences
