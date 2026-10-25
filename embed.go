package main

import "embed"

//go:embed views static migrations
var embedded_files embed.FS
