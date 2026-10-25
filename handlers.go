package main

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"html/template"
	"io/fs"
	"log"
	"net/http"
	"net/url"
	"path"
	"slices"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"
)

const default_theme = "default"

type App struct {
	store     *Store
	templates map[string]*template.Template
	mux       *http.ServeMux
	handler   http.Handler
	themes    map[string]template.CSS
}

type Flash struct {
	Message string `json:"message"`
	Open    string `json:"open,omitempty"`
}

// FormState carries a failed submission back into the page it came from.
type FormState struct {
	Dialog string
	Values url.Values
	Errors map[string]string
}

type PageData struct {
	Title      string
	Path       string
	Flash      *Flash
	Form       *FormState
	Shoplist   *Shoplist
	Shoplists  []Shoplist
	Items      []Item
	Formatted  string
	Options    []Product
	Products   []Product
	Category   string
	Quick      bool
	Categories []string
	SizeTypes  []string
	Theme      string
	Themes     []string
}

func (p PageData) Open(dialog string) bool {
	return (p.Form != nil && p.Form.Dialog == dialog) || (p.Flash != nil && p.Flash.Open == dialog)
}

func (p PageData) Error(dialog, field string) string {
	if p.Form == nil || p.Form.Dialog != dialog {
		return ""
	}
	return p.Form.Errors[field]
}

func (p PageData) Value(dialog, field, fallback string) string {
	if p.Form == nil || p.Form.Dialog != dialog {
		return fallback
	}
	return p.Form.Values.Get(field)
}

type formStateKey struct{}

type ProductFields struct {
	Page   PageData
	Dialog string
	Model  bool
}

type QuantityField struct {
	Value string
	Model string
}

var template_funcs = template.FuncMap{
	"categoryLabel": categoryLabel,
	"themeLabel": func(theme string) string {
		return strings.ToUpper(theme[:1]) + theme[1:]
	},
	"quantity": func(value, model string) QuantityField {
		return QuantityField{Value: value, Model: model}
	},
	"fields": func(page PageData, dialog string, model bool) ProductFields {
		return ProductFields{Page: page, Dialog: dialog, Model: model}
	},
	"displayDate": func(date string) string {
		parsed, err := time.Parse(time.DateOnly, date)
		if err != nil {
			return date
		}
		return parsed.Format("Jan 02, 2006")
	},
	"displayTimestamp": func(value string) string {
		parsed, err := time.Parse(time.DateTime, value)
		if err != nil {
			return value
		}
		return parsed.Local().Format("Jan 02, 2006 15:04")
	},
}

func newApp(store *Store) (*App, error) {
	app := &App{store: store, templates: map[string]*template.Template{}, mux: http.NewServeMux(), themes: map[string]template.CSS{}}

	theme_files, err := fs.Glob(embedded_files, "static/themes/*.css")
	if err != nil {
		return nil, err
	}
	for _, file := range theme_files {
		stylesheet, err := fs.ReadFile(embedded_files, file)
		if err != nil {
			return nil, err
		}
		app.themes[strings.TrimSuffix(path.Base(file), ".css")] = template.CSS(stylesheet)
	}
	if _, ok := app.themes[default_theme]; !ok {
		return nil, fmt.Errorf("missing static/themes/%s.css", default_theme)
	}

	// Inlined so the first paint is already styled; a linked stylesheet flashed unstyled content.
	funcs := template.FuncMap{"styles": func(theme string) template.CSS { return app.themes[theme] }}
	for name, function := range template_funcs {
		funcs[name] = function
	}

	for _, page := range []string{"dashboard", "shoplists", "shoplist", "products"} {
		parsed, err := template.New("layout.html").Funcs(funcs).ParseFS(
			embedded_files, "views/layout.html", "views/partials.html", "views/"+page+".html",
		)
		if err != nil {
			return nil, err
		}
		app.templates[page] = parsed
	}

	static_files, err := fs.Sub(embedded_files, "static")
	if err != nil {
		return nil, err
	}
	app.routes(static_files)
	app.handler = http.NewCrossOriginProtection().Handler(app.mux)

	return app, nil
}

