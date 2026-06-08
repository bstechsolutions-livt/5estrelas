import axios from "axios"
import html2canvas from "html2canvas"
import ExcelJS from "exceljs"
import { saveAs } from "file-saver"

import { useToast } from "vue-toastification"

import dayjs from "dayjs"
import utc from "dayjs/plugin/utc"
import timezone from "dayjs/plugin/timezone"

// Ativa os plugins
dayjs.extend(utc)
dayjs.extend(timezone)

/**
 * Converte uma data (string ou Date) para o timezone de São Paulo
 * e retorna no formato YYYY-MM-DD HH:mm:ss
 */
export function formatarHoraFuso(dataEntrada) {
  if (!dataEntrada) return null

  const data = dayjs.tz(dataEntrada, "America/Sao_Paulo")

  return data.format("YYYY-MM-DD HH:mm:ss")
}

/**
 * Formata data vinda do Plug4Market (GMT-0) para horário brasileiro (GMT-3)
 * Subtrai 3 horas e formata como DD/MM/YYYY HH:mm
 */
export function formatarDataP4M(dataString) {
  if (!dataString) return null

  // Interpreta a data como UTC e converte para São Paulo (GMT-3)
  const data = dayjs.utc(dataString).tz("America/Sao_Paulo")

  if (!data.isValid()) return null

  return data.format("DD/MM/YYYY HH:mm")
}

// Toast
const toast = { error:(m)=>useToast().error(m), success:(m)=>useToast().success(m), warning:(m)=>useToast().warning(m), info:(m)=>useToast().info(m) }

export function toastSuccess(message) {
  toast.success(message)
}

export function toastInfo(message) {
  toast.info(message)
}

export function toastError(message) {
  toast.error(message)
}

export function toastWarning(message) {
  toast.warning(message)
}

export function tratarNome(fullName) {
  try {
    if (!fullName) return ""

    // Divide a string em palavras, ignorando espaços extras
    const words = fullName.trim().split(/\s+/).filter(Boolean)

    // Se a string estiver vazia, retorna string vazia
    if (words.length === 0) return ""

    // Se tiver só um nome, retorna ele mesmo (evita "Caio Caio")
    if (words.length === 1) return words[0]

    // Caso padrão: primeiro + último nome
    const firstName = words[0]
    const lastName = words[words.length - 1]
    return firstName + " " + lastName
  } catch (error) {
    return ""
  }
}

export function formatarDataSemHoras(data) {
  if (!data) return null

  let parsedDate
  // Se data for uma string e estiver no formato ISO, usa a conversão padrão
  if (typeof data === "string" && data.includes("T")) {
    parsedDate = new Date(data)
  } else if (typeof data === "string") {
    // Se for string e contiver hífens e não barras, converte para barras
    const dataTratada =
      data.includes("-") && !data.includes("/") ? data.replace(/-/g, "/") : data
    parsedDate = new Date(dataTratada)
  } else {
    // Se não for string, assume que já é um objeto Date ou algo compatível
    parsedDate = new Date(data)
  }

  return parsedDate.toLocaleDateString()
}

export function truncarTexto(texto, limite) {
  if (!texto) return ""
  if (texto.length > limite) {
    return texto.substring(0, limite) + "..."
  }
  return texto
}

/**
 * Formata um CNPJ no padrão 00.000.000/0000-00
 * @param {string|number} valor - CNPJ sem pontuação
 * @returns {string} CNPJ formatado ou vazio se inválido
 */
export function formatarCnpj(valor) {
  if (!valor) return ""

  // Remove qualquer caractere que não seja número
  const cnpj = valor.toString().replace(/\D/g, "")

  // Verifica se tem 14 dígitos
  if (cnpj.length !== 14) return valor

  return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5")
}

export function formatarParaReais(valor, exibirSimbolo = true) {
  // Garante que o valor seja numérico
  const valorNumerico = parseFloat(valor)

  if (isNaN(valorNumerico)) {
    throw new Error("O valor fornecido não é um número válido.")
  }

  // Trunca o valor para duas casas decimais sem arredondar
  const valorTruncado = Number(valorNumerico.toFixed(2))

  if (exibirSimbolo) {
    // Formata como moeda com o símbolo da moeda
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL"
    }).format(valorTruncado)
  } else {
    // Formata apenas como número decimal, sem moeda ou código
    return new Intl.NumberFormat("pt-BR", {
      style: "decimal",
      minimumFractionDigits: 2, // Garantindo duas casas decimais
      maximumFractionDigits: 2 // Limitando a duas casas decimais
    }).format(valorTruncado)
  }
}

export function formatarData(dataString) {
  // Parse da string para objeto Date do JavaScript
  const data = new Date(dataString)

  // Verifica se a data é válida
  if (isNaN(data.getTime())) {
    return null // Retorna null se a data não for válida
  }

  // Extrai dia, mês, ano e horário da data
  const dia = String(data.getDate()).padStart(2, "0") // Preenche com zero à esquerda se necessário
  const mes = String(data.getMonth() + 1).padStart(2, "0") // Month é base zero, adicionamos 1
  const ano = data.getFullYear()
  const horas = String(data.getHours()).padStart(2, "0")
  const minutos = String(data.getMinutes()).padStart(2, "0")

  // Formata a data no formato desejado
  return `${dia}/${mes}/${ano} ${horas}:${minutos}`
}

export function primeiroUltimoNome(fullName) {
  try {
    // Divide a string em palavras
    const words = fullName.trim().split(" ")

    // Se a string estiver vazia ou só tiver espaços, retorna null
    if (words.length === 0) {
      return { firstName: null, lastName: null }
    }

    // Pega o primeiro nome
    const firstName = words[0]

    // Pega o último nome
    const lastName = words[words.length - 1]

    // Retorna o resultado como um objeto
    return firstName + " " + lastName
  } catch (error) {
    return ""
  }
}

export function getExtensao(nomeArquivo) {
  if (nomeArquivo === null) {
    return
  }
  return nomeArquivo.split(".").pop().toLowerCase()
}

export function isImagem(nomeArquivo) {
  if (nomeArquivo === null) {
    return
  }

  const extensoesImagem = ["jpg", "jpeg", "jfif", "png", "gif", "bmp"] // Extensões suportadas pelos navegadores
  const extensao = nomeArquivo.split(".").pop().toLowerCase()
  return extensoesImagem.includes(extensao)
}

