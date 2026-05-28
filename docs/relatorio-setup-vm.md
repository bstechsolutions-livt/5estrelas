# Relatório de Setup — VM G5E-SVM080
## Plataforma 5 Estrelas

**Data**: 28/05/2026  
**Responsável**: BS Tech Solutions  
**Ambiente**: G5E-SVM080 (Ubuntu 24.04.4 LTS)

---

## 1. Conectividade

| Item | Status |
|------|--------|
| VPN (OpenVPN) | ✅ Conectando normalmente |
| SSH (porta 22) | ✅ Acesso funcionando |
| Ping à VM (192.168.254.80) | ✅ ~29ms |

---

## 2. Stack Instalada

Todos os componentes necessários para a Plataforma 5 Estrelas foram instalados e estão ativos:

| Componente | Versão | Status |
|-----------|--------|--------|
| PHP | 8.3.31 (FPM + CLI) | ✅ Ativo no boot |
| Extensões PHP | pgsql, redis, mbstring, xml, zip, curl, gd, intl, bcmath | ✅ |
| Composer | 2.10.0 | ✅ |
| PostgreSQL | 16.14 | ✅ Ativo no boot |
| Redis | 7.0.15 | ✅ Ativo no boot |
| Node.js | 20.20.2 | ✅ |
| npm | 10.8.2 | ✅ |
| Nginx | 1.24.0 | ✅ Ativo no boot |
| Supervisor | 4.2.5 | ✅ Ativo no boot |
| Certbot (SSL) | 2.9.0 | ✅ |

---

## 3. Organização de Diretórios

A distribuição de diretórios segue a separação física dos discos da VM para otimizar performance e capacidade:

### SSD (300 GB — montado em `/`)
Usado para dados que exigem **leitura/escrita rápida**:

| Diretório | Conteúdo |
|-----------|----------|
| `/var/www/5estrelas` | Código-fonte da aplicação (Laravel + Vue) |
| `/var/lib/postgresql` | Dados do PostgreSQL (banco precisa de I/O rápido) |

### HDD (1 TB — montado em `/mnt/dados`)
Usado para dados que **crescem em volume** e não exigem alta velocidade:

| Diretório | Conteúdo |
|-----------|----------|
| `/mnt/dados/storage` | Uploads, fotos, evidências, anexos |
| `/mnt/dados/backups` | Backups automáticos do banco de dados |

---

## 4. Banco de Dados

- **Banco criado**: `estrelas`
- **Usuário**: `estrelas`
- **Acesso**: apenas local (127.0.0.1)

---

## 5. Pendência para Continuidade

Para prosseguir com o deploy da aplicação e configuração do acesso externo, precisamos que o DNS do domínio definido seja apontado para o IP externo da VM:

> **Domínio**: `intranet.grupo5estrelas.com.br`
>
> Solicitamos o apontamento do registro DNS (tipo A) para o IP público/externo da VM.

Com o DNS propagado, configuraremos:
- Virtual host no Nginx
- Certificado SSL (Let's Encrypt) via Certbot
- Deploy completo da aplicação
- Acesso externo funcional

---

## 6. Próximos Passos (após definição do domínio)

1. Apontar DNS do domínio para o IP público da VM (ou configurar proxy reverso)
2. Configurar Nginx com virtual host + SSL
3. Deploy do código da aplicação
4. Configurar Supervisor (queue worker + WebSocket)
5. Configurar Cron (backups automáticos + scheduler)
6. Smoke test e liberação do acesso

---

*BS Tech Solutions — Maio/2026*
