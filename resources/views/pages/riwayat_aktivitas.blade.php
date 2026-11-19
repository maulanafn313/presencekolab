<div id="page-activity" class="hidden animate-fade-in-up">
    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
        <div class="mb-5">
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center gap-3">
                <i class="fi fi-sr-time-past text-indigo-600"></i>
                Riwayat Aktivitas
            </h2>
            <p id="ac-status" role="status" class="text-sm text-gray-500 mt-1">Perubahan data oleh admin. Aksi massal bisa dibatalkan dari sini.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-gray-50/50 p-4 rounded-2xl border border-gray-200/60 mb-4">
            <input type="search" id="ac-search" aria-label="Cari aktivitas" placeholder="Cari aktivitas atau admin..." class="md:col-span-2 p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
            <select id="ac-filter" aria-label="Filter aktivitas" class="p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                <option value="">Semua Aktivitas</option>
                <option value="undo">Bisa Dibatalkan</option>
                <option value="undone">Sudah Dibatalkan</option>
            </select>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-gray-100">
            <table class="w-full min-w-max text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                    <tr>
                        <th class="py-3 px-4 font-bold">Waktu</th>
                        <th class="py-3 px-4 font-bold">Admin</th>
                        <th class="py-3 px-4 font-bold">Aktivitas</th>
                        <th class="py-3 px-4 font-bold">Detail</th>
                        <th class="py-3 px-4 font-bold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="ac-body" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const page = document.getElementById('page-activity');
    const $ = (selector) => page.querySelector(selector);
    let logs = [];
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const when = (value) => new Date(String(value).replace(' ', 'T')).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    function visible() {
        const term = $('#ac-search').value.trim().toLowerCase();
        const filter = $('#ac-filter').value;
        return logs.filter((log) => `${log.summary} ${log.detail || ''} ${log.actor_name || ''}`.toLowerCase().includes(term)
            && (!filter || (filter === 'undo' ? log.can_undo : !!log.undone_at)));
    }

    function render() {
        renderPaginatedTable('ac-body', visible(), (log) => {
            const tr = document.createElement('tr');
            const action = log.can_undo
                ? `<button type="button" data-undo="${log.id}" class="font-semibold text-red-700 hover:text-red-900">Batalkan</button>`
                : (log.undone_at ? `<span class="text-xs font-semibold text-gray-500">Dibatalkan ${esc(log.undone_by)}</span>` : '<span class="text-gray-400">-</span>');
            tr.innerHTML = `<td class="py-2.5 px-4 whitespace-nowrap">${esc(when(log.created_at))}</td>
                <td class="py-2.5 px-4">${esc(log.actor_name || '-')}</td>
                <td class="py-2.5 px-4 font-semibold text-gray-800">${esc(log.summary)}</td>
                <td class="py-2.5 px-4 whitespace-normal">${esc(log.detail || '-')}</td>
                <td class="py-2.5 px-4 text-right whitespace-nowrap">${action}</td>`;
            return tr;
        }, { colSpan: 5, emptyMessage: logs.length ? 'Tidak ada aktivitas yang cocok.' : 'Belum ada aktivitas tercatat.', onPageChange: render });
    }

    async function load() {
        let result;
        try {
            result = await api('?ajax=admin_get_activity_logs', {}, { method: 'GET', suppressModal: true });
        } catch (e) {
            result = { ok: false };
        }
        if (!result.ok) {
            $('#ac-status').textContent = 'Riwayat gagal dimuat. Muat ulang halaman.';
            return;
        }
        logs = result.data;
        render();
    }

    function undo(log) {
        const note = log.irreversible ? ` ${log.irreversible} data yang pindah antara hadir dan izin/sakit tidak ikut dikembalikan.` : '';
        showConfirmModal(`Batalkan "${log.summary}"? Data yang sudah diubah lagi setelah aksi ini tidak akan ditimpa.${note}`, async () => {
            let result;
            try {
                result = await api('?ajax=admin_undo_activity', { id: log.id }, { suppressModal: true });
            } catch (e) {
                result = { ok: false, message: 'Koneksi atau respons server bermasalah. Silakan coba lagi.' };
            }
            const message = result.ok ? `${result.restored} data dikembalikan${result.skipped ? `, ${result.skipped} dilewati karena sudah berubah` : ''}.` : (result.message || 'Gagal membatalkan aktivitas.');
            $('#ac-status').textContent = message;
            showNotif(message, !!result.ok);
            if (result.ok) load();
        });
    }

    ['#ac-search', '#ac-filter'].forEach((selector) => $(selector).addEventListener('input', () => { resetTablePage('ac-body'); render(); }));
    $('#ac-body').addEventListener('click', (e) => {
        const button = e.target.closest('[data-undo]');
        if (button) undo(logs.find((log) => log.id === +button.dataset.undo));
    });
    window.renderActivityPage = load;
})();
</script>
