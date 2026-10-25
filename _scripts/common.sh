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

command -v mise >/dev/null || _error "mise is not installed"

_build() {
	_info "Building"
	mise trust --quiet
	mise install
	mise run build || _error "Build failed"
	$SUDO install -m 755 bin/quicklist /usr/local/bin/quicklist
}

_restart() {
	_info "Restarting quicklist"
	case $INIT in
	"openrc") $SUDO rc-service quicklist restart ;;
	"systemd") $SUDO systemctl restart quicklist ;;
	esac
}
