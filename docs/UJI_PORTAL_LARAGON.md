# Uji portal penggajian di Laragon 6

Gunakan lingkungan uji dengan akun dan slip sintetis. HTTP tidak mengenkripsi kata sandi, cookie sesi, dan slip. Untuk pemakaian resmi dengan data gaji, siapkan HTTPS yang dipercaya perangkat karyawan. Contoh ini memakai jaringan kantor tanpa domain publik, hosting, atau port forwarding.

## Sebelum perubahan

1. Catat URL pembayaran yang sedang berjalan. Di Terminal Laragon, jalankan `httpd.exe -V`, `httpd.exe -S`, dan `httpd.exe -M`. Pakai executable dan argumen konfigurasi yang sama dengan proses Laragon aktif. Pastikan `rewrite_module`, `env_module`, serta `authz_host_module` aktif. Pastikan versi PHP memenuhi persyaratan proyek.
2. Catat IP LAN PC Laragon dan IP tetap PC superadmin. Cadangkan konfigurasi Apache, folder aplikasi, database penggajian, dan `.env` milik server di lokasi privat di luar web root. Simpan `EMPLOYEE_PASSWORD_KEY` yang sudah dipakai bersama cadangan.
3. Mintalah admin jaringan menambahkan `slip-gaji.intranet` dan `gaji-admin.intranet` ke DNS yang dipakai Wi-Fi kantor. Kedua nama menunjuk ke **IP server**. Minta konfirmasi HP di Wi-Fi dapat menjangkau server lewat port 80. Untuk percobaan terbatas di PC, pemetaan `hosts` bisa dipakai. Setelah DNS kantor aktif, HP karyawan cukup tersambung ke Wi-Fi dan membuka URL.

## Pasang aplikasi dan Apache

1. Tarik commit rilis dengan `git pull --ff-only origin main` setelah memastikan checkout server bersih. Siapkan checkout yang diperbarui di luar `www`, misalnya `C:\apps\system-penggajian`. Pertahankan `.env` server, set `APP_URL=http://gaji-admin.intranet` dan `APP_DEBUG=false`. Jangan salin `.env` dari PC pengembangan.
2. Dari folder proyek yang baru, jalankan `composer install --no-dev`. Jika skema akun portal belum diterapkan, jalankan `php scripts/migrate-auth.php` setelah backup. Jika konfigurasi portal belum disiapkan, jalankan `php scripts/configure-employee-portal.php`. Jangan jalankan seeder pada database kerja.
3. Salin [contoh konfigurasi](penggajian-lan.conf.example) ke folder konfigurasi Apache yang terpasang di server, biasanya `C:\laragon\etc\apache2\sites-enabled`. Gunakan nama manual tanpa awalan `auto.`. Isi path proyek dan IP **PC superadmin** di `Require ip`. Setiap virtual host memberi PHP mode portal lewat `SetEnv PAYROLL_PORTAL`.
4. Pertahankan virtual host bawaan untuk URL pembayaran. Permintaan ke IP atau nama yang tidak dikenali dapat masuk ke vhost pertama pada IP dan port tersebut. Pastikan `httpd.exe -S` masih menunjukkan vhost pembayaran/bawaan sebagai default, dengan alamat dan port yang cocok. Jangan masukkan salah satu portal sebagai default. Jalankan `httpd.exe -t` dan reload Apache lewat Laragon setelah hasilnya `Syntax OK`.
5. Uji kedua alamat baru. Setelah keduanya lulus, nonaktifkan vhost otomatis lama khusus penggajian dan keluarkan folder atau junction lama `www/system-penggajian` dari web root. Jangan jalankan launcher `JALANKAN.bat` untuk instalasi ini karena ia menautkan proyek kembali ke `www`. Reload dan uji lagi URL pembayaran.

## Pemeriksaan dari perangkat pengguna

| Perangkat | Hasil yang diharapkan |
|---|---|
| HP di Wi-Fi karyawan | Alamat slip terbuka. Akun uji karyawan hanya melihat dan mengunduh slip sendiri. Akun superadmin ditolak. |
| PC di luar daftar admin | Alamat admin memberi `403` sebelum login. |
| PC superadmin | Akun superadmin bisa masuk ke alamat admin. Akun karyawan ditolak. |
| PC petugas pembayaran | Alamat pembayaran lama tetap berfungsi. |
| PC atau HP biasa | `IP-SERVER/system-penggajian` sudah tidak membuka aplikasi. Rute laporan, transaksi, setup, dan reset melalui alamat slip memberi `404`. |

Jika reload atau akses pembayaran gagal, pulihkan konfigurasi Apache dari cadangan, validasi dengan `httpd.exe -t`, lalu reload. Bila penggajian perlu dikembalikan, pulihkan versi kode, lokasi proyek, dan konfigurasi lama sebagai satu paket. Pengaturan Master Konsumsi memakai tabel pengaturan yang sudah ada. Pemulihan database hanya diperlukan jika memang ada perubahan data yang harus dikembalikan.

## Pengembangan di satu PC

Gunakan `scripts/start-local.ps1` untuk menjalankan router PHP di `127.0.0.1:8018`. Router tersebut mengaktifkan mode pengembangan khusus loopback agar kedua jenis akun bisa diuji. Untuk Apache lokal, set `PAYROLL_PORTAL development` hanya pada vhost atau direktori proyek yang diizinkan lewat `Require local`. Aplikasi menolak mode pengembangan dari alamat klien non-loopback. Instalasi LAN memakai mode `employee` dan `admin`.
