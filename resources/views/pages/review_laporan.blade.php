<div id="page-review-laporan" class="hidden animate-fade-in-up">
    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center gap-3">
                <i class="fi fi-sr-document-signed text-indigo-600"></i>
                Review Laporan
            </h2>
            <p id="rl-period-label" class="text-sm text-gray-500 mt-1">Semua tanggal</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="bg-blue-50 rounded-2xl p-4">
                <div class="text-xs font-semibold text-blue-700">Laporan menunggu review</div>
                <div id="rl-stat-pending" class="text-2xl font-bold text-blue-900">0</div>
            </div>
            <div class="bg-indigo-50 rounded-2xl p-4">
                <div class="text-xs font-semibold text-indigo-700">Mahasiswa perlu ditinjau</div>
                <div id="rl-stat-students" class="text-2xl font-bold text-indigo-900">0</div>
            </div>
            <div class="bg-amber-50 rounded-2xl p-4">
                <div id="rl-stat-late-label" class="text-xs font-semibold text-amber-700">Laporan telat diisi</div>
                <div id="rl-stat-late" class="text-2xl font-bold text-amber-900">0</div>
            </div>
        </div>

        <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-200/60 mb-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                <input type="search" id="rl-search" placeholder="Cari Nama/NIM..." aria-label="Cari nama atau NIM" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                <select id="rl-startup" aria-label="Filter startup" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                    <option value="">Semua Startup</option>
                </select>
                <select id="rl-scope" aria-label="Filter status mahasiswa" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                    <option value="">Mahasiswa Aktif &amp; Arsip</option>
                    <option value="active">Mahasiswa Aktif</option>
                    <option value="archived">Mahasiswa Arsip</option>
                </select>
                <select id="rl-filter" aria-label="Filter kondisi laporan" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                    <option value="review">Menunggu Review</option>
                    <option value="flagged">Perlu Dicek</option>
                    <option value="late">Telat Diisi</option>
                    <option value="">Semua Laporan</option>
                </select>
            </div>
            <div class="flex flex-col lg:flex-row lg:items-end gap-3">
                <label class="text-xs font-semibold text-gray-600">Dari tanggal
                    <input type="date" id="rl-start" class="block mt-1 p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                </label>
                <label class="text-xs font-semibold text-gray-600">Sampai tanggal
                    <input type="date" id="rl-end" class="block mt-1 p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                </label>
                <button type="button" id="rl-all-dates" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Semua Tanggal</button>
                <button type="button" id="rl-approve-selected" disabled class="lg:ml-auto bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors whitespace-nowrap">Approve Terpilih (0)</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-100">
            <table class="w-full min-w-max text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                    <tr>
                        <th class="py-3 px-3"><input type="checkbox" id="rl-check-all" aria-label="Pilih semua mahasiswa yang tampil"></th>
                        <th class="py-3 px-3 font-bold">Mahasiswa</th>
                        <th class="py-3 px-3 font-bold text-center">Pending</th>
                        <th class="py-3 px-3 font-bold text-center">Perlu Dicek</th>
                        <th class="py-3 px-3 font-bold text-center">Telat Diisi</th>
                        <th class="py-3 px-3 font-bold">Pending Tertua</th>
                        <th class="py-3 px-3 font-bold text-center">Disetujui</th>
                        <th class="py-3 px-3 font-bold text-center">Ditolak</th>
                        <th class="py-3 px-3 font-bold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="rl-body" class="bg-white divide-y divide-gray-100"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const ATTENDANCE = {
        hadir: ['Hadir', 'bg-emerald-100 text-emerald-800'],
        terlambat: ['Terlambat', 'bg-amber-100 text-amber-800'],
        izin: ['Izin', 'bg-sky-100 text-sky-800'],
        sakit: ['Sakit', 'bg-violet-100 text-violet-800'],
        tanpa_presensi: ['Tanpa presensi', 'bg-red-100 text-red-800'],
    };
    const STATUS = {
        pending: ['Menunggu review', 'bg-blue-100 text-blue-800'],
        approved: ['Disetujui', 'bg-emerald-100 text-emerald-800'],
        disapproved: ['Ditolak', 'bg-red-100 text-red-800'],
    };
    const BULK_CHUNK = 500;
    const state = { employees: [], maxDaysBack: 5, selected: new Set(), openId: null, reports: [], drafts: {}, loadSeq: 0, detailSeq: 0, loaded: false };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const formatDate = (iso, options = { day: 'numeric', month: 'short', year: 'numeric' }) => new Date(iso.slice(0, 10) + 'T00:00:00').toLocaleDateString('id-ID', options);
    const time = (value) => (value && /^\d{2}:\d{2}/.test(value) ? value.slice(0, 5) : '-');
    const period = () => ({ start: qs('#rl-start').value, end: qs('#rl-end').value });
    const findEmployee = (id) => state.employees.find((e) => e.id === id);

    async function call(url, data = {}, options = {}) {
        try {
            const result = await api(url, data, { suppressModal: true, ...options });
            if (!result.ok) showNotif(result.message || 'Permintaan gagal diproses.', false);
            return result;
        } catch (error) {
            showNotif('Koneksi atau respons server bermasalah. Silakan coba lagi.', false);
            return { ok: false };
        }
    }

    async function load() {
        const seq = ++state.loadSeq;
        if (!state.loaded) qs('#rl-body').innerHTML = '<tr><td colspan="9" class="py-8 text-center text-gray-400">Memuat laporan...</td></tr>';
        const result = await call('?ajax=admin_get_report_review', period(), { method: 'GET' });
        if (seq !== state.loadSeq) return;
        if (!result.ok) {
            qs('#rl-body').innerHTML = '<tr><td colspan="9" class="py-8 text-center text-red-600">Laporan gagal dimuat. Periksa filter tanggal lalu coba lagi.</td></tr>';
            return;
        }
        state.loaded = true;
        state.employees = result.data.employees;
        state.maxDaysBack = result.data.max_days_back;
        [...state.selected].forEach((id) => { if (!findEmployee(id)?.pending) state.selected.delete(id); });
        renderStartups();
        render();
    }

    function renderStartups() {
        const select = qs('#rl-startup');
        const current = select.value;
        const startups = [...new Set(state.employees.map((e) => e.startup).filter(Boolean))].sort();
        select.innerHTML = '<option value="">Semua Startup</option>' + startups.map((s) => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
        select.value = startups.includes(current) ? current : '';
    }

    function visibleEmployees() {
        const term = qs('#rl-search').value.trim().toLowerCase();
        const startup = qs('#rl-startup').value;
        const scope = qs('#rl-scope').value;
        const filter = qs('#rl-filter').value;
        return state.employees.filter((e) => {
            if (term && !`${e.nama} ${e.nim}`.toLowerCase().includes(term)) return false;
            if (startup && e.startup !== startup) return false;
            if (scope === 'active' && e.archived) return false;
            if (scope === 'archived' && !e.archived) return false;
            if (filter === 'review') return e.pending > 0;
            if (filter === 'flagged') return e.flagged > 0;
            if (filter === 'late') return e.late > 0;
            return true;
        });
    }

    function count(value, alertClass) {
        return `<td class="py-2.5 px-3 text-center font-bold ${value > 0 ? alertClass : 'text-gray-300'}">${value}</td>`;
    }

    function render() {
        const { start, end } = period();
        qs('#rl-period-label').textContent = start || end
            ? `${start ? formatDate(start) : 'Awal'} – ${end ? formatDate(end) : 'Sekarang'}`
            : 'Semua tanggal';

        const visible = visibleEmployees();
        qs('#rl-stat-pending').textContent = visible.reduce((sum, e) => sum + e.pending, 0);
        qs('#rl-stat-students').textContent = visible.filter((e) => e.pending > 0).length;
        qs('#rl-stat-late').textContent = visible.reduce((sum, e) => sum + e.late, 0);
        qs('#rl-stat-late-label').textContent = `Pending telat diisi (lebih dari ${state.maxDaysBack} hari)`;

        const selectable = visible.filter((e) => e.pending > 0);
        const checkAll = qs('#rl-check-all');
        checkAll.disabled = selectable.length === 0;
        checkAll.checked = selectable.length > 0 && selectable.every((e) => state.selected.has(e.id));

        qs('#rl-body').innerHTML = visible.length ? visible.map((e) => `
            <tr class="hover:bg-gray-50">
                <td class="py-2.5 px-3"><input type="checkbox" data-select="${e.id}" aria-label="Pilih ${esc(e.nama)}" ${state.selected.has(e.id) ? 'checked' : ''} ${e.pending ? '' : 'disabled'}></td>
                <td class="py-2.5 px-3">
                    <div class="font-semibold text-gray-800">${esc(e.nama)}${e.archived ? ' <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600">Arsip</span>' : ''}</div>
                    <div class="text-xs text-gray-400">${esc(e.nim)}${e.startup ? ' · ' + esc(e.startup) : ''}</div>
                </td>
                ${count(e.pending, 'text-blue-700')}
                ${count(e.flagged, 'text-red-600')}
                ${count(e.late, 'text-amber-700')}
                <td class="py-2.5 px-3 text-gray-600">${e.oldest_pending ? esc(formatDate(e.oldest_pending)) : '-'}</td>
                ${count(e.approved, 'text-emerald-700')}
                ${count(e.disapproved, 'text-red-600')}
                <td class="py-2.5 px-3 text-center"><button type="button" data-open="${e.id}" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold py-1.5 px-3 rounded-lg text-xs">Tinjau</button></td>
            </tr>`).join('') : '<tr><td colspan="9" class="py-8 text-center text-gray-400">Tidak ada laporan yang cocok dengan filter.</td></tr>';
        renderSelection();
    }

    function selectedReportIds() {
        // Laporan yang perlu dicek tidak ikut approve massal.
        return [...state.selected].flatMap((id) => {
            const employee = findEmployee(id);
            return (employee?.pending_ids ?? []).filter((reportId) => !employee.flagged_ids.includes(reportId));
        });
    }

    function renderSelection() {
        const total = selectedReportIds().length;
        const button = qs('#rl-approve-selected');
        button.textContent = `Approve Terpilih (${total})`;
        button.disabled = total === 0;
    }

    async function approveIds(ids) {
        let approved = 0;
        for (let i = 0; i < ids.length; i += BULK_CHUNK) {
            const result = await call('?ajax=admin_bulk_approve_daily_reports', { ids: ids.slice(i, i + BULK_CHUNK).join(',') });
            if (!result.ok) return { ok: false, approved };
            approved += result.approved;
        }
        return { ok: true, approved };
    }

    async function loadDetail(id) {
        const seq = ++state.detailSeq;
        const result = await call('?ajax=admin_get_report_review_detail', { user_id: id, ...period() }, { method: 'GET' });
        if (seq !== state.detailSeq || state.openId !== id) return;
        if (!result.ok) { closeDetail(); return; }
        state.reports = result.data;
        renderDetail();
    }

    function openDetail(id) {
        const employee = findEmployee(id);
        if (!employee) return;
        state.openId = id;
        state.reports = [];
        state.drafts = {};
        qs('#rl-modal-title').textContent = employee.nama;
        qs('#rl-modal-subtitle').textContent = `${employee.nim || '-'}${employee.startup ? ' · ' + employee.startup : ''} · ${qs('#rl-period-label').textContent}`;
        qs('#rl-modal-filter').value = employee.pending > 0 ? 'pending' : '';
        qs('#rl-modal-body').innerHTML = '<p class="text-center text-gray-400 py-8">Memuat laporan...</p>';
        qs('#rl-modal-approve-all').disabled = true;
        qs('#report-review-modal').classList.remove('hidden');
        loadDetail(id);
    }

    function renderDetail() {
        const body = qs('#rl-modal-body');
        const scrollTop = body.scrollTop;
        const filter = qs('#rl-modal-filter').value;
        const reports = state.reports.filter((r) => (filter === 'pending' ? r.status === 'pending' : filter === 'flagged' ? r.flags.length > 0 : filter === 'late' ? r.is_late : true));
        body.innerHTML = reports.length ? reports.map((r) => {
            const [attendanceLabel, attendanceClass] = ATTENDANCE[r.attendance];
            const [statusLabel, statusClass] = STATUS[r.status];
            const present = ['hadir', 'terlambat'].includes(r.attendance);
            return `<section class="border border-gray-200 rounded-xl p-4">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h4 class="font-bold text-gray-800 mr-auto">${esc(formatDate(r.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))}</h4>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold ${attendanceClass}">${esc(attendanceLabel)}</span>
                    ${present ? `<span class="text-xs text-gray-500">${esc(time(r.jam_masuk))} – ${esc(time(r.jam_pulang))}</span>` : ''}
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold ${statusClass}">${esc(statusLabel)}</span>
                </div>
                ${r.flags.length ? `<ul class="flex flex-wrap gap-1.5 mb-2">${r.flags.map((f) => `<li class="text-xs font-semibold text-red-800 bg-red-50 border border-red-200 rounded-lg px-2 py-1">⚠ ${esc(f.label)}</li>`).join('')}</ul>` : ''}
                ${r.is_late ? `<p class="text-xs font-semibold text-amber-800 bg-amber-50 rounded-lg px-3 py-1.5 mb-2">Telat diisi ${r.days_late} hari, dikirim ${esc(formatDate(r.submitted_at))}</p>` : ''}
                <div class="whitespace-pre-wrap break-words text-sm text-gray-700 bg-gray-50 rounded-lg p-3 mb-2">${esc(r.content)}</div>
                <textarea data-report="${r.id}" rows="2" aria-label="Evaluasi laporan ${esc(formatDate(r.date))}" placeholder="Evaluasi admin (opsional)..." class="w-full border border-gray-200 rounded-lg p-2 text-sm mb-2">${esc(state.drafts[r.id] ?? r.evaluation ?? '')}</textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" data-status="disapproved" data-id="${r.id}" ${r.status === 'disapproved' ? 'disabled' : ''} class="bg-red-500 hover:bg-red-600 disabled:bg-gray-200 disabled:text-gray-500 text-white font-semibold px-3 py-1.5 rounded-lg text-xs">Tolak</button>
                    <button type="button" data-status="approved" data-id="${r.id}" ${r.status === 'approved' ? 'disabled' : ''} class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-200 disabled:text-gray-500 text-white font-semibold px-3 py-1.5 rounded-lg text-xs">Approve</button>
                </div>
            </section>`;
        }).join('') : '<p class="text-center text-gray-400 py-8">Tidak ada laporan pada filter ini.</p>';
        body.scrollTop = scrollTop;

        const pending = state.reports.filter((r) => r.status === 'pending' && !r.flags.length).length;
        const approveAll = qs('#rl-modal-approve-all');
        approveAll.textContent = `Approve Semua (${pending})`;
        approveAll.disabled = pending === 0;
    }

    function closeDetail() {
        state.openId = null;
        state.reports = [];
        state.drafts = {};
        qs('#rl-modal-body').innerHTML = '';
        qs('#report-review-modal').classList.add('hidden');
    }

    async function setStatus(id, status) {
        const report = state.reports.find((r) => r.id === id);
        const evaluation = state.drafts[id] ?? report?.evaluation ?? '';
        const result = await call('?ajax=admin_set_daily_status', { id, status, evaluation });
        if (!result.ok) return;
        delete state.drafts[id];
        await Promise.all([loadDetail(state.openId), load()]);
    }

    async function approveAllInDetail() {
        const employee = findEmployee(state.openId);
        const pending = state.reports.filter((r) => r.status === 'pending' && !r.flags.length);
        if (!employee || !pending.length) return;
        const skipped = state.reports.filter((r) => r.status === 'pending' && r.flags.length).length;
        showConfirmModal(`Approve ${pending.length} laporan ${employee.nama}?${skipped ? ` ${skipped} laporan yang perlu dicek dilewati.` : ''}`, async () => {
            // Evaluasi yang baru diketik ikut tersimpan lewat endpoint per laporan.
            const withDraft = pending.filter((r) => (state.drafts[r.id] ?? r.evaluation ?? '') !== (r.evaluation ?? ''));
            for (const report of withDraft) {
                const saved = await call('?ajax=admin_set_daily_status', { id: report.id, status: 'approved', evaluation: state.drafts[report.id] });
                if (!saved.ok) { await Promise.all([loadDetail(state.openId), load()]); return; }
            }
            const draftIds = new Set(withDraft.map((r) => r.id));
            const result = await approveIds(pending.filter((r) => !draftIds.has(r.id)).map((r) => r.id));
            if (result.ok) showNotif(`${pending.length} laporan ${employee.nama} disetujui.`, true);
            state.selected.delete(employee.id);
            state.drafts = {};
            await Promise.all([loadDetail(state.openId), load()]);
        });
    }

    function periodChanged() {
        const { start, end } = period();
        if (start && end && start > end) { showNotif('Tanggal mulai tidak boleh setelah tanggal akhir.', false); return; }
        state.selected.clear();
        load();
    }

    qs('#rl-start').addEventListener('change', periodChanged);
    qs('#rl-end').addEventListener('change', periodChanged);
    qs('#rl-all-dates').addEventListener('click', () => {
        qs('#rl-start').value = '';
        qs('#rl-end').value = '';
        periodChanged();
    });
    ['#rl-search', '#rl-startup', '#rl-scope', '#rl-filter'].forEach((sel) => qs(sel).addEventListener('input', render));

    qs('#rl-approve-selected').addEventListener('click', () => {
        const ids = selectedReportIds();
        if (!ids.length) return;
        const skipped = [...state.selected].reduce((sum, id) => sum + (findEmployee(id)?.flagged ?? 0), 0);
        showConfirmModal(`Approve ${ids.length} laporan dari ${state.selected.size} mahasiswa?${skipped ? ` ${skipped} laporan yang perlu dicek dilewati.` : ''}`, async () => {
            const result = await approveIds(ids);
            if (result.ok) {
                showNotif(`${result.approved} laporan disetujui.`, true);
                state.selected.clear();
            }
            await load();
        });
    });

    qs('#page-review-laporan').addEventListener('click', (e) => {
        const open = e.target.closest('[data-open]');
        if (open) openDetail(Number(open.dataset.open));
    });

    qs('#page-review-laporan').addEventListener('change', (e) => {
        if (e.target.id === 'rl-check-all') {
            visibleEmployees().filter((emp) => emp.pending > 0).forEach((emp) => {
                if (e.target.checked) state.selected.add(emp.id); else state.selected.delete(emp.id);
            });
            render();
        } else if (e.target.dataset.select) {
            const id = Number(e.target.dataset.select);
            if (e.target.checked) state.selected.add(id); else state.selected.delete(id);
            render();
        }
    });

    document.addEventListener('input', (e) => {
        if (e.target.matches('#rl-modal-body textarea')) state.drafts[e.target.dataset.report] = e.target.value;
    });
    document.addEventListener('change', (e) => {
        if (e.target.id === 'rl-modal-filter') renderDetail();
    });
    document.addEventListener('click', (e) => {
        if (e.target.id === 'report-review-modal' || e.target.closest('#rl-modal-close, #rl-modal-close-footer')) { closeDetail(); return; }
        const button = e.target.closest('#rl-modal-body [data-status]');
        if (button) { setStatus(Number(button.dataset.id), button.dataset.status); return; }
        if (e.target.closest('#rl-modal-approve-all')) approveAllInDetail();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && state.openId !== null && qs('#confirm-modal')?.classList.contains('hidden') !== false) closeDetail();
    });

    window.renderReportReview = load;
})();
</script>
