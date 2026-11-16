# Penggajian Mutiara Hikmah

Aplikasi penggajian lokal berbasis PHP MVC, PDO, MySQL 8, Bootstrap 5.3.8, dan Chart.js 4.5.1. Tampilan mengikuti referensi dashboard pengguna dengan sidebar putih dan aksen hijau bergradasi. Aset antarmuka tersedia lokal tanpa CDN saat aplikasi dijalankan.

## Kerja bersama melalui GitHub dan VS Code

Repo utama tim: **https://github.com/lakchamana/system-penggajian**. Anggota yang diundang sebagai kolaborator bekerja langsung pada repo ini agar **Sync Changes** mengambil dan mengirim perubahan ke tempat yang sama. Terima undangan GitHub menggunakan akun yang diundang. Repo pribadi anggota tidak otomatis menerima perubahan dari repo tim.

Untuk anggota yang belum memiliki project:

```powershell
git clone https://github.com/lakchamana/system-penggajian.git
cd system-penggajian
code .
```

Jika sudah memiliki project, periksa `git remote -v`. Bila `origin` menunjuk ke repo pribadi, arahkan kembali ke repo tim lalu ambil informasi branch:

```powershell
git remote set-url origin https://github.com/lakchamana/system-penggajian.git
git fetch origin
git branch --set-upstream-to=origin/main main
```

Gunakan branch **main**. Sebelum mulai bekerja, klik **Source Control > Sync Changes** untuk mengambil pembaruan teman. Setelah mengedit, tinjau perubahan, lakukan **Stage Changes**, isi pesan, klik **Commit**, lalu **Sync Changes** untuk mengirimnya. Commit yang belum di-push belum tersedia bagi anggota lain. Jika ada perubahan lokal yang belum siap di-commit, simpan dengan **Stash** sebelum sinkronisasi dan terapkan kembali setelahnya. Jika muncul konflik, selesaikan file yang ditandai, lalu commit dan sinkronkan kembali.

Pengaturan project mengaktifkan `git.autofetch` agar VS Code mengecek pembaruan remote secara berkala. Fetch memperbarui informasi commit; file kerja diperbarui saat **Sync Changes** atau **Pull** dijalankan.

Pada komputer ini, remote **origin** menunjuk ke repo tim `lakchamana/system-penggajian`. Remote **personal** menunjuk ke salinan `Jayszrs/system-penggajian`. **Sync Changes** memakai repo tim; jika ingin memperbarui salinan pribadi setelah sinkronisasi, jalankan:

```powershell
git push personal main
```

Salinan pribadi bersifat opsional dan tidak perlu digunakan untuk bekerja bersama tim. Sinkronisasi Git hanya membagikan source code; `.env`, data gaji, dan database lokal tidak ikut disinkronkan.

## Membuka aplikasi

1. Klik **Start All** pada Laragon untuk mengaktifkan Apache dan MySQL.
2. Pada penggunaan pertama atau setelah folder proyek dipindahkan/diubah namanya, klik dua kali **`JALANKAN.bat`** di folder proyek. Launcher menghubungkan `www/system-penggajian` ke folder proyek saat ini dan membuka **http://localhost/system-penggajian/**. Pada penggunaan berikutnya cukup **Start All**, lalu buka alamat tersebut. Tidak perlu menyalin proyek ke folder `www` atau menjalankan server PHP terpisah.
3. Jika database belum memiliki akun, buat nama, ID pengguna, email pemulihan, dan kata sandi minimal 12 karakter. Jika memakai backup SQL, masuk menggunakan akun yang ada dalam backup. Aplikasi tidak menyediakan kredensial bawaan.
4. Masuk. Periode awal mengikuti transaksi terbaru yang tersedia pada database; tanpa transaksi aplikasi menampilkan keadaan kosong.