func (a *App) routes(static_files fs.FS) {
	a.mux.Handle("GET /static/", http.StripPrefix("/static/", http.FileServerFS(static_files)))
	a.mux.HandleFunc("GET /sw.js", serveFile(static_files, "sw.js", "text/javascript"))
	a.mux.HandleFunc("GET /manifest.webmanifest", serveFile(static_files, "manifest.webmanifest", "application/manifest+json"))
	a.mux.HandleFunc("GET /favicon.ico", serveFile(static_files, "favicon.svg", "image/svg+xml"))
	a.mux.HandleFunc("GET /up", func(w http.ResponseWriter, r *http.Request) { fmt.Fprint(w, "OK") })

	a.mux.HandleFunc("GET /{$}", func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
	})
	a.mux.HandleFunc("GET /dashboard", a.dashboard)
	a.mux.HandleFunc("GET /shoplists", a.shoplistsIndex)
	a.mux.HandleFunc("POST /shoplists", a.createShoplist)
	a.mux.HandleFunc("GET /shoplists/{id}", a.shoplistShow)
	a.mux.HandleFunc("POST /shoplists/{id}/date", a.updateShoplistDate)
	a.mux.HandleFunc("POST /shoplists/{id}/delete", a.deleteShoplist)
	a.mux.HandleFunc("POST /shoplists/{id}/items", a.addItem)
	a.mux.HandleFunc("POST /shoplists/{id}/items/update", a.updateItem)
	a.mux.HandleFunc("POST /shoplists/{id}/items/{product_id}/remove", a.removeItem)
	a.mux.HandleFunc("GET /products", a.productsIndex)
	a.mux.HandleFunc("POST /products", a.createProduct)
	a.mux.HandleFunc("POST /products/update", a.updateProduct)
	a.mux.HandleFunc("POST /products/{id}/delete", a.deleteProduct)
	a.mux.HandleFunc("POST /theme", a.setTheme)
}

func (a *App) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	a.handler.ServeHTTP(w, r)
}

func serveFile(files fs.FS, name, content_type string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		contents, err := fs.ReadFile(files, name)
		if err != nil {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Content-Type", content_type)
		w.Header().Set("Cache-Control", "no-cache")
		w.Write(contents)
	}
}

// pages =======================================================================

func (a *App) dashboard(w http.ResponseWriter, r *http.Request) {
	data := a.pageData(w, r, "Dashboard")
	data.Quick = true

	shoplist, err := a.store.latestShoplist()
	if err != nil {
		serverError(w, err)
		return
	}

	if shoplist != nil {
		if err := a.loadShoplist(&data, shoplist, true); err != nil {
			serverError(w, err)
			return
		}
	}

	a.render(w, "dashboard", data)
}

func (a *App) shoplistsIndex(w http.ResponseWriter, r *http.Request) {
	data := a.pageData(w, r, "Shop Lists")

	shoplists, err := a.store.shoplists()
	if err != nil {
		serverError(w, err)
		return
	}
	data.Shoplists = shoplists

	a.render(w, "shoplists", data)
}

func (a *App) shoplistShow(w http.ResponseWriter, r *http.Request) {
	shoplist, ok := a.findShoplist(w, r)
	if !ok {
		return
	}

	data := a.pageData(w, r, "Shopping List")
	if err := a.loadShoplist(&data, shoplist, false); err != nil {
		serverError(w, err)
		return
	}

	a.render(w, "shoplist", data)
}

// loadShoplist formats sorted items for the dashboard but keeps added order on the show page.
func (a *App) loadShoplist(data *PageData, shoplist *Shoplist, format_sorted bool) error {
	items, err := a.store.itemsSorted(shoplist.ID)
	if err != nil {
		return err
	}

	formatted_items := items
	if !format_sorted {
		formatted_items, err = a.store.itemsInAddedOrder(shoplist.ID)
		if err != nil {
			return err
		}
	}

	options, err := a.store.productsNotOnList(shoplist.ID)
	if err != nil {
		return err
	}

	data.Shoplist = shoplist
	data.Items = items
	data.Formatted = formattedList(formatted_items)
	data.Options = options
	return nil
}

func (a *App) productsIndex(w http.ResponseWriter, r *http.Request) {
	data := a.pageData(w, r, "Products")
	data.Category = r.URL.Query().Get("category")

	products, err := a.store.products(data.Category)
	if err != nil {
		serverError(w, err)
		return
	}
	data.Products = products

	a.render(w, "products", data)
}

// shoplists ===================================================================

func (a *App) createShoplist(w http.ResponseWriter, r *http.Request) {
	if _, err := a.store.createShoplist(today()); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, "/shoplists", Flash{Message: "Shopping list created."})
}

