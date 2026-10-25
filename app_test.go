package main

import (
	"database/sql"
	"io/fs"
	"net/http"
	"net/http/httptest"
	"net/url"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"testing"
)

type testApp struct {
	t     *testing.T
	db    *sql.DB
	store *Store
	app   *App
}

func newTestApp(t *testing.T) *testApp {
	t.Helper()
	return newTestAppFromPath(t, filepath.Join(t.TempDir(), "test.sqlite"))
}

func newTestAppFromPath(t *testing.T, path string) *testApp {
	t.Helper()
	db, err := openDatabase(path)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { db.Close() })

	store := &Store{db: db}
	app, err := newApp(store)
	if err != nil {
		t.Fatal(err)
	}
	return &testApp{t: t, db: db, store: store, app: app}
}

func (ta *testApp) get(path string) *httptest.ResponseRecorder {
	request := httptest.NewRequest(http.MethodGet, path, nil)
	recorder := httptest.NewRecorder()
	ta.app.ServeHTTP(recorder, request)
	return recorder
}

func (ta *testApp) post(path string, values url.Values) *httptest.ResponseRecorder {
	request := httptest.NewRequest(http.MethodPost, path, strings.NewReader(values.Encode()))
	request.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	recorder := httptest.NewRecorder()
	ta.app.ServeHTTP(recorder, request)
	return recorder
}

func (ta *testApp) follow(recorder *httptest.ResponseRecorder) *httptest.ResponseRecorder {
	ta.t.Helper()
	assertStatus(ta.t, recorder, http.StatusSeeOther)
	request := httptest.NewRequest(http.MethodGet, recorder.Header().Get("Location"), nil)
	for _, cookie := range recorder.Result().Cookies() {
		request.AddCookie(cookie)
	}
	page := httptest.NewRecorder()
	ta.app.ServeHTTP(page, request)
	return page
}

func (ta *testApp) exec(query string, args ...any) int64 {
	ta.t.Helper()
	result, err := ta.db.Exec(query, args...)
	if err != nil {
		ta.t.Fatal(err)
	}
	id, _ := result.LastInsertId()
	return id
}

func (ta *testApp) queryInt(query string, args ...any) int64 {
	ta.t.Helper()
	var value int64
	if err := ta.db.QueryRow(query, args...).Scan(&value); err != nil {
		ta.t.Fatal(err)
	}
	return value
}

func (ta *testApp) queryString(query string, args ...any) string {
	ta.t.Helper()
	var value string
	if err := ta.db.QueryRow(query, args...).Scan(&value); err != nil {
		ta.t.Fatal(err)
	}
	return value
}

func (ta *testApp) product(name string, size int64, size_type, category string) int64 {
	return ta.exec(`INSERT INTO products (name, size, size_type, category, created_at) VALUES (?, ?, ?, ?, '2026-04-01 10:00:00')`, name, size, size_type, category)
}

func (ta *testApp) shoplist(date, created_at string) int64 {
	return ta.exec(`INSERT INTO shoplists (date, created_at) VALUES (?, ?)`, date, created_at)
}

func (ta *testApp) attach(shoplist_id, product_id, quantity int64) {
	ta.exec(`INSERT INTO shoplist_items (shoplist_id, product_id, quantity) VALUES (?, ?, ?)`, shoplist_id, product_id, quantity)
}

func assertStatus(t *testing.T, recorder *httptest.ResponseRecorder, status int) {
	t.Helper()
	if recorder.Code != status {
		t.Fatalf("status = %d, want %d\n%s", recorder.Code, status, recorder.Body.String())
	}
}

func assertContains(t *testing.T, body, want string) {
	t.Helper()
	if !strings.Contains(body, want) {
		t.Fatalf("body does not contain %q", want)
	}
}

func assertNotContains(t *testing.T, body, unwanted string) {
	t.Helper()
	if strings.Contains(body, unwanted) {
		t.Fatalf("body contains %q", unwanted)
	}
}