Pada instalasi lokal awal, data aplikasi utama sudah dimigrasikan. Repositori publik hanya berisi kode, skema, dan dokumentasi: backup, ekspor database, nominal gaji asli, kredensial, serta hasil QA yang memuat data pribadi tetap disimpan lokal dan tidak disertakan. Instalasi baru perlu melakukan migrasi menggunakan backup milik pengguna. Database `penggajian_test` digunakan khusus pengujian dan tidak menjadi database aplikasi utama.

## Instalasi pada komputer lain

Persyaratan: PHP 8.1 atau lebih baru dengan `pdo_mysql`, `bcmath`, `mbstring`, `session`, `json`, dan `openssl` (AES-256-GCM); MySQL 8; Apache dengan `mod_rewrite` dan `AllowOverride All`.

Salin `.env.example` menjadi `.env`, sesuaikan koneksi database, kemudian jalankan:

```powershell
composer install --no-dev
php scripts/install.php
```

Gunakan nama database kosong yang khusus untuk aplikasi ini. Installer membuat tabel yang belum ada dan tidak menghapus tabel atau data yang sudah tersedia. Arahkan virtual host ke direktori proyek; `.htaccess` melindungi source, konfigurasi, arsip, skrip, dan dokumentasi dari HTTP. Untuk jaringan kantor, arahkan akses ke komputer Laragon tersebut sesuai pengaturan jaringan setempat.

### Akun tambahan dan lupa kata sandi melalui SMTP

Pada instalasi lama, jalankan `composer install --no-dev` lalu `php scripts/migrate-auth.php` untuk menambahkan email pemulihan dan tabel reset tanpa mengganti akun lama. Akun lama perlu menambahkan email melalui **Profil Saya**, menggunakan kata sandi saat ini.

Untuk membuat akun tambahan dari terminal lokal:

```powershell
php scripts/create-user.php --username=nama_pengguna --name="Nama Lengkap" --email=alamat@example.com
```

Skrip menghasilkan kata sandi acak yang ditampilkan sekali di terminal. ID atau email yang sudah dipakai ditolak; akun lama tidak ditimpa. Ubah kata sandi sementara melalui **Profil Saya** setelah masuk.

Tautan **Lupa kata sandi?** di halaman masuk mengirim tautan reset lewat PHPMailer SMTP. Isi `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_AUTH`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM_ADDRESS`, dan `MAIL_FROM_NAME` di `.env`. Contoh Gmail memakai `smtp.gmail.com`, port `587`, enkripsi `tls`, dan `SMTP_AUTH=true`; isi username serta alamat pengirim dengan email Gmail pengirim. `SMTP_PASSWORD` memakai App Password Google. Pembuatan App Password memerlukan Verifikasi 2 Langkah; lihat [panduan resmi Google](https://support.google.com/accounts/answer/185833?hl=id). Kredensial cukup disimpan di `.env` lokal.

Pastikan `APP_URL` sama dengan alamat aplikasi yang dapat dibuka penerima email, termasuk subfolder, misalnya `http://localhost/system-penggajian`. Alamat localhost hanya dapat digunakan pada komputer yang menjalankan aplikasi. Untuk akses komputer lain, gunakan alamat server yang dapat dijangkau penerima.

Token reset disimpan sebagai hash, berlaku 30 menit, dan hanya bisa dipakai sekali. Permintaan dibatasi maksimal 5 per IP dan 3 per email dalam 15 menit; percobaan berulang memperpanjang masa pembatasan. Tautan baru menggantikan tautan sebelumnya setelah SMTP menerima pesan. Kegagalan SMTP mempertahankan tautan sebelumnya dan mencatat kesalahan tanpa kredensial atau token ke `storage/app.log`. Respons permintaan tidak mengungkap apakah email terdaftar. Reset kata sandi mengakhiri sesi lama. Penyimpanan profil membatalkan tautan reset dan sesi lain yang masih aktif.

