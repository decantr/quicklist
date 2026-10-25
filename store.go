package main

import (
	"database/sql"
	"errors"
	"time"
)

type Product struct {
	ID        int64
	Name      string
	Size      int64
	SizeType  string
	Category  string
	CreatedAt string
}

type Item struct {
	Product
	Quantity int64
}

type Shoplist struct {
	ID           int64
	Date         string
	CreatedAt    string
	ProductCount int64
}

type Store struct {
	db *sql.DB
}

func today() string {
	return time.Now().Format(time.DateOnly)
}

const product_columns = `p.id, p.name, p.size, p.size_type, p.category, p.created_at`

func scanProduct(row interface{ Scan(...any) error }, product *Product, extra ...any) error {
	destinations := append([]any{&product.ID, &product.Name, &product.Size, &product.SizeType, &product.Category, &product.CreatedAt}, extra...)
	return row.Scan(destinations...)
}

const shoplist_columns = `s.id, s.date, s.created_at`

func (s *Store) latestShoplist() (*Shoplist, error) {
	shoplist := &Shoplist{}
	err := s.db.QueryRow(`SELECT `+shoplist_columns+` FROM shoplists s ORDER BY s.created_at DESC, s.id DESC LIMIT 1`).
		Scan(&shoplist.ID, &shoplist.Date, &shoplist.CreatedAt)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	return shoplist, err
}

func (s *Store) shoplist(id int64) (*Shoplist, error) {
	shoplist := &Shoplist{}
	err := s.db.QueryRow(`SELECT `+shoplist_columns+` FROM shoplists s WHERE s.id = ?`, id).
		Scan(&shoplist.ID, &shoplist.Date, &shoplist.CreatedAt)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	return shoplist, err
}