func assertBefore(t *testing.T, body, first, second string) {
	t.Helper()
	first_index, second_index := strings.Index(body, first), strings.Index(body, second)
	if first_index < 0 || second_index < 0 || first_index > second_index {
		t.Fatalf("want %q before %q", first, second)
	}
}

func assertRedirect(t *testing.T, recorder *httptest.ResponseRecorder, location string) {
	t.Helper()
	assertStatus(t, recorder, http.StatusSeeOther)
	if got := recorder.Header().Get("Location"); got != location {
		t.Fatalf("location = %q, want %q", got, location)
	}
}

// schema ======================================================================

func TestMigrationsOnEmptyDatabase(t *testing.T) {
	ta := newTestApp(t)

	migrations, err := fs.Glob(embedded_files, "migrations/*.sql")
	if err != nil {
		t.Fatal(err)
	}
	if version := ta.queryInt(`PRAGMA user_version`); version != int64(len(migrations)) {
		t.Fatalf("user_version = %d, want %d", version, len(migrations))
	}
	if err := migrate(ta.db); err != nil {
		t.Fatalf("second migrate: %v", err)
	}
}

func TestMigrationsRefuseNewerDatabase(t *testing.T) {
	ta := newTestApp(t)
	ta.exec(`PRAGMA user_version = 99`)

	if err := migrate(ta.db); err == nil {
		t.Fatal("migrated a database from a newer build")
	}
}

// shoplists ===================================================================

func TestDashboardShowsEmptyState(t *testing.T) {
	ta := newTestApp(t)

	assertStatus(t, ta.get("/dashboard"), http.StatusOK)
}

func TestDashboardShowsLatestListByCreatedAt(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	bread := ta.product("Bread", 800, "g", "bakery")
	newest_date := ta.shoplist("2026-05-01", "2026-04-01 10:00:00")
	newest_created := ta.shoplist("2026-04-01", "2026-04-02 10:00:00")
	ta.attach(newest_date, milk, 1)
	ta.attach(newest_created, bread, 2)

	body := ta.get("/dashboard").Body.String()

	assertContains(t, body, "2x Bread (800 g)")
	assertNotContains(t, body, "1x Milk (500 ml)")
}

func TestRootRedirectsToDashboard(t *testing.T) {
	ta := newTestApp(t)

	recorder := ta.get("/")

	assertStatus(t, recorder, http.StatusFound)
	if location := recorder.Header().Get("Location"); location != "/dashboard" {
		t.Fatalf("location = %q", location)
	}
}

func TestShoplistIndexListsByDateWithCounts(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	older := ta.shoplist("2026-03-01", "2026-03-01 10:00:00")
	newer := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(older, milk, 3)

	shoplists, err := ta.store.shoplists()
	if err != nil {
		t.Fatal(err)
	}

	if len(shoplists) != 2 || shoplists[0].ID != newer || shoplists[1].ID != older {
		t.Fatalf("lists = %+v, want newer date first", shoplists)
	}
	if shoplists[0].ProductCount != 0 || shoplists[1].ProductCount != 1 {
		t.Fatalf("counts = %d, %d, want 0, 1", shoplists[0].ProductCount, shoplists[1].ProductCount)
	}
	assertStatus(t, ta.get("/shoplists"), http.StatusOK)
}

