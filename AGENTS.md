# QuickList

Single-user shopping list app. One static Go binary, served only over Tailscale, so there is no auth.

## Stack

- Go 1.26 (pinned in `mise.toml`), stdlib `net/http` router, `html/template`
- `modernc.org/sqlite` (pure Go, `CGO_ENABLED=0`), hand-written `database/sql` queries
- htmx and Alpine.js, vendored in `static/`; hand-rolled CSS in `static/app.css`
- `views/` and `static/` are embedded into the binary; restart the app to see changes

## Rules

- Do not change dependencies without approval
- Every change must be tested: add or update a test in `app_test.go`, then run `mise run test`
- Run `mise run fmt` after Go changes
- Keep the database compatible with the original Laravel schema (`testdata/laravel_schema.sql`):
  UTC `Y-m-d H:i:s` timestamps, `shoplists.date` as `Y-m-d 00:00:00`, new lists owned by user 1
- Changes go through POST forms with hx-boost, then redirect back with a flash cookie

## Tasks

- `mise run run`: run against `database.sqlite`
- `mise run test`: vet and test
- `mise run build` / `mise run build-linux`: build binaries into `bin/`
- `mise run setup` / `mise run update`: install or update on the server
