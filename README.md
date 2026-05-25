# MyFinance

Aplikasi pencatatan keuangan berbasis PHP native, Tailwind CSS, MySQL, dan webhook WhatsApp.

Fitur utama:

- Dashboard ringkasan pengeluaran.
- Multi-pocket berdasarkan Group ID WhatsApp.
- Rule kategori per pocket atau semua pocket.
- Limit pocket dan limit kategori.
- Laporan dan analisis.
- Notifikasi summary mingguan/bulanan via WhatsApp.
- Histori notifikasi.

## Konfigurasi

Copy file contoh:

```text
config/local.example.php
```

menjadi:

```text
config/local.php
```

Lalu isi kredensial database, token gateway WhatsApp, nomor yang diizinkan, dan token cron.

File `config/local.php` sengaja masuk `.gitignore` supaya rahasia hosting tidak ikut terupload ke GitHub.

## Deploy cPanel

Ikuti panduan:

```text
CPANEL_DEPLOY_GUIDE.md
```

Untuk database lama, import dump lama terlebih dahulu, lalu jalankan:

```text
database_migration_hosting.sql
```