Jalankan `node tests/password-reset.js` untuk menguji SMTP lokal, reset, kedaluwarsa, pembatasan, invalidasi sesi, dan tampilan desktop/ponsel. Pengujian memakai database sementara `penggajian_auth_test_*` dan server SMTP penangkap lokal, lalu membersihkannya. Gunakan `PHP_PATH` bila executable PHP dengan `pdo_mysql` belum ada di PATH. Pengujian ini tidak mengirim email internet dan tidak menyentuh database kerja.

### Portal slip gaji karyawan

Portal membedakan **superadmin** dan **karyawan**. Akun pengelola lama tetap menjadi superadmin. Untuk memperbarui instalasi, cadangkan database, lalu jalankan:

```powershell
composer install
php scripts/configure-employee-portal.php
php scripts/migrate-auth.php
php scripts/seed-employee-portal.php
```

Konfigurasi membuat `EMPLOYEE_PASSWORD_KEY` acak di `.env` lokal dan mempertahankan kunci yang sudah ada. Installer baru sudah memasang skema akun; konfigurasi kunci dan seeder tetap dijalankan secara eksplisit. Seeder hanya menambahkan tiga karyawan dummy berikut beserta slip Oktober 2026 pada bagian `DMY`:

| Nama | Nomor induk / ID login | Gaji bersih contoh |
|---|---|---:|
| Dummy Karyawan 1 | `DUMMY001` | Rp3.000.000 |
| Dummy Karyawan 2 | `DUMMY002` | Rp3.500.000 |
| Dummy Karyawan 3 | `DUMMY003` | Rp4.000.000 |

Password awal ketiganya adalah **`Mutiara@2026!`** dari `EMPLOYEE_INITIAL_PASSWORD` di konfigurasi lokal. Password karyawan menggunakan 8-72 karakter dan harus berbeda dari password saat ini maupun password awal. Pengulangan seeder mempertahankan password pribadi dan status akun; benturan identitas atau perubahan data dummy menghentikan proses tanpa menimpa data.

Setelah login, karyawan langsung membuka **Slip Gaji Saya**, termasuk ketika masih memakai password awal. Popup pengingat muncul sekali per sesi login selama password awal belum diganti, dengan pilihan **Ubah Sekarang** atau **Nanti**. Menunda pengingat tetap mengizinkan akses slip. Setelah password diganti, popup berhenti muncul dan sesi lain dibatalkan.

Halaman slip hanya berisi filter **Bulan**, **Tahun**, tombol **Cetak** dan **Unduh PDF**, serta dokumen slip yang langsung terlihat. Periode awal menggunakan bulan/tahun berjalan dalam zona waktu **Asia/Jakarta**, meskipun transaksi terbaru berada pada periode lain. Mengubah bulan atau tahun langsung memperbarui dokumen melalui GET tanpa membuka dialog cetak. Tahun mencakup tahun berjalan, tahun terpilih, dan riwayat milik karyawan. Periode kosong menampilkan lembar skeleton statis dengan pesan **Slip gaji {bulan} {tahun} belum tersedia.** serta kedua tombol tindakan nonaktif; periode tidak valid ditolak dengan HTTP 422.

