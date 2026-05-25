# Deploy Notes

Perubahan multi-pocket akan membuat atau memperbarui schema secara otomatis saat aplikasi pertama kali dibuka:

- Membuat tabel `pockets`.
- Menambah kolom `pockets.budget_amount` jika belum ada.
- Membuat tabel `category_rules`.
- Membuat tabel `notification_settings`.
- Membuat tabel `notification_logs`.
- Menambah kolom `expenses.pocket_id` jika belum ada.
- Membuat pocket default `Uang Belanja Ibu` untuk setiap user.
- Mengisi transaksi lama yang belum punya `pocket_id` ke pocket default.

Setelah deploy:

1. Login ke dashboard.
2. Buka menu `Konfigurasi Pocket`.
3. Isi `Group ID WhatsApp` untuk pocket Ibu.
4. Tambah pocket baru, misalnya `Pengeluaran Ayan`, lalu isi `Group ID WhatsApp` miliknya.
5. Isi `Budget Per Periode` untuk setiap pocket jika ingin memantau limit.
6. Buka menu `Rule Kategori`, lalu tambahkan keyword otomatis seperti `beras`, `sayur`, `bensin`.
7. Buka menu `Notifikasi Summary`, lalu buat aturan ringkasan mingguan/bulanan dan isi `Group ID Tujuan`.
8. Gunakan tombol `Preview` untuk melihat isi pesan dan tombol `Test` untuk memastikan Group ID tujuan menerima notifikasi.
9. Tes kirim transaksi dari masing-masing grup.

Environment variable yang disarankan di hosting:

```text
DB_HOST=localhost
DB_NAME=...
DB_USER=...
DB_PASS=...
WA_GATEWAY_URL=https://gateway.dennyardi.com/api/proxy.php?endpoint=/message/send-text
WA_GATEWAY_TOKEN=...
WA_GATEWAY_SESSION=notifikasidenny
WA_ALLOWED_NUMBERS=628xxxxxxxxxx,628xxxxxxxxxx
WEBHOOK_DEBUG=0
SUMMARY_CRON_TOKEN=isi_token_panjang_acak
```

Contoh cron summary:

```text
https://domain.com/api/send_summary.php?period=weekly&key=isi_token_panjang_acak
https://domain.com/api/send_summary.php?period=monthly&key=isi_token_panjang_acak
```

Jalankan cron harian. Sistem akan mengirim hanya jika hari/tanggal sesuai konfigurasi dan tidak akan mengirim dobel pada hari yang sama.
Hasil pengiriman otomatis dan test manual bisa dicek pada bagian `Histori Notifikasi`.

Catatan keamanan:

- `WEBHOOK_DEBUG=1` hanya dipakai saat debugging karena payload webhook bisa berisi data sensitif.
- Simpan token gateway dan password database di environment hosting jika tersedia.
- Webhook grup hanya diterima jika `Group ID` sudah terdaftar pada pocket aktif.
- Endpoint summary wajib memakai `SUMMARY_CRON_TOKEN`; jika token belum diset, endpoint akan menolak request publik.
