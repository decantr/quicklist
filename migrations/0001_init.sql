CREATE TABLE products (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL CHECK (name <> ''),
	size INTEGER NOT NULL CHECK (size >= 0),
	size_type TEXT NOT NULL CHECK (size_type IN ('g', 'ml', 'count', 'pt')),
	category TEXT NOT NULL CHECK (category IN ('bakery', 'cupboard', 'dairy', 'fridge', 'frozen', 'meat', 'produce', 'other')),
	created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (name, size, size_type)
) STRICT;

CREATE TABLE shoplists (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	date TEXT NOT NULL CHECK (date IS date(date)),
	created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;

CREATE TABLE shoplist_items (
	id INTEGER PRIMARY KEY,
	shoplist_id INTEGER NOT NULL REFERENCES shoplists (id) ON DELETE CASCADE,
	product_id INTEGER NOT NULL REFERENCES products (id) ON DELETE CASCADE,
	quantity INTEGER NOT NULL CHECK (quantity >= 1),
	UNIQUE (shoplist_id, product_id)
) STRICT;

CREATE INDEX shoplist_items_product_id ON shoplist_items (product_id);

PRAGMA user_version = 1;
