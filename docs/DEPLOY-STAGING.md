# UNIGES — Staging deploy runbook (`titulacion.carlosgardea.cloud`)

> **Human-gated.** Per the OMNIBUS standards, Docker deploys to the VPS are run by
> a human (`deployer_vps`), not by the agent. This runbook is the exact sequence;
> Carlos (or an explicitly-authorised session) runs it. The agent prepared and
> verified the build artifacts locally; it did **not** touch the VPS.

## What ships
Branch `feature/graduation-domain` (merge to `develop` first if you gate staging on
`develop`). UNIGES is feature-complete: 9-state pipeline, auth + RBAC + throttle,
demo mode, dashboards, reporting, catalog CRUD. Deferred: PDF/Word/Excel doc
generation, CI.

## Prerequisites to verify ON the VPS (the agent cannot check these)
1. **DNS** — `titulacion.carlosgardea.cloud` → `195.35.37.195` (A record), proxied
   or direct, propagated. `dig +short titulacion.carlosgardea.cloud` must return the IP.
2. **Traefik v3** running on the VPS with the external `web` network and a
   `letsencrypt` certresolver (same as the live CMS staging).
3. The external `global_data_private` Docker network exists (the prod compose joins it).
4. A staging `.env` on the VPS (NOT in git) with at minimum:
   - `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://titulacion.carlosgardea.cloud`
   - `APP_DOMAIN=titulacion.carlosgardea.cloud`  ← drives the Traefik host rule (see below)
   - a fresh `APP_KEY` (`php artisan key:generate --show`)
   - DB/Valkey/Reverb creds; `BROADCAST_CONNECTION=reverb` + the Reverb app keys
   - `SESSION_SECURE_COOKIE=true`

## One-line change to make the compose domain-parametric (recommended)
`docker-compose.prod.yml` hardcodes `Host(\`titulacion.carlosgardea.com\`)`. To reuse it
for staging, replace the literal host in the two router rules + the headers
middleware name with `${APP_DOMAIN}` and the router keys with `${APP_SLUG:-uniges}`,
then set `APP_DOMAIN` per environment. (Alternatively keep a separate
`docker-compose.staging.yml`; the parametric route is less drift.) Verify locally:
`APP_DOMAIN=titulacion.carlosgardea.cloud docker compose -f docker-compose.prod.yml config`
shows the `.cloud` host in the rendered labels.

## Deploy sequence (run as `deployer_vps`)
```bash
# 0. Build the image locally (or in CI) — multi-stage: assets (node) + vendor + php:8.5-fpm
docker build --target production -t omnibus-uniges:staging .

# 1. Ship the image to the VPS (no registry): save | ssh | load
docker save omnibus-uniges:staging | gzip \
  | ssh deployer_vps@195.35.37.195 'gunzip | docker load'

# 2. On the VPS, in /opt/omnibus/projects/uniges/ (compose + staging .env in place):
ssh deployer_vps@195.35.37.195
cd /opt/omnibus/projects/uniges
docker compose -f docker-compose.prod.yml --env-file .env up -d postgres valkey
docker compose -f docker-compose.prod.yml --env-file .env run --rm app php artisan migrate --force
docker compose -f docker-compose.prod.yml --env-file .env run --rm app php artisan db:seed --class=DemoBaselineSeeder --force
docker compose -f docker-compose.prod.yml --env-file .env run --rm app php artisan optimize
docker compose -f docker-compose.prod.yml --env-file .env up -d        # app + reverb + horizon + scheduler

# 3. Verify
curl -fsS https://titulacion.carlosgardea.cloud/up        # health endpoint → 200
```

## Post-deploy checks
- `/up` returns 200 over HTTPS (Let's Encrypt cert issued by Traefik).
- Login works for a seeded demo persona; the demo banner + 30-min TTL behave.
- Reverb: a status change broadcasts (graduation status channel) in real time.
- `php artisan horizon:status` (queue worker) is `running`; the scheduler logs
  `demo:cleanup` every 15 min.
- **PII:** the demo baseline seeder uses fictional data only — confirm no real
  names/CURPs/RFCs reached the staging DB.

## Rollback
`docker compose -f docker-compose.prod.yml down` then redeploy the previous image
tag. Migrations on this branch are reversible; `php artisan migrate:rollback` if a
schema change must be undone.
