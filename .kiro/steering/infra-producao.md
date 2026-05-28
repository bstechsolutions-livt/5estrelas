---
inclusion: always
---

# Infra de Produção (steering)

Lê **sempre** antes de tomar decisões que envolvam servidor, deploy, infra ou comandos remotos.

## Acesso ao servidor do cliente

- **VM**: G5E-SVM080 (Ubuntu 24.04.4 LTS)
- **Recursos**: 6 vCPUs · 6 GB RAM · 4 GB Swap · 300 GB SSD (`/`) · 1 TB HDD (`/mnt/dados`)
- **IP interno**: 192.168.254.80 (porta 22, SSH)
- **Acesso**: exclusivo via VPN do cliente
- **Usuários sudo**: `easytech`, `matheusxavier`

### VPN

- Arquivo: `infra/vpn/acesso-vpn-easytech.ovpn` (commitado, repo é privado)
- User: `vpn.easytech` / Pwd: `Xm7$kP2#vL`
- Conecta via OpenVPN: `sudo openvpn --config infra/vpn/acesso-vpn-easytech.ovpn --auth-user-pass`

### SSH

- Comando: `ssh easytech@192.168.254.80`
- User: `easytech` / Pwd: `UE2emkL1uANFetmQl3W1EAgc`
- Tem sudo/root
- Senha rotacionada em 28/05/2026 (original do e-mail invalidada)

### Detalhes adicionais

- Servidor físico: G5E-DF-SRV-001
- Hypervisor: Microsoft Hyper-V

### E-mail do cliente (avisos/notificações)

- E-mail: `modernizacao.avisos@grupo5estrelas.com.br`
- Senha: `5Estrelas@2026`
- Host: Outlook (Microsoft 365)
- Uso: e-mail de avisos do sistema, notificações, comunicações automáticas
- Também usado como conta Shorebird (code push)

### Shorebird (code push)

- Conta: `modernizacao.avisos@grupo5estrelas.com.br` (login via Microsoft)
- App ID: `bb9ea030-36c7-4eb8-a520-a57792b90b18`
- Plano: free
- CLI: `shorebird` (instalado em `~/.shorebird/bin/shorebird`)

## Regras de operação

- **Domínio de produção**: `intranet.grupo5estrelas.com.br`
- **Acesso temporário**: via VPN no IP interno `192.168.254.80` (até liberarem portas 80/443 externas)
- **Antes de qualquer ação contra a VM, confirmar com Bruno**. Servidor de produção = high-risk.
- Mudanças destrutivas (drop, rm -rf, restart de serviço, fechamento de porta) precisam aprovação explícita.
- Sempre testar localmente antes; rodar em produção apenas o que já passou em dev.
- Documentar tudo que for alterado em `docs/infra-acesso-producao.md` ou em arquivo dedicado.

## Regras de deploy

- **NUNCA copiar e colar código manualmente**. Deploy é sempre via Git (clone/pull).
- Servidor conecta ao repo GitHub via SSH key ou HTTPS token.
- Fluxo de trabalho:
  1. Desenvolve em branch (`feature/xxx`, `fix/xxx`)
  2. Abre PR pra `main`
  3. Merge na `main`
  4. Tag de versão (`v1.0.0`, `v1.0.1`, etc)
  5. Deploy em produção: `git pull` da tag na VM
- Nunca fazer `git push` direto na main. Sempre branch → PR → merge.
- Configurar deploy key (SSH) no repo GitHub pra VM fazer pull sem senha.

## Regras gerais (anotar sempre)

- **Toda regra importante que o Bruno falar, anotar aqui no steering** pra nunca esquecer.
- **Nunca expor senhas em mensagens de grupo/chat**. Senhas ficam só nos docs internos (repo privado).
- **Toda decisão de infra/deploy/arquitetura fica documentada** em steering ou docs.
- **Toda credencial nova que chegar, anotar imediatamente** no steering + docs.

## Documento de referência

Detalhes completos em [`docs/infra-acesso-producao.md`](../../docs/infra-acesso-producao.md).