export async function imprimirConteudo(
  conteudosHtml,
  escala = 2,
  tamanhoPagina = "A4"
) {
  // Cria um iframe oculto
  const iframe = document.createElement("iframe")
  document.body.appendChild(iframe)

  // Define as dimensões da página com base no tamanho da página selecionado
  let pageSize
  let pageWidth
  let pageHeight
  let imgWidth
  let imgHeight

  switch (tamanhoPagina) {
    case "A5":
      pageSize = "A5" // Define o tamanho do papel como A5
      pageWidth = "210mm" // Largura da página A4
      pageHeight = "295mm" // Altura da página A4
      imgWidth = "148.1mm" // Largura da imagem A5
      imgHeight = "208mm" // Altura da imagem A5
      break
    case "A6":
      pageSize = "A6" // Define o tamanho do papel como A6
      pageWidth = "210mm" // Largura da página A4
      pageHeight = "295mm" // Altura da página A4
      imgWidth = "103mm" // Largura da imagem A6
      imgHeight = "143mm" // Altura da imagem A6
      break

    case "A4":
      pageSize = "A4" // Define o tamanho do papel como A4
      pageWidth = "209mm" // Largura do papel A4
      pageHeight = "295mm" // Altura do papel A4
      imgWidth = "209mm" // Largura da imagem A4
      imgHeight = "294mm" // Altura da imagem A4
      break
  }

  // Prepara o conteúdo para impressão
  const doc = iframe.contentWindow.document
  doc.open()
  doc.write(`
        <html>
        <head>
            <title>Imprimir Conteúdo</title>
            <style>
                @page {
                    size: ${pageSize};
                    margin: 0; /* Remove margens */
                }
                html, body {
                    margin: 0; /* Remove margens do html e do corpo */
                    padding: 0; /* Remove padding do html e do corpo */
                    overflow: hidden; /* Remove o overflow */

                }
                .page {
                    width: ${pageWidth};
                    height: ${pageHeight};
                    display: flex;
                    justify-content: center; /* Centraliza horizontalmente */
                    background: white; /* Fundo branco */
                    page-break-after: always; /* Garante que cada .page esteja em uma nova página */

                }

                .content {
                    width: ${imgWidth};
                    height: ${imgHeight};

                }
                .content img {
                    width: 100%; /* A imagem ocupará 100% da largura da .content */
                    height: 100%; /* Mantém a proporção da imagem */
                }
                .content-A6 {
                    width: ${imgWidth};
                    height: ${imgHeight};
                    display: flex;
                    align-items: center; // Centraliza verticalmente
                    justify-content: center; // Centraliza horizontalmente
                }
                .margem-custom{
                    margin-top: 10px;
                }
                .content-A6 img {
                    width: 100%;
                    height: 100%;
                }
                @media print {
                    body {
                        -webkit-print-color-adjust: exact; /* Mantém as cores exatas ao imprimir */
                    }
                }
            </style>
        </head>
        <body>
    `)

  // Itera sobre os conteúdos e captura cada um como imagem
  for (const conteudoHtml of conteudosHtml) {
    const canvas = await html2canvas(conteudoHtml, {
      backgroundColor: "#ffffff", // Fundo branco
      useCORS: true
    })
    const imageData = canvas.toDataURL("image/png")

    if (tamanhoPagina == "A6") {
      doc.write(`
                <div class="page">
                    <div class="content-a6">
                        <div class="margem-custom">
                            <img src="${imageData}" />
                        </div>
                    </div>
                </div>
            `)
    } else {
      // Adiciona a imagem centralizada dentro de uma página A4
      doc.write(`
                <div class="page">
                    <div class="content">
                        <img src="${imageData}" />
                    </div>
                </div>
            `)
    }
  }

  doc.write("</body></html>")
  doc.close()

  // Aguarda o carregamento e imprime
  setTimeout(() => {
    iframe.contentWindow.print() // Inicia a impressão
    document.body.removeChild(iframe) // Remove o iframe após a impressão
  }, 1000) // Atraso para garantir que o documento esteja pronto
}

// Função para buscar parâmetros parcelas cartazeamento
export async function buscarParametros() {
  try {
    const response = await axios.get("/cartazeamento/configuracao/parametros")
    return response.data
  } catch (error) {
    console.error("Erro ao buscar parâmetros:", error)
    return {
      qtdMaxParcelas: 0, // Valores padrão
      valorMinParcela: 0
    }
  }
}

// Função para calcular parcelas usando parâmetros obtidos
export async function calcularParcelas(valor) {
  // Buscar parâmetros
  const { qtdMaxParcelas, valorMinParcela } = await buscarParametros()

  // Calcula o número máximo de parcelas possíveis
  const parcelasPossiveis = Math.floor(valor / valorMinParcela)

  // Ajusta a quantidade de parcelas para garantir que o valor da parcela seja pelo menos o mínimo permitido
  let quantidadeParcelas = Math.min(qtdMaxParcelas, parcelasPossiveis)

  // Calcula o valor da parcela sem arredondar
  let valorParcela = valor / quantidadeParcelas

  // Arredonda o valor da parcela para duas casas decimais
  valorParcela = parseFloat(valorParcela.toFixed(2))

  // Verifica se o valor pode ser parcelado conforme o valor mínimo da parcela
  const permiteParcelar =
    quantidadeParcelas > 1 && valorParcela >= valorMinParcela

  // Retorna o resultado com a possibilidade de parcelamento, quantidade de parcelas e valor da parcela
  return {
    permiteParcelar: permiteParcelar,
    quantidadeParcelas: permiteParcelar ? quantidadeParcelas : 0,
    valorParcela: permiteParcelar ? valorParcela : 0
  }
}

export async function saveFileBase64(pasta, nome, arquivo) {
  let retorno

  try {
    const params = new FormData()

    params.append("file", arquivo.imagemFile)
    params.append("fileName", nome)

    const uploadUrl =
      pasta === "cartazeamentosp"
        ? "/cartazeamento/configuracao/upload-image-sp"
        : "/cartazeamento/configuracao/upload-image"

    await axios
      .post(uploadUrl, params)
      .then(async (res) => {
        retorno = res.data
      })
      .catch((err) => {
        console.error(err)
        return ""
      })
    return retorno
  } catch (error) {
    console.error("Erro inesperado: ", error)
  }
}

