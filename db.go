package main

import (
	"database/sql"
	"fmt"
	"io/fs"
	"net/url"

	_ "modernc.org/sqlite"
)

func openDatabase(path string) (*sql.DB, error) {
	dsn := "file:" + url.PathEscape(path) + "?_pragma=foreign_keys(1)&_pragma=busy_timeout(5000)"
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return nil, err
	}

	if err := migrate(db); err != nil {
		db.Close()
		return nil, err
	}

	return db, nil
}

// Each migration sets PRAGMA user_version itself so _scripts/migrate-db.sh can apply it with the sqlite3 CLI.
func migrate(db *sql.DB) error {
	migrations, err := fs.Glob(embedded_files, "migrations/*.sql")
	if err != nil {
		return err
	}

	var version int
	if err := db.QueryRow(`PRAGMA user_version`).Scan(&version); err != nil {
		return err
	}
	if version > len(migrations) {
		return fmt.Errorf("database version %d is newer than this build (%d)", version, len(migrations))
	}

	for index, name := range migrations[version:] {
		if err := applyMigration(db, name, version+index+1); err != nil {
			return fmt.Errorf("%s: %w", name, err)
		}
	}
	return nil
}

func applyMigration(db *sql.DB, name string, want_version int) error {
	statements, err := fs.ReadFile(embedded_files, name)
	if err != nil {
		return err
	}

	tx, err := db.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	if _, err := tx.Exec(string(statements)); err != nil {
		return err
	}
	var version int
	if err := tx.QueryRow(`PRAGMA user_version`).Scan(&version); err != nil {
		return err
	}
	if version != want_version {
		return fmt.Errorf("sets user_version %d, want %d", version, want_version)
	}
	return tx.Commit()
}
