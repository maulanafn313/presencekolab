<div id="page-calendar" class="hidden animate-fade-in-up">
    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
        <div class="mb-5">
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center gap-3">
                <i class="fi fi-sr-calendar-check text-indigo-600"></i>
                Kalender Kehadiran
            </h2>
            <p id="cal-status" role="status" class="text-sm text-gray-500 mt-1">Klik tanggal untuk melihat detail atau mengubah kehadiran.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 bg-gray-50/50 p-4 rounded-2xl border border-gray-200/60 mb-4">
            <div class="flex gap-2">
                <button type="button" id="cal-prev" aria-label="Bulan sebelumnya" class="bg-white border border-gray-200 hover:bg-gray-100 rounded-xl px-3 font-bold text-gray-700">‹</button>
                <input type="month" id="cal-month" aria-label="Pilih bulan" class="flex-1 min-w-0 p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                <button type="button" id="cal-next" aria-label="Bulan berikutnya" class="bg-white border border-gray-200 hover:bg-gray-100 rounded-xl px-3 font-bold text-gray-700">›</button>
            </div>
            <input type="search" id="cal-search" aria-label="Cari mahasiswa" placeholder="Cari Nama/NIM..." class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
            <select id="cal-startup" aria-label="Filter startup" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                <option value="">Semua Startup</option>
            </select>
            <select id="cal-show" aria-label="Filter kondisi" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                <option value="">Semua Mahasiswa</option>
                <option value="alpha">Ada Alpha</option>
                <option value="late">Ada Terlambat</option>
                <option value="missing_reports">Ada Laporan Kosong</option>
            </select>
        </div>

        <div id="cal-legend" class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600 mb-4"></div>

        <div class="overflow-auto max-h-[70vh] rounded-2xl border border-gray-100">
            <table class="min-w-max text-xs text-gray-700 border-separate border-spacing-0">
                <thead id="cal-head"></thead>
                <tbody id="cal-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const STATES = {
        hadir: ['H', 'Hadir', 'bg-emerald-100 text-emerald-800'],
        terlambat: ['T', 'Terlambat', 'bg-amber-200 text-amber-900'],
        wfa: ['W', 'WFA', 'bg-sky-100 text-sky-800'],
        overtime: ['O', 'Overtime', 'bg-purple-100 text-purple-800'],
        izin: ['I', 'Izin', 'bg-indigo-100 text-indigo-800'],
        sakit: ['S', 'Sakit', 'bg-violet-200 text-violet-900'],
        alpha: ['A', 'Alpha', 'bg-red-600 text-white'],
        belum: ['?', 'Belum presensi', 'bg-white text-gray-700 border border-gray-300'],
        nodata: ['-', 'Tanpa data presensi', 'bg-gray-50 text-gray-400'],
        libur: ['', 'Libur', 'bg-gray-100 text-gray-400'],
    };
    const KETS = [['wfo', 'Hadir'], ['wfa', 'WFA'], ['izin', 'Izin'], ['sakit', 'Sakit']];
    const page = document.getElementById('page-calendar');
    const $ = (selector) => page.querySelector(selector);
    const modal = (id) => document.getElementById(id);
    let data = null, seq = 0, open = null;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const longDate = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const hhmm = (value) => (value && /^\d{2}:\d{2}/.test(value) ? value.slice(0, 5) : '-');
    const thisMonth = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; };
    const stateText = (cell) => `${STATES[cell.state][1]}${cell.state === 'wfa' && cell.late ? ', terlambat' : ''}${cell.report === 'missing' ? ', laporan kosong' : ''}`;

    function shiftMonth(step) {
        const [year, month] = $('#cal-month').value.split('-').map(Number);
        const d = new Date(year, month - 1 + step, 1);
        $('#cal-month').value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        load();
    }

    async function load() {
        const current = ++seq;
        $('#cal-status').textContent = 'Memuat kalender...';
        let result;
        try {
            result = await api('?ajax=admin_get_attendance_calendar', { month: $('#cal-month').value }, { method: 'GET', suppressModal: true });
        } catch (e) {
            result = { ok: false };
        }
        if (current !== seq) return;
        if (!result.ok) {
            $('#cal-status').textContent = result.message || 'Kalender gagal dimuat.';
            return;
        }
        data = result.data;
        const selected = $('#cal-startup').value;
        const startups = [...new Set(data.students.map((s) => s.startup))].sort((a, b) => a.localeCompare(b, 'id'));
        $('#cal-startup').length = 1;
        startups.forEach((name) => $('#cal-startup').add(new Option(name, name)));
        $('#cal-startup').value = startups.includes(selected) ? selected : '';
        render();
    }

    function visible() {
        const term = $('#cal-search').value.trim().toLowerCase();
        const startup = $('#cal-startup').value;
        const show = $('#cal-show').value;
        return data.students.filter((s) => `${s.nama} ${s.nim}`.toLowerCase().includes(term) && (!startup || s.startup === startup) && (!show || s.totals[show] > 0));
    }

    function render() {
        const students = visible();
        $('#cal-status').textContent = `Menampilkan ${students.length} dari ${data.students.length} mahasiswa. Klik tanggal untuk melihat detail.`;
        const th = 'sticky top-0 bg-gray-50 border-b border-gray-200 py-2 font-bold';
        // Urutan lapisan ditulis langsung supaya label Mahasiswa selalu di atas kolom tanggal.
        $('#cal-head').innerHTML = `<tr>
            <th scope="col" style="left:0;z-index:40;box-shadow:1px 0 0 #e5e7eb" class="${th} text-left px-3 min-w-[180px]">Mahasiswa</th>
            ${data.days.map((d) => `<th scope="col" style="z-index:30" title="${esc(d.holiday || '')}" class="${th} w-8 text-center ${d.off ? 'text-red-600' : 'text-gray-700'} ${d.date === data.today ? 'bg-indigo-100' : ''}"><div class="font-normal text-[10px]">${d.weekday}</div>${d.day}</th>`).join('')}
            ${['Hadir', 'Telat', 'Izin/Sakit', 'Alpha', 'Lap. Kosong'].map((h) => `<th scope="col" style="z-index:30" class="${th} px-2 text-center whitespace-nowrap">${h}</th>`).join('')}
        </tr>`;
        $('#cal-body').innerHTML = students.map((s) => `<tr>
            <th scope="row" style="z-index:20;box-shadow:1px 0 0 #e5e7eb" class="sticky left-0 bg-white border-b border-gray-100 text-left px-3 py-1 font-semibold text-gray-800 max-w-[220px] truncate" title="${esc(s.nama)}">${esc(s.nama)}<div class="font-normal text-[10px] text-gray-500 truncate">${esc(s.startup)}</div></th>
            ${data.days.map((d) => cellHtml(s, d)).join('')}
            ${['present', 'late', 'leave', 'alpha', 'missing_reports'].map((key) => `<td class="border-b border-gray-100 text-center px-2 font-semibold ${key === 'alpha' && s.totals[key] ? 'text-red-700' : ''}">${s.totals[key]}</td>`).join('')}
        </tr>`).join('') || `<tr><td colspan="${data.days.length + 6}" class="py-8 text-center text-gray-400">Tidak ada mahasiswa yang cocok dengan filter.</td></tr>`;
    }

    function cellHtml(student, day) {
        const cell = student.cells[day.date];
        if (!cell) return '<td class="border-b border-gray-100 p-0.5"></td>';
        const [letter, , classes] = STATES[cell.state];
        const label = `${student.nama}, ${longDate(day.date)}: ${stateText(cell)}`;
        if (cell.state === 'libur') return `<td class="border-b border-gray-100 p-0.5"><div title="${esc(day.holiday || 'Libur')}" class="w-7 h-7 rounded ${classes}"></div></td>`;
        return `<td class="border-b border-gray-100 p-0.5"><button type="button" data-user="${student.id}" data-day="${day.date}" title="${esc(stateText(cell))}" aria-label="${esc(label)}" class="relative w-7 h-7 rounded font-bold ${classes} hover:ring-2 hover:ring-indigo-500">${letter}${cell.report === 'missing' ? '<span aria-hidden="true" class="absolute top-0 right-0.5 text-red-700 leading-none">•</span>' : ''}</button></td>`;
    }

    function openCell(userId, day) {
        const student = data.students.find((s) => s.id === userId);
        const cell = student.cells[day];
        open = { student, day, cell };
        modal('cal-modal-title').textContent = student.nama;
        modal('cal-modal-date').textContent = longDate(day);
        const present = ['hadir', 'terlambat', 'wfa', 'overtime'].includes(cell.state);
        modal('cal-modal-info').textContent = `${stateText(cell)}${present ? ` · Masuk ${hhmm(cell.in)} · Pulang ${hhmm(cell.out)}` : ''}`;
        const button = (attr, value, label, current) => `<button type="button" ${attr}="${value}" ${current ? 'disabled' : ''} class="bg-indigo-50 hover:bg-indigo-100 disabled:bg-gray-100 disabled:text-gray-400 text-indigo-700 font-bold py-2 px-3 rounded-xl text-sm">${label}</button>`;
        modal('cal-modal-kets').innerHTML = KETS.map(([value, label]) => button('data-ket', value, label, cell.ket === value)).join('');
        modal('cal-modal-status-wrap').classList.toggle('hidden', !present);
        modal('cal-modal-status').innerHTML = button('data-status', 'ontime', 'On Time', present && !cell.late) + button('data-status', 'terlambat', 'Terlambat', cell.late);
        modal('cal-modal').classList.remove('hidden');
    }

    const closeCell = () => { open = null; modal('cal-modal').classList.add('hidden'); };

    async function submit(payload) {
        const { student, day, cell } = open;
        const request = cell.ref
            ? ['admin_bulk_update_attendance', { ids: cell.ref, status: payload.status || '', ket: payload.ket || '' }]
            : ['admin_add_absence', { date: day, bulk_data: JSON.stringify([{ user_id: student.id, type: payload.ket, jam_masuk: null, jam_pulang: null, alasan: '', lokasi: '' }]) }];
        let result;
        try {
            result = await api(`?ajax=${request[0]}`, request[1], { suppressModal: true });
        } catch (e) {
            result = { ok: false, message: 'Koneksi atau respons server bermasalah. Silakan coba lagi.' };
        }
        showNotif(result.ok ? 'Kehadiran diperbarui.' : (result.message || 'Gagal memperbarui kehadiran.'), !!result.ok);
        if (result.ok) {
            closeCell();
            load();
        }
    }

    function change(payload) {
        const { cell } = open;
        const present = ['hadir', 'terlambat', 'wfa', 'overtime'].includes(cell.state);
        if (['izin', 'sakit'].includes(payload.ket) && present) {
            showConfirmModal('Ubah menjadi izin/sakit? Jam masuk, jam pulang, dan foto bukti pada tanggal ini akan dihapus permanen.', () => submit(payload));
        } else {
            submit(payload);
        }
    }

    $('#cal-month').value = thisMonth();
    $('#cal-month').addEventListener('change', load);
    $('#cal-prev').addEventListener('click', () => shiftMonth(-1));
    $('#cal-next').addEventListener('click', () => shiftMonth(1));
    ['#cal-search', '#cal-startup', '#cal-show'].forEach((selector) => $(selector).addEventListener('input', () => { if (data) render(); }));
    $('#cal-legend').innerHTML = Object.values(STATES).filter(([letter]) => letter).map(([letter, label, classes]) => `<span class="inline-flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded font-bold ${classes}">${letter}</span>${label}</span>`).join('') + '<span class="inline-flex items-center gap-1.5"><span class="text-red-700 font-bold">•</span>Laporan kosong</span>';
    $('#cal-body').addEventListener('click', (e) => {
        const button = e.target.closest('[data-day]');
        if (button) openCell(+button.dataset.user, button.dataset.day);
    });
    document.addEventListener('click', (e) => {
        if (!open) return;
        if (e.target.id === 'cal-modal' || e.target.closest('#cal-modal-close')) { closeCell(); return; }
        const ket = e.target.closest('#cal-modal [data-ket]');
        const status = e.target.closest('#cal-modal [data-status]');
        if (ket) change({ ket: ket.dataset.ket });
        if (status) change({ status: status.dataset.status });
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && open && document.getElementById('confirm-modal')?.classList.contains('hidden') !== false) closeCell(); });
    window.renderAttendanceCalendar = () => { if (!data) load(); else render(); };
})();
</script>