func TestCreateShoplistCopiesPreviousList(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	bread := ta.product("Bread", 800, "g", "bakery")
	previous := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(previous, milk, 3)
	ta.attach(previous, bread, 1)
	older_but_newer_created := ta.shoplist("2026-03-01", "2026-04-02 10:00:00")
	ta.attach(older_but_newer_created, ta.product("Eggs", 12, "count", "dairy"), 1)

	recorder := ta.post("/shoplists", url.Values{"return": {"/dashboard"}})

	assertRedirect(t, recorder, "/dashboard")
	created := ta.queryInt(`SELECT MAX(id) FROM shoplists`)
	if created == older_but_newer_created {
		t.Fatal("no list created")
	}
	if quantity := ta.queryInt(`SELECT quantity FROM shoplist_items WHERE shoplist_id = ? AND product_id = ?`, created, milk); quantity != 3 {
		t.Fatalf("milk quantity = %d", quantity)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items WHERE shoplist_id = ?`, created); count != 2 {
		t.Fatalf("copied items = %d", count)
	}
}

func TestCreateShoplistConcurrently(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	ta.attach(ta.shoplist("2026-04-01", "2026-04-01 10:00:00"), milk, 1)

	var group sync.WaitGroup
	errors := make(chan error, 10)
	for range 10 {
		group.Go(func() {
			_, err := ta.store.createShoplist("2026-04-02")
			errors <- err
		})
	}
	group.Wait()
	close(errors)

	for err := range errors {
		if err != nil {
			t.Fatal(err)
		}
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items`); count != 11 {
		t.Fatalf("items = %d, want 11", count)
	}
}

func TestCreateShoplistWithoutPreviousList(t *testing.T) {
	ta := newTestApp(t)

	recorder := ta.post("/shoplists", url.Values{})

	assertRedirect(t, recorder, "/shoplists")
	if date := ta.queryString(`SELECT date FROM shoplists`); date != today() {
		t.Fatalf("date = %q, want %q", date, today())
	}
}

func TestShoplistShowEmpty(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")

	assertStatus(t, ta.get("/shoplists/"+itoa(id)), http.StatusOK)
}

func TestUpdateShoplistDate(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	path := "/shoplists/" + itoa(id)

	recorder := ta.post(path+"/date", url.Values{"date": {"2026-04-10"}, "return": {path}})

	assertRedirect(t, recorder, path)
	if date := ta.queryString(`SELECT date FROM shoplists WHERE id = ?`, id); date != "2026-04-10" {
		t.Fatalf("date = %q", date)
	}
}

func TestUpdateShoplistDateValidation(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	path := "/shoplists/" + itoa(id)

	for date, message := range map[string]string{
		"":           "The date field is required.",
		"not-a-date": "The date field must be a valid date.",
	} {
		recorder := ta.post(path+"/date", url.Values{"date": {date}, "return": {path}, "dialog": {"edit-date"}})

		assertStatus(t, recorder, http.StatusUnprocessableEntity)
		assertContains(t, recorder.Body.String(), message)
		if push := recorder.Header().Get("HX-Push-Url"); push != "false" {
			t.Fatalf("HX-Push-Url = %q", push)
		}
	}
	if date := ta.queryString(`SELECT date FROM shoplists WHERE id = ?`, id); date != "2026-04-01" {
		t.Fatalf("date changed to %q", date)
	}
}

