# Gambar katalog demo

Gambar yang dirujuk HomepageCatalogSeeder disertakan dalam public/images/demo. Resolver gambar event dan hadiah memetakan nama file seed lama ke aset ini, sehingga database demo yang sudah diisi tidak perlu di-seed ulang.

Setelah push, deploy commit terbaru dan pastikan npm run build dijalankan. Aset ini tidak membutuhkan storage:link dan tersedia kembali pada setiap deployment.

Hanya gambar katalog contoh yang disertakan, bukan avatar atau unggahan pengguna. Unggahan melalui admin tetap memerlukan penyimpanan persisten serta konfigurasi public storage. Nama merek dan gambar pihak ketiga mengikuti hak pemiliknya.
