# Aturan pengembangan

- Route memetakan endpoint dan middleware; controller mengoordinasikan request/response.
- FormRequest menangani validasi HTTP dan authorization; service menangani transaksi dan keputusan domain.
- `app/Services/Attendance` menyatukan presensi, koreksi admin, dan audit. Jangan membuat aturan masuk/pulang baru di template atau controller.
- `app/Legacy/actions` adalah adapter procedural yang dipisah per fitur. Ekstraksi berikutnya wajib mempertahankan lingkup variabel include dan kontrak `ResponseSignal`.
- `resources/views` berisi presentasi; kode baru tidak menjalankan DDL atau query bisnis dari view.
- JavaScript baru berada di `resources/js` untuk bundling atau `public/assets/js/modules` untuk kompatibilitas klasik. Transport memakai `api-client.js`.
- File diagnostik tidak boleh berada di `public`. Model FaceNet tidak dipindahkan tanpa penelusuran referensi.
- Test database menggunakan SQLite memory atau database sintetis; data historis diperbaiki hanya dengan bukti dan alasan audit.

API listing menerima `page` dan `per_page` (1–200). Tanpa parameter tersebut, kontrak array lama dipertahankan. Migrasikan pemanggil lama sebelum mewajibkan pagination supaya laporan tidak terpotong diam-diam.
