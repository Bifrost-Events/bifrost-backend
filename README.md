# bifrost-backend

Felles API, auth-integrasjon, tenant-håndtering og domenelogikk.

Bygget med samme MVC-mønster som `jaktfeltnamdalen` (se `bifrost-shared/reference/mvc-standard-from-jaktfeltnamdalen.md`).

## Lokal URL

| Miljø | URL |
|-------|-----|
| XAMPP Apache (anbefalt) | http://api.bifrost.local |
| PHP innebygd server | http://localhost:8082 |

## Avhengigheter

- PHP 8.1+
- Composer
- MySQL/MariaDB – **`jaktfeltkarusell_prod`** (delt med jaktfeltnamdalen) eller egen `bifrost`-DB for grønt felt
- Migreringer fra **`bifrost-shared/database/migrations`**

## Database

### Eksisterende database (anbefalt lokalt/prod)

Bruk `jaktfeltkarusell_prod`. Kjør **kun** additive `bifrost_*.sql`-migreringer:

```bash
php bin/console migrate
```

**Ta backup før første kjøring.**

### Lokal admin-bruker (auth)

Etter migrering på `jaktfeltkarusell_prod`, seed testbruker og roller:

```powershell
Get-Content C:\xampp\htdocs\bifrost\bifrost-shared\database\seeds\002_local_admin_user.sql | C:\xampp\mysql\bin\mysql.exe -u root jaktfeltkarusell_prod
```

- E-post: `admin@bifrost.local`
- Passord (kun lokal dev): `local-admin-change-me`
- Roller: `SystemAdmin` + `CupAdmin` for valgte tenants (se `auth-design.md`)

### Grønt felt (ren dev-DB)

Opprett database `bifrost`, deretter:

```bash
php bin/console migrate --greenfield
```

Deretter seed (se `bifrost-shared/database/seeds/README.md`):

```bash
# Greenfield
mysql -u root bifrost < ../bifrost-shared/database/seeds/001_local_tenants.sql
mysql -u root bifrost < ../bifrost-shared/database/seeds/001_local_greenfield_cup_data.sql
mysql -u root bifrost < ../bifrost-shared/database/seeds/002_local_admin_user.sql
```

**Kjør aldri** `001_initial_bifrost_schema.sql` manuelt på `jaktfeltkarusell_prod`.

## Oppsett

```bash
cd C:\xampp\htdocs\bifrost\bifrost-backend
composer install
copy .env.example .env
php bin/console migrate
```

Konfigurer Apache virtual host med document root `bifrost-backend/public` og host `api.bifrost.local`.

## Produksjon (api.bifrostevents.no)

Admin-ui snakker server-til-server med backend (`BACKEND_API_URL`). Backend ligger på **egen webroot** (`r1464762`), ikke under admin-ui.

| App | Webroot | Deploy-mappe | ProISP document root |
|-----|---------|--------------|----------------------|
| Backend | `r1464762` | `bifrostbackend/` | `.../r1464762/bifrostbackend/public/` |

ProISP tillater ikke bindestrek (`-`) i mappenavn på serveren.

### Deploy-Admin

| Miljø | GitHub Environment | `app_folder` | Trigger |
|-------|-------------------|--------------|---------|
| test | `test` | `bifrostbackend/` | `npm run release:deploy` (etter quality-godkjenning) |
| production | `production` | `bifrostbackend/` | `npm run release:deploy` (etter test-godkjenning) |

Legacy staging/test-miljøer (`staging_api_bifrostevents_no`, `test_api_bifrostevents_no`) brukes ikke i ny release-flyt.

Filområde prod: `api.bifrostevents.no` (r1464762). Se [Deploy-Admin docs](../../platformstandard/Deploy-Admin/docs/bifrost-deploy-environments.md) og [release/README.md](../bifrost-public-ui/release/README.md).

Kjør `npm run release:sync-secrets` fra bifrost-public-ui for å synke FTP til GitHub Environments `test` og `production`.

### ProISP

`api.bifrostevents.no` → rotmappe `.../r1464762/bifrostbackend/public/`.

### Staging Playwright-reset (historisk)

`POST /deploy/reset-staging` nullstiller staging-DB for automatiske tester (kun `APP_ENV=staging`, Bearer `STAGING_DEPLOY_SECRET`). Ny release-flyt bruker lokal quality i stedet – se [staging-playwright.md](../bifrost-public-ui/docs/staging-playwright.md).

Mal: [.env.staging.example](.env.staging.example)

