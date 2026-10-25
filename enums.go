package main

import (
	"slices"
	"strings"
)

var categories = []string{"bakery", "cupboard", "dairy", "fridge", "frozen", "meat", "produce", "other"}

var size_types = []string{"g", "ml", "count", "pt"}

func categoryLabel(category string) string {
	if category == "" {
		return ""
	}
	return strings.ToUpper(category[:1]) + category[1:]
}

func isCategory(value string) bool {
	return slices.Contains(categories, value)
}

func isSizeType(value string) bool {
	return slices.Contains(size_types, value)
}
