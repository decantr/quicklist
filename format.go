package main

import (
	"fmt"
	"strings"
)

// formattedList groups by category in order of first appearance.
func formattedList(items []Item) string {
	var category_order []string
	lines_by_category := map[string][]string{}

	for _, item := range items {
		if _, seen := lines_by_category[item.Category]; !seen {
			category_order = append(category_order, item.Category)
		}
		line := fmt.Sprintf("%dx %s (%d %s)", item.Quantity, item.Name, item.Size, item.SizeType)
		lines_by_category[item.Category] = append(lines_by_category[item.Category], line)
	}

	groups := make([]string, 0, len(category_order))
	for _, category := range category_order {
		groups = append(groups, strings.Join(lines_by_category[category], "\n"))
	}

	return strings.Join(groups, "\n\n")
}
