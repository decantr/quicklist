package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
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

	listener, err := net.Listen("tcp", *addr)
	if err != nil {
		log.Fatal(err)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	server := &http.Server{
		Handler:           app,
		ReadHeaderTimeout: 10 * time.Second,
	}

	log.Printf("listening on http://%s (db: %s)", *addr, *db_path)
	if err := serve(ctx, server, listener); err != nil {
		log.Fatal(err)
	}
	log.Print("stopped")
}

// Shorter than docker's default 10s stop grace period, so it finishes before SIGKILL.
const shutdown_timeout = 8 * time.Second

func serve(ctx context.Context, server *http.Server, listener net.Listener) error {
	errs := make(chan error, 1)
	go func() { errs <- server.Serve(listener) }()

	select {
	case err := <-errs:
		return err
	case <-ctx.Done():
	}

	shutdown_ctx, cancel := context.WithTimeout(context.Background(), shutdown_timeout)
	defer cancel()
	if err := server.Shutdown(shutdown_ctx); err != nil {
		return err
	}
	if err := <-errs; !errors.Is(err, http.ErrServerClosed) {
		return err
	}
	return nil
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