func (s *Store) shoplists() ([]Shoplist, error) {
	rows, err := s.db.Query(`SELECT ` + shoplist_columns + `, (SELECT COUNT(*) FROM shoplist_items i WHERE i.shoplist_id = s.id)
		FROM shoplists s ORDER BY s.date DESC, s.id DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var shoplists []Shoplist
	for rows.Next() {
		var shoplist Shoplist
		if err := rows.Scan(&shoplist.ID, &shoplist.Date, &shoplist.CreatedAt, &shoplist.ProductCount); err != nil {
			return nil, err
		}
		shoplists = append(shoplists, shoplist)
	}
	return shoplists, rows.Err()
}

// createShoplist copies every item from the list with the latest date.
func (s *Store) createShoplist(date string) (int64, error) {
	tx, err := s.db.Begin()
	if err != nil {
		return 0, err
	}
	defer tx.Rollback()

	var previous_id int64
	err = tx.QueryRow(`SELECT id FROM shoplists ORDER BY date DESC, id DESC LIMIT 1`).Scan(&previous_id)
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return 0, err
	}

	result, err := tx.Exec(`INSERT INTO shoplists (date) VALUES (?)`, date)
	if err != nil {
		return 0, err
	}
	id, err := result.LastInsertId()
	if err != nil {
		return 0, err
	}

	if previous_id != 0 {
		_, err = tx.Exec(
			`INSERT INTO shoplist_items (shoplist_id, product_id, quantity)
			SELECT ?, product_id, quantity FROM shoplist_items WHERE shoplist_id = ? ORDER BY id`,
			id, previous_id,
		)
		if err != nil {
			return 0, err
		}
	}

	return id, tx.Commit()
}

func (s *Store) updateShoplistDate(id int64, date string) error {
	_, err := s.db.Exec(`UPDATE shoplists SET date = ? WHERE id = ?`, date, id)
	return err
}

func (s *Store) deleteShoplist(id int64) error {
	_, err := s.db.Exec(`DELETE FROM shoplists WHERE id = ?`, id)
	return err
}

func (s *Store) itemsSorted(shoplist_id int64) ([]Item, error) {
	return s.items(shoplist_id, `p.category, p.name`)
}

func (s *Store) itemsInAddedOrder(shoplist_id int64) ([]Item, error) {
	return s.items(shoplist_id, `i.id`)
}

func (s *Store) items(shoplist_id int64, order_by string) ([]Item, error) {
	rows, err := s.db.Query(`SELECT `+product_columns+`, i.quantity
		FROM shoplist_items i JOIN products p ON p.id = i.product_id
		WHERE i.shoplist_id = ? ORDER BY `+order_by, shoplist_id)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var items []Item
	for rows.Next() {
		var item Item
		if err := scanProduct(rows, &item.Product, &item.Quantity); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (s *Store) isOnList(shoplist_id, product_id int64) (bool, error) {
	var exists bool
	err := s.db.QueryRow(`SELECT EXISTS (SELECT 1 FROM shoplist_items WHERE shoplist_id = ? AND product_id = ?)`, shoplist_id, product_id).Scan(&exists)
	return exists, err
}

func (s *Store) addItem(shoplist_id, product_id, quantity int64) error {
	_, err := s.db.Exec(
		`INSERT INTO shoplist_items (shoplist_id, product_id, quantity) VALUES (?, ?, ?)`,
		shoplist_id, product_id, quantity,
	)
	return err
}

func (s *Store) updateItem(shoplist_id, product_id, quantity int64) error {
	_, err := s.db.Exec(
		`UPDATE shoplist_items SET quantity = ? WHERE shoplist_id = ? AND product_id = ?`,
		quantity, shoplist_id, product_id,
	)
	return err
}

func (s *Store) removeItem(shoplist_id, product_id int64) error {
	_, err := s.db.Exec(`DELETE FROM shoplist_items WHERE shoplist_id = ? AND product_id = ?`, shoplist_id, product_id)
	return err
}

func (s *Store) product(id int64) (*Product, error) {
	product := &Product{}
	err := scanProduct(s.db.QueryRow(`SELECT `+product_columns+` FROM products p WHERE p.id = ?`, id), product)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	return product, err
}

func (s *Store) products(category string) ([]Product, error) {
	return s.queryProducts(`SELECT `+product_columns+` FROM products p WHERE ? = '' OR p.category = ? ORDER BY p.name`, category, category)
}

func (s *Store) productsNotOnList(shoplist_id int64) ([]Product, error) {
	return s.queryProducts(`SELECT `+product_columns+` FROM products p
		WHERE p.id NOT IN (SELECT product_id FROM shoplist_items WHERE shoplist_id = ?) ORDER BY p.name`, shoplist_id)
}

func (s *Store) queryProducts(query string, args ...any) ([]Product, error) {
	rows, err := s.db.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var products []Product
	for rows.Next() {
		var product Product
		if err := scanProduct(rows, &product); err != nil {
			return nil, err
		}
		products = append(products, product)
	}
	return products, rows.Err()
}

func (s *Store) isDuplicateProduct(product Product) (bool, error) {
	var exists bool
	err := s.db.QueryRow(
		`SELECT EXISTS (SELECT 1 FROM products WHERE name = ? AND size = ? AND size_type = ? AND id != ?)`,
		product.Name, product.Size, product.SizeType, product.ID,
	).Scan(&exists)
	return exists, err
}

// createProduct optionally adds the new product to a list in the same transaction.
func (s *Store) createProduct(product Product, shoplist_id, quantity int64) (int64, error) {
	tx, err := s.db.Begin()
	if err != nil {
		return 0, err
	}
	defer tx.Rollback()

	result, err := tx.Exec(
		`INSERT INTO products (name, size, size_type, category) VALUES (?, ?, ?, ?)`,
		product.Name, product.Size, product.SizeType, product.Category,
	)
	if err != nil {
		return 0, err
	}
	id, err := result.LastInsertId()
	if err != nil {
		return 0, err
	}

	if shoplist_id != 0 {
		_, err = tx.Exec(
			`INSERT INTO shoplist_items (shoplist_id, product_id, quantity) VALUES (?, ?, ?)`,
			shoplist_id, id, quantity,
		)
		if err != nil {
			return 0, err
		}
	}

	return id, tx.Commit()
}

func (s *Store) updateProduct(product Product) error {
	_, err := s.db.Exec(
		`UPDATE products SET name = ?, size = ?, size_type = ?, category = ? WHERE id = ?`,
		product.Name, product.Size, product.SizeType, product.Category, product.ID,
	)
	return err
}

func (s *Store) deleteProduct(id int64) error {
	_, err := s.db.Exec(`DELETE FROM products WHERE id = ?`, id)
	return err
}