func TestDeleteShoplistCascades(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, milk, 1)

	recorder := ta.post("/shoplists/"+itoa(id)+"/delete", url.Values{"return": {"/shoplists"}})

	assertRedirect(t, recorder, "/shoplists")
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplists`); count != 0 {
		t.Fatalf("lists left = %d", count)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items`); count != 0 {
		t.Fatalf("items left = %d", count)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM products`); count != 1 {
		t.Fatalf("products = %d", count)
	}
}

func TestMissingShoplistIsNotFound(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	quick_create := productValues("Eggs", "12", "count", "dairy")
	quick_create.Set("shoplist_id", "99")

	for path, values := range map[string]url.Values{
		"/shoplists/99/date":                            {"date": {"2026-04-01"}},
		"/shoplists/99/delete":                          {},
		"/shoplists/99/items":                           {"product_id": {itoa(milk)}, "quantity": {"1"}},
		"/shoplists/99/items/update":                    {"product_id": {itoa(milk)}, "quantity": {"1"}},
		"/shoplists/99/items/" + itoa(milk) + "/remove": {},
		"/products":                                     quick_create,
	} {
		assertStatus(t, ta.post(path, values), http.StatusNotFound)
	}
	assertStatus(t, ta.get("/shoplists/99"), http.StatusNotFound)
	if count := ta.queryInt(`SELECT COUNT(*) FROM products`); count != 1 {
		t.Fatalf("products = %d", count)
	}
}

// items =======================================================================

func TestAddItem(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")

	recorder := ta.post("/shoplists/"+itoa(id)+"/items", url.Values{"product_id": {itoa(milk)}, "quantity": {"2"}, "return": {"/dashboard"}})

	assertRedirect(t, recorder, "/dashboard")
	if quantity := ta.queryInt(`SELECT quantity FROM shoplist_items WHERE shoplist_id = ? AND product_id = ?`, id, milk); quantity != 2 {
		t.Fatalf("quantity = %d", quantity)
	}
}

func TestAddItemOptionsExcludeProductsOnList(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	ta.product("Bread", 800, "g", "bakery")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, milk, 1)

	body := ta.get("/shoplists/" + itoa(id)).Body.String()

	assertContains(t, body, "Bread (800 g)</option>")
	assertNotContains(t, body, "Milk (500 ml)</option>")
}

func TestValidateProductIDReturnsDatabaseErrors(t *testing.T) {
	ta := newTestApp(t)
	ta.exec(`DROP TABLE products`)
	errors := map[string]string{}

	if _, err := ta.app.validateProductID("1", errors); err == nil {
		t.Fatal("database error was not returned")
	}
	if len(errors) != 0 {
		t.Fatalf("database error reported as validation: %v", errors)
	}
}

func TestAddItemValidation(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, milk, 1)
	path := "/shoplists/" + itoa(id)

	cases := []struct {
		product_id string
		quantity   string
		message    string
	}{
		{"", "1", "The product field is required."},
		{"999", "1", "The selected product is invalid."},
		{"abc", "1", "The selected product is invalid."},
		{itoa(milk), "1", "This product is already in the shopping list."},
		{itoa(milk), "", "The quantity field is required."},
		{itoa(milk), "0", "The quantity field must be at least 1."},
		{itoa(milk), "abc", "The quantity field must be an integer."},
	}
	for _, c := range cases {
		recorder := ta.post(path+"/items", url.Values{"product_id": {c.product_id}, "quantity": {c.quantity}, "return": {path}, "dialog": {"add-item"}})

		assertStatus(t, recorder, http.StatusUnprocessableEntity)
		assertContains(t, recorder.Body.String(), c.message)
		assertContains(t, recorder.Body.String(), `<dialog id="add-item" x-data x-init="$el.showModal()">`)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items`); count != 1 {
		t.Fatalf("items = %d", count)
	}
}

func TestUpdateItemQuantity(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	other := ta.shoplist("2026-03-01", "2026-03-01 10:00:00")
	ta.attach(id, milk, 1)
	ta.attach(other, milk, 1)
	path := "/shoplists/" + itoa(id)

	recorder := ta.post(path+"/items/update", url.Values{"product_id": {itoa(milk)}, "quantity": {"5"}, "return": {path}})

	assertRedirect(t, recorder, path)
	if quantity := ta.queryInt(`SELECT quantity FROM shoplist_items WHERE shoplist_id = ?`, id); quantity != 5 {
		t.Fatalf("quantity = %d", quantity)
	}
	if quantity := ta.queryInt(`SELECT quantity FROM shoplist_items WHERE shoplist_id = ?`, other); quantity != 1 {
		t.Fatalf("other list quantity = %d", quantity)
	}
}

func TestUpdateItemValidation(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, milk, 1)
	path := "/shoplists/" + itoa(id)

	recorder := ta.post(path+"/items/update", url.Values{"product_id": {itoa(milk)}, "quantity": {"0"}, "return": {path}, "dialog": {"edit-item"}})

	assertStatus(t, recorder, http.StatusUnprocessableEntity)
	assertContains(t, recorder.Body.String(), "The quantity field must be at least 1.")
	if quantity := ta.queryInt(`SELECT quantity FROM shoplist_items`); quantity != 1 {
		t.Fatalf("quantity changed to %d", quantity)
	}
}

