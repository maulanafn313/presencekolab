<?php
/**
 * Admin-specific Modals
 */
?>

<!-- Modal Tambah/Edit Member -->
<div id="member-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-40 hidden p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h2 id="modal-title" class="text-lg font-bold flex items-center gap-2">
                <i class="fi fi-sr-user-add"></i>
                Tambah Member Baru
            </h2>
            <button type="button" id="btn-cancel-modal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-full p-1.5 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <!-- Modal Body -->
        <form id="member-form" class="p-6 space-y-4">
            <input type="hidden" id="member-id">
            <input type="hidden" id="foto-data-url">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                <input type="email" id="email" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">NIM <span class="text-red-500">*</span></label>
                <input type="text" id="nim" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm" required>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" id="nama" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm" required>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Program Studi <span class="text-red-500">*</span></label>
                <input type="text" id="prodi" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm" required>
            </div>
            
            <div>
                <label for="startup" class="block text-sm font-semibold text-gray-700 mb-2">Nama Startup</label>
                <select id="startup" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
                    <option value="">Tanpa startup</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Foto Wajah</label>
                <div id="modal-video-container" class="relative bg-gray-100 rounded-xl w-full aspect-video mb-3 hidden overflow-hidden">
                    <video id="modal-video" autoplay playsinline class="w-full h-full object-cover"></video>
                </div>
                <canvas id="modal-canvas" class="hidden"></canvas>
                <img id="foto-preview" class="mt-2 h-40 w-40 object-cover rounded-xl border-2 border-gray-200 hidden mx-auto mb-3">
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <button type="button" id="btn-start-camera" class="bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                        <i class="fi fi-sr-camera"></i>
                        <span class="hidden sm:inline">Kamera</span>
                    </button>
                    <button type="button" id="btn-upload-photo" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                        <i class="fi fi-sr-upload"></i>
                        <span class="hidden sm:inline">Upload</span>
                    </button>
                </div>
                <input type="file" id="photo-file-input" accept="image/*" class="hidden">
                <button type="button" id="btn-take-photo" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2.5 px-4 rounded-xl hidden transition-all shadow-sm hover:shadow-md">
                    <i class="fi fi-sr-camera"></i> Ambil Foto
                </button>
            </div>
            
            <div id="password-admin-wrapper" class="grid grid-cols-1 sm:grid-cols-2 gap-3 hidden">
                <input type="password" id="password-new" placeholder="Password Baru" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
                <input type="password" id="password-confirm" placeholder="Konfirmasi Password" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm">
            </div>
            
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="button" id="btn-cancel-modal-btn" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-xl font-semibold transition-all">
                    Batal
                </button>
                <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Jadwal Kerja -->
<div id="work-schedule-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Kelola Jadwal Kerja</h3>
            <button id="work-schedule-close" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Pegawai</label>
            <select id="work-schedule-user" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Pilih pegawai...</option>
            </select>
        </div>
        
        <div id="work-schedule-form" class="hidden">
            <div class="mb-4">
                <h4 class="text-lg font-semibold mb-3">Jadwal Kerja Mingguan</h4>
                <div class="space-y-3">
                    <div class="grid grid-cols-7 gap-2 text-sm font-medium text-gray-700">
                        <div>Hari</div>
                        <div>Bekerja</div>
                        <div>Jam Masuk</div>
                        <div>Jam Pulang</div>
                        <div>Durasi</div>
                        <div>Status</div>
                        <div>Aksi</div>
                    </div>
                    
                    <div id="work-schedule-days" class="space-y-2">
                        <!-- Days will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai Bekerja</label>
                <input id="work-start-date" type="date" class="p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                <p class="text-xs text-gray-500 mt-1">Digunakan sebagai tanggal awal perhitungan KPI pegawai.</p>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button id="work-schedule-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button id="work-schedule-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan Jadwal</button>
            </div>
        </div>
    </div>
</div>

