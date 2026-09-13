# Operasional aplikasi

## Verifikasi lokal

```text
php artisan test --compact
npm test
npm run build
npx playwright install chromium
npm run test:browser
php vendor/bin/phpstan analyse --no-progress
php artisan absen:health --python
```

PHPStan saat sesi implementasi dijalankan dari rilis PHAR resmi di `storage/framework/phpstan-tool.phar` karena Composer gagal menulis unduhan sementara pada Windows. Dependency tetap tercatat di lockfile untuk instalasi biasa/CI. Cakupan awal analisis: FormRequest, SafeResponse, dan ApiListing; perlu diperluas per modul.

## Backup dan antrean

Scheduler mendaftarkan backup pukul 02:00 Asia/Jakarta. Server deployment harus menjalankan `php artisan schedule:run` setiap menit dan worker `php artisan queue:work --timeout=300 --tries=3` sebagai layanan yang dipantau. Mendaftarkan jadwal di source tidak otomatis memasang layanan sistem.

- `php artisan absen:backup` membuat recovery point baru dan checksum; tidak memangkas file lama.
- `php artisan absen:backup --prune` menerapkan `BACKUP_KEEP` untuk file buatan aplikasi. Tinjau kebutuhan retensi sebelum menjalankannya.
- `php artisan absen:health` mengembalikan exit nonzero ketika database bermasalah, failed job ada, atau backup hilang/kedaluwarsa/checksum invalid. Pantau juga umur job tertua dan kapasitas disk pada monitoring server.
- `php scripts/verify_roadmap.php` menguji restore database sintetis; backup produksi tetap perlu diuji melalui prosedur restore terisolasi yang disetujui admin, termasuk foto/file eksternal yang tidak tercakup dump SQL.

## FaceNet

Runtime saat audit adalah Python 3.13 dengan empat konflik dependency facenet-pytorch. `requirements-observed.txt` adalah inventaris, sedangkan `requirements-facenet.txt` menargetkan virtual environment Python 3.11. Jangan memasang paket tersebut ke Python global.

Sesuaikan `FACENET_PYTHON`, `FACENET_PYTHONPATH`, dan `FACENET_MODEL_PATH` ke lingkungan/model yang telah divalidasi. Uji deteksi, embedding, false accept/reject, dan timeout pada perangkat. Path model konfigurasi saat ini belum berisi file; library memiliki fallback pretrained, tetapi fallback belum diverifikasi pada sesi ini.

Jika registrasi wajah gagal, akun tetap tersedia dan API memberikan `face_status=failed`. Retry terautentikasi melalui endpoint pendaftaran wajah yang sudah ada, atau admin menjalankan `php artisan absen:face-retry ID`. Jangan mendaftarkan akun duplikat. Foto sementara verifikasi kini di `storage/app/private/temp`; foto profil historis di disk public belum dimigrasikan massal.

## Batas verifikasi browser

Playwright menjalankan Chromium desktop dan emulasi Pixel 7. Tes mencakup halaman login, akses API tamu, logout tamu, console JavaScript, dan audit aksesibilitas login. Ini belum menggantikan UAT login pegawai nyata → kamera/GPS → masuk → pulang → logout pada Android fisik, kehilangan jaringan, serta izin kamera/lokasi ditolak.

CI ada di `.github/workflows/quality.yml`; workflow belum dijalankan pada GitHub dalam sesi lokal ini. Tidak ada deployment atau pengiriman pesan eksternal.
