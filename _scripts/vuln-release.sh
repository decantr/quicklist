#!/bin/sh
# scan the latest published release; it can lag behind master until a new tag is pushed

_info() {
	echo ":: $(date -u "+%F %T") : $1"
}

_error() {
	echo "!! $(date -u "+%F %T") : $1"
	exit 1
}

REPO="https://github.com/decantr/quicklist"
IMAGE="ghcr.io/decantr/quicklist"

[ -n "$GOVULNCHECK" ] || _error "GOVULNCHECK is not set, run with: mise run vuln-release"
command -v curl >/dev/null || _error "curl is not installed"
command -v docker >/dev/null || _error "docker is not installed"

tmp=$(mktemp -d) || _error "mktemp failed"
trap 'rm -rf "$tmp"' EXIT

latest_url=$(curl -fsSLo /dev/null -w '%{url_effective}' "$REPO/releases/latest") || _error "Release lookup failed"
tag=${latest_url##*/}
case $tag in
v*) ;;
*) _error "No release found at $latest_url" ;;
esac
_info "Latest release is $tag"

_info "Downloading release binary"
curl -fsSL -o "$tmp/release" "$REPO/releases/download/$tag/quicklist-linux-amd64" || _error "Download failed"

_info "Extracting binary from $IMAGE:${tag#v}"
container=$(docker create --platform linux/amd64 "$IMAGE:${tag#v}") || _error "Image pull failed"
docker cp "$container:/quicklist" "$tmp/image" >/dev/null
copied=$?
docker rm "$container" >/dev/null
[ "$copied" -eq 0 ] || _error "Copy from image failed"

failed=0
for binary in release image; do
	_info "Scanning $binary binary ($(go version "$tmp/$binary" | cut -d' ' -f2))"
	go run "$GOVULNCHECK" -mode binary "$tmp/$binary" || failed=1
done

[ "$failed" -eq 0 ] || _error "Release $tag has known vulnerabilities, push a new tag to rebuild it"
_info "Release $tag has no known vulnerabilities"
