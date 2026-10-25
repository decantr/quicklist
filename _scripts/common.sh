#!/bin/sh
# shared helpers for setup.sh and update.sh

_curdate() {
	date -u "+%F %T"
}

_info() {
	echo ":: $(_curdate) : $1"
}

_error() {
	echo "!! $(_curdate) : $1"
	exit 1
}

# root check ==================================================================
SUDO=""
if [ "$(id -u)" -ne 0 ]; then
	_info "Asking for sudo"
	sudo -v || _error "Sudo failed"
	SUDO="sudo"
fi

# init system =================================================================
[ -f /etc/os-release ] || _error "Unknown Distro"
. /etc/os-release

case $ID in
"alpine") INIT="openrc" ;;
"arch") INIT="systemd" ;;
"ubuntu") INIT="systemd" ;;
*) _error "Unknown Distro" ;;
esac

command -v curl >/dev/null || _error "curl is not installed"

case $(uname -m) in
"x86_64") ARCH="amd64" ;;
"aarch64") ARCH="arm64" ;;
*) _error "Unknown architecture $(uname -m)" ;;
esac

RELEASES="https://github.com/decantr/quicklist/releases/latest/download"

# returns 1 when the installed binary is already the latest release
_install() {
	_info "Downloading latest release for $ARCH"
	tmp=$(mktemp -d) || _error "mktemp failed"
	trap 'rm -rf "$tmp"' EXIT
	curl -fsSL -o "$tmp/quicklist-linux-$ARCH" "$RELEASES/quicklist-linux-$ARCH" || _error "Download failed"
	curl -fsSL -o "$tmp/SHA256SUMS" "$RELEASES/SHA256SUMS" || _error "Checksum download failed"
	(cd "$tmp" && grep " quicklist-linux-$ARCH$" SHA256SUMS > CHECK && sha256sum -c CHECK >/dev/null) || _error "Checksum mismatch"

	if cmp -s "$tmp/quicklist-linux-$ARCH" /usr/local/bin/quicklist; then
		_info "Already up to date"
		return 1
	fi
	$SUDO install -m 755 "$tmp/quicklist-linux-$ARCH" /usr/local/bin/quicklist
}

_restart() {
	_info "Restarting quicklist"
	case $INIT in
	"openrc") $SUDO rc-service quicklist restart ;;
	"systemd") $SUDO systemctl restart quicklist ;;
	esac
}
