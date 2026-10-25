package main

import (
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"time"

	_ "time/tzdata"
)

func main() {
	addr := flag.String("addr", envOr("QUICKLIST_ADDR", "127.0.0.1:8080"), "listen address")
	db_path := flag.String("db", envOr("QUICKLIST_DB", "database.sqlite"), "SQLite database file")
	healthcheck := flag.Bool("healthcheck", false, "check that the server at -addr is up, then exit")
	flag.Parse()

	if *healthcheck {
		if err := checkHealth(*addr); err != nil {
			log.Fatal(err)
		}
		return
	}

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

func checkHealth(addr string) error {
	host, port, err := net.SplitHostPort(addr)
	if err != nil {
		return err
	}
	if host == "" || net.ParseIP(host).IsUnspecified() {
		host = "127.0.0.1"
	}

	client := http.Client{Timeout: 5 * time.Second}
	response, err := client.Get("http://" + net.JoinHostPort(host, port) + "/up")
	if err != nil {
		return err
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		return fmt.Errorf("health check: %s", response.Status)
	}
	return nil
}
