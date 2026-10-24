package main

import (
	"database/sql"
	"fmt"
	"net/url"

	_ "modernc.org/sqlite"
)

const owner_user_id = 1

// Table DDL is copied from the Laravel-migrated database so both apps can share one file.
var schema_statements = []string{
	`CREATE TABLE IF NOT EXISTS "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "two_factor_secret" text, "two_factor_recovery_codes" text, "two_factor_confirmed_at" datetime)`,
	`CREATE UNIQUE INDEX IF NOT EXISTS "users_email_unique" on "users" ("email")`,
	`CREATE TABLE IF NOT EXISTS "products" ("id" integer primary key autoincrement not null, "name" varchar not null, "size" integer not null, "size_type" varchar, "created_at" datetime, "updated_at" datetime, "category" varchar not null default ('produce'))`,
	`CREATE TABLE IF NOT EXISTS "shoplists" ("id" integer primary key autoincrement not null, "date" date not null, "created_at" datetime, "updated_at" datetime, "user_id" integer not null, foreign key("user_id") references "users"("id") on delete cascade)`,
	`CREATE TABLE IF NOT EXISTS "product_shoplist" ("id" integer primary key autoincrement not null, "shoplist_id" integer not null, "product_id" integer not null, "quantity" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("shoplist_id") references "shoplists"("id") on delete cascade, foreign key("product_id") references "products"("id") on delete cascade)`,
	`CREATE UNIQUE INDEX IF NOT EXISTS "product_shoplist_shoplist_id_product_id_unique" on "product_shoplist" ("shoplist_id", "product_id")`,
	`CREATE INDEX IF NOT EXISTS "product_shoplist_product_id_index" on "product_shoplist" ("product_id")`,
	`CREATE INDEX IF NOT EXISTS "shoplists_user_id_index" on "shoplists" ("user_id")`,
	`CREATE UNIQUE INDEX IF NOT EXISTS "products_name_size_size_type_unique" on "products" ("name", "size", "size_type")`,
}

func openDatabase(path string) (*sql.DB, error) {
	dsn := "file:" + url.PathEscape(path) + "?_pragma=foreign_keys(1)&_pragma=busy_timeout(5000)"
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return nil, err
	}

	if err := ensureSchema(db); err != nil {
		db.Close()
		return nil, err
	}

	return db, nil
}

func ensureSchema(db *sql.DB) error {
	for _, statement := range schema_statements {
		if _, err := db.Exec(statement); err != nil {
			return fmt.Errorf("schema: %w", err)
		}
	}

	now := timestamp()
	_, err := db.Exec(
		`INSERT OR IGNORE INTO users (id, name, email, password, created_at, updated_at) VALUES (?, 'Owner', 'owner@localhost', '!', ?, ?)`,
		owner_user_id, now, now,
	)
	if err != nil {
		return fmt.Errorf("owner user: %w", err)
	}

	return nil
}
