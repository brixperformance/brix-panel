# Update: Migrasi Brill Shop Admin Menu 

Tanggal: 2026-05-26

## Ringkasan

Semua menu admin dari `brix-shop` sudah dipindahkan ke project ini (`brix-panel`). UI/UX mengikuti design system Tabler yang sudah dipakai di project ini (bukan custom admin.css dari brix-shop).

---

## Menu Baru yang Ditambahkan

Sidebar sekarang punya group baru **"Brill Shop"** dengan 4 halaman:

| Menu | URL | Deskripsi |
|------|-----|-----------|
| Shop Dashboard | `/shop/dashboard` | Ringkasan statistik: total produk, pending orders, active referrals, live pricing rules + tabel 8 recent orders |
| Orders | `/shop/orders` | Daftar order dengan filter status & search, pagination, popup detail order per baris |
| Product Pricing | `/shop/pricing` | Manajemen pricing rules per produk: create, edit, disable, delete via Bootstrap modal |
| Referrals | `/shop/referrals` | Manajemen referral code + benefit (product discount & shipping discount), view usage logs |

---

## File Baru yang Dibuat

### Halaman
- `pages/shop-dashboard.php`
- `pages/shop-orders.php`
- `pages/shop-pricing.php`
- `pages/shop-referrals.php`

### Config / Helper
- `application/configs/csrf.php` — CSRF token generator & verifier (menggunakan session yang sudah berjalan)
- `application/configs/shop_pdo.php` — PDO wrapper untuk koneksi DB shop (pakai env vars yang sama)

### Models
- `application/models/ProductDisplay.php` — Helper untuk menampilkan judul produk
- `application/models/ProductPricing.php` — Kalkulasi pricing rules (standalone, tanpa CheckoutPricing)

### Database
- `.dev/shop-tables.sql` — DDL lengkap untuk semua tabel baru

---

## File yang Dimodifikasi

### `templates/sidebar.php`
- Tambah menu group "Brill Shop" dengan icon toko
- Tambah icon SVG `shop` di fungsi `sidebar_icon()`
- Tambah array `$shopRoutes` untuk tracking active state

### `index.php`
- Tambah 4 GET routes: `/shop/dashboard`, `/shop/orders`, `/shop/pricing`, `/shop/referrals`
- Tambah 2 POST routes: `/shop/pricing`, `/shop/referrals` (form submission)
- Tambah 4 case handlers di switch statement

---

## Setup Database

> **Wajib** dilakukan sebelum halaman shop bisa menampilkan data.

1. Buka database yang dipakai project ini (lihat `.env` untuk kredensial)
2. Jalankan file SQL schema:

```bash
mysql -u USER -p DB_NAME < .dev/shop-tables.sql
```

Atau buka file `.dev/shop-tables.sql` di phpMyAdmin / DBeaver dan execute.

**Tabel yang akan dibuat** (semua pakai `CREATE TABLE IF NOT EXISTS`):
- `ms_product_types`
- `ms_products`
- `ms_product_pricing_rules`
- `tr_orders`
- `tr_order_items`
- `tr_payments`
- `tr_referrals`
- `tr_referral_benefits`
- `tr_referral_usages`

> Jika database sudah punya tabel ini (dari brix-shop yang di-migrate), schema tidak akan bentrok karena menggunakan `IF NOT EXISTS`.

---

## Cara Testing

```bash
php -S localhost:8080 router.php
```

Buka di browser: [http://localhost:8080](http://localhost:8080)

1. Login dengan kredensial admin yang sudah ada
2. Di sidebar, klik **"Brill Shop"** untuk membuka group menu baru
3. Navigasi ke masing-masing halaman:
   - `/shop/dashboard` — harus tampil stats card (meski semua 0 jika DB kosong)
   - `/shop/orders` — harus tampil tabel orders (kosong jika tidak ada data)
   - `/shop/pricing` — harus tampil stats + tabel products (kosong jika tidak ada data), ada tombol "Create Rule"
   - `/shop/referrals` — harus tampil stats + tabel referrals (kosong jika tidak ada data), ada tombol "Create Referral"

**Jika database belum di-setup**, setiap halaman shop akan menampilkan alert kuning:
> "Database tidak tersedia. Pastikan tabel shop sudah dibuat. — Lihat .dev/shop-tables.sql"

Ini adalah behavior yang diharapkan dan tidak akan merusak halaman lain.

---

## Catatan Teknis

### Auth
Halaman shop menggunakan session auth yang sama dengan halaman lain (`$_SESSION['logged_in']`). Tidak ada sesi terpisah.

### CSRF
Form POST di halaman pricing dan referrals dilindungi CSRF token. Token disimpan di session yang sudah aktif.

### Graceful Error Handling
Semua halaman shop menangani `PDOException` secara graceful — jika DB tidak tersedia atau tabel belum dibuat, halaman tetap render dengan pesan error yang informatif, tidak crash.

### Koneksi DB
`shop_pdo.php` menggunakan env vars yang sama dengan project (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Jika shop berjalan di database yang berbeda, tambahkan env vars baru seperti `SHOP_DB_NAME` dan sesuaikan `shop_pdo.php`.
