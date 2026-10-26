# AGENTS.md: php-api-foundation

Shared rules for every AI agent on this repo, in Claude Code (through CLAUDE.md) and Google Antigravity (which reads this file directly). Keep it under ~150 lines. It is read at the start of every run. Write `TBD` rather than guessing.

## Project
- What it is: Dependency-light PHP 8.3 REST API foundation with JWT auth, PDO migrations, an OpenAPI contract, and deployment pipelines for FTP/SFTP shared hosting and for AWS or Azure through OIDC.
- Users: Developers building lightweight, production-minded REST APIs for shared hosting or containerized cloud deployments.
- Related repos and contracts: API contract at `openapi/openapi.yaml`; deployment references for AWS and Azure in `deploy/`.

## Stack packs
- php-api
- sql-db
- github-actions

## Commands
- Install: `composer install`
- Run locally: `docker compose up` → URL: http://localhost:8080 (`GET /health/ready`), or `php -S localhost:8080 -t public` on SQLite
- Verify fast: `bash scripts/verify.sh fast` (Windows PowerShell: `./scripts/verify.ps1 fast`)
- Verify full: `bash scripts/verify.sh full`
- Disposable database: `db` service in `docker-compose.yml` (MySQL 8, database `app_test`, connection in env `TEST_DB_DSN`). Fast tests use in-memory SQLite. Agents touch no other database.

## Git
- Base branch: main
- Branches: `feat/<slug>`, `fix/<slug>`, `chore/<slug>`
- PR: manual

## Conventions
- Success bodies are `{data, meta}`, as the `php-api` pack says. Errors are RFC 9457 `application/problem+json`, which overrides the pack's default `{error:{code,message,details}}` envelope. `openapi/openapi.yaml` is the source of truth for both.
- Layers under `src/`: Http (controllers, middleware), Domain, Infrastructure, Support. Controllers hold no SQL. All SQL goes through repositories with prepared statements.
- Migrations live in `db/migrations` (Phinx), run forward and back, and are tested on SQLite and MySQL. A committed migration is never edited. Add a new one.
- Secrets arrive only through `.env`, read by the typed config. Nothing secret appears in `config/` defaults, tests or deploy scripts. Deploy scripts never echo `FTP_*`, `APP_ENV_FILE` or `DEPLOY_TOKEN`.
- This is a public MIT repository written clean-room: no code, assets, names or data from client or employer projects. Sample data is synthetic.
- The standards and this repository's requirements are in `.pipeline/SPEC.md` (local, never committed). Requirement IDs appear in AC lines as `AC-n [ID]` and in test names.
- Docs describe only what exists and was run. What an agent cannot run (push, live cloud, signing, a Unity Editor, GitHub settings) is a `[USER]` item in `docs/release-checklist.md` and is never described as tested.
- GitHub Actions: top-level `permissions: {}` plus minimal per-job permissions; `timeout-minutes` and `concurrency` on every workflow; actions pinned to a full SHA with the version in a comment; explicit `shell:`; LF line endings; no `${{ github.event.* }}` inside `run:` (use `env:`); OIDC for cloud login. Check with `actionlint`.
- Commits are small and follow `<type>(<scope>): <goal> [step N]`. Dates are real and history is never rewritten.
- Dependencies need MIT-compatible licences. Record stack choices and open-decision outcomes in `docs/adr/`.

## Design
- Design system / tokens: None (API foundation, `--no-design`)
- Direction: Headless REST API; RFC 9457 problem+json error formatting and `{data, meta}` JSON responses.

## Safety: agents never do these. They prepare the command and hand it over.
- Deploy, publish, release, upload, sign or notarize builds
- `terraform` / `terragrunt` apply, destroy, import or any state command
- Any write to a database other than the disposable one above
- Change real secrets, `.env` values, CI/CD or production configuration
- Push to the base branch, force-push, rewrite published history
- Deploy scripts and workflows are tested but never run against a real host by agents.
- Safety additions: never push, tag, create releases, change GitHub settings or run `git filter-repo`; write the command into `docs/release-checklist.md` instead. Never print secret values: show only file, line, rule and the last four characters.

## Known baseline failures
None.

## Lessons
<!-- One line each, added only with the user's approval after a review found a repeatable mistake.
     Format: - [area] Do X, not Y, because Z. -->
