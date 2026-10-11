# Inventory Management System (Mini Project 3)
> **Mata Kuliah:** Web Programming / Relational Database + Advanced PHP  
> **Pertemuan 4:** Dari CRUD Menuju Aplikasi Berbasis Relasi, Transaksi, Laporan, dan Business Logic

Aplikasi Sistem Informasi Manajemen Inventaris Gudang berbasis Web PHP Native & MySQL dengan arsitektur relasional, keamanan tinggi (*Prepared Statements*, proteksi XSS, *password hashing*), serta *Database Transactions* (*Commit/Rollback*).

---

## Fitur Utama Sistem

Sistem ini dibangun lengkap untuk memenuhi capaian dan rubrik penilaian tugas Pertemuan 4:

1. **Autentikasi & Otorisasi Berbasis Role:**
   - Multi-role: **Administrator** dan **Staff**.
   - Keamanan login dengan `password_verify` dan pembaruan session ID `session_regenerate_id(true)`.
   - Proteksi akses server-side dengan `require_role('admin')` & template `HTTP 403 Forbidden`.

2. **Relasi Database (ERD):**
   - **One-to-One:** Tabel `users` $\leftrightarrow$ `user_profiles` (FK dengan constraint `UNIQUE`).
   - **One-to-Many:** Tabel `categories` $\rightarrow$ `products` (FK `category_id` dengan `ON DELETE RESTRICT`).
   - **Many-to-Many:** Tabel `products` $\leftrightarrow$ `suppliers` via tabel perantara (*junction table*) `product_suppliers`.
   - **Audit Trail:** Tabel `products` & `users` $\rightarrow$ `stock_movements`.

3. **Logika Bisnis & Transaksi Database (*Database Transactions*):**
   - **Barang Masuk (IN):** Validasi kuantitas positif, penambahan stok, pencatatan histori transaksi dalam satu kesatuan `beginTransaction()` dan `commit()`.
   - **Barang Keluar (OUT):** Pengecekan row locking `SELECT ... FOR UPDATE`, validasi kuantitas tidak melebihi stok yang tersedia (*anti-stok negatif*), pengurangan stok, dan pembatalan otomatis (*rollback*) jika stok tidak cukup.
   - **Proteksi Hapus Data:** Produk yang memiliki histori transaksi tidak dapat dihapus sembarangan untuk menjaga keaslian audit pembukuan.

4. **Listing Data Lanjutan (*Products Catalog*):**
   - **Search:** Pencarian instan berdasarkan nama produk atau kode SKU.
   - **Filter:** Penyaringan berdasarkan kategori barang.
   - **Sorting Aman:** Pengurutan (Nama, Harga, Stok) menggunakan validasi *Allowlist* (mencegah SQL Injection via `ORDER BY`).
   - **Pagination:** Pembagian halaman dinamis dengan mempertahankan parameter filter dan pencarian.

5. **Dashboard & Laporan Agregat:**
   - Ringkasan 5 metrik utama: Total Produk, Nilai Total Inventaris (`SUM(stock * price)`), Produk Stok Rendah, Total Barang Masuk, dan Total Barang Keluar.
   - Laporan stok per kategori menggunakan `GROUP BY` dan fungsi agregat (`COUNT`, `SUM`, `AVG`).
   - Laporan pergerakan arus masuk & keluar per barang (`SUM(CASE WHEN type='IN' ...)`).
   - Indikator peringatan dini (*warning banner*) untuk barang dengan stok $\le$ batas minimum.

---

## Struktur Direktori Proyek

```text
inventory-app/
├── config/
│   └── database.php           # Konfigurasi koneksi PDO MySQL (Error Mode Exception)
├── helpers/
│   ├── auth.php               # Helper sesi login & pembatasan hak akses role
│   ├── escape.php             # Sanitasi output encoding htmlspecialchars (Anti-XSS)
│   └── validation.php         # Validasi input server-side & sorting allowlist
├── database/
│   └── schema.sql             # Skema DDL tabel relasional & data awal (seed)
├── views/
│   ├── header.php             # Navigasi UI navbar & komponen visual
│   ├── footer.php             # Footer & aset script bootstrap
│   └── 403.php                # Tampilan halaman akses ditolak (HTTP 403 Forbidden)
└── public/
    ├── index.php              # Router pengalihan ke dashboard/login
    ├── login.php              # Form login pengguna
    ├── logout.php             # Proses keluar sesi aman
    ├── dashboard.php          # Halaman dashboard metrik & laporan
    ├── products/              # Modul CRUD Master Produk, Search, Filter, Pagination
    ├── categories/            # Modul Master Kategori Produk
    ├── suppliers/             # Modul Master Data Supplier (Many-to-Many)
    ├── users/                 # Modul Administrasi User & Profil (Khusus Admin)
    └── stock/                 # Modul Transaksi Masuk/Keluar & Riwayat Transaksi
```

---

## Panduan Instalasi & Menjalankan Aplikasi

### 1. Prasyarat
- XAMPP (Apache & MySQL)
- Web Browser (Google Chrome / Mozilla Firefox / Microsoft Edge)

### 2. Persiapan File
1. Ekstrak file `inventory-app.zip` ke dalam direktori `htdocs` XAMPP Anda:
   ```text
   C:\xampp\htdocs\inventory-app
   ```

### 3. Impor Basis Data (Database)
1. Buka browser dan akses **phpMyAdmin**:
   ```text
   http://localhost/phpmyadmin
   ```
2. Klik tab **Import** pada bagian atas menu phpMyAdmin.
3. Klik tombol **Choose File / Telusuri File**, lalu pilih file skema:
   ```text
   C:\xampp\htdocs\inventory-app\database\schema.sql
   ```
   *(Atau file `schema.sql` yang ada di folder Downloads Anda)*.
4. Klik tombol **Go / Kirim / Import** di bagian bawah.
5. Basis data `inventory_db` beserta 7 tabel dan data awal berhasil dibuat.

### 4. Mengakses Aplikasi
Buka browser dan buka tautan berikut:
```text
http://localhost/inventory-app/public/login.php
```

---

## Akun Demo Pengujian Praktikum

Gunakan akun berikut untuk menguji fitur aplikasi:

| Role | Username | Password | Keterangan & Hak Akses |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | Memiliki akses penuh ke seluruh menu (Dashboard, Produk, Kategori, Supplier, Transaksi Stok, dan Kelola Pengguna). |
| **Staff** | `staff` | `staff123` | Hanya dapat melihat produk, mencatat barang masuk/keluar, dan melihat histori stok. Dilarang mengakses modul Kelola Pengguna. |

> [!TIP]
> **Skenario Uji Akses Terlarang (Slide 38):**
> 1. Masuk (*login*) menggunakan akun `staff`.
> 2. Coba akses menu URL: `http://localhost/inventory-app/public/users/index.php`.
> 3. Sistem akan otomatis memblokir dan menampilkan halaman respons **HTTP 403 Forbidden**.
