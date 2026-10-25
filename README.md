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

On Alpine (OpenRC), Arch or Ubuntu (systemd), with mise installed:

```sh
mise run setup                      # fresh install
sh ./_scripts/setup.sh old.sqlite   # install and copy in an existing database
mise run update                     # pull, rebuild and restart
tailscale serve --bg http://127.0.0.1:8080
```

The database lives at `/var/lib/quicklist/database.sqlite`.
