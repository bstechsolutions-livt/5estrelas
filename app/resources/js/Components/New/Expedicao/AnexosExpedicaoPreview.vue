<script setup>
/**
 * AnexosExpedicaoPreview
 * ----------------------
 * Renderiza preview visual (thumbnails) dos 3 anexos novos da Expedição:
 *   - Canhoto da NF
 *   - Placa do Caminhão
 *   - Produto Recebido (imagem OU PDF — mostra ícone PDF quando não for imagem)
 *
 * Cada thumbnail abre em nova aba ao clicar. Quando a URL é null, oculta o item.
 *
 * Props:
 *   - row: objeto com os campos foto_{canhoto_nf|placa_caminhao|produto_recebido}_{url,ext}
 */
import { computed } from "vue"
import { Image } from "primevue"

const props = defineProps({
  row: {
    type: Object,
    required: true
  }
})

const temAnexos = computed(() =>
  !!(
    props.row.foto_canhoto_nf_url ||
    props.row.foto_placa_caminhao_url ||
    props.row.foto_produto_recebido_url
  )
)

function ehPdf(ext) {
  return (ext || "").toLowerCase() === "pdf"
}
</script>

<template>
  <div
    v-if="temAnexos"
    class="flex flex-wrap gap-4"
  >
    <!-- Canhoto da NF -->
    <div
      v-if="row.foto_canhoto_nf_url"
      class="text-center"
    >
      <a
        v-if="ehPdf(row.foto_canhoto_nf_ext)"
        :href="row.foto_canhoto_nf_url"
        target="_blank"
        rel="noopener"
        class="flex flex-col items-center justify-center w-32 h-32 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-900 shadow-sm cursor-pointer hover:shadow-md transition"
      >
        <i class="pi pi-file-pdf text-4xl text-emerald-600"></i>
        <span class="text-[10px] text-emerald-700 mt-2 font-semibold">
          Abrir PDF
        </span>
      </a>
      <Image
        v-else
        :src="row.foto_canhoto_nf_url"
        alt="Canhoto da NF"
        width="150"
        preview
        class="rounded-lg shadow-sm"
        image-class="rounded-lg border border-emerald-200"
      />
      <p
        class="text-[10px] text-emerald-700 dark:text-emerald-400 mt-1 font-semibold"
      >
        <i class="pi pi-file-edit mr-0.5"></i>
        Canhoto da NF
      </p>
    </div>

    <!-- Placa do Caminhão -->
    <div
      v-if="row.foto_placa_caminhao_url"
      class="text-center"
    >
      <a
        v-if="ehPdf(row.foto_placa_caminhao_ext)"
        :href="row.foto_placa_caminhao_url"
        target="_blank"
        rel="noopener"
        class="flex flex-col items-center justify-center w-32 h-32 rounded-lg border border-orange-200 dark:border-orange-800 bg-white dark:bg-slate-900 shadow-sm cursor-pointer hover:shadow-md transition"
      >
        <i class="pi pi-file-pdf text-4xl text-orange-600"></i>
        <span class="text-[10px] text-orange-700 mt-2 font-semibold">
          Abrir PDF
        </span>
      </a>
      <Image
        v-else
        :src="row.foto_placa_caminhao_url"
        alt="Placa do Caminhão"
        width="150"
        preview
        class="rounded-lg shadow-sm"
        image-class="rounded-lg border border-orange-200"
      />
      <p
        class="text-[10px] text-orange-700 dark:text-orange-400 mt-1 font-semibold"
      >
        <i class="pi pi-truck mr-0.5"></i>
        Placa do Caminhão
      </p>
    </div>

    <!-- Produto Recebido -->
    <div
      v-if="row.foto_produto_recebido_url"
      class="text-center"
    >
      <a
        v-if="ehPdf(row.foto_produto_recebido_ext)"
        :href="row.foto_produto_recebido_url"
        target="_blank"
        rel="noopener"
        class="flex flex-col items-center justify-center w-32 h-32 rounded-lg border border-blue-200 dark:border-blue-800 bg-white dark:bg-slate-900 shadow-sm cursor-pointer hover:shadow-md transition"
      >
        <i class="pi pi-file-pdf text-4xl text-blue-600"></i>
        <span class="text-[10px] text-blue-700 mt-2 font-semibold">
          Abrir PDF
        </span>
      </a>
      <Image
        v-else
        :src="row.foto_produto_recebido_url"
        alt="Produto Recebido"
        width="150"
        preview
        class="rounded-lg shadow-sm"
        image-class="rounded-lg border border-blue-200"
      />
      <p
        class="text-[10px] text-blue-700 dark:text-blue-400 mt-1 font-semibold"
      >
        <i class="pi pi-box mr-0.5"></i>
        Produto Recebido
      </p>
    </div>
  </div>
</template>
