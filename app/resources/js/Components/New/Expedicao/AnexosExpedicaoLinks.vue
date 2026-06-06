<script setup>
/**
 * AnexosExpedicaoLinks
 * ---------------------
 * Componente reutilizável que renderiza até 3 links de anexos de uma NF
 * (Canhoto da NF, Placa do Caminhão, Produto Recebido), só exibindo cada
 * link quando a respectiva URL estiver presente em `row`.
 *
 * Cada link abre em nova aba e exibe ícone de PDF ou imagem conforme a
 * extensão em `foto_*_ext` (valor em minúsculas, ex.: 'pdf', 'jpg').
 *
 * Esperado em `row`:
 *   - foto_canhoto_nf_url / foto_canhoto_nf_ext
 *   - foto_placa_caminhao_url / foto_placa_caminhao_ext
 *   - foto_produto_recebido_url / foto_produto_recebido_ext
 *
 * Props:
 *   - row: objeto com os campos acima (pode conter outros)
 *   - size: 'xs' | 'sm' (default 'xs') — tamanho do texto
 *   - compact: boolean (default false) — quando true, exibe apenas os
 *     ícones (sem o label "Canhoto" / "Placa" / "Produto")
 */
defineProps({
  row: {
    type: Object,
    required: true
  },
  size: {
    type: String,
    default: "xs",
    validator: (v) => ["xs", "sm"].includes(v)
  },
  compact: {
    type: Boolean,
    default: false
  }
})
</script>

<template>
  <div class="flex flex-wrap gap-2 items-center">
    <a
      v-if="row.foto_canhoto_nf_url"
      :href="row.foto_canhoto_nf_url"
      target="_blank"
      rel="noopener"
      :class="[
        'inline-flex items-center gap-1 text-emerald-700 hover:text-emerald-900 hover:underline',
        size === 'sm' ? 'text-sm' : 'text-xs'
      ]"
      title="Canhoto da NF"
    >
      <i
        :class="[
          row.foto_canhoto_nf_ext === 'pdf' ? 'pi pi-file-pdf' : 'pi pi-image',
          'text-[10px]'
        ]"
      ></i>
      <span v-if="!compact">Canhoto</span>
    </a>

    <a
      v-if="row.foto_placa_caminhao_url"
      :href="row.foto_placa_caminhao_url"
      target="_blank"
      rel="noopener"
      :class="[
        'inline-flex items-center gap-1 text-orange-700 hover:text-orange-900 hover:underline',
        size === 'sm' ? 'text-sm' : 'text-xs'
      ]"
      title="Placa do Caminhão"
    >
      <i
        :class="[
          row.foto_placa_caminhao_ext === 'pdf'
            ? 'pi pi-file-pdf'
            : 'pi pi-image',
          'text-[10px]'
        ]"
      ></i>
      <span v-if="!compact">Placa</span>
    </a>

    <a
      v-if="row.foto_produto_recebido_url"
      :href="row.foto_produto_recebido_url"
      target="_blank"
      rel="noopener"
      :class="[
        'inline-flex items-center gap-1 text-blue-700 hover:text-blue-900 hover:underline',
        size === 'sm' ? 'text-sm' : 'text-xs'
      ]"
      title="Produto Recebido"
    >
      <i
        :class="[
          row.foto_produto_recebido_ext === 'pdf'
            ? 'pi pi-file-pdf'
            : 'pi pi-image',
          'text-[10px]'
        ]"
      ></i>
      <span v-if="!compact">Produto</span>
    </a>
  </div>
</template>