func TestUpdateItemNotOnList(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	path := "/shoplists/" + itoa(id)

	recorder := ta.post(path+"/items/update", url.Values{"product_id": {itoa(milk)}, "quantity": {"2"}, "return": {path}})

	assertRedirect(t, recorder, path)
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items`); count != 0 {
		t.Fatalf("items = %d", count)
	}
	assertContains(t, ta.follow(recorder).Body.String(), "Product not on this Shop List")
}

func TestRemoveItem(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	other := ta.shoplist("2026-03-01", "2026-03-01 10:00:00")
	ta.attach(id, milk, 1)
	ta.attach(other, milk, 1)
	path := "/shoplists/" + itoa(id)

	recorder := ta.post(path+"/items/"+itoa(milk)+"/remove", url.Values{"return": {path}})

	assertRedirect(t, recorder, path)
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items WHERE shoplist_id = ?`, id); count != 0 {
		t.Fatalf("items = %d", count)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items WHERE shoplist_id = ?`, other); count != 1 {
		t.Fatalf("other list items = %d", count)
	}
}

// products ====================================================================

func productValues(name, size, size_type, category string) url.Values {
	return url.Values{"name": {name}, "size": {size}, "size_type": {size_type}, "category": {category}, "return": {"/products"}, "dialog": {"create-product"}}
}

func TestProductsIndexFiltersByCategory(t *testing.T) {
	ta := newTestApp(t)
	ta.product("Milk", 500, "ml", "dairy")
	ta.product("Bread", 800, "g", "bakery")

	body := ta.get("/products?category=dairy").Body.String()

	assertContains(t, body, `data-name="Milk"`)
	assertNotContains(t, body, `data-name="Bread"`)

	assertBefore(t, ta.get("/products").Body.String(), `data-name="Bread"`, `data-name="Milk"`)
}

func TestCreateProduct(t *testing.T) {
	ta := newTestApp(t)

	recorder := ta.post("/products", productValues("Milk", "500", "ml", "dairy"))

	assertRedirect(t, recorder, "/products")
	if count := ta.queryInt(`SELECT COUNT(*) FROM products WHERE name = 'Milk' AND size = 500 AND size_type = 'ml' AND category = 'dairy'`); count != 1 {
		t.Fatalf("products = %d", count)
	}
}

func TestCreateProductAndAddAnotherReopensDialog(t *testing.T) {
	ta := newTestApp(t)
	values := productValues("Milk", "500", "ml", "dairy")
	values.Set("another", "1")

	recorder := ta.post("/products", values)

	assertContains(t, ta.follow(recorder).Body.String(), `<dialog id="create-product" x-data x-init="$el.showModal()">`)
}

func TestCreateProductValidation(t *testing.T) {
	ta := newTestApp(t)
	ta.product("Milk", 500, "ml", "dairy")

	cases := []struct {
		values  url.Values
		message string
	}{
		{productValues("", "500", "ml", "dairy"), "The name field is required."},
		{productValues(strings.Repeat("a", 256), "500", "ml", "dairy"), "The name field must not be greater than 255 characters."},
		{productValues("Eggs", "", "count", "dairy"), "The size field is required."},
		{productValues("Eggs", "-1", "count", "dairy"), "The size field must be at least 0."},
		{productValues("Eggs", "1.5", "count", "dairy"), "The size field must be an integer."},
		{productValues("Eggs", "12", "dozen", "dairy"), "The selected size type is invalid."},
		{productValues("Eggs", "12", "count", "eggs"), "The selected category is invalid."},
		{productValues("Milk", "500", "ml", "fridge"), "The name has already been taken."},
	}
	for _, c := range cases {
		recorder := ta.post("/products", c.values)

		assertStatus(t, recorder, http.StatusUnprocessableEntity)
		assertContains(t, recorder.Body.String(), c.message)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM products`); count != 1 {
		t.Fatalf("products = %d", count)
	}
}

