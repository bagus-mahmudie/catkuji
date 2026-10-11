# Optimasi internal tanpa perubahan tampilan

Tanggal: 23 September 2026.

## Perubahan

| Area | Sebelum | Sesudah |
| --- | --- | --- |
| Homepage | Satu query event untuk setiap kategori | Eager loading dalam maksimal dua kelompok: standard dan promo. Limit empat event diterapkan per kategori standard di database; seluruh event promo tetap ditampilkan |
| Transaksi | Semua jenis transaksi dimuat walaupun filter layanan aktif | Hanya query layanan yang dipilih dijalankan |
| Filter status | Model dan relasi dimuat lalu disaring dalam memori | Status pembayaran, shipment, dan purchase disaring di database sebelum hydration; alias status dan fallback other tetap sama |
| Relasi transaksi | Event purchase dan walletTransaction sellback dimuat tetapi tidak digunakan | Eager loading yang tidak dipakai dihapus |
| Sync shipment | Seluruh shipment pending dimuat sekaligus | lazyById(100), sehingga perubahan status selama proses tidak menyebabkan baris berikutnya terlewat |
| Hasil sync | Command selalu mengembalikan sukses meski terjadi error | Tetap memproses order lain, tetapi exit code gagal bila ada order gagal; monitoring dapat mendeteksi kegagalan |
| Resi sync | Hanya membaca field tingkat atas | Memprioritaskan courier.waybill_id, dengan fallback format lama |
| Webhook | Identifier alternatif memakai OR dan memilih order terbaru | Ambil maksimal dua kandidat di bawah lock; tolak kandidat ambigu atau identifier yang bertentangan; pemilihan dan sync berada dalam satu transaksi |
| Input webhook | Nilai array dapat dipaksa menjadi string | Identifier hanya menerima string/integer |
| Logging webhook | Seluruh payload dicatat sebelum autentikasi | Pencatatan payload masuk tanpa autentikasi dihapus; logging kegagalan yang ada tetap tersedia |
| Tracking duplikat | Relasi memiliki order ascending sebelum latest, sehingga pemeriksaan bisa membaca event lama | Reset order lalu urut event_time dan id descending; pemeriksaan duplikat membaca event terakhir yang benar |
| Query tracking | Semua trackingEvents dimuat sebelum proses, lalu event terakhir diquery ulang | Eager loading awal seluruh timeline dihapus; bentuk response akhir tetap dipertahankan |

Tidak mengubah file Vue/CSS, label, aturan harga, saldo/stok, jumlah data yang tersedia, maupun kontrak response frontend. Tidak menambahkan caching saldo atau stok. Tidak menjalankan mutasi data operasional atau panggilan provider nyata.

## Pengujian dan bukti

- `php artisan test`: **48 lulus, 235 assertion** setelah formatting Pint.
- Fixture tiga kategori dengan enam event masing-masing: query event homepage **2**, kategori standard tetap **4 event per kategori**, promo tetap **6 event**; pencarian juga mempertahankan hasil yang benar.
- Filter topup: hasil setiap status dibandingkan dengan penyaringan response lengkap, termasuk status huruf besar, unknown/other, dan status tanpa hasil. Query shipment, draw, dan sellback tidak dijalankan ketika service=topup.
- Filter shipment: hasil dibandingkan dengan response lengkap untuk pending, processing, shipped, delivered, failed, cancelled, other, dan status tanpa hasil.
- Webhook dengan reference_id dan ID provider milik dua order berbeda ditolak tanpa mengubah keduanya.
- Webhook processing lalu shipped dua kali menghasilkan hanya dua tracking events, termasuk saat waktu event sama.
- Sync 101 shipment melintasi batch 100; seluruhnya mencapai delivered tanpa terlewat meski keluar dari filter selama proses.
- Kegagalan satu provider request tidak menghentikan order lain dan membuat command melaporkan exit code gagal.

## Batas optimasi

Ini bukti jumlah query dan kesetaraan perilaku pada SQLite terisolasi, bukan benchmark latency/memori produksi. Uji concurrency multi-worker MySQL tetap diperlukan. Tidak ada perubahan dependensi atau frontend pada tahap ini; build frontend terakhir berasal dari tahap pertama.

Halaman transaksi tanpa filter masih mengembalikan seluruh histori. Pagination atau lazy detail membutuhkan perubahan kontrak/pemuatan frontend agar semua data tetap dapat diakses; tidak diterapkan diam-diam dengan memotong data. Kategori promo tetap tanpa limit sesuai perilaku lama.

Helper URL gambar belum disatukan karena implementasi yang ada memiliki fallback berbeda; menyatukannya tanpa kontrak yang seragam berisiko mengubah gambar yang tampil. Indeks tambahan dan caching belum diterapkan tanpa profil query produksi. Penanganan booking ambigu dan prosedur rekonsiliasi dari tahap sebelumnya tetap berlaku; klaim tidak dihapus otomatis.

## File

- app/Http/Controllers/Web/HomeController.php
- app/Http/Controllers/Web/TransactionController.php
- app/Http/Controllers/Web/BiteshipWebhookController.php
- app/Console/Commands/SyncPendingShipments.php
- app/Actions/Shipment/SyncShipmentStatusAction.php
- tests/Feature/ReadQueryOptimizationTest.php (baru)
- tests/Feature/ShipmentSafetyTest.php

Lihat juga [audit tahap pertama](AUDIT-DAN-PERBAIKAN-2026-09-23.md) dan [perbaikan pengiriman](AUDIT-LANJUTAN-PENGIRIMAN-2026-09-23.md).