export async function getFileBase64(pasta, fileName) {
  let retorno
  const params = new FormData()
  // Construir o caminho completo com intranet/
  params.append("pasta", `intranet/${pasta}`)
  params.append("nome", fileName)

  await axios
    .post("/util/buscar-arquivo-web", params)
    .then((res) => {
      retorno = res.data
    })
    .catch((error) => {
      console.error("Erro inesperado: ", error)
    })

  return retorno
}

/**
 * Retorna a URL estática de um arquivo de layout se ele existir no servidor.
 * Usa fetch HEAD (servido pelo Nginx direto, sem PHP) para verificar existência.
 * Drop-in replacement para getFileBase64 nos casos de preview e impressão.
 */
export async function getFileUrl(pasta, fileName) {
  const baseUrl = `/intranet/${pasta}/${fileName}`
  const extensoes = ["png", "svg"]

  for (const extensao of extensoes) {
    const url = `${baseUrl}.${extensao}`
    try {
      const res = await fetch(url, { method: "HEAD" })
      if (res.ok) {
        return url
      }
    } catch {
      // Tenta a proxima extensao disponivel.
    }
  }

  return null
}

// Função global para upload de arquivos
export async function uploadFile(file, aplicacao, pasta, nome, userLogado) {
  if (!file) {
    return {
      success: false,
      message: "Nenhum arquivo selecionado"
    }
  }

  const fileExtension = file.name.split(".").pop()

  // Verifica se o nome foi passado e adiciona a extensão, se necessário
  let finalName = nome
  if (nome && !nome.includes(".")) {
    finalName = `${nome}.${fileExtension}`
  } else if (!nome) {
    // Caso o nome não tenha sido passado, usa o nome original do arquivo
    finalName = file.name
  }

  // Remove caracteres especiais e espaços em branco por _
  finalName = finalName.replace(/[\/\s,;:!@#$%^&*()+={}\[\]|<>?'"~`]/g, "_").replace(/_+/g, "_")

  // Cria o FormData com os parâmetros fornecidos
  const formData = new FormData()
  formData.append("file", file)
  formData.append("application", aplicacao) // Nome da aplicação passada como argumento
  formData.append("folder", pasta) // Nome da pasta passada como argumento
  formData.append("filename", finalName) // Nome do arquivo passado como argumento
  formData.append("userLogado", JSON.stringify(userLogado))

  try {
    // Faz a requisição para a rota de upload no Laravel
    const response = await axios.post("/api/files", formData, {
      headers: {
        "Content-Type": "multipart/form-data"
      }
    })

    // Retorna sucesso com a resposta da API
    return {
      success: true,
      data: response.data,
      message: "Arquivo enviado com sucesso!"
    }
  } catch (error) {
    // Retorna falha com a mensagem de erro
    return {
      success: false,
      message: "Erro ao enviar o arquivo",
      error: error.response ? error.response.data : error.message
    }
  }
}

// Função global para deletar arquivo
export async function deleteFile(id) {
  // Verifica se o ID foi passado
  if (!id) {
    return {
      success: false,
      message: "ID do arquivo não foi fornecido"
    }
  }

  try {
    // Faz a requisição DELETE para a rota de exclusão do Laravel
    const response = await axios.delete(`/api/files/${id}`)

    return {
      success: true,
      data: response.data,
      message: "Arquivo excluído com sucesso!"
    }
  } catch (error) {
    console.error("Erro ao excluir o arquivo:", error)

    return {
      success: false,
      message: "Erro ao excluir o arquivo",
      error: error.response ? error.response.data : error.message
    }
  }
}

export async function disableFile(id) {
  if (!id) {
    return {
      success: false,
      message: "ID do arquivo não foi fornecido"
    }
  }

  try {
    // Faz a requisição DELETE para a rota de exclusão do Laravel
    const response = await axios.post("/api/files/disable", { id: id })

    return {
      success: true,
      data: response.data,
      message: "Arquivo excluído com sucesso!"
    }
  } catch (error) {
    console.error("Erro ao excluir o arquivo:", error)

    return {
      success: false,
      message: "Erro ao excluir o arquivo",
      error: error.response ? error.response.data : error.message
    }
  }
}

// Função global para download de arquivo
export async function downloadFile(id) {
  if (!id) {
    return { success: false, message: "ID do arquivo não foi fornecido" }
  }

  try {
    // Faz a requisição para pegar o arquivo
    const response = await axios.get(`/api/files/download/${id}`, {
      responseType: "blob"
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement("a")
    link.href = url

    // Pega o cabeçalho de "Content-Disposition"
    const contentDisposition = response.headers["content-disposition"]
    let fileName = "arquivo_desconhecido" // Valor default caso não encontre o nome

    if (contentDisposition) {
      // Regex para encontrar o nome do arquivo, tanto codificado quanto simples
      const fileNameMatch = contentDisposition.match(
        /filename\*?=["']?([^;"]+)["']?/
      )
      if (fileNameMatch && fileNameMatch[1]) {
        const fileNameEncoded = fileNameMatch[1].trim()

        // Se o nome do arquivo tiver encoding (utf-8''), faz a decodificação
        if (fileNameEncoded.startsWith("utf-8''")) {
          fileName = decodeURIComponent(fileNameEncoded.slice(7)) // Remove "utf-8''" e decodifica
        } else {
          fileName = fileNameEncoded // Caso simples, usa o nome direto
        }
      }
    }

    // Define o nome do arquivo para download
    link.setAttribute("download", fileName)
    document.body.appendChild(link)
    link.click()

    // Remove o link da DOM após o download
    link.parentNode.removeChild(link)

    return { success: true, message: "Arquivo baixado com sucesso!" }
  } catch (error) {
    console.error("Erro ao baixar o arquivo:", error)
    return {
      success: false,
      message: "Erro ao baixar o arquivo",
      error: error.message
    }
  }
}

import Swal from "sweetalert2"

export function swalBloqueio(titulo = "Ticket Bloqueado", mensagens = []) {
  const isDark = document.documentElement.classList.contains("dark")

  if (!Array.isArray(mensagens) || mensagens.length === 0) {
    mensagens = ["Você está bloqueado(a) para novos tickets."]
  }

  const iconSvg = `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
  </svg>`

  const cardBg = isDark ? "rgba(239, 68, 68, 0.15)" : "rgba(239, 68, 68, 0.06)"
  const cardBorder = isDark
    ? "rgba(239, 68, 68, 0.4)"
    : "rgba(239, 68, 68, 0.25)"
  const textColor = isDark ? "#fca5a5" : "#991b1b"
  const iconDot = isDark ? "#fca5a5" : "#dc2626"

  const htmlContent = mensagens
    .map(
      (msg) =>
        `<div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; padding: 12px 16px; background: ${cardBg}; border-radius: 10px; border-left: 4px solid ${cardBorder};">
          <span style="color: ${iconDot}; font-size: 14px; flex-shrink: 0; margin-top: 1px;">●</span>
          <span style="color: ${textColor}; font-size: 0.875rem; line-height: 1.5; font-weight: 500;">${msg}</span>
        </div>`
    )
    .join("")

  return Swal.fire({
    html: `
      <div style="
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem 0;
      ">
        <div style="
          width: 64px;
          height: 64px;
          border-radius: 50%;
          background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
        ">
          ${iconSvg}
        </div>
        <div style="
          text-align: center;
          font-size: 1.1rem;
          font-weight: 600;
          color: ${isDark ? "#f1f5f9" : "#1e293b"};
          line-height: 1.5;
        ">
          ${titulo}
        </div>
        <div style="width: 100%; text-align: left; padding: 0 0.25rem;">
          ${htmlContent}
        </div>
      </div>
    `,
    showConfirmButton: true,
    showCancelButton: false,
    confirmButtonText:
      '<i class="pi pi-check" style="margin-right: 6px;"></i>Entendi',
    confirmButtonColor: "#dc2626",
    allowOutsideClick: false,
    allowEscapeKey: true,
    allowEnterKey: true,
    showCloseButton: true,
    background: isDark ? "#1e293b" : "#ffffff",
    color: isDark ? "#f1f5f9" : "#1e293b",
    width: "420px",
    customClass: {
      popup: "swal-custom-popup",
      actions: "swal-custom-actions",
      confirmButton: "swal-custom-confirm"
    }
  })
}

export async function swalConfirm(
  titulo = "Você tem certeza?",
  texto = "Essa ação não pode ser desfeita.",
  confirmText = "Sim",
  cancelText = "Cancelar",
  options = {}
) {
  const isDark = document.documentElement.classList.contains("dark")
  const iconType = options.icon || "warning"
  const iconColor = options.danger ? "#ef4444" : "#f59e0b"
  const confirmColor = options.danger ? "#dc2626" : "#16a34a"

  // Ícones SVG customizados
  const icons = {
    warning: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
      <line x1="12" y1="9" x2="12" y2="13"></line>
      <line x1="12" y1="17" x2="12.01" y2="17"></line>
    </svg>`,
    trash: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M3 6h18"></path>
      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
      <line x1="10" y1="11" x2="10" y2="17"></line>
      <line x1="14" y1="11" x2="14" y2="17"></line>
    </svg>`,
    question: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"></circle>
      <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
      <line x1="12" y1="17" x2="12.01" y2="17"></line>
    </svg>`
  }

  const selectedIcon = icons[iconType] || icons.warning
  const gradientStart = options.danger ? "#ef4444" : "#f59e0b"
  const gradientEnd = options.danger ? "#dc2626" : "#d97706"

  return Swal.fire({
    html: `
      <div style="
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem 0;
      ">
        <div style="
          width: 64px;
          height: 64px;
          border-radius: 50%;
          background: linear-gradient(135deg, ${gradientStart} 0%, ${gradientEnd} 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 14px ${gradientStart}66;
        ">
          ${selectedIcon}
        </div>
        <div style="
          text-align: center;
          font-size: 1.1rem;
          font-weight: 600;
          color: ${isDark ? "#f1f5f9" : "#1e293b"};
          line-height: 1.5;
        ">
          ${titulo}
        </div>
        <div style="
          text-align: center;
          font-size: 0.875rem;
          color: ${isDark ? "#94a3b8" : "#64748b"};
        ">
          ${texto}
        </div>
      </div>
    `,
    showConfirmButton: true,
    showCancelButton: true,
    confirmButtonText: `<i class="pi pi-${options.danger ? "trash" : "check"}" style="margin-right: 6px;"></i>${confirmText}`,
    cancelButtonText: `<i class="pi pi-times" style="margin-right: 6px;"></i>${cancelText}`,
    confirmButtonColor: confirmColor,
    cancelButtonColor: isDark ? "#475569" : "#64748b",
    reverseButtons: false,
    focusCancel: options.danger || false,
    allowOutsideClick: false,
    allowEscapeKey: true,
    allowEnterKey: true,
    background: isDark ? "#1e293b" : "#ffffff",
    color: isDark ? "#f1f5f9" : "#1e293b",
    customClass: {
      popup: "swal-custom-popup",
      actions: "swal-custom-actions",
      confirmButton: "swal-custom-confirm",
      cancelButton: "swal-custom-cancel"
    }
  })
}

/**
 * Função para diálogos de input com visual moderno
 *
 * @param {string} titulo - Título do diálogo
 * @param {string} inputLabel - Label do campo de input
 * @param {string} placeholder - Placeholder do input
 * @param {string} confirmText - Texto do botão de confirmação
 * @param {string} cancelText - Texto do botão de cancelamento
 * @param {Object} options - Opções adicionais
 * @returns {Promise} - Promise que resolve com o valor do input ou undefined se cancelado
 */
export async function swalInput(
  titulo = "Digite um valor",
  inputLabel = "",
  placeholder = "Digite aqui...",
  confirmText = "Confirmar",
  cancelText = "Cancelar",
  options = {}
) {
  const isDark = document.documentElement.classList.contains("dark")
  const iconColor = options.danger ? "#ef4444" : "#3b82f6"
  const confirmColor = options.danger ? "#dc2626" : "#16a34a"

  // Ícones SVG customizados
  const icons = {
    edit: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
    </svg>`,
    warning: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
      <line x1="12" y1="9" x2="12" y2="13"></line>
      <line x1="12" y1="17" x2="12.01" y2="17"></line>
    </svg>`
  }

  const selectedIcon = options.danger ? icons.warning : icons.edit
  const gradientStart = options.danger ? "#ef4444" : "#3b82f6"
  const gradientEnd = options.danger ? "#dc2626" : "#2563eb"

  return Swal.fire({
    html: `
      <div style="
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem 0;
      ">
        <div style="
          width: 64px;
          height: 64px;
          border-radius: 50%;
          background: linear-gradient(135deg, ${gradientStart} 0%, ${gradientEnd} 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 14px ${gradientStart}66;
        ">
          ${selectedIcon}
        </div>
        <div style="
          text-align: center;
          font-size: 1.1rem;
          font-weight: 600;
          color: ${isDark ? "#f1f5f9" : "#1e293b"};
          line-height: 1.5;
        ">
          ${titulo}
        </div>
        ${
          inputLabel
            ? `<div style="
          text-align: center;
          font-size: 0.875rem;
          color: ${isDark ? "#94a3b8" : "#64748b"};
        ">
          ${inputLabel}
        </div>`
            : ""
        }
      </div>
    `,
    input: options.inputType || "text",
    inputPlaceholder: placeholder,
    inputValidator: (value) => {
      if (!value && options.required !== false) {
        return options.requiredMessage || "Este campo é obrigatório!"
      }
    },
    showConfirmButton: true,
    showCancelButton: true,
    confirmButtonText: `<i class="pi pi-check" style="margin-right: 6px;"></i>${confirmText}`,
    cancelButtonText: `<i class="pi pi-times" style="margin-right: 6px;"></i>${cancelText}`,
    confirmButtonColor: confirmColor,
    cancelButtonColor: isDark ? "#475569" : "#64748b",
    reverseButtons: false,
    focusCancel: false,
    allowOutsideClick: false,
    allowEscapeKey: true,
    allowEnterKey: true,
    background: isDark ? "#1e293b" : "#ffffff",
    color: isDark ? "#f1f5f9" : "#1e293b",
    customClass: {
      popup: "swal-custom-popup swal-input-popup",
      input: "swal-custom-input",
      actions: "swal-custom-actions",
      confirmButton: "swal-custom-confirm",
      cancelButton: "swal-custom-cancel"
    },
    didOpen: () => {
      const input = Swal.getInput()
      if (input && input.tagName === "TEXTAREA") {
        input.style.overflow = "hidden"
        input.style.resize = "none"
        const autoResize = () => {
          input.style.height = "auto"
          input.style.height = input.scrollHeight + "px"
        }
        input.addEventListener("input", autoResize)
      }
    }
  })
}

/**
 * Função global para diálogos de confirmação usando PrimeVue
 *
 * @param {Function} confirm - Instância do useConfirm do PrimeVue
 * @param {Object} options - Opções de configuração
 * @param {string} options.message - Mensagem de confirmação
 * @param {string} options.header - Título do diálogo
 * @param {string} options.icon - Ícone do diálogo
 * @param {string} options.acceptLabel - Texto do botão de confirmação
 * @param {string} options.rejectLabel - Texto do botão de cancelamento
 * @param {string} options.acceptClass - Classes CSS do botão de confirmação
 * @param {string} options.rejectClass - Classes CSS do botão de cancelamento
 * @returns {Promise<boolean>} - Promise que resolve com true se confirmado, false se cancelado
 *
 * @example
 * // Importar no componente
 * import { dialogConfirm } from "@/utils/globalFunctions"
 * import { useConfirm } from "primevue/useconfirm"
 * import ConfirmDialog from "primevue/confirmdialog"
 *
 * // No setup do componente
 * const confirm = useConfirm()
 *
 * // Usar a função
 * const confirmed = await dialogConfirm(confirm, {
 *   message: "Deseja excluir este item?",
 *   header: "Confirmar Exclusão",
 *   acceptLabel: "Excluir"
 * })
 *
 * if (confirmed) {
 *   // Executar ação
 * }
 *
 * // Não esquecer de adicionar no template:
 * // <ConfirmDialog />
 */
export async function dialogConfirm(
  confirm,
  {
    message = "Tem certeza que deseja continuar?",
    header = "Confirmar Ação",
    icon = "pi pi-exclamation-triangle",
    acceptLabel = "Sim",
    rejectLabel = "Cancelar",
    acceptClass = "p-button-danger",
    rejectClass = "p-button-secondary p-button-outlined"
  } = {}
) {
  if (!confirm) {
    console.warn(
      "dialogConfirm requires confirm instance from PrimeVue useConfirm"
    )
    return false
  }

  return new Promise((resolve) => {
    confirm.require({
      message,
      header,
      icon,
      acceptLabel,
      rejectLabel,
      acceptClass,
      rejectClass,
      accept: () => resolve(true),
      reject: () => resolve(false)
    })
  })
}

export function swalErro(
  titulo = "Oops...",
  mensagem = "Ocorreu um erro inesperado. Tente novamente!",
  icone = "error"
) {
  return Swal.fire({
    icon: icone,
    title: titulo,
    text: mensagem,
    confirmButtonText: `<i class="pi pi-check" style="margin-right: 6px;"></i>OK`,
    confirmButtonColor: "#dc2626",
    allowOutsideClick: false,
    allowEscapeKey: true,
    allowEnterKey: true,
    showCancelButton: false,
    showCloseButton: false,
    customClass: {
      popup: "swal-custom-popup",
      title: "swal-custom-title",
      htmlContainer: "swal-custom-text",
      actions: "swal-custom-actions",
      confirmButton: "swal-custom-confirm"
    }
  })
}

export function swalValidacoes(
  titulo = "Validações Necessárias",
  mensagens = []
) {
  // Verificar se existem mensagens a serem exibidas
  if (!Array.isArray(mensagens) || mensagens.length === 0) {
    mensagens = ["Ocorreu um erro desconhecido."]
  }

  // Detectar dark mode
  const isDarkMode =
    document.documentElement.classList.contains("dark") ||
    document.body.classList.contains("dark") ||
    window.matchMedia("(prefers-color-scheme: dark)").matches

  // Estilos baseados no modo
  const cardBg = isDarkMode
    ? "linear-gradient(135deg, #451a1a 0%, #3d1515 100%)"
    : "linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%)"
  const textColor = isDarkMode ? "#fecaca" : "#7f1d1d"
  const popupBg = isDarkMode ? "#1e293b" : "#ffffff"
  const titleColor = isDarkMode ? "#f1f5f9" : "#1f2937"

  // Transformar o array de mensagens em uma lista formatada com ícones modernos
  const htmlContent = mensagens
    .map(
      (msg) =>
        `<div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; padding: 12px 16px; background: ${cardBg}; border-radius: 10px; border-left: 4px solid #ef4444;">
          <span style="color: #ef4444; font-size: 16px; flex-shrink: 0;">⚠️</span>
          <span style="color: ${textColor}; font-size: 14px; line-height: 1.5; font-weight: 500;">${msg}</span>
        </div>`
    )
    .join("")

  return Swal.fire({
    icon: "warning",
    iconColor: "#f59e0b",
    title: titulo,
    html: `
      <div style="
        text-align: left;
        margin: 0;
        padding: 8px 0;
        max-height: 300px;
        overflow-y: auto;
      ">
        ${htmlContent}
      </div>
    `,
    confirmButtonColor: "#3b82f6",
    confirmButtonText:
      '<i class="pi pi-check" style="margin-right: 6px;"></i> Entendi',
    showCancelButton: false,
    showCloseButton: true,
    width: "420px",
    backdrop: `rgba(0,0,0,0.6)`,
    background: popupBg,
    color: titleColor,
    customClass: {
      popup: "swal-popup-modern",
      title: "swal-title-modern",
      confirmButton: "swal-confirm-modern"
    }
  })
}

export async function swalSucesso(
  titulo = "Sucesso!",
  mensagem = "Operação realizada com sucesso."
) {
  return await Swal.fire({
    icon: "success",
    title: titulo,
    text: mensagem,
    confirmButtonColor: "#28a745", // Verde para representar sucesso
    confirmButtonText: "OK",
    showCancelButton: false,
    showCloseButton: false,
    timer: 3000, // Fecha automaticamente após 3 segundos
    timerProgressBar: true,
    customClass: {
      popup: "swal-popup-custom", // Classe personalizada para ajustes de estilo
      confirmButton: "btn btn-success"
    }
  })
}

export async function swalObservacao(
  mensagem = "Aqui está sua observação.",
  titulo = ""
) {
  return Swal.fire({
    html: `
      <div style="
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem 0;
      ">
        <div style="
          width: 64px;
          height: 64px;
          border-radius: 50%;
          background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        ">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
          </svg>
        </div>
        <div style="
          text-align: center;
          font-size: 0.95rem;
          color: #374151;
          line-height: 1.6;
          max-width: 280px;
        ">
          ${mensagem}
        </div>
      </div>
    `,
    title: titulo,
    showCancelButton: false,
    showConfirmButton: true,
    confirmButtonText: "Entendi",
    confirmButtonColor: "#3b82f6",
    showCloseButton: true,
    icon: null,
    width: "380px",
    padding: "1.5rem",
    customClass: {
      popup: "swal-popup-modern",
      confirmButton: "swal-confirm-modern",
      closeButton: "swal-close-modern"
    },
    backdrop: `
      rgba(0,0,0,0.5)
    `
  })
}

export async function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export async function getFile(id) {
  try {
    let file_data

    await axios.get(`/api/files/get-file/${id}`).then((res) => {
      file_data = res.data.file_data
    })
    return file_data
  } catch (error) {
    return error
  }
}

export async function getUser() {
  try {
    const res = await axios.get("/util/get-usuario")
    return res.data
  } catch (e) {
    swalErro("Erro Inesperado", e)
  }
}
export function validarCGC(cgc, tipo) {
  // Remove todos os caracteres não numéricos
  cgc = cgc.replace(/\D/g, "")

  if (tipo === "PF") {
    if (cgc.length !== 11 || /^(\d)\1+$/.test(cgc)) {
      return false
    }

    let soma = 0
    let resto

    // Validação do primeiro dígito verificador
    for (let i = 1; i <= 9; i++) {
      soma += parseInt(cgc[i - 1]) * (11 - i)
    }
    resto = (soma * 10) % 11
    resto = resto === 10 || resto === 11 ? 0 : resto
    if (resto !== parseInt(cgc[9])) {
      return false
    }

    soma = 0
    // Validação do segundo dígito verificador
    for (let i = 1; i <= 10; i++) {
      soma += parseInt(cgc[i - 1]) * (12 - i)
    }
    resto = (soma * 10) % 11
    resto = resto === 10 || resto === 11 ? 0 : resto
    return resto === parseInt(cgc[10])
  }

  if (tipo === "PJ") {
    if (cgc.length !== 14 || /^(\d)\1+$/.test(cgc)) {
      console.log("caiu aqui")
      return false
    }
    // Validação do primeiro dígito verificador do CNPJ
    let soma = 0
    let pesos = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
    for (let i = 0; i < 12; i++) {
      soma += parseInt(cgc[i]) * pesos[i]
    }
    let resto = soma % 11
    let digito1 = resto < 2 ? 0 : 11 - resto
    if (digito1 !== parseInt(cgc[12])) {
      return false
    }

    // Validação do segundo dígito verificador do CNPJ
    soma = 0
    pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
    for (let i = 0; i < 13; i++) {
      soma += parseInt(cgc[i]) * pesos[i]
    }
    resto = soma % 11
    let digito2 = resto < 2 ? 0 : 11 - resto
    return digito2 === parseInt(cgc[13])
  }

  // Retorna false se o tipo não for reconhecido como 'CPF' ou 'CNPJ'
  return false
}

export async function buscarPessoaJuridica(cnpj) {
  try {
    const response = await axios.post("/util/buscarPessoaJuridicaCompleto", {
      cnpj: cnpj
    })
    return response.data
  } catch (error) {
    return {}
  }
}

export function removerMascara(valor) {
  return valor ? valor.replace(/[\.\-\/\(\)\s]/g, "") : valor
}
export async function buscarCep(cep) {
  if (!cep || typeof cep !== "string") {
    return {}
  }

  try {
    const response = await axios.get(
      `https://viacep.com.br/ws/${cep.replace(/\D/g, "")}/json/`
    )
    if (response.data.erro) {
      return {}
    }
    return response.data
  } catch (error) {
    console.error("Erro ao buscar o CEP:", error.message)
    return {}
  }
}

export function removerAcentos(texto) {
  return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "")
}

export function log({ title, message, file, level = "info" }) {
  const logLevels = ["debug", "info", "error", "warning", "critical"] // Níveis de log permitidos

  // Valida se o nível de log é válido
  if (!logLevels.includes(level)) {
    console.error(
      `Nível de log inválido: ${level}. Os níveis válidos são: ${logLevels.join(", ")}`
    )
    return // Retorna para evitar o envio de log com nível inválido
  }

  const formData = new FormData()
  formData.append("title", title)
  formData.append("message", message)
  formData.append("level", level)

  // Se for enviado um arquivo, adiciona ao FormData
  if (file) {
    formData.append("file", file)
  }

  // Envia os dados para o backend
  axios
    .post("/log", formData)
    .then((response) => {
      console.log(
        `${level.charAt(0).toUpperCase() + level.slice(1)} log enviado com sucesso:`,
        response
      )
    })
    .catch((error) => {
      console.error(`Erro ao enviar log ${level}:`, error)
    })
}
export async function exportarDadosParaExcel(
  dados,
  nomeArquivo = "dados.xlsx"
) {
  if (!Array.isArray(dados) || dados.length === 0) {
    console.error("Dados inválidos ou vazios.")
    return
  }

  // Formata a data e hora atual no padrão desejado (YYYYMMDD_HHMMSS)
  const dataAtual = new Date()
  const ano = dataAtual.getFullYear()
  const mes = String(dataAtual.getMonth() + 1).padStart(2, "0")
  const dia = String(dataAtual.getDate()).padStart(2, "0")
  const horas = String(dataAtual.getHours()).padStart(2, "0")
  const minutos = String(dataAtual.getMinutes()).padStart(2, "0")
  const segundos = String(dataAtual.getSeconds()).padStart(2, "0")
  const dataHoraFormatada = `${ano}${mes}${dia}_${horas}${minutos}${segundos}`

  // Adiciona o sufixo da data e hora ao nome do arquivo
  const nomeArquivoComDataHora = nomeArquivo.replace(
    ".xlsx",
    `_${dataHoraFormatada}.xlsx`
  )

  // Cria o workbook e uma nova planilha
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet("Dados")

  // Pega as chaves do primeiro objeto para usar como cabeçalho
  const colunas = Object.keys(dados[0])

  // Identifica colunas especiais para estilização
  const colunasValor = colunas.filter(
    (col) =>
      col.toLowerCase().includes("valor") ||
      col.toLowerCase().includes("preco") ||
      col.toLowerCase().includes("total")
  )
  const colunasEtapa = colunas.filter(
    (col) =>
      col.toLowerCase().includes("etapa") ||
      col.toLowerCase().includes("status")
  )
  const colunasId = colunas.filter(
    (col) =>
      col.toLowerCase() === "id" ||
      col.toLowerCase().includes("codigo") ||
      col.toLowerCase().includes("matricula")
  )

  // Define o cabeçalho com as colunas - calcula largura baseada no conteúdo
  worksheet.columns = colunas.map((coluna) => {
    // Calcula a largura máxima baseada no conteúdo
    let maxLength = coluna.length
    dados.forEach((item) => {
      const valor = item[coluna]
      if (valor !== null && valor !== undefined) {
        const len = String(valor).length
        if (len > maxLength) maxLength = len
      }
    })
    return {
      header: coluna,
      key: coluna,
      width: Math.min(Math.max(maxLength + 2, 12), 50) // Mínimo 12, máximo 50
    }
  })

  // Adiciona os dados à planilha
  dados.forEach((item) => {
    worksheet.addRow(item)
  })

  // Estilo para o cabeçalho - cor cyan moderna
  worksheet.getRow(1).eachCell((cell) => {
    cell.font = { bold: true, color: { argb: "FFFFFFFF" }, size: 11 }
    cell.fill = {
      type: "pattern",
      pattern: "solid",
      fgColor: { argb: "FF0891B2" } // Cyan-600
    }
    cell.alignment = { horizontal: "center", vertical: "middle" }
    cell.border = {
      top: { style: "medium", color: { argb: "FF0E7490" } },
      left: { style: "medium", color: { argb: "FF0E7490" } },
      bottom: { style: "medium", color: { argb: "FF0E7490" } },
      right: { style: "medium", color: { argb: "FF0E7490" } }
    }
  })
  worksheet.getRow(1).height = 28

  // Cores para etapas
  const coresEtapa = {
    AUTORIZACAO: { bg: "FFFEF3C7", font: "FFB45309" },
    FATURAMENTO: { bg: "FFFED7AA", font: "FFC2410C" },
    "EM COTACAO": { bg: "FFDBEAFE", font: "FF1D4ED8" },
    RECEBIMENTO: { bg: "FFEDE9FE", font: "FF7C3AED" },
    "ENTRADA DE NOTA": { bg: "FFCFFAFE", font: "FF0891B2" },
    FINANCEIRO: { bg: "FF1E40AF", font: "FFFFFFFF" },
    NEGADA: { bg: "FFFECACA", font: "FFDC2626" },
    FINALIZADA: { bg: "FFBBF7D0", font: "FF16A34A" },
    NOVA: { bg: "FFE5E7EB", font: "FF4B5563" },
    CANCELADA: { bg: "FFEF4444", font: "FFFFFFFF" }
  }

  // Estilo para as linhas de dados - alternando cores
  worksheet.eachRow((row, rowNumber) => {
    if (rowNumber > 1) {
      const isEven = rowNumber % 2 === 0
      row.eachCell((cell, colNumber) => {
        const coluna = colunas[colNumber - 1]
        const valor = cell.value

        // Cor de fundo alternada padrão
        let bgColor = isEven ? "FFF0FDFA" : "FFFFFFFF" // Cyan-50 alternado
        let fontColor = "FF374151" // Gray-700
        let isBold = false

        // Estilo especial para colunas de ID
        if (colunasId.includes(coluna)) {
          fontColor = "FF0891B2" // Cyan-600
          isBold = true
          cell.alignment = { horizontal: "center", vertical: "middle" }
        }

        // Estilo especial para colunas de valor
        if (colunasValor.includes(coluna)) {
          fontColor = "FF059669" // Emerald-600
          isBold = true
          cell.alignment = { horizontal: "right", vertical: "middle" }
        }

        // Estilo especial para colunas de etapa
        if (colunasEtapa.includes(coluna) && valor) {
          const etapaUpper = String(valor).toUpperCase()
          if (coresEtapa[etapaUpper]) {
            bgColor = coresEtapa[etapaUpper].bg
            fontColor = coresEtapa[etapaUpper].font
            isBold = true
            cell.alignment = { horizontal: "center", vertical: "middle" }
          }
        }

        cell.fill = {
          type: "pattern",
          pattern: "solid",
          fgColor: { argb: bgColor }
        }
        cell.font = {
          color: { argb: fontColor },
          bold: isBold,
          size: 10
        }
        cell.border = {
          top: { style: "thin", color: { argb: "FFE5E7EB" } },
          left: { style: "thin", color: { argb: "FFE5E7EB" } },
          bottom: { style: "thin", color: { argb: "FFE5E7EB" } },
          right: { style: "thin", color: { argb: "FFE5E7EB" } }
        }
        if (!cell.alignment) {
          cell.alignment = { vertical: "middle" }
        }
      })
      row.height = 22
    }
  })

  // Congela a primeira linha (cabeçalho)
  worksheet.views = [{ state: "frozen", ySplit: 1 }]

  // Gera o arquivo Excel
  const buffer = await workbook.xlsx.writeBuffer()

  // Salva o arquivo usando `file-saver` com o nome incluindo a data e hora
  saveAs(new Blob([buffer]), nomeArquivoComDataHora)
}

export async function getFiliais() {
  try {
    const res = await axios.get("/util/buscar-filiais")
    return res.data // Retorna os dados diretamente
  } catch (err) {
    console.error(err)
    return "" // Retorna uma string vazia em caso de erro
  }
}

export async function getUsuarioById(
  id,
  resumido = false,
  identificador = false
) {
  var nome
  await axios
    .post("/util/buscar-usuario-nome/" + id)
    .then((res) => {
      if (resumido) {
        nome = tratarNome(res.data)
      } else {
        nome = res.data
      }
    })
    .catch((err) => {
      nome = ""
    })

  var nomeFinal = ""
  if (identificador) {
    nomeFinal += id + " - "
  }

  nomeFinal += nome

  return nomeFinal
}

export function criarNotificacao(
  titulo,
  mensagem,
  origem,
  canais,
  destinatarios,
  menu,
  submenu,
  link
) {
  var params = {
    titulo: titulo,
    mensagem: mensagem,
    origem: origem,
    canais: canais,
    destinatarios: destinatarios,
    menu: menu ?? null,
    submenu: submenu ?? null,
    link: link ?? null
  }

  axios
    .post("/notificacao/envia-notif", params)
    .then((res) => {
      swalSucesso()
    })
    .catch((e) => {
      swalErro(e.response.data.mensagem)
    })
}

export function calcularIdadeDetalhada(dataNascimento) {
  const hoje = new Date()
  const nascimento = new Date(dataNascimento)

  let anos = hoje.getFullYear() - nascimento.getFullYear()
  let meses = hoje.getMonth() - nascimento.getMonth()

  if (meses < 0 || (meses === 0 && hoje.getDate() < nascimento.getDate())) {
    anos--
    meses += 12
  }

  return `${anos} anos e ${meses} meses`
}

export function isAtrasado(data, intervalo = 2) {
  const dataAtual = new Date()
  const dataInformada = new Date(data)

  const diferencaDias = (dataAtual - dataInformada) / (1000 * 60 * 60 * 24)

  return diferencaDias < intervalo
}

/**
 * Retorna o horário no formato HH:mm a partir de uma string de data ou objeto Date.
 * @param {string|Date} data - Data no formato string ou objeto Date.
 * @returns {string|null} Horário no formato HH:mm ou null se inválido.
 */
export function getHoras(data) {
  if (!data) return null
  const dateObj = typeof data === "string" ? new Date(data) : data
  if (isNaN(dateObj.getTime())) return null
  const horas = String(dateObj.getHours()).padStart(2, "0")
  const minutos = String(dateObj.getMinutes()).padStart(2, "0")
  return `${horas}:${minutos}`
}

export function formatarTelefoneFixo(telefone) {
  if (!telefone) return ""

  // Remove tudo que não for número
  const digits = telefone.replace(/\D/g, "")

  // Celular: (xx) xxxxx-xxxx
  if (digits.length === 11) {
    return digits.replace(/^(\d{2})(\d{5})(\d{4})$/, "($1) $2-$3")
  }

  // Fixo: (xx) xxxx-xxxx
  if (digits.length === 10) {
    return digits.replace(/^(\d{2})(\d{4})(\d{4})$/, "($1) $2-$3")
  }

  return telefone // Retorna original se não encaixar
}

export async function getDevice() {
  const isFlutter = typeof window.flutter_inappwebview !== "undefined"

  if (isFlutter) {
    const dispositivoExistente = window.localStorage.getItem(
      "flutter.dispositivo"
    )

    if (dispositivoExistente) return dispositivoExistente

    try {
      const device = await window.flutter_inappwebview.callHandler("getDevice")

      if (device) {
        window.localStorage.setItem("flutter.dispositivo", device)
        return device
      } else {
        console.warn("Biometria falhou ou não disponível")
      }
    } catch (e) {
      console.error("Canal 'Biometria' não disponível:", e)
    }
  }
}

/**
 * Busca o nome de um funcionário pela matrícula
 * @param {string} matricula - A matrícula do funcionário
 * @param {boolean} resumido - Se true, retorna apenas primeiro e último nome
 * @param {boolean} identificador - Se true, inclui a matrícula no resultado
 * @returns {Promise<string>} - Nome do funcionário
 */
export async function getNomePorMatricula(matricula, resumido = true) {
  try {
    let nome = ""

    // Faz a requisição para buscar o nome pela matrícula
    await axios
      .post("/util/buscar-usuario-matricula/" + matricula)
      .then((res) => {
        if (resumido) {
          nome = tratarNome(res.data)
        } else {
          nome = res.data
        }
      })
      .catch((err) => {
        console.warn(`Funcionário não encontrado para matrícula: ${matricula}`)
        nome = matricula // Retorna a própria matrícula se não encontrar
      })
    console.log(nome)
    return nome
  } catch (error) {
    console.error("Erro ao buscar nome por matrícula:", error)
    return matricula // Retorna a matrícula em caso de erro
  }
}

/**
 * Retorna o nome do mês atual em português com a primeira letra maiúscula
 * @returns {string} Nome do mês atual
 */
export function nomeMesAtual() {
  const data = new Date()
  const mes = data.toLocaleString("pt-BR", { month: "long" })
  const mesCapitalizado = mes.charAt(0).toUpperCase() + mes.slice(1)
  return `${mesCapitalizado}`
}

export function formatarCep(cep) {
  if (!cep) return ""

  // Remove tudo que não for número
  const digits = cep.replace(/\D/g, "")

  // Aplica máscara para xxxxx-xxx
  if (digits.length >= 8) {
    return digits.replace(/^(\d{5})(\d{3}).*$/, "$1-$2")
  }

  return cep // Retorna original se não tiver 8 dígitos
}

export function formatarCpf(cpf) {
  if (!cpf) return ""

  // Remove tudo que não for número
  const digits = cpf.replace(/\D/g, "")

  // Aplica máscara para xxx.xxx.xxx-xx
  if (digits.length >= 11) {
    return digits.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*$/, "$1.$2.$3-$4")
  }

  return cpf // Retorna original se não tiver 11 dígitos
}