<!-- Manual Holidays Modal -->
<div id="manual-holidays-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-xl">
        <h3 class="text-xl font-bold mb-4">Kelola Hari Libur Manual</h3>
        <div class="flex gap-2 mb-3">
            <input type="date" id="mh-date" class="p-2 border rounded">
            <input type="text" id="mh-name" class="flex-1 p-2 border rounded" placeholder="Nama/Alasan libur (mis. Demo, Bencana)">
            <button id="mh-add" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Tambah</button>
        </div>
        <div class="overflow-x-auto max-h-80 overflow-y-auto">
            <table class="min-w-full bg-white bordered">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-3 text-left">Tanggal</th>
                        <th class="py-2 px-3 text-left">Keterangan</th>
                        <th class="py-2 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="mh-body"></tbody>
            </table>
        </div>
        <div class="text-right mt-3">
            <button id="mh-close" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Tinjau Laporan Mahasiswa -->
<div id="report-review-modal" role="dialog" aria-modal="true" aria-labelledby="rl-modal-title" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-start gap-4 p-5 border-b border-gray-100">
            <div>
                <h3 id="rl-modal-title" class="text-xl font-bold text-gray-800"></h3>
                <p id="rl-modal-subtitle" class="text-sm text-gray-500"></p>
            </div>
            <button type="button" id="rl-modal-close" aria-label="Tutup" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <div id="rl-modal-body" class="flex-1 overflow-y-auto p-5 space-y-3"></div>
        <div class="flex flex-wrap justify-end items-center gap-2 p-4 border-t border-gray-100">
            <select id="rl-modal-filter" aria-label="Filter laporan mahasiswa" class="mr-auto p-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-700">
                <option value="pending">Menunggu Review</option>
                <option value="flagged">Perlu Dicek</option>
                <option value="late">Telat Diisi</option>
                <option value="">Semua Laporan</option>
            </select>
            <button type="button" id="rl-modal-close-footer" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-xl text-sm">Tutup</button>
            <button type="button" id="rl-modal-approve-all" class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold px-4 py-2 rounded-xl text-sm">Approve Semua (0)</button>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Modal Detail Kalender Kehadiran -->
<div id="cal-modal" role="dialog" aria-modal="true" aria-labelledby="cal-modal-title" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto p-5">
        <div class="flex justify-between items-start gap-4 mb-3">
            <div>
                <h3 id="cal-modal-title" class="text-lg font-bold text-gray-800"></h3>
                <p id="cal-modal-date" class="text-sm text-gray-500"></p>
            </div>
            <button type="button" id="cal-modal-close" aria-label="Tutup" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <p id="cal-modal-info" class="text-sm font-semibold text-gray-700 bg-gray-50 rounded-xl p-3 mb-4"></p>
        <h4 class="text-sm font-semibold text-gray-700 mb-2">Ubah keterangan</h4>
        <div id="cal-modal-kets" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4"></div>
        <div id="cal-modal-status-wrap">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">Ubah status masuk</h4>
            <div id="cal-modal-status" class="grid grid-cols-2 gap-2"></div>
        </div>
    </div>
</div>

<!-- Modal Pengingat WhatsApp -->
<div id="reminder-modal" role="dialog" aria-modal="true" aria-labelledby="reminder-title" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-start gap-4 p-5 border-b border-gray-100">
            <div>
                <h3 id="reminder-title" class="text-xl font-bold text-gray-800">Pengingat WhatsApp</h3>
                <p class="text-sm text-gray-500">Periksa pesan, salin, lalu tempel ke grup.</p>
            </div>
            <button type="button" id="reminder-close" aria-label="Tutup" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div>
                <label for="reminder-startup" class="block text-sm font-semibold text-gray-700 mb-1">Kirim untuk</label>
                <select id="reminder-startup" class="w-full p-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Startup</option>
                </select>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-6 text-sm text-gray-700">
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="reminder-absent" checked class="w-4 h-4 rounded border-gray-300"> Belum presensi hari ini</label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="reminder-reports" checked class="w-4 h-4 rounded border-gray-300"> Belum mengisi laporan</label>
            </div>
            <div>
                <label for="reminder-text" class="block text-sm font-semibold text-gray-700 mb-1">Isi pesan</label>
                <textarea id="reminder-text" rows="12" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
        </div>
        <div class="flex flex-wrap justify-end items-center gap-2 p-4 border-t border-gray-100">
            <p id="reminder-status" role="status" class="mr-auto text-sm text-gray-600"></p>
            <button type="button" id="reminder-close-footer" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-xl text-sm">Tutup</button>
            <button type="button" id="reminder-copy" disabled class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold px-4 py-2 rounded-xl text-sm">Salin Pesan</button>
        </div>
    </div>
