// Unidades federativas do Brasil — para SearchSelect (value = sigla, label = "SIGLA — Nome").
export const UFS = [
  { value: 'AC', nome: 'Acre' },
  { value: 'AL', nome: 'Alagoas' },
  { value: 'AP', nome: 'Amapá' },
  { value: 'AM', nome: 'Amazonas' },
  { value: 'BA', nome: 'Bahia' },
  { value: 'CE', nome: 'Ceará' },
  { value: 'DF', nome: 'Distrito Federal' },
  { value: 'ES', nome: 'Espírito Santo' },
  { value: 'GO', nome: 'Goiás' },
  { value: 'MA', nome: 'Maranhão' },
  { value: 'MT', nome: 'Mato Grosso' },
  { value: 'MS', nome: 'Mato Grosso do Sul' },
  { value: 'MG', nome: 'Minas Gerais' },
  { value: 'PA', nome: 'Pará' },
  { value: 'PB', nome: 'Paraíba' },
  { value: 'PR', nome: 'Paraná' },
  { value: 'PE', nome: 'Pernambuco' },
  { value: 'PI', nome: 'Piauí' },
  { value: 'RJ', nome: 'Rio de Janeiro' },
  { value: 'RN', nome: 'Rio Grande do Norte' },
  { value: 'RS', nome: 'Rio Grande do Sul' },
  { value: 'RO', nome: 'Rondônia' },
  { value: 'RR', nome: 'Roraima' },
  { value: 'SC', nome: 'Santa Catarina' },
  { value: 'SP', nome: 'São Paulo' },
  { value: 'SE', nome: 'Sergipe' },
  { value: 'TO', nome: 'Tocantins' },
]

// Opções no formato { value, nome, label } onde label = "SP — São Paulo".
export const UF_OPTIONS = UFS.map((u) => ({ value: u.value, nome: u.nome, label: `${u.value} — ${u.nome}` }))