func (a *App) updateShoplistDate(w http.ResponseWriter, r *http.Request) {
	shoplist, ok := a.findShoplist(w, r)
	if !ok {
		return
	}

	errors := map[string]string{}
	date := strings.TrimSpace(r.FormValue("date"))
	if date == "" {
		errors["date"] = "The date field is required."
	} else if _, err := time.Parse(time.DateOnly, date); err != nil {
		errors["date"] = "The date field must be a valid date."
	}
	if len(errors) > 0 {
		a.renderErrors(w, r, errors)
		return
	}

	if err := a.store.updateShoplistDate(shoplist.ID, date); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, fmt.Sprintf("/shoplists/%d", shoplist.ID), Flash{Message: "Shopping list date updated."})
}

func (a *App) deleteShoplist(w http.ResponseWriter, r *http.Request) {
	shoplist, ok := a.findShoplist(w, r)
	if !ok {
		return
	}

	if err := a.store.deleteShoplist(shoplist.ID); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, "/shoplists", Flash{Message: "Shopping list deleted."})
}

// items =======================================================================

func (a *App) addItem(w http.ResponseWriter, r *http.Request) {
	shoplist, ok := a.findShoplist(w, r)
	if !ok {
		return
	}

	errors := map[string]string{}
	product_id, err := a.validateProductID(r.FormValue("product_id"), errors)
	if err != nil {
		serverError(w, err)
		return
	}
	quantity := validateQuantity(r.FormValue("quantity"), errors)

	if product_id != 0 {
		on_list, err := a.store.isOnList(shoplist.ID, product_id)
		if err != nil {
			serverError(w, err)
			return
		}
		if on_list {
			errors["product_id"] = "This product is already in the shopping list."
		}
	}
	if len(errors) > 0 {
		a.renderErrors(w, r, errors)
		return
	}

	if err := a.store.addItem(shoplist.ID, product_id, quantity); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, fmt.Sprintf("/shoplists/%d", shoplist.ID), Flash{Message: "Product added to list."})
}

func (a *App) updateItem(w http.ResponseWriter, r *http.Request) {
	shoplist, ok := a.findShoplist(w, r)
	if !ok {
		return
	}

	errors := map[string]string{}
	product_id, err := a.validateProductID(r.FormValue("product_id"), errors)
	if err != nil {
		serverError(w, err)
		return
	}
	quantity := validateQuantity(r.FormValue("quantity"), errors)
	if len(errors) > 0 {
		a.renderErrors(w, r, errors)
		return
	}

	fallback := fmt.Sprintf("/shoplists/%d", shoplist.ID)
	on_list, err := a.store.isOnList(shoplist.ID, product_id)
	if err != nil {
		serverError(w, err)
		return
	}
	if !on_list {
		redirectBack(w, r, fallback, Flash{Message: "Product not on this Shop List"})
		return
	}

	if err := a.store.updateItem(shoplist.ID, product_id, quantity); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, fallback, Flash{Message: "Product quantity updated."})
}

func (a *App) removeItem(w http.ResponseWriter, r *http.Request) {
	shoplist, ok := a.findShoplist(w, r)
	if !ok {
		return
	}

	product_id, err := strconv.ParseInt(r.PathValue("product_id"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}

	if err := a.store.removeItem(shoplist.ID, product_id); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, fmt.Sprintf("/shoplists/%d", shoplist.ID), Flash{Message: "Product removed from list."})
}

// products ====================================================================

func (a *App) createProduct(w http.ResponseWriter, r *http.Request) {
	errors := map[string]string{}
	product, err := a.validateProduct(r, 0, errors)
	if err != nil {
		serverError(w, err)
		return
	}

	var shoplist_id, quantity int64
	if r.FormValue("shoplist_id") != "" {
		shoplist, ok := a.findShoplistByValue(w, r, r.FormValue("shoplist_id"))
		if !ok {
			return
		}
		shoplist_id = shoplist.ID
		quantity = validateQuantity(r.FormValue("quantity"), errors)
	}

	if len(errors) > 0 {
		a.renderErrors(w, r, errors)
		return
	}

	if _, err := a.store.createProduct(product, shoplist_id, quantity); err != nil {
		serverError(w, err)
		return
	}

	flash := Flash{Message: "Product created."}
	if shoplist_id != 0 {
		flash.Message = "Product created and added to list."
	}
	if r.FormValue("another") != "" {
		flash.Open = r.FormValue("dialog")
	}
	redirectBack(w, r, "/products", flash)
}

