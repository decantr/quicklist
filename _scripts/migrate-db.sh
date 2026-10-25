#!/bin/sh
# copy the data from the old Laravel database into a new QuickList database
# usage: migrate-db.sh <laravel database> <new database>

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

[ $# -eq 2 ] || _error "usage: migrate-db.sh <laravel database> <new database>"
OLD="$1"
NEW="$2"
INIT="$(dirname "$0")/../migrations/0001_init.sql"

command -v sqlite3 >/dev/null || _error "sqlite3 is not installed"
[ -f "$OLD" ] || _error "No database at $OLD"
[ -e "$NEW" ] && _error "$NEW already exists"
case "$OLD$INIT" in
*\'*) _error "Paths must not contain single quotes" ;;
esac

# later migrations run when the app opens the database
_info "Copying $OLD into $NEW"
if ! sqlite3 -bail "$NEW" <<SQL
PRAGMA foreign_keys = ON;
.read '$INIT'
ATTACH '$OLD' AS old;
BEGIN;
INSERT INTO products (id, name, size, size_type, category, created_at)
	SELECT id, name, size, size_type, category, COALESCE(created_at, CURRENT_TIMESTAMP) FROM old.products;
INSERT INTO shoplists (id, date, created_at)
	SELECT id, substr(date, 1, 10), COALESCE(created_at, CURRENT_TIMESTAMP) FROM old.shoplists;
INSERT INTO shoplist_items (id, shoplist_id, product_id, quantity)
	SELECT id, shoplist_id, product_id, quantity FROM old.product_shoplist ORDER BY id;
COMMIT;
SQL
then
	rm -f "$NEW"
	_error "Migration failed, $NEW removed"
fi

_info "Copied $(sqlite3 "$NEW" "SELECT (SELECT COUNT(*) FROM products) || ' products, ' || (SELECT COUNT(*) FROM shoplists) || ' lists, ' || (SELECT COUNT(*) FROM shoplist_items) || ' items'")"
_info 'Done'
exit 0
