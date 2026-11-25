# Time Tracker

A maintained fork of [Anuko Time Tracker](https://github.com/anuko/timetracker) —
an open source, web-based time tracking application written in PHP. It lets you
track the time employees or colleagues spend on projects and tasks. It runs on a
web server (Apache) with a database (MySQL/MariaDB).

> **This is a modified version of Anuko Time Tracker.** The original work is
> © Anuko International Ltd. and remains so; this fork carries continued
> maintenance and new features. See [License](#license) below.

## Why this fork
Development of the original project stopped: in late 2023 Anuko's owner and lead
developer became terminally ill and the company began winding down. Upstream is
frozen at `1.22.22.5817` (the last commit, November 2023).

This fork picks maintenance back up — keeping the app running on modern PHP,
hardening it, and adding integrations — while preserving Anuko's copyright and
the SSPL license.

## What's different from upstream
- **PHP 8.5 support** — runs on the current PHP (upstream targeted PHP 7).
- **Security hardening** — modern password hashing (bcrypt) with transparent
  rehash on login, login-attempt throttling, and a hardened Apache config
  (WEB-INF/.git/build files denied, security headers, `expose_php off`).
- **REST API + MCP endpoint** — a token-authenticated REST API for time entries,
  exposed as a per-group plugin, with an MCP endpoint for AI agent integration.
- **OIDC single sign-on** — an OpenID Connect auth module (`AUTH_MODULE = oidc`)
  for providers such as Keycloak, with an admin break-glass fallback to local
  (db) login. See `WEB-INF/config.php.dist` for the full parameter reference.
- **Rebranding support** — the product name (`APP_NAME`) and logo are
  configurable per deployment; the dead upstream help/forum/doc links have been
  removed. Anuko's copyright notice is retained (see License).

## Versioning
The fork uses standard **SemVer** (`MAJOR.MINOR.PATCH`), set as `APP_VERSION` in
`initialize.php`. Upstream's old 4-part scheme (`1.22.22.5817`, where the 4th
part was an SVN build counter) was retired — the fork owns `1.23.0` and up.
Docker image tags equal `APP_VERSION`.

## Running it
Production-style stack:
```
docker compose up -d --build
```
Local development (live-render bind mount, OIDC against a real provider via a
`localhost` redirect):
```
cp WEB-INF/config.php.dist WEB-INF/config.php   # then edit for your setup
docker compose -f docker-compose.local.yaml up -d --build
```
The app is served on `http://localhost:8080`. Configuration lives in
`WEB-INF/config.php` (copy it from `WEB-INF/config.php.dist`). Secrets (DB
password, OIDC client secret) are read from environment variables — never commit
them.

Images are published as `sirmysterion/anuko_timetracker` (app) and
`sirmysterion/anuko_database` (database).

## Branding
A deployment may set its own product name and logo:
- `APP_NAME` — product name shown in the footer version line and used as the
  logo alt text.
- `LOGO_LINK` — optional URL to wrap the header logo in.
- `SUPPORT_LINK` / `SUPPORT_TEXT` — optional footer support line.

Per the SSPL license, the **Anuko copyright notice in the footer is retained**
and is not configurable — rebrand the product, not the attribution.

## License
Time Tracker is distributed under the **Server Side Public License (SSPL),
Version 1** — see [`license.txt`](license.txt). This is a strong copyleft license
(an AGPL derivative with an additional service clause). In short:

- You may run, modify, and self-host it.
- You must keep the existing copyright notices and the `license.txt` file.
- If you distribute a modified version (including publishing container images),
  the corresponding source must be available under the SSPL — this repository
  provides it.
- Offering Time Tracker *as a service to third parties* triggers SSPL §13
  (the whole service stack must then be released under the SSPL). Internal and
  personal use does not.

The original Anuko Time Tracker is © Anuko International Ltd.

## Maintenance

### SQL restore (into a running database container)
```
docker exec -i anuko-database sh -c 'mariadb -uroot -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' < timetrackerDUMP.sql
```

## Upstream & credits
- This fork: https://github.com/SirMysterion/timetracker
- Original project: https://github.com/anuko/timetracker (© Anuko International Ltd.)

With thanks to Nik Okuntseff and Anuko for the original Time Tracker.