</div>
<script>
(() => {
    const el = (id) => document.getElementById(id);
    const modal = el('reminder-modal'), text = el('reminder-text'), status = el('reminder-status'), copyBtn = el('reminder-copy'), startup = el('reminder-startup');
    let data = null;

    // Penulisan startup di data bervariasi, misalnya "Uni Inside" dan "Uni-Inside".
    const startupKey = (name) => String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const cleanName = (name) => String(name || '').replace(/\s+/g, ' ').trim();
    const longDate = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const numbered = (items) => items.map((item, i) => `${i + 1}. ${item}`).join('\n');
    const syncCopy = () => { copyBtn.disabled = !text.value.trim(); };

    function compose() {
        if (!data) return;
        const key = startup.value;
        const list = data.students.filter((s) => !key || startupKey(s.startup) === key);
        const absent = el('reminder-absent').checked ? list.filter((s) => s.absent_today) : [];
        const reports = el('reminder-reports').checked ? list.filter((s) => s.missing_reports > 0) : [];
        status.textContent = '';
        if (!absent.length && !reports.length) {
            text.value = '';
            status.textContent = 'Tidak ada yang perlu diingatkan.';
            syncCopy();
            return;
        }
        const label = key ? ` - ${startup.selectedOptions[0].textContent}` : '';
        const parts = [`*Pengingat Presensi & Laporan Harian${label}*\n${longDate(data.date)}`];
        if (absent.length) parts.push(`*Belum presensi hari ini:*\n${numbered(absent.map((s) => cleanName(s.nama)))}`);
        if (reports.length) parts.push(`*Belum mengisi laporan harian (${data.max_days_back} hari terakhir):*\n${numbered(reports.map((s) => `${cleanName(s.nama)} (${s.missing_reports} laporan)`))}`);
        parts.push('Mohon segera dilengkapi. Terima kasih.');
        text.value = parts.join('\n\n');
        syncCopy();
    }

    function fillStartups() {
        const selected = startup.value, names = new Map();
        data.students.forEach((s) => { if (startupKey(s.startup) && !names.has(startupKey(s.startup))) names.set(startupKey(s.startup), cleanName(s.startup)); });
        startup.length = 1;
        [...names].sort((a, b) => a[1].localeCompare(b[1])).forEach(([value, label]) => startup.add(new Option(label, value)));
        startup.value = names.has(selected) ? selected : '';
    }

    async function open() {
        modal.classList.remove('hidden');
        data = null;
        text.value = '';
        syncCopy();
        status.textContent = 'Memuat data...';
        try {
            const r = await api('?ajax=admin_get_attendance_reminder', {}, { method: 'GET', suppressModal: true });
            if (!r.ok) throw new Error(r.message);
            data = r.data;
            fillStartups();
            compose();
        } catch (e) {
            status.textContent = 'Gagal memuat data. Coba lagi.';
        }
    }

    async function copy() {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text.value);
                return true;
            }
        } catch (e) { /* lanjut ke cara lama */ }
        text.focus();
        text.select();
        try { return document.execCommand('copy'); } catch (e) { return false; }
    }

    const close = () => modal.classList.add('hidden');
    el('btn-open-reminder')?.addEventListener('click', open);
    el('reminder-close').addEventListener('click', close);
    el('reminder-close-footer').addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    [startup, el('reminder-absent'), el('reminder-reports')].forEach((input) => input.addEventListener('change', compose));
    text.addEventListener('input', () => { status.textContent = ''; syncCopy(); });
    copyBtn.addEventListener('click', async () => {
        status.textContent = await copy() ? 'Pesan tersalin. Tempel ke grup WhatsApp.' : 'Gagal menyalin. Teks sudah terpilih, tekan Ctrl+C.';
    });
})();
</script>
<?php endif; ?>

