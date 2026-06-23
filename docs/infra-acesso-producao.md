# Infra de Produção - 5 Estrelas

Acesso ao ambiente da Plataforma 5 Estrelas (servidor do cliente).

## Resumo

- **Servidor físico**: G5E-DF-SRV-001
- **Hypervisor**: Microsoft Hyper-V
- **VM**: G5E-SVM080
- **Sistema operacional**: Ubuntu 24.04.4 LTS
- **IP interno**: 192.168.254.80
- **Acesso**: exclusivamente via SSH (porta 22) através da VPN

### Recursos da VM

| Recurso | Quantidade |
|---------|-----------|
| vCPUs | 6 |
| RAM | 6 GB |
| Swap | 4 GB |
| SSD (sistema) | 300 GB — montado em `/` |
| HDD (dados) | 1 TB — montado em `/mnt/dados` |

### Usuários autorizados (sudo/root)

- `easytech`
- `matheusxavier`

Cada usuário recebe acessos individualmente por e-mail (rastreabilidade).

### E-mail do cliente (avisos/notificações do sistema)

- **E-mail**: `modernizacao.avisos@grupo5estrelas.com.br`
- **Senha**: `5Estrelas@2026`
- **Uso**: envio de notificações, avisos, recuperação de senha, comunicações automáticas da plataforma

### Diagrama da infraestrutura

Diagrama oficial enviado pelo time de infra está em [`infra/diagramas/infraestrutura-vm.png`](../infra/diagramas/infraestrutura-vm.png).

Topologia:

```
Usuário Remoto → Internet → VPN obrigatória → SSH (22) → 192.168.254.80
                                                          (G5E-SVM080)
```

## VPN

Cliente OpenVPN fornecido pelo time de infra do cliente.

- **Arquivo**: [`infra/vpn/acesso-vpn-easytech.ovpn`](../infra/vpn/acesso-vpn-easytech.ovpn)
- **Usuário**: `vpn.easytech`
- **Senha**: `Xm7$kP2#vL`

### Como conectar (Ubuntu/Debian)

```bash
sudo apt install -y openvpn

# Conexão via terminal (precisa do user/senha do auth)
sudo openvpn --config /home/bstech/Documentos/Hub/clientes/5estrelas/infra/vpn/acesso-vpn-easytech.ovpn --auth-user-pass
```

Cole o usuário/senha quando perguntar. Mantém o terminal aberto enquanto usar.

Alternativamente, importar o `.ovpn` no NetworkManager (Configurações → VPN) ou no Tunnelblick (macOS).

## SSH na VM

Após VPN conectada:

```bash
ssh easytech@192.168.254.80
```

- **Usuário**: `easytech`
- **Senha**: `UE2emkL1uANFetmQl3W1EAgc` (rotacionada em 28/05/2026, original do e-mail invalidada)
- **Privilégios**: sudo/root liberado

## Acessos Senior (UI)

Acesso à interface gráfica do Senior (web/HTML5). É **distinto** do usuário técnico de webservices `5estrelas.integracao` (esse fica em [`.kiro/steering/integracao-senior.md`](../.kiro/steering/integracao-senior.md)). Fornecidos pelo Luan em 23/06/2026.

- **Plataforma Senior (Sirius S2 GW02)** — login da plataforma cloud
  - URL: `https://sirius-s2.seniorcloud.com.br/`
  - Login: `estre.estre.modern`
  - Senha: `Senhateste1!`
- **ERP Gestão Empresarial** — login interno do ERP
  - Login: `bruno.easy`
  - Senha inicial: `123456` (TEMPORÁRIA — troca obrigatória no primeiro acesso; atualizar aqui com a nova após trocar)

> Detalhes completos (usuário do Luan, base de produção, empresas operacionais) em [`.kiro/steering/integracao-senior.md`](../.kiro/steering/integracao-senior.md).

## Responsabilidade

Conforme combinado com o cliente: quaisquer alterações, instalações, configurações ou ações executadas dentro da VM passam a ser de responsabilidade da BS Tech / Easy Tech (usuários autorizados).

## Stack que vai rodar nesta VM

| Item | Status |
|------|--------|
| Ubuntu 24.04 LTS | ✅ provisionado |
| PHP 8.3 + extensões | ✅ 8.3.31 (pgsql, redis, mbstring, xml, zip, curl, gd, intl, bcmath) |
| PHP-FPM 8.3 | ✅ ativo |
| Composer | ✅ 2.10.0 |
| PostgreSQL 16 | ✅ 16.14 (user: estrelas / pwd: Estr3l4s@Pr0d2026 / db: estrelas) |
| Redis 7 | ✅ 7.0.15 |
| Node 20 + npm | ✅ v20.20.2 / npm 10.8.2 |
| Nginx | ✅ 1.24.0 |
| Supervisor (queue worker, reverb) | ✅ 4.2.5 |
| Certbot (SSL Let's Encrypt) | ✅ 2.9.0 |
| Cron (`schedule:run`, backup) | ⏳ a configurar |
| Domínio/subdomínio | ⏳ a definir com cliente |

## Distribuição de discos sugerida

A VM tem 2 discos:

- **`/` (300 GB SSD)** — sistema, código da aplicação, vendor, node_modules, logs do nginx
- **`/mnt/dados` (1 TB HDD)** — dados que crescem: PostgreSQL, uploads/storage, backups locais

Sugestão de uso:

| Caminho | Conteúdo |
|---------|----------|
| `/var/www/5estrelas` | código da aplicação |
| `/mnt/dados/postgres` | data dir do PostgreSQL (link em `/var/lib/postgresql`) |
| `/mnt/dados/storage` | `storage/app/public` e uploads (link no Laravel) |
| `/mnt/dados/backups` | dumps do banco e archives |

## Próximos passos

1. Conectar via VPN + SSH e validar acesso
2. Verificar rede / firewall (precisamos abrir 80/443 pra fora?)
3. Confirmar com cliente: domínio que apontará pra VM
4. Provisionar a stack (script de setup)
5. Configurar deploy (rsync + git pull + migrate)
6. Apontar DNS
7. Smoke test e entregar URL pro cliente

---

> **Importante para os agentes/Kiro**: Antes de executar comandos remotos contra a VM, **sempre confirmar com o Bruno**. SSH em produção é "high-risk" — qualquer ação destrutiva ou de mudança de config precisa aprovação explícita.
