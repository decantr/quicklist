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

// timestamp matches Laravel's UTC "Y-m-d H:i:s" column format.
func timestamp() string {
	return time.Now().UTC().Format(time.DateTime)
}

func today() string {
	return time.Now().Format(time.DateOnly)
}

func storedDate(date string) string {
	return date + " 00:00:00"
}

const product_columns = `p.id, p.name, p.size, COALESCE(p.size_type, ''), p.category, COALESCE(p.created_at, '')`

func scanProduct(row interface{ Scan(...any) error }, product *Product, extra ...any) error {
	destinations := append([]any{&product.ID, &product.Name, &product.Size, &product.SizeType, &product.Category, &product.CreatedAt}, extra...)
	return row.Scan(destinations...)
}

const shoplist_columns = `s.id, substr(s.date, 1, 10), COALESCE(s.created_at, '')`

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
	rows, err := s.db.Query(`SELECT ` + shoplist_columns + `, (SELECT COUNT(*) FROM product_shoplist ps WHERE ps.shoplist_id = s.id)
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

// createShoplist copies every item from the list with the latest date, like the Livewire create().
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

	now := timestamp()
	result, err := tx.Exec(
		`INSERT INTO shoplists (date, created_at, updated_at, user_id) VALUES (?, ?, ?, ?)`,
		storedDate(date), now, now, owner_user_id,
	)
	if err != nil {
		return 0, err
	}
	id, err := result.LastInsertId()
	if err != nil {
		return 0, err
	}

	if previous_id != 0 {
		_, err = tx.Exec(
			`INSERT INTO product_shoplist (shoplist_id, product_id, quantity, created_at, updated_at)
			SELECT ?, product_id, quantity, ?, ? FROM product_shoplist WHERE shoplist_id = ? ORDER BY id`,
			id, now, now, previous_id,
		)
		if err != nil {
			return 0, err
		}
	}

	return id, tx.Commit()
}

func (s *Store) updateShoplistDate(id int64, date string) error {
	_, err := s.db.Exec(`UPDATE shoplists SET date = ?, updated_at = ? WHERE id = ?`, storedDate(date), timestamp(), id)
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
	return s.items(shoplist_id, `ps.id`)
}

func (s *Store) items(shoplist_id int64, order_by string) ([]Item, error) {
	rows, err := s.db.Query(`SELECT `+product_columns+`, ps.quantity
		FROM product_shoplist ps JOIN products p ON p.id = ps.product_id
		WHERE ps.shoplist_id = ? ORDER BY `+order_by, shoplist_id)
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
	err := s.db.QueryRow(`SELECT EXISTS (SELECT 1 FROM product_shoplist WHERE shoplist_id = ? AND product_id = ?)`, shoplist_id, product_id).Scan(&exists)
	return exists, err
}

func (s *Store) addItem(shoplist_id, product_id, quantity int64) error {
	now := timestamp()
	_, err := s.db.Exec(
		`INSERT INTO product_shoplist (shoplist_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, ?, ?, ?)`,
		shoplist_id, product_id, quantity, now, now,
	)
	return err
}

func (s *Store) updateItem(shoplist_id, product_id, quantity int64) error {
	_, err := s.db.Exec(
		`UPDATE product_shoplist SET quantity = ?, updated_at = ? WHERE shoplist_id = ? AND product_id = ?`,
		quantity, timestamp(), shoplist_id, product_id,
	)
	return err
}

func (s *Store) removeItem(shoplist_id, product_id int64) error {
	_, err := s.db.Exec(`DELETE FROM product_shoplist WHERE shoplist_id = ? AND product_id = ?`, shoplist_id, product_id)
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
		WHERE p.id NOT IN (SELECT product_id FROM product_shoplist WHERE shoplist_id = ?) ORDER BY p.name`, shoplist_id)
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

	now := timestamp()
	result, err := tx.Exec(
		`INSERT INTO products (name, size, size_type, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)`,
		product.Name, product.Size, product.SizeType, product.Category, now, now,
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
			`INSERT INTO product_shoplist (shoplist_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, ?, ?, ?)`,
			shoplist_id, id, quantity, now, now,
		)
		if err != nil {
			return 0, err
		}
	}

	return id, tx.Commit()
}

func (s *Store) updateProduct(product Product) error {
	_, err := s.db.Exec(
		`UPDATE products SET name = ?, size = ?, size_type = ?, category = ?, updated_at = ? WHERE id = ?`,
		product.Name, product.Size, product.SizeType, product.Category, timestamp(), product.ID,
	)
	return err
}

func (s *Store) deleteProduct(id int64) error {
	_, err := s.db.Exec(`DELETE FROM products WHERE id = ?`, id)
	return err
}