<!-- Modal Pilihan Export Data Presensi -->
<div id="export-presensi-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Export Data Presensi</h3>
            <button onclick="closeExportDailyModal()" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
        </div>
        <form id="export-presensi-form" method="GET" action="?">
            <input type="hidden" name="ajax" value="export_daily">
            <input type="hidden" name="filter_type" id="export-p-filter-type-fallback" value="period">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Periode Data</label>
                <select id="export-p-range" class="w-full p-2 border border-gray-300 rounded-lg" onchange="const opts=document.getElementById('export-p-monthly-opts'); const fallback=document.getElementById('export-p-filter-type-fallback'); fallback.value=this.value; if(this.value==='monthly') { opts.style.display='block'; opts.classList.remove('hidden'); } else { opts.style.display='none'; opts.classList.add('hidden'); }">
                    <option value="period">Seluruh Periode (Default)</option>
                    <option value="monthly">Bulan Tertentu</option>
                </select>
            </div>
            
            <div id="export-p-monthly-opts" class="mb-4 hidden p-3 border rounded-lg bg-gray-50">
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-sm font-bold text-gray-800">Pilih Bulan & Tahun</label>
                    <select id="export-p-year" name="year" class="p-1 border rounded text-xs bg-white focus:ring-1 focus:ring-indigo-500" onchange="updateMonthLabels(this.value)">
                        <?php for($y=2024;$y<=2026;$y++) echo "<option value='$y'".($y==date('Y')?' selected':'').">$y</option>"; ?>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <?php 
                    $m_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    for($i=1;$i<=12;$i++): ?>
                        <label class="flex items-center space-x-2 text-xs p-1.5 border bg-white rounded shadow-sm hover:border-indigo-400 cursor-pointer transition-colors month-item">
                            <input type="checkbox" name="export_months[]" value="<?= $i ?>" <?= ($i==date('n')?'checked':'') ?> class="export-month-cb w-3 h-3 text-indigo-600 rounded">
                            <span class="month-label text-gray-700"><?= $m_names[$i] ?> <span class="text-[9px] text-gray-400 year-suffix"><?= date('Y') ?></span></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <div class="mt-3 flex justify-between items-center px-1">
                    <button type="button" onclick="qsa('.export-month-cb').forEach(cb=>cb.checked=true)" class="text-[10px] text-indigo-600 hover:font-bold transition-all">Select All</button>
                    <button type="button" onclick="qsa('.export-month-cb').forEach(cb=>cb.checked=false)" class="text-[10px] text-red-500 hover:font-bold transition-all">Clear All</button>
                </div>
            </div>
            <script>
            function updateMonthLabels(year) {
                qsa('.year-suffix').forEach(el => el.textContent = year);
            }
            </script>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Format Tampilan</label>
                <div class="space-y-2">
                    <label class="flex items-center space-x-2 p-2 border rounded-lg hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="export_format" value="combined" checked>
                        <span class="text-sm">Combined (Satu sheet untuk semua pegawai)</span>
                    </label>
                    <label class="flex items-center space-x-2 p-2 border rounded-lg hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="export_format" value="per_employee">
                        <span class="text-sm">Per Pegawai (Satu sheet per pegawai)</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeExportDailyModal()" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Download Excel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Landmark Wajah (Bukti Presensi) -->
