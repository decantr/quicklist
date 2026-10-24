package main

import "embed"

//go:embed views static
var embedded_files embed.FS
