package main

import (
	"flag"
	"log"
	"net/http"
	"os"
	"time"

	_ "time/tzdata"
)

func main() {
	addr := flag.String("addr", envOr("QUICKLIST_ADDR", "127.0.0.1:8080"), "listen address")
	db_path := flag.String("db", envOr("QUICKLIST_DB", "database.sqlite"), "SQLite database file")
	flag.Parse()

	db, err := openDatabase(*db_path)
	if err != nil {
		log.Fatalf("open %s: %v", *db_path, err)
	}
	defer db.Close()

	app, err := newApp(&Store{db: db})
	if err != nil {
		log.Fatalf("templates: %v", err)
	}

	server := &http.Server{
		Addr:              *addr,
		Handler:           app,
		ReadHeaderTimeout: 10 * time.Second,
	}

	log.Printf("listening on http://%s (db: %s)", *addr, *db_path)
	log.Fatal(server.ListenAndServe())
}

func envOr(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}
