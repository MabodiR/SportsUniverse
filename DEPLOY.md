Deployment pipeline
===================

This repository includes two GitHub Actions workflows:

- `.github/workflows/ci.yml` — runs on push/pull_request: installs PHP + Node deps, builds assets, runs migrations on an SQLite DB, and runs `phpunit`.
- `.github/workflows/deploy.yml` — runs on push to `main`: installs deps, builds assets, and uploads site files to an FTP server using secrets.

Required repository secrets (set in GitHub Settings → Secrets):

- `FTP_HOST` — your xneelo FTP host (e.g. ftp.sportsuniverse.co.za)
- `FTP_USERNAME` — FTP user
- `FTP_PASSWORD` — FTP password
- `FTP_SERVER_DIR` — path on the server to deploy into (example: `/public_html` or `/`)

Notes and recommendations
- For shared hosting it's recommended to build assets and vendor locally (Actions already runs `composer install` and `npm run build`) and then upload the built files.
- Ensure the server's document root points to the `public/` directory. If you cannot change that, use an `.htaccess` in the server root to redirect to `public/`.
- The CI workflow uses SQLite to run tests quickly; if your repository tests require MySQL, adapt `ci.yml` to use a service container and set DB credentials.
- FFmpeg and background queue workers are unlikely to be available on shared hosting — prefer using a VPS or external services for video processing and queues.

Manual deploy (example using `lftp`):

```bash
# build locally
npm ci && npm run build
composer install --no-dev --optimize-autoloader

# upload with lftp (example)
lftp -u "$FTP_USER","$FTP_PASS" $FTP_HOST <<'LFTP'
mirror -R --delete --exclude .git --exclude node_modules --exclude tests ./ /path/on/server/
quit
LFTP
```

If you'd like, I can:

- Add an SSH/rsync deploy option instead of FTP.
- Create a small `scripts/deploy.sh` helper and a sample `.env.production` template.
- Walk through adding the required GitHub secrets and testing the pipeline.