func TestFailedFormKeepsValues(t *testing.T) {
	ta := newTestApp(t)

	body := ta.post("/products", productValues("Eggs", "-1", "count", "dairy")).Body.String()

	assertContains(t, body, `value="Eggs"`)
	assertContains(t, body, `value="-1"`)
}

func TestCreateProductAllowsSameNameWithDifferentSize(t *testing.T) {
	ta := newTestApp(t)
	ta.product("Milk", 500, "ml", "dairy")

	recorder := ta.post("/products", productValues("Milk", "1000", "ml", "dairy"))

	assertRedirect(t, recorder, "/products")
}

func TestQuickCreateProductAddsToList(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	values := productValues("Milk", "500", "ml", "dairy")
	values.Set("return", "/dashboard")
	values.Set("dialog", "quick-create")
	values.Set("shoplist_id", itoa(id))
	values.Set("quantity", "3")

	recorder := ta.post("/products", values)

	assertRedirect(t, recorder, "/dashboard")
	if quantity := ta.queryInt(`SELECT quantity FROM shoplist_items WHERE shoplist_id = ?`, id); quantity != 3 {
		t.Fatalf("quantity = %d", quantity)
	}
}

func TestQuickCreateProductValidatesQuantity(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	values := productValues("Milk", "500", "ml", "dairy")
	values.Set("return", "/dashboard")
	values.Set("dialog", "quick-create")
	values.Set("shoplist_id", itoa(id))
	values.Set("quantity", "0")

	recorder := ta.post("/products", values)

	assertStatus(t, recorder, http.StatusUnprocessableEntity)
	assertContains(t, recorder.Body.String(), "The quantity field must be at least 1.")
	if count := ta.queryInt(`SELECT COUNT(*) FROM products`); count != 0 {
		t.Fatalf("products = %d", count)
	}
}

func TestUpdateProduct(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	values := productValues("Oat Milk", "1000", "ml", "fridge")
	values.Set("id", itoa(milk))

	recorder := ta.post("/products/update", values)

	assertRedirect(t, recorder, "/products")
	if name := ta.queryString(`SELECT name || '|' || size || '|' || category FROM products WHERE id = ?`, milk); name != "Oat Milk|1000|fridge" {
		t.Fatalf("product = %q", name)
	}
}

func TestUpdateMissingProductIsNotFound(t *testing.T) {
	ta := newTestApp(t)
	values := productValues("Milk", "500", "ml", "dairy")
	values.Set("id", "99")

	assertStatus(t, ta.post("/products/update", values), http.StatusNotFound)
	if count := ta.queryInt(`SELECT COUNT(*) FROM products`); count != 0 {
		t.Fatalf("products = %d", count)
	}
}

func TestUpdateProductIgnoresItselfForDuplicates(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	ta.product("Bread", 800, "g", "bakery")

	same := productValues("Milk", "500", "ml", "fridge")
	same.Set("id", itoa(milk))
	assertRedirect(t, ta.post("/products/update", same), "/products")

	duplicate := productValues("Bread", "800", "g", "bakery")
	duplicate.Set("id", itoa(milk))
	duplicate.Set("dialog", "edit-product")
	recorder := ta.post("/products/update", duplicate)
	assertStatus(t, recorder, http.StatusUnprocessableEntity)
	assertContains(t, recorder.Body.String(), "The name has already been taken.")
}