<div id="landmark-modal" class="fixed inset-0 bg-black/80 backdrop-blur-md flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-slate-900 rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden border border-slate-700">
        <div class="flex justify-between items-center p-6 border-b border-slate-800">
            <h3 id="landmark-modal-title" class="text-xl font-bold text-white flex items-center gap-3">
                <i class="fi fi-sr-face-recognition text-blue-400"></i>
                Visualisasi Landmark Wajah
            </h3>
            <button onclick="closeLandmarkModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fi fi-rr-cross-small text-2xl"></i>
            </button>
        </div>
        <div class="p-8 flex flex-col items-center">
            <div class="relative group">
                <canvas id="landmark-modal-canvas" class="rounded-2xl shadow-inner bg-slate-950 border border-slate-800 w-full max-w-md aspect-[4/3]"></canvas>
                <div class="absolute inset-0 pointer-events-none border-2 border-blue-500/20 rounded-2xl group-hover:border-blue-500/40 transition-colors"></div>
            </div>
            <p class="mt-6 text-slate-400 text-sm text-center leading-relaxed max-w-sm">
                Visualisasi 68 titik pola wajah yang digunakan sebagai bukti presensi tanpa menyimpan foto asli untuk keamanan data dan efisiensi server.
            </p>
        </div>
        <div class="p-6 bg-slate-800/50 flex justify-center">
            <button onclick="closeLandmarkModal()" class="bg-slate-700 hover:bg-slate-600 text-white px-8 py-3 rounded-xl font-bold transition-all transform hover:scale-105">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal: Buat / Edit Kelompok Magang                           -->
<!-- ============================================================ -->
<div id="modal-kelompok-form" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[60] hidden p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-5 flex items-center justify-between">
            <h3 id="modal-kelompok-title" class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fi fi-sr-layers"></i> Buat Kelompok Magang
            </h3>
            <button onclick="closeGroupModal()" class="text-white/70 hover:text-white transition-colors p-1 hover:bg-white/20 rounded-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="kf-id">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Kelompok <span class="text-red-500">*</span></label>
                <input type="text" id="kf-nama" placeholder="contoh: Periode 1, Angkatan A" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" onkeydown="if(event.key==='Enter') submitGroupForm()">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input type="date" id="kf-mulai" class="w-full px-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tanggal Selesai <span class="text-red-500">*</span></label>
                    <input type="date" id="kf-selesai" class="w-full px-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                </div>
            </div>
            <p id="kf-error" class="text-sm text-red-500 font-medium min-h-[1.2em]"></p>
            <div class="flex gap-3 pt-2">
                <button onclick="closeGroupModal()" class="flex-1 px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all text-sm">Batal</button>
                <button onclick="submitGroupForm()" class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg text-sm">Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal: Kelola Anggota Kelompok                               -->
<!-- ============================================================ -->
<div id="modal-kelola-anggota" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[60] hidden p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-5 flex items-center justify-between rounded-t-3xl flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fi fi-sr-users"></i> Kelola Anggota
                </h3>
                <p id="ma-group-name" class="text-indigo-200 text-sm mt-0.5"></p>
            </div>
            <button onclick="closeManageMembersModal()" class="text-white/70 hover:text-white transition-colors p-1 hover:bg-white/20 rounded-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <!-- Search -->
        <div class="px-4 py-3 border-b border-gray-100 flex-shrink-0">
            <div class="relative">
                <i class="fi fi-sr-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="ma-search" placeholder="Cari pegawai..." class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" oninput="filterMemberCheckList(this.value)">
            </div>
        </div>
        <!-- Body scrollable -->
        <div id="ma-body" class="flex-1 overflow-y-auto p-3 space-y-1 min-h-0">
            <!-- Populated by JS -->
        </div>
        <!-- Footer -->
        <div class="flex items-center justify-between gap-3 px-4 py-4 border-t border-gray-100 bg-gray-50/60 rounded-b-3xl flex-shrink-0">
            <span id="ma-count" class="text-sm text-indigo-600 font-semibold">0 dipilih</span>
            <div class="flex gap-2">
                <button onclick="closeManageMembersModal()" class="px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-semibold text-sm transition-all">Batal</button>
                <button onclick="submitManageMembers()" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl font-semibold text-sm transition-all shadow-md hover:shadow-lg">Simpan Anggota</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal: Konfirmasi Aksi Kelompok (Archive/Unarchive/Delete)   -->
