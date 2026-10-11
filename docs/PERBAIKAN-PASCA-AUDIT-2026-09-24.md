# Perbaikan seluruh prioritas audit - 24 September 2026

Dasar: AUDIT-PASCA-OPTIMASI-2026-09-24.md. Tidak ada file Vue/CSS/template yang berubah.

## Implementasi

| Temuan | Perbaikan |
| --- | --- |
| P1: cancellation tertolak oleh anti-regresi | Cancellation dan return mendapat transisi khusus; failure tetap tercatat walaupun tahap normal sudah lebih lanjut. Inventory tidak otomatis dilepas dan saldo tidak direfund. Progres tertinggi disimpan agar callback paid lama setelah failure tidak menurunkan tahap |
| P1: respons booking menimpa webhook | Penyimpanan ID provider dan respons booking dilakukan di bawah lock; respons disimpan di booking_response, bukan mengganti seluruh metadata. Status/resi webhook yang lebih baru dipertahankan. Konflik ID provider ditolak |
| P2: resi setelah delivered tidak diperbarui | Metadata tanpa transisi status dapat diperbarui pada status terminal. Webhook order.waybill_id membaca courier_waybill_id dan diproses sebagai metadata saja; tidak membuka kembali order |
| P2: relasi sync berlebihan | loadDetails=false untuk webhook, command sync, rekonsiliasi, dan pembayaran; tidak memuat ulang payment/alamat/seluruh timeline. Inventory dimuat hanya jika perlu transisi status inventory |
| P2: query kategori ganda/O(n^2) | Homepage mengambil kategori sekali, memproyeksikan allCategories, menyaring kategori terpilih dalam memori; loop firstWhere dihapus |
| P2: UPDATE setiap kunjungan | Visibilitas promo memeriksa kedaluwarsa di query tanpa write. Halaman kategori juga menolak promo kedaluwarsa. Pengarsipan dipindah ke scheduler setiap menit |
| P2: histori berat | Pilih kolom tampilan, buang JSON gateway dan kolom yang tidak dipakai; topup, shipment, purchase dihidrasi per 200 baris menggunakan cursor created_at/id, bukan offset. Hasil akhir tetap lengkap dengan struktur yang sama |
| P3: filter/indeks | Tambahkan indeks gabungan user/purpose/created_at/id pada payment dan user/created_at/id pada shipment/draw/sellback. Normalisasi LOWER tetap dipertahankan demi kompatibilitas status lama; tidak mengubah nilai data secara massal |
| P3: jadwal/overlap sync | Sync dijadwalkan setiap lima menit dengan withoutOverlapping. Command juga Isolatable aktif secara default untuk pemanggilan manual. Provider GET tetap serial untuk menjaga batas API tanpa menambah infrastruktur worker |
| Operasional: klaim tanpa order provider | Command pelepasan klaim berdasarkan attempt ID, wajib konfirmasi provider tidak membuat order, worker lama berhenti, serta bukti/alasan. Riwayat pelepasan disimpan; tidak ada POST baru otomatis. Attempt baru memakai UUID dan penulisan hasil memeriksa token klaim |

