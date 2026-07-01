# Database SQL (staging reset / migrate)

Kopier av `bifrost-shared/database/migrations` og `seeds` for deploy til ProISP (FTP inkluderer `database/`).

Ved endring i bifrost-shared: kopier på nytt hit eller sett `MIGRATIONS_PATH` / `SEEDS_PATH` i `.env.staging`.

Brukes av `POST /deploy/reset-staging` og `php bin/console migrate`.
