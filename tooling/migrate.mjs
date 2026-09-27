#!/usr/bin/env node
/**
 * Adivius Content migrations — this repo's entry point to the constellation runner.
 *
 *   node tooling/migrate.mjs status
 *   node tooling/migrate.mjs up            # dry run: lists what would apply
 *   node tooling/migrate.mjs up --yes      # applies, records each file in the ledger
 *   node tooling/migrate.mjs query q.sql   # ad-hoc read against the live database
 *
 * Why tooling/ and not scripts/: in this repo scripts/ is the browser
 * JavaScript, deployed by vercel.json as public static files. This folder is
 * matched by no build, so nothing here is uploaded or served.
 *
 * Rules (docs/migration-ledger.md at the constellation root):
 *   - Migrations live in ../docs/migrations (Content Creator/docs/migrations),
 *     numbered NNN_name.sql. Apply BEFORE pushing code that needs the new
 *     columns — a push to main is production within a minute, with no staging,
 *     and this exact ordering mistake took production down twice in July 2026.
 *   - Never edit an applied migration; the checksum guard refuses to run.
 *   - Never apply SQL with an ad-hoc script: the ledger then no longer knows
 *     what is applied.
 *   - Verify DDL afterwards with an information_schema / pg_catalog query.
 *
 * The token comes from ../.env (Content Creator/.env, SUPABASE_ACCESS_TOKEN).
 * The engine and the migrations sit beside this repo in the constellation
 * checkout, not inside it — a bare clone of the repo cannot migrate, and says so.
 */

import { existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const CORE = resolve(REPO, '../../scripts/migrate-core.mjs')
const MIGRATIONS = resolve(REPO, '../docs/migrations')

if (!existsSync(CORE) || !existsSync(MIGRATIONS)) {
  console.error(
    'This runner needs the Adivius constellation checkout around the repo:\n' +
      `  engine:     ${CORE}\n` +
      `  migrations: ${MIGRATIONS}\n` +
      'Run it from the synced workspace (C:\\Dev\\Adivius Agency), not from a bare clone.',
  )
  process.exit(1)
}

const { createProject, runCli } = await import(pathToFileURL(CORE).href)

await runCli(process.argv.slice(2), {
  content: createProject({
    label: 'content',
    ref: 'ptonwhknnjtudwvwwdnn',
    envFile: resolve(REPO, '../.env'),
    migrationsDir: MIGRATIONS,
  }),
})
