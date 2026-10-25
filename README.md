# QuickList

QuickList is a small shopping list app. It keeps categorised lists, a product catalogue, and a dashboard
for the latest list, with a copy-to-clipboard text version for sharing. Pages already visited stay readable offline.

It is a single static Go binary with an SQLite database, meant to run behind `tailscale serve`.

## Development

Tools are managed with [mise](https://mise.jdx.dev).

- `mise run run`: run the app on <http://127.0.0.1:8080> against `database.sqlite`
- `mise run test`: run vet and the tests
- `mise run fmt`: format the code
- `mise run build`: build `bin/quicklist`

To open over a tailnet you can use something like:
`tailscale serve --bg --https=8443 http://127.0.0.1:8080`

## Configuration

| Flag    | Environment      | Default          |
|---------|------------------|------------------|
| `-addr` | `QUICKLIST_ADDR` | `127.0.0.1:8080` |
| `-db`   | `QUICKLIST_DB`   | `database.sqlite`|

`quicklist -healthcheck` exits 0 when the server at `-addr` answers on `/up`.

## Migrating from the Laravel version

The schema is new, so the old Laravel database is copied into a fresh file. The old file is not changed.

```sh
sh ./_scripts/migrate-db.sh laravel.sqlite database.sqlite
```

## Deployment

Tag a version to publish a release with static Linux binaries (amd64, arm64):

```sh
git tag v1.0.0 && git push origin v1.0.0
```

On the server (Alpine with OpenRC, Arch or Ubuntu with systemd), from a clone of this repo:

```sh
sh ./_scripts/setup.sh              # fresh install
sh ./_scripts/setup.sh old.sqlite   # install and migrate in the old Laravel database
sh ./_scripts/update.sh             # install the latest release and restart
tailscale serve --bg http://127.0.0.1:8080
```

The server needs `curl`, plus `sqlite3` when migrating a database. The database lives at
`/var/lib/quicklist/database.sqlite`.

### Docker

Each release also publishes `ghcr.io/decantr/quicklist` (amd64, arm64). This example keeps the database in a
volume and uses [DockTail](https://docktail.org) labels to serve it over HTTPS on the tailnet:

```yaml
name: quicklist

services:
  quicklist:
    image: ghcr.io/decantr/quicklist:latest
    restart: unless-stopped
    environment:
      TZ: Europe/London
    volumes:
      - data:/data
    labels:
      - docktail.service.enable=true
      - docktail.service.name=quicklist
      - docktail.service.port=8080
      - docktail.service.service-port=443

volumes:
  data:
```

Set `TZ` so new lists get the local date. Without DockTail, publish the port instead (`ports: ["127.0.0.1:8080:8080"]`).
