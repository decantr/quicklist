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

## Deployment

Tag a version to publish a release with static Linux binaries (amd64, arm64):

```sh
git tag v1.0.0 && git push origin v1.0.0
```

On the server (Alpine with OpenRC, Arch or Ubuntu with systemd), from a clone of this repo:

```sh
sh ./_scripts/setup.sh              # fresh install
sh ./_scripts/setup.sh old.sqlite   # install and copy in an existing database
sh ./_scripts/update.sh             # install the latest release and restart
tailscale serve --bg http://127.0.0.1:8080
```

The server needs `curl`, plus `sqlite3` when copying in a database. The database lives at
`/var/lib/quicklist/database.sqlite`.