### `.env` på server (`bifrostbackend/.env`)

```env
APP_ENV=production
APP_DEBUG=false
STORAGE_DRIVER=pdo
DB_DSN=mysql:host=<proisp-db-host>;dbname=jaktfeltkarusell_prod;charset=utf8mb4
DB_USER=<db-bruker>
DB_PASS=<db-passord>
```

Migreringer er allerede kjørt additive på `jaktfeltkarusell_prod` — du trenger normalt ikke `bin/console migrate` på webhotellet med mindre nye `bifrost_*.sql` er lagt til.

## Teste

```bash
composer serve
```

Eller med curl mot Apache:

```bash
curl http://api.bifrost.local/api/health
curl http://api.bifrost.local/api/tenants
curl http://api.bifrost.local/api/tenants/2
curl "http://api.bifrost.local/api/tenant/resolve?host=namdal.jaktfeltkarusell.local"
```

### Auth (session cookie)

```bash
curl -c cookies.txt -X POST http://api.bifrost.local/api/auth/login -H "Content-Type: application/json" -d "{\"email\":\"admin@bifrost.local\",\"password\":\"local-admin-change-me\"}"

curl -b cookies.txt http://api.bifrost.local/api/auth/me
curl -b cookies.txt -X POST http://api.bifrost.local/api/auth/logout
```

Login krever minst én admin-rolle: `SystemAdmin` eller `CupAdmin`. Uten dette får du HTTP 403.

### Admin API (krever session)

Alle `/api/admin/*` krever innlogget admin (`can_access_admin`). Admin-ui videresender backend session-cookie (`BIFROSTSESSID`) etter login.

```bash
curl -c cookies.txt -X POST http://api.bifrost.local/api/auth/login -H "Content-Type: application/json" -d "@login.json"

curl -b cookies.txt http://api.bifrost.local/api/admin/tenants
curl -b cookies.txt http://api.bifrost.local/api/admin/users
curl -b cookies.txt http://api.bifrost.local/api/admin/roles
curl -b cookies.txt http://api.bifrost.local/api/admin/tenants/2/domains
```

| Metode | Sti | Beskrivelse |
|--------|-----|-------------|
| GET | `/api/admin/tenants` | Liste tenants (CupAdmin: egne) |
| GET | `/api/admin/tenants/{id}` | Én tenant |
| POST | `/api/admin/tenants` | Opprett (kun SystemAdmin) |
| PUT | `/api/admin/tenants/{id}` | Oppdater |
| DELETE | `/api/admin/tenants/{id}` | Deaktiver (`status=inactive`) |
| GET | `/api/admin/tenants/{tenantId}/domains` | Domener per tenant |
| POST | `/api/admin/tenants/{tenantId}/domains` | Legg til domene |
| PUT | `/api/admin/domains/{id}` | Oppdater domene |
| DELETE | `/api/admin/domains/{id}` | Slett domene |
| GET | `/api/admin/users` | Liste brukere |
| GET | `/api/admin/users/{id}` | Én bruker |
| POST | `/api/admin/users` | Opprett bruker |
| PUT | `/api/admin/users/{id}` | Oppdater bruker |
| DELETE | `/api/admin/users/{id}` | Deaktiver (`is_active=0`) |
| GET | `/api/admin/roles` | Rolledefinisjoner |
| GET | `/api/admin/users/{id}/access` | Systemroller + cup-tilgang |
| POST | `/api/admin/users/{id}/system-roles` | Gi SystemAdmin (kun SystemAdmin) |
| DELETE | `/api/admin/users/{id}/system-roles/{role}` | Fjern SystemAdmin |
| POST | `/api/admin/users/{id}/tenant-access` | Gi CupAdmin for tenant |
| DELETE | `/api/admin/users/{id}/tenant-access/{accessId}` | Fjern cup-tilgang |

## Endpoints

| Metode | Sti | Beskrivelse |
|--------|-----|-------------|
| GET | `/api/health` | Health + database-status |
| GET | `/api/tenants` | Liste tenants med domener |
| GET | `/api/tenants/{id}` | Én tenant med domener |
| GET | `/api/tenant/resolve?host=...` | Slå opp tenant via domene |
| POST | `/api/auth/login` | Logg inn (JSON eller form); setter session-cookie |
| POST | `/api/auth/logout` | Logg ut (tømmer session) |
| GET | `/api/auth/me` | Innlogget bruker + roller (krever session) |

Alle svar er JSON. Beskyttede endepunkter returnerer 401 uten gyldig session.
