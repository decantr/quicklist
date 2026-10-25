#!/bin/sh
# single entry point for setting up this project
# usage: setup.sh [existing database to copy in]

. ./_scripts/common.sh

DATA_DIR="/var/lib/quicklist"
DATABASE="$DATA_DIR/database.sqlite"

# user ========================================================================
if ! id quicklist >/dev/null 2>&1; then
	_info "Creating user quicklist"
	case $INIT in
	"openrc") $SUDO adduser -S -D -H -h "$DATA_DIR" -s /sbin/nologin quicklist ;;
	"systemd") $SUDO useradd --system --home-dir "$DATA_DIR" --shell /usr/sbin/nologin quicklist ;;
	esac
fi

# database ====================================================================
$SUDO mkdir -p "$DATA_DIR"
if [ -n "$1" ] && [ ! -f "$DATABASE" ]; then
	_info "Copying database from $1"
	[ -f "$1" ] || _error "No database at $1"
	$SUDO sqlite3 "$1" ".backup '$DATABASE'" || _error "Database copy failed"
fi
$SUDO chown -R quicklist: "$DATA_DIR"

# binary ======================================================================
_install

# service =====================================================================
_info "Installing $INIT service"
case $INIT in
"openrc")
	$SUDO install -m 755 deploy/quicklist.openrc /etc/init.d/quicklist
	$SUDO rc-update add quicklist default
	;;
"systemd")
	$SUDO install -m 644 deploy/quicklist.service /etc/systemd/system/quicklist.service
	$SUDO systemctl daemon-reload
	$SUDO systemctl enable quicklist
	;;
esac
_restart

# finish ======================================================================
_info "Expose it on the tailnet with: tailscale serve --bg http://127.0.0.1:8080"
_info 'Done'
exit 0
