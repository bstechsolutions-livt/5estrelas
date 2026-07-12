#!/usr/bin/env node
/**
 * Resolve storageIds legados do Convex para URLs de download (batch).
 * Uso: node resolve_storage_urls.mjs '["id1","id2"]'
 * Requer CONVEX_DEPLOY_KEY no ambiente.
 */
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const ids = JSON.parse(process.argv[2] ?? '[]');
if (!Array.isArray(ids) || ids.length === 0) {
    console.log('{}');
    process.exit(0);
}

const legadoDir = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const quoted = ids.map((id) => JSON.stringify(id)).join(', ');
const query = `const ids = [${quoted}]; const out = {}; for (const id of ids) { out[id] = await ctx.storage.getUrl(id); } return out;`;

const raw = execSync(
    `npx convex run --inline-query ${JSON.stringify(query)}`,
    {
        cwd: legadoDir,
        env: process.env,
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 120_000,
    },
).toString();

process.stdout.write(raw.trim());