func (a *App) updateProduct(w http.ResponseWriter, r *http.Request) {
	id, err := strconv.ParseInt(r.FormValue("id"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	existing, err := a.store.product(id)
	if err != nil {
		serverError(w, err)
		return
	}
	if existing == nil {
		http.NotFound(w, r)
		return
	}

	errors := map[string]string{}
	product, err := a.validateProduct(r, id, errors)
	if err != nil {
		serverError(w, err)
		return
	}
	if len(errors) > 0 {
		a.renderErrors(w, r, errors)
		return
	}

	if err := a.store.updateProduct(product); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, "/products", Flash{Message: "Product updated."})
}

func (a *App) deleteProduct(w http.ResponseWriter, r *http.Request) {
	id, err := strconv.ParseInt(r.PathValue("id"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}

	if err := a.store.deleteProduct(id); err != nil {
		serverError(w, err)
		return
	}
	redirectBack(w, r, "/products", Flash{Message: "Product deleted successfully."})
}

// validation ==================================================================

func (a *App) validateProduct(r *http.Request, id int64, errors map[string]string) (Product, error) {
	product := Product{
		ID:       id,
		Name:     strings.TrimSpace(r.FormValue("name")),
		SizeType: r.FormValue("size_type"),
		Category: r.FormValue("category"),
	}

	if product.Name == "" {
		errors["name"] = "The name field is required."
	} else if utf8.RuneCountInString(product.Name) > 255 {
		errors["name"] = "The name field must not be greater than 255 characters."
	}

	size_value := strings.TrimSpace(r.FormValue("size"))
	size, err := strconv.ParseInt(size_value, 10, 64)
	switch {
	case size_value == "":
		errors["size"] = "The size field is required."
	case err != nil:
		errors["size"] = "The size field must be an integer."
	case size < 0:
		errors["size"] = "The size field must be at least 0."
	}
	product.Size = size

	if product.SizeType == "" {
		errors["size_type"] = "The size type field is required."
	} else if !isSizeType(product.SizeType) {
		errors["size_type"] = "The selected size type is invalid."
	}

	if product.Category == "" {
		errors["category"] = "The category field is required."
	} else if !isCategory(product.Category) {
		errors["category"] = "The selected category is invalid."
	}

	if len(errors) == 0 {
		duplicate, err := a.store.isDuplicateProduct(product)
		if err != nil {
			return product, err
		}
		if duplicate {
			errors["name"] = "The name has already been taken."
		}
	}

	return product, nil
}

func (a *App) validateProductID(value string, errors map[string]string) (int64, error) {
	if value == "" {
		errors["product_id"] = "The product field is required."
		return 0, nil
	}

	id, err := strconv.ParseInt(value, 10, 64)
	if err != nil {
		errors["product_id"] = "The selected product is invalid."
		return 0, nil
	}

	product, err := a.store.product(id)
	if err != nil {
		return 0, err
	}
	if product == nil {
		errors["product_id"] = "The selected product is invalid."
		return 0, nil
	}
	return id, nil
}

func validateQuantity(value string, errors map[string]string) int64 {
	value = strings.TrimSpace(value)
	quantity, err := strconv.ParseInt(value, 10, 64)
	switch {
	case value == "":
		errors["quantity"] = "The quantity field is required."
	case err != nil:
		errors["quantity"] = "The quantity field must be an integer."
	case quantity < 1:
		errors["quantity"] = "The quantity field must be at least 1."
	}
	return quantity
}

// theme =======================================================================

func (a *App) setTheme(w http.ResponseWriter, r *http.Request) {
	theme := r.FormValue("theme")
	if _, ok := a.themes[theme]; !ok {
		http.Error(w, "unknown theme", http.StatusBadRequest)
		return
	}

	http.SetCookie(w, &http.Cookie{
		Name:     "theme",
		Value:    theme,
		Path:     "/",
		MaxAge:   365 * 24 * 60 * 60,
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
	})
	http.Redirect(w, r, returnPath(r, "/dashboard"), http.StatusSeeOther)
}

func (a *App) theme(r *http.Request) string {
	if cookie, err := r.Cookie("theme"); err == nil {
		if _, ok := a.themes[cookie.Value]; ok {
			return cookie.Value
		}
	}
	return default_theme
}

func (a *App) themeNames() []string {
	names := []string{default_theme}
	for name := range a.themes {
		if name != default_theme {
			names = append(names, name)
		}
	}
	slices.Sort(names[1:])
	return names
}

// helpers =====================================================================

func (a *App) findShoplist(w http.ResponseWriter, r *http.Request) (*Shoplist, bool) {
	return a.findShoplistByValue(w, r, r.PathValue("id"))
}

func (a *App) findShoplistByValue(w http.ResponseWriter, r *http.Request, value string) (*Shoplist, bool) {
	id, err := strconv.ParseInt(value, 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return nil, false
	}

	shoplist, err := a.store.shoplist(id)
	if err != nil {
		serverError(w, err)
		return nil, false
	}
	if shoplist == nil {
		http.NotFound(w, r)
		return nil, false
	}
	return shoplist, true
}

func (a *App) pageData(w http.ResponseWriter, r *http.Request, title string) PageData {
	data := PageData{
		Title:      title,
		Path:       r.URL.RequestURI(),
		Flash:      takeFlash(w, r),
		Categories: categories,
		SizeTypes:  size_types,
		Theme:      a.theme(r),
		Themes:     a.themeNames(),
	}
	if form, ok := r.Context().Value(formStateKey{}).(*FormState); ok {
		data.Form = form
	}
	return data
}

func (a *App) render(w http.ResponseWriter, page string, data PageData) {
	var output strings.Builder
	if err := a.templates[page].Execute(&output, data); err != nil {
		serverError(w, err)
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	fmt.Fprint(w, output.String())
}

// renderErrors re-renders the page named by the form's "return" field with the dialog reopened.
func (a *App) renderErrors(w http.ResponseWriter, r *http.Request, errors map[string]string) {
	target, err := url.Parse(returnPath(r, "/dashboard"))
	if err != nil {
		http.Error(w, "invalid return path", http.StatusBadRequest)
		return
	}

	form := &FormState{Dialog: r.FormValue("dialog"), Values: r.PostForm, Errors: errors}
	page_request := r.Clone(context.WithValue(r.Context(), formStateKey{}, form))
	page_request.Method = http.MethodGet
	page_request.URL = target
	page_request.RequestURI = target.RequestURI()
	page_request.Body = http.NoBody
	page_request.Form = nil
	page_request.PostForm = nil

	// Keeps htmx from pushing the POST url into browser history.
	w.Header().Set("HX-Push-Url", "false")
	a.mux.ServeHTTP(&statusWriter{ResponseWriter: w, status: http.StatusUnprocessableEntity}, page_request)
}

type statusWriter struct {
	http.ResponseWriter
	status       int
	wrote_header bool
}

func (s *statusWriter) WriteHeader(status int) {
	if s.wrote_header {
		return
	}
	s.wrote_header = true
	if status == http.StatusOK {
		status = s.status
	}
	s.ResponseWriter.WriteHeader(status)
}

func (s *statusWriter) Write(contents []byte) (int, error) {
	s.WriteHeader(http.StatusOK)
	return s.ResponseWriter.Write(contents)
}

func returnPath(r *http.Request, fallback string) string {
	path := r.FormValue("return")
	if !strings.HasPrefix(path, "/") || strings.HasPrefix(path, "//") || strings.HasPrefix(path, "/\\") {
		return fallback
	}
	// url.Parse rejects control characters, which browsers strip, turning "/\t/host" into "//host".
	if _, err := url.Parse(path); err != nil {
		return fallback
	}
	return path
}

func redirectBack(w http.ResponseWriter, r *http.Request, fallback string, flash Flash) {
	encoded, err := json.Marshal(flash)
	if err == nil {
		http.SetCookie(w, &http.Cookie{
			Name:     "flash",
			Value:    base64.RawURLEncoding.EncodeToString(encoded),
			Path:     "/",
			HttpOnly: true,
			SameSite: http.SameSiteLaxMode,
		})
	}
	http.Redirect(w, r, returnPath(r, fallback), http.StatusSeeOther)
}

func takeFlash(w http.ResponseWriter, r *http.Request) *Flash {
	cookie, err := r.Cookie("flash")
	if err != nil {
		return nil
	}
	http.SetCookie(w, &http.Cookie{Name: "flash", Path: "/", MaxAge: -1})

	decoded, err := base64.RawURLEncoding.DecodeString(cookie.Value)
	if err != nil {
		return nil
	}
	flash := &Flash{}
	if err := json.Unmarshal(decoded, flash); err != nil {
		return nil
	}
	return flash
}

func serverError(w http.ResponseWriter, err error) {
	log.Printf("error: %v", err)
	http.Error(w, "Internal Server Error", http.StatusInternalServerError)
}
