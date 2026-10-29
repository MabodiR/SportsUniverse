# SportsUniverse xneelo GitHub deployment

Production deploys are performed by `.github/workflows/deploy.yml` on a dedicated self-hosted GitHub Actions runner installed on the xneelo instance.

## Required runner

- Repository: `MabodiR/SportsUniverse`
- Runner name: `xneelo-production`
- Custom label: `xneelo-production`
- Linux user: `ubuntu`
- Working directory: `/opt/actions-runner`
- Server hostname: `sportuniverse-production`

In GitHub, open **Settings → Actions → Runners → New self-hosted runner**, choose Linux x64, and follow the displayed commands on the xneelo web console. During configuration use:

```text
Runner group: Default
Runner name: xneelo-production
Additional label: xneelo-production
Work folder: _work
```

Install it as a service after configuration:

```bash
cd /opt/actions-runner
sudo ./svc.sh install ubuntu
sudo ./svc.sh start
sudo ./svc.sh status
```

The runner should show **Idle** in GitHub before pushing to `main`.

## Server sudo requirements

The `ubuntu` runner user must be able to run the existing production administration commands with `sudo`. Do not run the GitHub runner itself as root.

## Deployment safety

Every release:

1. Builds and tests in the runner workspace using a disposable SQLite database.
2. Records the production user count.
3. Creates a PostgreSQL custom-format backup in `/var/backups/sportuniverse`.
4. Preserves `.env`, `storage`, media, cache runtime directories and the runner installation.
5. Runs additive Laravel migrations and idempotent production seeders.
6. Fails if the production user count decreases.
7. Restarts queue, Reverb and PHP services.
8. Verifies `/up`, `/`, and `/feed` locally before reporting success.

The normal deployment workflow never runs `migrate:fresh`, `db:wipe`, `dropdb`, or production test-user seeders.

## TLS

TLS is managed separately by `.github/workflows/ssl.yml`. Run it only after the domain A records point to `154.65.108.156` and HTTP port 80 is publicly reachable.
