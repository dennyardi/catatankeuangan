# Panduan Upload ke Hosting cPanel

Panduan ini memakai dump database lama `dennyar2_keuangan (1).sql`, lalu menjalankan migrasi `database_migration_hosting.sql` agar aplikasi versi multi-pocket siap dipakai.

## 1. Siapkan Database di cPanel

1. Masuk ke cPanel.
2. Buka `MySQL Databases`.
3. Buat database, contoh: `dennyar2_keuangan`.
4. Buat user database.
5. Assign user ke database dengan hak akses `ALL PRIVILEGES`.
6. Catat:
   - DB host, biasanya `localhost`.
   - DB name.
   - DB user.
   - DB password.

## 2. Import Database Lama

1. Buka `phpMyAdmin` dari cPanel.
2. Pilih database tujuan.
3. Klik tab `Import`.
4. Upload file:
   `dennyar2_keuangan (1).sql`
5. Jalankan import.

Jika import gagal karena collation `utf8mb4_0900_ai_ci`, edit file SQL lama dan ganti semua:

```text
utf8mb4_0900_ai_ci
```

menjadi:

```text
utf8mb4_unicode_ci
```

Lalu ulangi import.

## 3. Jalankan Migrasi Multi-Pocket

Setelah database lama berhasil di-import, tetap di phpMyAdmin:

1. Pilih database yang sama.
2. Klik tab `SQL`.
3. Buka file `database_migration_hosting.sql`.
4. Copy seluruh isinya.
5. Paste ke tab SQL phpMyAdmin.
6. Klik `Go`.

Migrasi ini akan:

- Menambah kolom pocket dan budget ke `categories`.
- Membuat tabel `pockets`.
- Membuat pocket `Uang Belanja Ibu` untuk user lama.
- Menambah `expenses.pocket_id`.
- Mengisi semua transaksi lama ke pocket `Uang Belanja Ibu`.
- Membuat tabel `category_rules`.
- Membuat tabel `notification_settings`.
- Membuat tabel `notification_logs`.

Validasi cepat di phpMyAdmin:

```sql
SELECT p.name, COUNT(e.id) AS total_transaksi
FROM pockets p
LEFT JOIN expenses e ON e.pocket_id = p.id
GROUP BY p.id, p.name;
```

Hasil yang diharapkan:

```text
Uang Belanja Ibu | 76
```

## 4. Upload File Aplikasi

1. Buka `File Manager` di cPanel.
2. Masuk ke folder domain, biasanya `public_html`.
3. Upload semua file aplikasi dari folder:
   `C:\Users\denev\Documents\Codex\keuangan.dennyardi.com`
4. Pastikan struktur file di hosting seperti ini:

```text
public_html/
  api/
  config/
  includes/
  analysis.php
  category_rules.php
  index.php
  input.php
  notification_logs.php
  notifications.php
  pockets.php
  transactions.php
  ...
```

Jangan upload file SQL ke folder publik jika tidak diperlukan. Kalau sudah terlanjur upload, hapus setelah import selesai.

## 5. Sesuaikan Koneksi Database

Copy file:

```text
config/local.example.php
```

menjadi:

```text
config/local.php
```

Lalu isi nilainya sesuai hosting:

```php
return [
    'db_host' => 'localhost',
    'db_name' => 'NAMA_DATABASE_CPANEL',
    'db_user' => 'USER_DATABASE_CPANEL',
    'db_pass' => 'PASSWORD_DATABASE_CPANEL',
    'wa_gateway_url' => 'https://gateway.dennyardi.com/api/proxy.php?endpoint=/message/send-text',
    'wa_gateway_token' => 'TOKEN_GATEWAY',
    'wa_gateway_session' => 'notifikasidenny',
    'wa_allowed_numbers' => '628xxxxxxxxxx,628xxxxxxxxxx',
    'summary_cron_token' => 'TOKEN_CRON_PANJANG',
    'webhook_debug' => '0',
];
```

File `config/local.php` tidak perlu diupload ke GitHub karena berisi data sensitif.

Kalau hosting mendukung Environment Variables, bisa juga isi melalui env:

```text
DB_HOST=localhost
DB_NAME=...
DB_USER=...
DB_PASS=...
WA_GATEWAY_URL=https://gateway.dennyardi.com/api/proxy.php?endpoint=/message/send-text
WA_GATEWAY_TOKEN=...
WA_GATEWAY_SESSION=notifikasidenny
WA_ALLOWED_NUMBERS=628xxxxxxxxxx,628xxxxxxxxxx
SUMMARY_CRON_TOKEN=token_panjang_acak
WEBHOOK_DEBUG=0
```

## 6. Tes Login dan Data

1. Buka domain aplikasi.
2. Login memakai akun lama dari dump:
   - Username: `denny`
   - Password: password lama yang biasa dipakai.
3. Buka `Konfigurasi Pocket`.
4. Pastikan ada pocket `Uang Belanja Ibu`.
5. Buka `Riwayat Transaksi`.
6. Pastikan transaksi lama muncul.
7. Filter pocket ke `Uang Belanja Ibu` untuk memastikan semua transaksi lama sudah masuk pocket tersebut.

## 7. Konfigurasi Group ID

1. Buka `Konfigurasi Pocket`.
2. Isi `Group ID WhatsApp` untuk pocket `Uang Belanja Ibu`.
3. Tambahkan pocket baru bila diperlukan, misalnya `Pengeluaran Ayan`.
4. Isi Group ID masing-masing pocket.
5. Buka `Rule Kategori`.
6. Klik `Isi Rule Awal` jika rule belum ada.

Webhook WhatsApp diarahkan ke:

```text
https://domainkamu.com/api/webhook.php
```

## 8. Konfigurasi Notifikasi Summary

1. Buka `Notifikasi Summary`.
2. Tambahkan konfigurasi baru.
3. Pilih scope:
   - `Semua Pocket`, atau
   - pocket tertentu.
4. Isi `Group ID Tujuan`.
5. Aktifkan summary mingguan atau bulanan.
6. Gunakan `Preview` untuk cek isi pesan.
7. Gunakan `Test` untuk memastikan pesan masuk ke WA.
8. Cek hasilnya di `Histori Notifikasi`.

## 9. Cron Job di cPanel

Di cPanel buka `Cron Jobs`, lalu buat cron harian.

Contoh command mingguan:

```bash
wget -q -O - "https://domainkamu.com/api/send_summary.php?period=weekly&key=TOKEN_KAMU"
```

Contoh command bulanan:

```bash
wget -q -O - "https://domainkamu.com/api/send_summary.php?period=monthly&key=TOKEN_KAMU"
```

Jalankan keduanya 1x per hari, misalnya jam 07:00. Sistem hanya mengirim jika hari/tanggal sesuai konfigurasi dan tidak mengirim dobel pada hari yang sama.

## 10. Checklist Setelah Deploy

- Dashboard terbuka tanpa error.
- Login berhasil.
- Pocket `Uang Belanja Ibu` ada.
- Semua transaksi lama masuk ke pocket `Uang Belanja Ibu`.
- Group ID pocket sudah diisi.
- Webhook WA bisa mencatat transaksi.
- Rule kategori sudah diisi.
- Preview notifikasi tampil.
- Test notifikasi terkirim.
- Cron summary tercatat di `Histori Notifikasi`.
