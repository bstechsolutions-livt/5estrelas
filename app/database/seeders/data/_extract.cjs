// Extrai HISTORICO_INICIAL e SEED_CLIENTES do protótipo HTML e grava JSONs.
// Uso: node _extract.cjs <caminho-do-html>
const fs = require('fs')
const path = require('path')

const htmlPath = process.argv[2]
const html = fs.readFileSync(htmlPath, 'utf8')

function extractArrayLiteral(src, marker) {
  const idx = src.indexOf(marker)
  if (idx === -1) throw new Error('marcador não encontrado: ' + marker)
  const start = src.indexOf('[', idx)
  // varredura balanceada de colchetes respeitando strings
  let depth = 0, inStr = false, q = '', esc = false
  for (let i = start; i < src.length; i++) {
    const c = src[i]
    if (inStr) {
      if (esc) { esc = false }
      else if (c === '\\') { esc = true }
      else if (c === q) { inStr = false }
      continue
    }
    if (c === '"' || c === "'") { inStr = true; q = c; continue }
    if (c === '[') depth++
    else if (c === ']') { depth--; if (depth === 0) return src.slice(start, i + 1) }
  }
  throw new Error('array não fechado para ' + marker)
}

// eval seguro do literal (objeto JS com chaves não-aspeadas)
function toJson(literal) {
  // eslint-disable-next-line no-new-func
  return Function('"use strict"; return (' + literal + ')')()
}

const outDir = __dirname
const histLit = extractArrayLiteral(html, 'HISTORICO_INICIAL')
const cliLit = extractArrayLiteral(html, 'SEED_CLIENTES')

const historico = toJson(histLit)
const clientes = toJson(cliLit)

fs.writeFileSync(path.join(outDir, 'propostas_historico.json'), JSON.stringify(historico, null, 2))
fs.writeFileSync(path.join(outDir, 'clientes_seed.json'), JSON.stringify(clientes, null, 2))

console.log('propostas:', historico.length, '| clientes:', clientes.length)