Referensi event resi: [dokumentasi resmi webhook Biteship](https://biteship.com/en/docs/api/webhook/overview).

## Validasi

- `php artisan test`: **58 lulus, 282 assertion** setelah formatting Pint.
- Tiga kasus audit sekarang menjadi regresi yang mengharapkan hasil benar di ShipmentSafetyTest.
- Fixture 401 topup dengan timestamp sama melewati tiga batch, lengkap dan tanpa duplikasi.
- Homepage terbukti hanya satu query kategori dan tidak melakukan UPDATE pada request; promo kedaluwarsa tetap tidak terlihat sebelum scheduler berjalan.
- EXPLAIN QUERY PLAN SQLite pada filter topup termasuk LOWER(status) menggunakan indeks history_payment_user_purpose_created tanpa temporary B-tree untuk sorting.
- Background sync tidak mengambil alamat, payment, atau timeline lengkap yang tidak dipakai.
- Command pelepasan menolak permintaan tanpa bukti dan attempt ID yang salah.
- Tampilan dan daftar hasil lama diverifikasi melalui tes response. Tidak ada build frontend baru karena tidak ada perubahan frontend/dependensi.

## Aktivasi deployment

Migrasi indeks baru tersedia di `database/migrations/2026_09_24_000001_add_transaction_history_indexes.php`. Migrasi hanya dijalankan oleh suite pada SQLite in-memory; database operasional belum dimigrasikan. Jalankan saat deployment:

```sh
php artisan migrate --force
```

Pastikan scheduler Laravel dipanggil setiap menit oleh cron/Task Scheduler (`php artisan schedule:run`). Definisi jadwal dalam repo sudah ditambahkan, tetapi scheduler OS tidak dibuat/diubah pada sesi ini. Hindari memasang dua scheduler berbeda untuk pekerjaan yang sama. Untuk beberapa server, gunakan cache bersama yang mendukung lock. Isolatable Laravel memakai expiry default satu jam; monitor durasi sync dan kapasitas sebelum backlog sangat besar mendekati durasi tersebut. Jangan menonaktifkan isolasi secara manual.

Pembuatan indeks pada tabel besar dapat memerlukan waktu/lock; lakukan deployment pada jendela yang sesuai setelah pemeriksaan staging MySQL.

## Pemulihan booking ambigu

Jika provider sudah membuat order, tetap gunakan:

```sh
php artisan shipments:reconcile-booking ORDER_NO ID_PROVIDER
```

Jika provider memastikan tidak ada order dan worker lama sudah dihentikan:

```sh
php artisan shipments:release-uncreated ORDER_NO ATTEMPT_ID --confirmed-not-created --worker-stopped --reason="Tiket provider dan hasil pemeriksaan operator"
```

ATTEMPT_ID berasal dari external_response_json.booking_attempt.id; untuk klaim lama tanpa UUID, gunakan started_at persis. Command menolak klaim yang tidak cocok, order yang sudah punya ID provider, dan shipment yang tidak eligible. Bukti operator wajib benar: aplikasi tidak dapat membuktikan ketiadaan order provider hanya dari timeout. Setelah pelepasan, retry memakai alur admin yang sudah ada. Tidak ada TTL yang melepas klaim POST otomatis.

## Batas dan perubahan yang sengaja tidak dilakukan

Semua prioritas mendapat penanganan kode atau mekanisme operasional di atas. Respons histori akhir masih berukuran sebanding seluruh histori karena kontrak frontend lama menerima daftar lengkap. Batching membatasi model/relasi yang dihidrasi sekaligus, bukan membuat payload akhir konstan. Sellback tetap dikelompokkan sebagai transaksi gabungan agar nilainya tidak terpecah lintas batch. Pagination/lazy detail tidak dipaksakan karena mengubah kontrak pemuatan halaman.

EXPLAIN MySQL, beban paralel nyata, scheduler OS, dan provider sandbox belum diuji. Tidak ada klaim percepatan latency produksi, tidak ada refund, pelepasan inventory, penghapusan data, atau booking nyata yang dijalankan.

## File utama

- app/Actions/Shipment/{SyncShipmentStatusAction,BookBiteshipOrderAction,MarkShipmentOrderPaidAction}.php
- app/Http/Controllers/Web/{BiteshipWebhookController,HomeController,CategoryPageController,TransactionController}.php
- app/Models/GachaCategory.php
- app/Console/Commands/{SyncPendingShipments,ReconcileShipmentBooking,ReleaseUncreatedShipmentBooking}.php
- routes/console.php
- database/migrations/2026_09_24_000001_add_transaction_history_indexes.php
- tests/Feature/{ShipmentSafetyTest,ReadQueryOptimizationTest}.php
