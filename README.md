# SIMAPAT

Website Laravel untuk penjadwalan rapat, konfirmasi peserta, agenda, notulensi, tindak lanjut, klien, pengingat dalam website, dan rekap rapat. Kebutuhan bersumber dari vault Obsidian SIMAPAT. Flutter tidak digunakan.

## Lingkungan lokal

Implementasi menggunakan PHP 8.5, Laravel 13, Blade, Vite, dan SQLite. Versi paket dikunci dalam composer.lock/package-lock.json. Zona waktu Asia/Jakarta (WIB).

Dari direktori proyek:

```powershell
composer install
npm ci
# Untuk checkout baru saja: salin .env.example ke .env lalu php artisan key:generate
php artisan migrate
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Buka http://localhost:8000/login. Jangan menjalankan migrate:fresh pada database berisi data yang ingin disimpan. Database lokal ada di database/database.sqlite. Laravel Boost tersedia sebagai dependency pengembangan.

## Akun

Pendaftaran publik melalui halaman Daftar selalu memberikan role User basic. Admin mengatur role dan menautkan akun Klien ke perusahaan melalui menu Pengguna. Membuat Admin pertama pada lingkungan baru:

```powershell
php artisan simapat:admin admin admin@example.com --name="Admin SIMAPAT"
```

Perintah menampilkan password acak satu kali. Seeder lokal opsional:

```powershell
php artisan db:seed --class=DemoSeeder
```

Seeder membuat akun dan data fiktif. Untuk memperbarui kredensial dummy tanpa menambah rapat, jalankan `php artisan db:seed --class=DemoAccountSeeder --no-interaction`.

| Username | Role | Password lokal |
| --- | --- | --- |
| admin | Admin | admin123 |
| operator | Operator | operator123 |
| peserta | User basic / PIC internal | user123 |
| klien | Klien | klien123 |

Seeder hanya berjalan di local/testing, menolak akun non-demo dengan username yang sama, dan mempertahankan akun pribadi. Perubahan password dummy membatalkan sesi lamanya. Password pendek ini khusus demo lokal; jangan gunakan akun demo untuk data klien nyata.

Admin mengelola akun, membaca seluruh rapat/laporan, dan bernegosiasi mewakili perusahaan. Operator mengelola klien serta rapat miliknya. User basic/PIC internal mengakses rapat dan tugas sesuai penugasan. Klien hanya melihat negosiasi perusahaan yang ditautkan; tugas internal tidak ditampilkan. Klien tidak dapat dijadikan PIC internal. Akun Operator dengan rapat aktif tidak dapat diganti rolenya sebelum rapat ditutup.

## Pengingat website

Buka terminal kedua dan biarkan proses berikut berjalan:

```powershell
php artisan schedule:work
```

Scheduler memeriksa reminder setiap menit. Pemeriksaan satu kali:

```powershell
php artisan meetings:send-reminders
```

Kanal awal hanya database notification, dibaca pada menu Pengingat setelah login. Email untuk negosiasi jadwal telah disiapkan; pengingat rutin tetap melalui website. Pilihan per peserta: H-1 (24 jam sebelum mulai), pagi pukul 07.00 WIB, atau tanpa pengingat. Rapat sebelum/pukul 07.00 menggunakan H-1. Jika waktu pengingat terlewat, pengiriman dijadwalkan pada pemeriksaan berikutnya selama rapat belum dimulai. Pengingat lama dibatalkan saat penjadwalan berubah; versi rapat dan transaksi mencegah pengiriman ganda. Kegagalan dicatat dan dicoba pada pemeriksaan berikutnya.

Untuk hosting, jalankan Laravel scheduler setiap menit melalui cron/Task Scheduler sesuai server, dengan direktori kerja proyek. Queue worker diperlukan untuk email negosiasi dan broadcast chat.

## Negosiasi dan chat real time

Admin dan Operator pemilik rapat adalah satu pihak perusahaan. Ajuan perusahaan harus disetujui Klien; ajuan Klien dapat disetujui Admin atau Operator pemilik. Jadwal berubah setelah persetujuan formal. Edit agenda tidak menghapus ajuan aktif. Akun Klien yang baru ditautkan menerima ajuan perusahaan yang masih aktif.

Chat per rapat hanya tersedia bagi Klien terkait dan Operator pemilik. Admin menggunakan alur negosiasi formal. Pesan tersimpan di database, maksimal 2.000 karakter, dibatasi 30 kiriman per menit, dan retry dengan ID sama tidak menggandakan pesan. Setelah koneksi tersambung kembali, riwayat disinkronkan. Rapat yang ditutup tetap menyimpan chat sebagai arsip baca.

Untuk menjalankan server, Vite, queue worker dan Reverb sekaligus:

```powershell
composer run dev
```

Alternatif terminal terpisah selain server Laravel:

```powershell
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan queue:work --tries=3
```

Reverb menggunakan variabel `REVERB_*` dan `VITE_REVERB_*` di `.env`. Isi key/secret acak untuk lingkungan baru. Setelah mengubah variabel frontend jalankan `npm run build`. Untuk akses dari perangkat lain sesuaikan host dan gunakan HTTPS/WSS pada hosting.

Email masih memakai `MAIL_MAILER=log`, sehingga tidak dikirim ke inbox. Untuk mengaktifkannya isi konfigurasi SMTP di `.env`, jalankan `php artisan config:clear`, dan mulai ulang queue worker. Jangan menaruh password SMTP dalam kode.

## Aturan awal implementasi

Interval jadwal [mulai, selesai): rapat berdempetan diperbolehkan. Benturan diperiksa untuk setiap peserta termasuk perubahan peserta, dengan transaksi serta lock; SQLite memakai IMMEDIATE. Satu klien opsional, satu agenda dan maksimum satu notulensi per rapat. Notulensi dicatat Operator pemilik setelah rapat mulai. Agenda dapat dihapus sebelum rapat mulai, dengan konfirmasi.

Rapat dibatalkan tanpa menghapus riwayat. Rapat dapat ditandai selesai setelah waktu berakhir dan notulensi tersimpan, tanpa mewajibkan seluruh tugas selesai. Akun/klien yang masih dipakai tidak dapat dihapus. Status tugas: belum mulai, berjalan, selesai.

Jam reminder, aturan penutupan, notulen, dan kebijakan operasional di atas merupakan pilihan implementasi awal; keputusan klien lainnya tetap mengikuti catatan terbuka di Obsidian.

## Verifikasi

```powershell
php artisan test --compact
php vendor/bin/pint --format agent
npm run build
```

Pengujian memakai database terpisah. Test konkurensi memakai file SQLite sementara dan dua proses PHP sungguhan. Test reminder menyimulasikan waktu, pengulangan, perubahan jadwal, penghapusan peserta, pembatalan, kegagalan kirim/retry, dan pencabutan akses.

Belum divalidasi: penerimaan oleh klien, backup/restore operasional, kapasitas/SLA, atau deployment produksi/MySQL. Sebelum produksi tentukan target hosting/database, siapkan backup beserta uji pemulihan, HTTPS, APP_DEBUG=false, dan akun non-demo.

