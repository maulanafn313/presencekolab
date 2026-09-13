# Tooling aplikasi

Perintah operasional aktif berada pada `app/Console/Commands`: `absen:backup`, `absen:audit`, `absen:correct`, `absen:health`, dan `absen:face-retry`.

`verify_roadmap.php` membuat database MySQL sintetis, menguji migration/konkurensi/restore, lalu membersihkan database sementara miliknya. Jangan menggantinya dengan `migrate:fresh` pada database aplikasi.

`facenet_cli.py` merupakan entrypoint integrasi Python. Konfigurasi runtime ada pada `config/facenet.php`; dependency dan model harus dipasang sesuai mesin deployment. Jalankan `php artisan absen:health --python` sebelum mengaktifkan verifikasi wajah wajib.

`diagnostics/` menyimpan utility lama yang sudah dikeluarkan dari document root. Skrip ini memiliki konfigurasi historis dan sebagian dapat menulis database; jangan digunakan sebagai prosedur deployment. Gunakan Artisan untuk operasional.

Utility ekstraksi/refactor (`extract*`, `strip_logic`, `find_*`, `clean_page`) bukan dependency runtime. Jangan menjalankan ulang pada source tanpa review diff.