<!-- ============================================================ -->
<div id="modal-konfirmasi-group" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[70] hidden p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden animate-fade-in-up">
        <!-- Icon area -->
        <div id="konfirmasi-icon-area" class="px-6 pt-8 pb-4 flex flex-col items-center text-center">
            <div id="konfirmasi-icon-circle" class="w-16 h-16 rounded-full flex items-center justify-center mb-4 text-2xl">
                <i id="konfirmasi-icon" class="fi fi-sr-archive"></i>
            </div>
            <h3 id="konfirmasi-title" class="text-xl font-bold text-gray-800 mb-2">Konfirmasi</h3>
            <p id="konfirmasi-desc" class="text-sm text-gray-500 leading-relaxed"></p>
            <div id="konfirmasi-badges" class="mt-4 space-y-1.5 text-left w-full hidden">
                <!-- Dynamic badges injected by JS -->
            </div>
        </div>
        <!-- Footer buttons -->
        <div class="px-6 pb-6 flex gap-3 mt-2">
            <button id="konfirmasi-batal" onclick="closeKonfirmasiModal()" class="flex-1 px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all text-sm">
                Batal
            </button>
            <button id="konfirmasi-ok" class="flex-1 px-4 py-3 rounded-xl font-semibold text-white transition-all shadow-md hover:shadow-lg text-sm">
                Konfirmasi
            </button>
        </div>
    </div>
</div>

<style>
#modal-konfirmasi-group .animate-fade-in-up {
    animation: konfirmasi-pop 0.2s ease-out;
}
@keyframes konfirmasi-pop {
    from { opacity: 0; transform: scale(0.9) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<script>
// ============================================================
// Modal Konfirmasi Universal
// ============================================================
let _konfirmasiCallback = null;

function showKonfirmasiModal(config) {
    // config: { type: 'archive'|'unarchive'|'delete', title, desc, badges[], btnLabel, callback }
    const modal       = document.getElementById('modal-konfirmasi-group');
    const iconCircle  = document.getElementById('konfirmasi-icon-circle');
    const iconEl      = document.getElementById('konfirmasi-icon');
    const titleEl     = document.getElementById('konfirmasi-title');
    const descEl      = document.getElementById('konfirmasi-desc');
    const badgesEl    = document.getElementById('konfirmasi-badges');
    const okBtn       = document.getElementById('konfirmasi-ok');

    const themes = {
        archive:   { bg: 'bg-amber-100',  text: 'text-amber-600',  btn: 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700', icon: 'fi-sr-archive' },
        unarchive: { bg: 'bg-blue-100',   text: 'text-blue-600',   btn: 'bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700',     icon: 'fi-sr-undo' },
        delete:    { bg: 'bg-red-100',    text: 'text-red-600',    btn: 'bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700',         icon: 'fi-sr-trash' },
    };

    const t = themes[config.type] || themes.archive;

    // Apply theme
    iconCircle.className = 'w-16 h-16 rounded-full flex items-center justify-center mb-4 text-2xl ' + t.bg + ' ' + t.text;
    iconEl.className     = 'fi ' + t.icon;
    okBtn.className      = 'flex-1 px-4 py-3 rounded-xl font-semibold text-white transition-all shadow-md hover:shadow-lg text-sm ' + t.btn;
    okBtn.textContent    = config.btnLabel || 'Konfirmasi';

    titleEl.textContent  = config.title || 'Konfirmasi';
    descEl.textContent   = config.desc  || '';

    // Badges
    if (config.badges && config.badges.length > 0) {
        badgesEl.innerHTML = config.badges.map(b =>
            `<div class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 rounded-lg px-3 py-1.5 border border-gray-100">
                <i class="fi fi-sr-check-circle text-indigo-400 flex-shrink-0"></i>
                <span>${b}</span>
            </div>`
        ).join('');
        badgesEl.classList.remove('hidden');
    } else {
        badgesEl.classList.add('hidden');
    }

    _konfirmasiCallback = config.callback;
    okBtn.onclick = function() {
        const cb = _konfirmasiCallback;
        closeKonfirmasiModal();
        if (typeof cb === 'function') cb();
    };

    modal.classList.remove('hidden');
}

function closeKonfirmasiModal() {
    const modal = document.getElementById('modal-konfirmasi-group');
    if (modal) modal.classList.add('hidden');
    _konfirmasiCallback = null;
}

// Close on backdrop click
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-konfirmasi-group');
    if (!modal) return;
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeKonfirmasiModal();
    });
});
</script>
