<div id="page-startups" class="hidden animate-fade-in-up">
    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center gap-3">
                    <i class="fi fi-sr-rocket-lunch text-indigo-600"></i>
                    Kelola Startup
                </h2>
                <p id="st-status" role="status" class="text-sm text-gray-500 mt-1">Daftar startup yang dipakai di form member dan pendaftaran.</p>
            </div>
            <button type="button" id="st-sync" class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors whitespace-nowrap">Sinkron dari Portal</button>
        </div>

        <section id="st-cleanup" class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-4 md:p-5 mb-6" aria-labelledby="st-cleanup-title">
            <h3 id="st-cleanup-title" class="font-bold text-amber-900">Rapikan Data Lama</h3>
            <p class="text-sm text-amber-800 mb-3">Nama berikut belum sesuai daftar resmi. Periksa tujuan tiap nama sebelum menerapkan.</p>
            <div class="overflow-x-auto rounded-xl border border-amber-200 bg-white">
                <table class="w-full min-w-max text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-800 uppercase bg-amber-50/60">
                        <tr>
                            <th class="py-3 px-4 font-bold">Nama Lama</th>
                            <th class="py-3 px-4 font-bold text-center">Mahasiswa</th>
                            <th class="py-3 px-4 font-bold">Jadikan</th>
                        </tr>
                    </thead>
                    <tbody id="st-cleanup-body" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <div class="flex justify-end mt-3">
                <button type="button" id="st-cleanup-apply" disabled class="bg-amber-600 hover:bg-amber-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Terapkan Penyesuaian</button>
            </div>
        </section>

        <form id="st-add-form" class="flex flex-col sm:flex-row gap-2 mb-4">
            <label for="st-new-name" class="sr-only">Nama startup baru</label>
            <input type="text" id="st-new-name" maxlength="100" required placeholder="Nama startup baru" class="flex-1 p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
            <button type="submit" class="bg-gray-800 hover:bg-gray-900 disabled:bg-gray-300 text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Tambah Startup</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-gray-100">
            <table class="w-full min-w-max text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                    <tr>
                        <th class="py-3 px-4 font-bold">Startup</th>
                        <th class="py-3 px-4 font-bold text-center">Sumber</th>
                        <th class="py-3 px-4 font-bold text-center">Mahasiswa</th>
                        <th class="py-3 px-4 font-bold text-center">Status</th>
                        <th class="py-3 px-4 font-bold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="st-body" class="bg-white divide-y divide-gray-100"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const state = { startups: [], unregistered: [], editing: null, merging: null, busy: false };
    const page = document.getElementById('page-startups');
    const $ = (selector) => page.querySelector(selector);
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const find = (id) => state.startups.find((s) => s.id === id);
    const active = () => state.startups.filter((s) => s.is_active);
    const options = (exceptId, selected) => active().filter((s) => s.id !== exceptId)
        .map((s) => `<option value="${s.id}" ${s.id === selected ? 'selected' : ''}>${esc(s.name)}</option>`).join('');
    const pill = (text, classes) => `<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${classes}">${text}</span>`;
    const linkBtn = (action, id, label, classes = 'text-indigo-700 hover:text-indigo-900') => `<button type="button" data-action="${action}" data-id="${id}" class="font-semibold ${classes}">${label}</button>`;

    function setBusy(busy, message = '') {
        state.busy = busy;
        page.querySelectorAll('button, input, select').forEach((el) => { el.disabled = busy; });
        if (message) $('#st-status').textContent = message;
        if (!busy) syncCleanupButton();
    }

    async function send(action, data, success) {
        setBusy(true, 'Memproses...');
        let result;
        try {
            result = await api(`?ajax=${action}`, data, { suppressModal: true });
        } catch (e) {
            result = { ok: false, message: 'Koneksi atau respons server bermasalah. Silakan coba lagi.' };
        }
        setBusy(false);
        if (!result.ok) {
            $('#st-status').textContent = result.message || 'Permintaan gagal diproses.';
            showNotif(result.message || 'Permintaan gagal diproses.', false);
            return;
        }
        state.editing = state.merging = null;
        const message = success(result);
        await load();
        $('#st-status').textContent = message;
        showNotif(message, true);
    }

    function rowActions(s) {
        if (state.editing === s.id) {
            return `<div class="flex justify-end gap-3">${linkBtn('save-name', s.id, 'Simpan')}${linkBtn('cancel', s.id, 'Batal', 'text-gray-600 hover:text-gray-800')}</div>`;
        }
        if (state.merging === s.id) {
            return `<div class="flex flex-wrap justify-end items-center gap-3">
                <select data-merge-target aria-label="Gabungkan ${esc(s.name)} ke" class="p-1.5 bg-white border border-gray-200 rounded-lg text-sm"><option value="">Gabungkan ke...</option>${options(s.id)}</select>
                ${linkBtn('merge', s.id, 'Gabungkan', 'text-red-700 hover:text-red-900')}${linkBtn('cancel', s.id, 'Batal', 'text-gray-600 hover:text-gray-800')}</div>`;
        }
        const local = s.from_portal ? '' : linkBtn('edit', s.id, 'Ubah Nama') + linkBtn('start-merge', s.id, 'Gabungkan');
        return `<div class="flex justify-end gap-3">${local}${linkBtn('toggle', s.id, s.is_active ? 'Nonaktifkan' : 'Aktifkan', 'text-gray-600 hover:text-gray-800')}</div>`;
    }

    function render() {
        $('#st-body').innerHTML = state.startups.map((s) => `<tr>
            <td class="py-3 px-4 font-semibold text-gray-800">${state.editing === s.id
                ? `<input type="text" data-edit-name maxlength="100" value="${esc(s.name)}" aria-label="Nama baru ${esc(s.name)}" class="p-1.5 border border-gray-200 rounded-lg text-sm w-56">`
                : esc(s.name)}</td>
            <td class="py-3 px-4 text-center">${s.from_portal ? pill('Portal', 'bg-indigo-100 text-indigo-800') : pill('Lokal', 'bg-gray-100 text-gray-700')}</td>
            <td class="py-3 px-4 text-center">${s.members}</td>
            <td class="py-3 px-4 text-center">${s.is_active ? pill('Aktif', 'bg-emerald-100 text-emerald-800') : pill('Nonaktif', 'bg-red-100 text-red-800')}</td>
            <td class="py-3 px-4">${rowActions(s)}</td>
        </tr>`).join('') || '<tr><td colspan="5" class="py-8 text-center text-gray-400">Belum ada startup.</td></tr>';

        $('#st-cleanup').classList.toggle('hidden', state.unregistered.length === 0);
        $('#st-cleanup-body').innerHTML = state.unregistered.map((u, i) => `<tr>
            <td class="py-2.5 px-4 font-semibold text-gray-800">${esc(u.name)}</td>
            <td class="py-2.5 px-4 text-center">${u.members}</td>
            <td class="py-2.5 px-4"><select data-cleanup="${i}" aria-label="Tujuan untuk ${esc(u.name)}" class="w-full p-2 bg-white border border-gray-200 rounded-lg text-sm"><option value="">Lewati</option>${options(null, u.suggested_id)}</select></td>
        </tr>`).join('');
        syncCleanupButton();
    }

    const cleanupMapping = () => [...page.querySelectorAll('[data-cleanup]')].filter((select) => select.value)
        .map((select) => ({ ...state.unregistered[+select.dataset.cleanup], startup_id: +select.value }));
    const syncCleanupButton = () => { $('#st-cleanup-apply').disabled = state.busy || cleanupMapping().length === 0; };

    async function load() {
        let result;
        try {
            result = await api('?ajax=admin_get_startups', {}, { method: 'GET', suppressModal: true });
        } catch (e) {
            result = { ok: false };
        }
        if (!result.ok) {
            $('#st-body').innerHTML = '<tr><td colspan="5" class="py-8 text-center text-red-600">Data startup gagal dimuat. Muat ulang halaman.</td></tr>';
            return;
        }
        state.startups = result.data.startups;
        state.unregistered = result.data.unregistered;
        render();
    }

    $('#st-add-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const name = $('#st-new-name').value.trim();
        if (!name) return;
        send('admin_create_startup', { name }, () => { $('#st-new-name').value = ''; return `${name} ditambahkan.`; });
    });

    $('#st-sync').addEventListener('click', () => send('admin_sync_startups', {}, (r) => {
        const conflict = r.conflicts ? `, ${r.conflicts} perlu diperiksa` : '';
        return `Sinkron selesai: ${r.added} baru, ${r.updated} diperbarui${conflict}.`;
    }));

    $('#st-cleanup-body').addEventListener('change', syncCleanupButton);
    $('#st-cleanup-apply').addEventListener('click', () => {
        const mapping = cleanupMapping();
        const total = mapping.reduce((sum, row) => sum + row.members, 0);
        showConfirmModal(`Sesuaikan startup ${total} mahasiswa dari ${mapping.length} nama lama? Database di-backup otomatis lebih dulu. Export dan laporan lama akan menampilkan nama baru.`, () => {
            send('admin_apply_startup_cleanup', { mapping: JSON.stringify(mapping.map(({ name, startup_id }) => ({ name, startup_id }))) }, (r) => `${r.moved} mahasiswa disesuaikan. Backup: ${r.backup}`);
        });
    });

    $('#st-body').addEventListener('click', (e) => {
        const button = e.target.closest('[data-action]');
        if (!button || state.busy) return;
        const id = +button.dataset.id, startup = find(id);
        const row = button.closest('tr');
        switch (button.dataset.action) {
            case 'edit': state.editing = id; state.merging = null; render(); $('[data-edit-name]').focus(); break;
            case 'start-merge': state.merging = id; state.editing = null; render(); break;
            case 'cancel': state.editing = state.merging = null; render(); break;
            case 'save-name': {
                const name = row.querySelector('[data-edit-name]').value.trim();
                if (name) send('admin_rename_startup', { id, name }, () => `Nama diubah menjadi ${name}.`);
                break;
            }
            case 'toggle':
                send('admin_set_startup_active', { id, active: startup.is_active ? '0' : '1' }, () => `${startup.name} ${startup.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`);
                break;
            case 'merge': {
                const target = find(+row.querySelector('[data-merge-target]').value);
                if (!target) { showNotif('Pilih startup tujuan.', false); break; }
                showConfirmModal(`Gabungkan ${startup.name} (${startup.members} mahasiswa) ke ${target.name}? ${startup.name} akan dihapus dari daftar. Database di-backup otomatis lebih dulu.`, () => {
                    send('admin_merge_startup', { id, target_id: target.id }, (r) => `${r.moved} mahasiswa dipindahkan ke ${target.name}. Backup: ${r.backup}`);
                });
                break;
            }
        }
    });

    window.renderStartupPage = load;
})();
</script>
