# QuickList

Single-user shopping list app. One static Go binary, served only over Tailscale, so there is no auth.

## Stack

- Go 1.26 (pinned in `mise.toml`), stdlib `net/http` router, `html/template`
- `modernc.org/sqlite` (pure Go, `CGO_ENABLED=0`), hand-written `database/sql` queries
- htmx and Alpine.js, vendored in `static/`; hand-rolled CSS themes in `static/themes/*.css`, picked per device by the `theme` cookie (`default.css` is required)
- `views/` and `static/` are embedded into the binary; restart the app to see changes

## Rules

- Do not change dependencies without approval
- Every change must be tested: add or update a test in `app_test.go`, then run `mise run test`
- Run `mise run fmt` after Go changes
- Schema changes go in a new `migrations/NNNN_name.sql` that ends with `PRAGMA user_version = N;`;
  never edit an applied migration
- Dates are `YYYY-MM-DD` text, timestamps are UTC `YYYY-MM-DD HH:MM:SS` text
- `_scripts/migrate-db.sh` copies an old Laravel database into a new one
- Changes go through POST forms with hx-boost, then redirect back with a flash cookie

## Tasks

- `mise run run`: run against `database.sqlite`
- `mise run test`: vet and test
- `mise run build` / `mise run build-linux`: build binaries into `bin/`
- Release: push a `v*` tag; `.github/workflows/release.yml` publishes the binaries and the ghcr.io image
- Docker: `Dockerfile`, with a compose example in the README (exposed on the tailnet by DockTail labels); CI builds the image and checks its health
- Server: `_scripts/setup.sh` installs, `_scripts/update.sh` installs the latest release
