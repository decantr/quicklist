#!/bin/sh
# single entry point for setting up this project


# helper functions ============================================================

curdate() {
	date -u "+%F %T"
}

info() {
	echo ":: $(curdate) : $1"
}

error() {
	echo "!! $(curdate) : $1";
	exit 1;
}

# os dependencies =============================================================

if [ -f /etc/os-release ]; then
	. /etc/os-release;
else
	error "Unknown Distro";
fi

case $ID in
"Alpine") sh ./_scripts/alpine.sh;;
*)  error "Unknown Distro" ;;
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
php storage:link

# finish ======================================================================
info 'Done'
exit 0;