Pratinjau, cetakan, dan unduhan memakai satu template bergaya **legacy**: Times New Roman, hitam-putih, penghasilan/potongan berdampingan, garis tipis, serta tanda tangan di bawah. Kertas tetap **A4 portrait**, margin 12 mm, satu transaksi per halaman, menggunakan nominal tersimpan. Pada ponsel, kedua kelompok tersusun vertikal agar tetap terbaca. Tombol **Cetak** membuka dialog printer browser. **Unduh PDF** langsung mengunduh file `slip-gaji-YYYY-MM.pdf` tanpa dialog cetak melalui GET `/karyawan/slip/pdf?bulan=...&tahun=...`. Endpoint memeriksa sesi dan kepemilikan dari akun, menolak periode kosong/tidak valid, dan tidak menyimpan PDF di server maupun cache. Pembuatan PDF menggunakan [Dompdf](https://github.com/dompdf/dompdf); jalankan `composer install` setelah menarik pembaruan. Akses jaringan dan eksekusi PHP/JavaScript dinonaktifkan pada renderer. Setelah dialog ditutup, slip dan filter tetap terlihat. URL detail lama tetap memeriksa kepemilikan lalu mengarahkan ke periode terkait; parameter `view=1` lama tidak mencetak otomatis. Kartu identitas tambahan, judul pengantar, promosi sidebar, dan footer tambahan tidak ditampilkan pada halaman slip. Chart.js tidak dimuat pada halaman karyawan. Format cetak laporan superadmin dijelaskan pada bagian Format cetak.

Superadmin menambahkan karyawan melalui **Akun Karyawan > Tambah Karyawan** atau **Transaksi > Karyawan > Tambah Karyawan**. Isi data sekali lalu tekan **Simpan Karyawan & Akun**. Data karyawan dan akun login menggunakan NIP disimpan dalam satu transaksi, termasuk hash password, ciphertext, dan audit. NIP mempertahankan nol di depan. Password awal mengikuti `EMPLOYEE_INITIAL_PASSWORD` dan dapat dilihat melalui **Akun Karyawan > Lihat Password**. Jika pembuatan akun gagal, data karyawan juga dibatalkan dan isian tetap tersedia. Mengedit data lama tidak membuat akun baru atau mengubah password/status/sesi akun yang sudah ada. Data lama tanpa akun tidak dibuatkan akun massal; penambahan karyawan juga tidak membuat transaksi gaji otomatis. Kartu promosi sidebar tidak ditampilkan pada seluruh halaman karyawan; navigasi Ubah Password dan Keluar tetap tersedia.

Superadmin membuka **Akun Karyawan** untuk mencari nama/NIP, mengaktifkan atau menonaktifkan akses, serta memakai **Lihat Password**. Tombol tersebut langsung membuka password terbaru di modal tanpa memasukkan ulang password superadmin. Pembukaan tetap memerlukan sesi superadmin aktif dan CSRF, dicatat dalam audit tanpa isi password, dan tidak di-cache. Password dibersihkan saat modal ditutup atau tab disembunyikan. Kartu pembuatan akun di halaman ini sudah dihapus; layanan pembuatan akun melalui skrip tetap tersedia. NIP mempertahankan nol di depan dan karyawan yang terhubung ke akun tidak dapat dihapus. Penonaktifan membatalkan sesi aktif, sementara aktivasi mempertahankan password pribadi. Pemulihan melalui email tetap khusus superadmin.

Autentikasi memakai hash password. Salinan password karyawan disimpan terpisah sebagai ciphertext AES-256-GCM dengan nonce acak dan identitas akun sebagai pengikat. Hash dan ciphertext berubah dalam satu transaksi. **Cadangkan database dan kunci `EMPLOYEE_PASSWORD_KEY` secara privat.** Kunci tidak berada dalam dump database; kunci hilang atau diganti membuat password tersimpan tidak dapat dibuka. Skrip konfigurasi menolak membuat pengganti jika ciphertext sudah ada. Jangan memasukkan `.env`, kunci, atau backup ke Git. Akun superadmin tidak memiliki salinan password yang dapat dibaca kembali.

Jalankan `npm run test:employee` untuk menguji migrasi akun lama, enkripsi, pengingat yang dapat ditunda, validasi 8 karakter, default bulan berjalan, filter otomatis, pratinjau legacy, cetak manual dan unduh PDF langsung, isolasi slip, pembukaan password tanpa verifikasi ulang, invalidasi sesi, serta tampilan 1440/390/360 px. Suite menggunakan database sementara `penggajian_employee_test_*` dan tidak mengubah database kerja. PDF normal dan nama/nominal panjang serta screenshot sintetis berada di `storage/qa/employee-portal/`. Jalankan `python tests/verify-employee-pdfs.py` setelah suite selesai untuk memeriksa ukuran A4 portrait, jumlah halaman, kelengkapan teks, dan batas kertas; pemeriksaan ini memerlukan PyMuPDF seperti suite verifikasi cetak yang sudah ada.

### Memperbarui instalasi yang sudah berisi data

Cadangkan database terlebih dahulu ke lokasi privat di `storage/`. Setelah kode diperbarui, jalankan:

```powershell
php scripts/migrate-accounting.php
```

Migrasi ini idempoten: menambahkan `calculation_version` dan `calculation_snapshot` hanya jika belum ada. Nominal, 32 kolom sumber, identitas transaksi, dan arsip tidak dihitung ulang. Transaksi lama diberi versi `legacy_v1`; transaksi baru atau perubahan komponen yang dihitung ulang memakai `accounting_v2`. Installer baru sudah menjalankan migrasi metadata ini secara otomatis.

Baseline audit serta backup lokal berada di `storage/audit/` dan `storage/exports/`; keduanya diabaikan Git dan dilindungi dari akses HTTP. Jika baseline audit lokal sudah tersedia, jalankan `php scripts/verify-accounting-baseline.php` untuk membandingkan checksum kolom asli sebelum/sesudah pembaruan. Skrip tersebut membaca database dan menulis hasil verifikasi ke direktori audit privat.

### Launcher lokal Windows

Setelah `.env` dan database disiapkan, klik **Start All** di Laragon lalu klik dua kali `JALANKAN.bat`. Launcher memakai Apache Laragon di **http://localhost/system-penggajian/**. Folder proyek boleh tetap berada di Downloads dan boleh memiliki spasi pada namanya. Junction yang terputus akibat perubahan nama folder diperbaiki otomatis; folder biasa atau junction yang masih dipakai proyek lain tidak ditimpa. Launcher tidak mengimpor atau mereset database.

Laragon ditemukan dari aplikasi Laragon yang sedang berjalan, lokasi proyek di dalam `www`, atau `C:\laragon` / `D:\laragon`. Untuk instalasi di lokasi lain:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/setup-laragon.ps1 -LaragonPath "D:\lokasi\laragon" -OpenBrowser
```

Setelah komputer dihidupkan ulang, cukup **Start All** di Laragon lalu buka **http://localhost/system-penggajian/**. Alamat **http://system-penggajian.test** juga dapat digunakan setelah fitur Auto Virtual Hosts Laragon diaktifkan dan **Reload** dijalankan.

Jika memakai terminal Laragon (Cmder), jalankan pindah folder dan launcher sebagai **dua perintah terpisah**:

```bat
cd /d "C:\Users\jaela\Downloads\Project2 Web\penggajianmutiarahikmah"
JALANKAN.bat
```

Untuk server PHP terpisah tanpa Apache, skrip lama tetap tersedia. Aktifkan MySQL, lalu jalankan `powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/start-local.ps1 -PhpPath "C:\lokasi\php.exe" -OpenBrowser`. Alamat alternatif ini adalah **http://127.0.0.1:8018**; log server ada di `storage/php-local.error.log`.

### Memakai backup SQL MySQL

Jika memiliki `penggajian.sql`, buat database kosong melalui HeidiSQL/phpMyAdmin di Laragon, lalu impor berkas tersebut ke database itu. Sesuaikan `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env`. Gunakan database terpisah jika database lama sudah berisi data yang masih diperlukan. Akun dalam backup tetap dipakai; halaman pendaftaran hanya muncul jika belum ada akun.

Jalankan `php scripts/install.php` dari terminal Laragon di folder proyek untuk melengkapi tabel dan metadata yang belum ada setelah impor. Installer tidak mengganti akun atau nominal yang sudah tersimpan. Simpan dump SQL, backup, dan `.env` secara lokal; berkas tersebut tidak perlu diunggah ke GitHub.

## Migrasi ulang dari backup SQL Server

Backup sumber adalah native SQL Server 2008 R2 (`10.50`), dengan nama database `gaji_bmt`. `gaji.exe` menyebut katalog `gaji`; aplikasi baru memakai data backup yang diberikan.

Migrasi dijalankan otomatis melalui satu perintah, termasuk persiapan Docker/SQL Server audit, restore salinan baru, ekspor seluruh sumber, backup MySQL yang diuji pemulihannya, rekonsiliasi, serta pemeriksaan hasil:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/migrate-legacy.ps1 -BackupPath "$env:USERPROFILE\Downloads\GAJI-5.dat"
```

Target MySQL mengikuti `.env`. Proses tidak meminta konfirmasi pengguna. Backup sumber dipasang hanya baca; container audit hanya membuka port loopback dan dihentikan setelah proses. Kredensial, backup, ekspor, serta laporan privat berada dalam `storage/legacy/<SHA-256>/<proses>/`, diabaikan Git, dan dilindungi dari HTTP.

Backup yang sama tetap direkonsiliasi meskipun pernah terverifikasi. Baris yang belum tersedia ditambahkan otomatis jika identitas dan relasinya valid. Perubahan/tambahan lokal dan penghapusan yang tercatat dipertahankan. Master kosong tidak diisi dari transaksi historis. Pengulangan identik tidak menulis ulang data atau metadata. Sumber berbeda pada database yang sudah memiliki arsip ditolak; gunakan database terpisah.

Halaman **Data & Migrasi** menyediakan inventaris tabel, status pemetaan, pencarian nama/NIP, serta pembandingan setiap master dan komponen historis dengan arsip. Panduan lengkap, aturan konflik, dan pilihan audit hanya baca tersedia pada [Migrasi otomatis](docs/MIGRASI_OTOMATIS.md).

### Hasil rekonsiliasi backup yang diberikan

| Pemeriksaan | Hasil |
|---|---:|
| Tabel sumber, termasuk tabel kosong | 19 |
| Seluruh baris aktif sumber yang diarsipkan | 485 |
| Karyawan | 39 |
| Transaksi penghasilan | 332 |
| Baris operasional yang dipetakan | 396 |
| Perbandingan nilai operasional | 11.000, cocok |
| Jumlah penghasilan bruto historis | Cocok; nominal tersimpan lokal |
| Jumlah potongan historis | Cocok; nominal tersimpan lokal |
| Jumlah penghasilan bersih historis | Cocok; nominal tersimpan lokal |

`RESTORE VERIFYONLY` dan `DBCC CHECKDB` lolos. Checksum setiap baris dan tabel arsip telah dibandingkan setelah penulisan. Kolom sumber `id` dipertahankan sebagai `legacy_id` pada tabel operasional; `id` aplikasi adalah identitas terpisah. Spasi pengisi `char` dipangkas di kanan pada data operasional dan dipertahankan utuh pada arsip.

## Alur penggunaan

- **Master:** kelola bagian dan karyawan. NIP berupa teks, termasuk nol di depan. Kode/NIP tidak diganti saat mengubah data. Penghapusan master yang masih digunakan ditolak.
- **Tunjangan dan Potongan:** kelola komponen berbasis bagian atau NIP sesuai aplikasi lama.
- **Transaksi Penghasilan:** pilih periode, bagian, dan karyawan; masukkan hari efektif serta kehadiran. Mode **Hitung dari master saat ini** memakai tarif/master sekarang. Mode **Nilai komponen transaksi** mempertahankan komponen masukan; digunakan sebagai default saat membuka transaksi tersimpan.
- **Muat komponen master** mengaktifkan perhitungan dari master. Jangan gunakan untuk transaksi historis kecuali memang hendak menghitung ulang dengan master saat ini.
- **Slip & Laporan:** tujuh jenis keluaran, cakupan semua/tertentu, rentang lintas tahun, seluruh komponen, subtotal bagian, serta cetak atau simpan PDF melalui dialog browser. Cetak seluruh komponen dipisah menjadi tabel penghasilan, potongan, kehadiran, dan ringkasan agar tidak terpotong pada kertas landscape.
- **Data & Migrasi:** riwayat rekonsiliasi, status setiap tabel, pencarian karyawan, serta pembandingan master dan komponen historis dengan arsip hanya baca.
- **Profil:** ubah nama atau kata sandi menggunakan kata sandi saat ini.

Setiap simpan melewati evaluasi server; nilai total yang dikirim browser tidak dipercaya. Komponen yang berubah dihitung ulang, sedangkan penyimpanan tanpa perubahan mempertahankan nominal sebelumnya. Transaksi NIP/periode sama ditolak dan pengguna perlu membuka transaksi yang sudah ada. Perubahan tarif master tidak mengubah transaksi historis secara otomatis.

Pada `accounting_v2`, nominal setiap komponen dibulatkan ke rupiah sebelum dijumlahkan: bruto = seluruh penghasilan, potongan = seluruh potongan, bersih = bruto − potongan. Master potongan lain-lain mengisi `plain`; `lain1` tetap penghasilan manual. Dashboard menghitung penerima berdasarkan NIP unik yang benar-benar memiliki transaksi pada periode/bagian terpilih. Rekap gaji pokok menjumlahkan gaji pokok + jabatan; tarif harian dan lembur hanya ditampilkan sebagai tarif, tanpa subtotal.

Kesalahan masukan memblokir simpan. Peringatan, seperti kehadiran belum lengkap, perubahan manual, atau hitung ulang histori, perlu diperiksa dan dikonfirmasi melalui dialog sebelum disimpan. Perubahan nominal, sumber tarif, atau revisi transaksi setelah pratinjau memerlukan pemeriksaan hasil terbaru. Aturan absensi lama dan nominal BJBS/BPJS tetap ditampilkan sebagai dasar perhitungan, tanpa menambahkan asumsi kebijakan atau persentase baru.

## Pengujian

### Format cetak

Pada laporan superadmin **Slip Gaji** dan **Slip Gaji per Bagian**, pratinjau layar tetap menggunakan desain klasik landscape. Saat memilih **Cetak / Simpan PDF** atau Ctrl+P, hasil cetak menggunakan **A4 portrait**, margin 12 mm, satu transaksi per lembar, dengan template dan spasi yang sama seperti slip pada portal karyawan. Semua pintasan slip pada Penggajian mengikuti format ini. Nama Accounting mengikuti nama lengkap akun yang mencetak; lokasi default Tambun dapat disesuaikan melalui `App\Domain\PrintLayout::LOCATION`. Tanggal dan jam diperbarui saat mencetak dengan zona waktu **WIB**, termasuk melalui Ctrl+P.

Rekap tetap memakai **A4 landscape**. Judul, periode, nama petugas, jam cetak, dan kepala tabel diulang pada halaman lanjutan. Rincian lengkap tetap dipisah menjadi penghasilan, potongan, kehadiran, dan ringkasan. Pada dialog cetak Chrome, gunakan skala **100%**, ukuran kertas sesuai dokumen, dan nonaktifkan header/footer bawaan browser.

Jalankan `npm run test:print` dari terminal Laragon untuk memeriksa tujuh laporan dan kedua pintasan slip. Pengujian ini membuat database sementara dengan nama acak `penggajian_print_test_*`, hanya berisi fixture sintetis, lalu menghapusnya setelah selesai. PDF uji disimpan di `storage/qa/print/`. Pemeriksaan ukuran kertas, jumlah halaman, pengulangan judul, kelengkapan teks, serta gambar hasil render dapat dijalankan dengan `python tests/verify-print-pdfs.py` setelah paket Python `pymupdf` tersedia. Pengujian cetak tidak memerlukan backup SQL Server dan tidak mengubah database aplikasi utama.

### Pengujian perhitungan dan browser

```powershell
php tests/run.php
php tests/calculator-accounting.php
php tests/confirmation-accounting.php
php tests/report-accounting.php
php tests/dashboard-accounting.php
```

`tests/run.php` memerlukan ekspor SQL Server lokal (argumen path, ekspor lama, atau ekspor proses terbaru) dan database hasil migrasi sebagai pembanding hanya baca. Ekspektasi mengikuti manifest sumber; setiap kolom master/transaksi, relasi, checksum arsip, dan total baris sumber diperiksa tanpa menghitung tambahan lokal sebagai data sumber. Pengujian kalkulasi dan konfirmasi merupakan fixture murni tanpa koneksi database. Pengujian laporan/dashboard membaca transaksi dalam database yang dikonfigurasi melalui transaksi hanya baca; keluarannya tidak memuat nominal atau identitas karyawan.

Setelah database uji disiapkan oleh suite browser, jalankan `php tests/calculator-integration.php` untuk pemetaan tarif/master dan `php tests/workflow-accounting.php` untuk pratinjau, konfirmasi, simpan, serta benturan revisi. Keduanya membatasi penulisan ke `penggajian_test`; fixture integrasi di-rollback dan fixture alur dibersihkan setelah pemeriksaan. Jangan mengarahkannya ke database kerja.

Untuk pengujian browser, jalankan terminal terpisah:

Router pengujian untuk database `penggajian_test` memakai kunci enkripsi sintetis, terpisah dari konfigurasi kerja.

```powershell
$env:DB_DATABASE = 'penggajian_test'
php -d variables_order=EGPCS -S 127.0.0.1:8018 -t . scripts/router.php
```

Kemudian:

```powershell
npm install
npm run test:browser
```

Suite browser memakai Chrome yang terpasang pada Windows. Suite membuat ulang **hanya** database `penggajian_test`; reset ditolak jika ditemukan akun selain akun QA. Jangan menggunakan nama ini untuk data kerja. Gambar hasil QA dan contoh PDF berada di `storage/qa/` dan tidak bisa diakses lewat HTTP.

Setelah suite browser selesai, jalankan `node tests/laragon-routing.js` untuk memeriksa akses dari akar domain dan subfolder seperti `localhost/system-penggajian/`. Pengujian ini menjalankan server PHP sementara dengan database `penggajian_test`, memeriksa login, aset, navigasi, formulir, API, CSRF, arsip terlindungi, serta logout, kemudian menghentikan server tersebut.

## Dokumen teknis dan batas verifikasi

- `docs/MIGRASI_OTOMATIS.md`: perintah otomatis, rekonsiliasi, pelestarian lokal, dan penelusuran karyawan.
- `docs/KAMUS_DATA.md`: seluruh tabel/kolom database hasil restore dan pemetaannya.
- `docs/PEMETAAN_LEGACY.md`: form, handler, sumber tarif, pemetaan field, dan rumus yang ditelusuri dari EXE.
- `docs/VERIFIKASI.md`: hasil pemeriksaan dan keterbatasan.
- `docs/AUDIT_PENGGAJIAN.md`: temuan audit angka, aturan versi baru, pelestarian histori, serta batas kebijakan.
- `docs/PERAPIHAN_TAMPILAN.md`: filter dan Reset, input tahun, tata letak slip, serta pengujian tampilan/PDF sintetis.
- `storage/legacy/reconciliation.json`: hasil rekonsiliasi terstruktur.

EXE tidak dieksekusi pada lingkungan Windows 7. Kesetaraan perhitungan dibuktikan melalui handler native dan data transaksi tersimpan; ini bukan klaim bahwa seluruh interaksi EXE telah dijalankan. Template `.rpt` tidak tersedia, sehingga laporan dibangun ulang dari data dan dokumentasi visual; kesamaan persis tata letak/pengelompokan Crystal Reports lama belum dapat dibuktikan.

Nilai historis tidak dihitung ulang ketika diimpor. Penyimpanan ulang tanpa perubahan komponen mempertahankan nilai dan versi sebelumnya. Konfirmasi peringatan adalah pemeriksaan oleh pengguna yang sedang memasukkan transaksi; aplikasi belum menyediakan persetujuan berjenjang, status pembayaran, mesin pajak/iuran otomatis, absensi sidik jari, atau transfer bank otomatis.