func TestDeleteProductCascades(t *testing.T) {
	ta := newTestApp(t)
	milk := ta.product("Milk", 500, "ml", "dairy")
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, milk, 1)

	recorder := ta.post("/products/"+itoa(milk)+"/delete", url.Values{"return": {"/products"}})

	assertRedirect(t, recorder, "/products")
	if count := ta.queryInt(`SELECT COUNT(*) FROM products`); count != 0 {
		t.Fatalf("products = %d", count)
	}
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplist_items`); count != 0 {
		t.Fatalf("items = %d", count)
	}
}

// formatted list ==============================================================

func TestFormattedListOutput(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, ta.product("Milk", 500, "ml", "dairy"), 3)
	ta.attach(id, ta.product("Bread", 1, "g", "bakery"), 1)
	ta.attach(id, ta.product("Eggs", 12, "count", "dairy"), 1)
	ta.attach(id, ta.product("Beer", 1, "pt", "other"), 2)

	body := ta.get("/shoplists/" + itoa(id)).Body.String()

	assertContains(t, body, "3x Milk (500 ml)\n1x Eggs (12 count)\n\n1x Bread (1 g)\n\n2x Beer (1 pt)")
}

func TestDashboardFormattedListIsSorted(t *testing.T) {
	ta := newTestApp(t)
	id := ta.shoplist("2026-04-01", "2026-04-01 10:00:00")
	ta.attach(id, ta.product("Milk", 500, "ml", "dairy"), 3)
	ta.attach(id, ta.product("Bread", 1, "g", "bakery"), 1)

	body := ta.get("/dashboard").Body.String()

	assertContains(t, body, "1x Bread (1 g)\n\n3x Milk (500 ml)")
}

// http ========================================================================

type headerCounter struct {
	*httptest.ResponseRecorder
	calls int
}

func (h *headerCounter) WriteHeader(status int) {
	h.calls++
	h.ResponseRecorder.WriteHeader(status)
}

func TestStatusWriterWritesHeaderOnce(t *testing.T) {
	cases := []struct {
		name   string
		write  func(http.ResponseWriter)
		status int
	}{
		{"page", func(w http.ResponseWriter) { w.Write([]byte("a")); w.Write([]byte("b")) }, http.StatusUnprocessableEntity},
		{"not found", func(w http.ResponseWriter) { http.NotFound(w, nil) }, http.StatusNotFound},
	}
	for _, c := range cases {
		counter := &headerCounter{ResponseRecorder: httptest.NewRecorder()}

		c.write(&statusWriter{ResponseWriter: counter, status: http.StatusUnprocessableEntity})

		if counter.calls != 1 || counter.Code != c.status {
			t.Errorf("%s: WriteHeader called %d times, status %d, want once with %d", c.name, counter.calls, counter.Code, c.status)
		}
	}
}

func TestFlashIsShownOnce(t *testing.T) {
	ta := newTestApp(t)

	page := ta.follow(ta.post("/shoplists", url.Values{}))

	assertContains(t, page.Body.String(), "Shopping list created.")
	for _, cookie := range page.Result().Cookies() {
		if cookie.Name == "flash" && cookie.MaxAge < 0 {
			return
		}
	}
	t.Fatal("flash cookie was not cleared")
}

func TestCrossSiteFormPostIsRejected(t *testing.T) {
	ta := newTestApp(t)
	request := httptest.NewRequest(http.MethodPost, "/shoplists", nil)
	request.Header.Set("Sec-Fetch-Site", "cross-site")
	recorder := httptest.NewRecorder()

	ta.app.ServeHTTP(recorder, request)

	assertStatus(t, recorder, http.StatusForbidden)
	if count := ta.queryInt(`SELECT COUNT(*) FROM shoplists`); count != 0 {
		t.Fatalf("lists = %d", count)
	}
}

func TestExternalReturnPathIsIgnored(t *testing.T) {
	ta := newTestApp(t)

	for _, path := range []string{"//evil.example", "/\\evil.example", "/\t/evil.example", "/\n/evil.example", "https://evil.example"} {
		recorder := ta.post("/shoplists", url.Values{"return": {path}})

		assertRedirect(t, recorder, "/shoplists")
	}
}

func TestStaticAssetsAreServed(t *testing.T) {
	ta := newTestApp(t)

	for _, path := range []string{"/static/app.css", "/static/htmx.min.js", "/static/alpine.min.js", "/sw.js", "/manifest.webmanifest", "/up", "/favicon.ico", "/static/favicon.svg", "/static/icon-192.png", "/static/icon-512.png", "/static/apple-touch-icon.png"} {
		assertStatus(t, ta.get(path), http.StatusOK)
	}
}

func itoa(value int64) string {
	return strconv.FormatInt(value, 10)
}
