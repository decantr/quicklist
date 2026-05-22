#!/bin/sh
# single entry point for setting up this project

# helper functions ============================================================

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
if [ "$(id -u)" -ne 0 ]; then
	_info "Asking for sudo"

	if ! sudo -v; then
		_error "Sudo failed"
	fi
fi

# os dependencies =============================================================
if [ -f /etc/os-release ]; then
	. /etc/os-release
else
	_error "Unknown Distro"
fi

case $ID in
"alpine") sh ./_scripts/install-alpine.sh ;;
"ubuntu")
	case $VERSION_ID in
	"26.04") sh ./_scripts/install-ubuntu-2604.sh ;;
	*) _error "Unknown ubuntu version" ;;
	esac
	;;
*) _error "Unknown Distro" ;;
esac

# file setup ==================================================================
cp -n .env.example .env
touch database/database.sqlite

# dependencies ================================================================
composer install --no-dev --optimize-autoloader --classmap-authoritative
deno install --minimum-dependency-age=P1W # --prod AFTER deno 2.8

# php setup ===================================================================
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# finish ======================================================================
_info 'Done'
exit 0
