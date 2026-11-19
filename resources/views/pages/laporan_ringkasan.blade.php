<div id="page-insight" class="hidden animate-fade-in-up">
    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center gap-3">
                    <i class="fi fi-sr-chart-histogram text-indigo-600"></i>
                    Laporan Ringkasan
                </h2>
                <p id="in-period-label" role="status" class="text-sm text-gray-500 mt-1">Pilih periode laporan.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" id="in-export-excel" disabled class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Export Excel</button>
                <button type="button" id="in-export-pdf" disabled class="bg-rose-600 hover:bg-rose-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Export PDF</button>
            </div>
        </div>
        <div class="flex flex-col lg:flex-row lg:items-end gap-3 bg-gray-50/50 p-4 rounded-2xl border border-gray-200/60">
            <div class="flex flex-wrap gap-2">
                <button type="button" data-preset="last-week" class="in-preset bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Minggu Lalu</button>
                <button type="button" data-preset="last-month" class="in-preset bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Bulan Lalu</button>
                <button type="button" data-preset="this-month" class="in-preset bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Bulan Ini</button>
            </div>
            <form id="in-form" class="flex flex-col sm:flex-row sm:items-end gap-3 lg:ml-auto">
                <label class="text-xs font-semibold text-gray-600">Dari tanggal
                    <input type="date" id="in-start" required class="block mt-1 w-full p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                </label>
                <label class="text-xs font-semibold text-gray-600">Sampai tanggal
                    <input type="date" id="in-end" required class="block mt-1 w-full p-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm text-gray-700">
                </label>
                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">Tampilkan</button>
            </form>
        </div>
    </div>

    <div id="in-content" class="hidden space-y-6">
        <section aria-labelledby="in-quality-title" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
            <h3 id="in-quality-title" class="text-lg font-bold text-gray-800 mb-1">Pemeriksaan Data</h3>
            <p class="text-sm text-gray-500 mb-4">Periksa bagian ini sebelum laporan dibagikan.</p>
            <div id="in-quality" class="space-y-2"></div>
        </section>

        <section aria-labelledby="in-summary-title" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
            <h3 id="in-summary-title" class="text-lg font-bold text-gray-800 mb-4">Ringkasan</h3>
            <div id="in-cards" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-5"></div>
            <h4 class="text-sm font-bold text-gray-700 mb-2">Kesimpulan</h4>
            <ul id="in-conclusions" class="list-disc pl-5 space-y-1 text-sm text-gray-700"></ul>
        </section>

        <section aria-labelledby="in-startup-title" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
            <h3 id="in-startup-title" class="text-lg font-bold text-gray-800 mb-4">Perbandingan Startup</h3>
            <div class="overflow-x-auto rounded-2xl border border-gray-100"><table class="w-full min-w-max text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50"><tr>
                    <th class="py-3 px-4 font-bold">#</th><th class="py-3 px-4 font-bold">Startup</th><th class="py-3 px-4 font-bold text-right">Mahasiswa</th><th class="py-3 px-4 font-bold text-right">Kehadiran</th><th class="py-3 px-4 font-bold text-right">Perubahan</th><th class="py-3 px-4 font-bold text-right">Terlambat</th><th class="py-3 px-4 font-bold text-right">Hari Alpha</th><th class="py-3 px-4 font-bold text-right">Laporan Terisi</th><th class="py-3 px-4 font-bold text-right">Tepat Waktu</th>
                </tr></thead>
                <tbody id="in-startups" class="divide-y divide-gray-100"></tbody>
            </table></div>
        </section>

        <section aria-labelledby="in-risk-title" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
            <h3 id="in-risk-title" class="text-lg font-bold text-gray-800 mb-1">Mahasiswa Berisiko</h3>
            <p class="text-sm text-gray-500 mb-4">Alpha 2 hari kerja beruntun, keterlambatan naik minimal 15 poin, atau kehadiran turun minimal 10 poin.</p>
            <div class="overflow-x-auto rounded-2xl border border-gray-100"><table class="w-full min-w-max text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50"><tr>
                    <th class="py-3 px-4 font-bold">Mahasiswa</th><th class="py-3 px-4 font-bold">Startup</th><th class="py-3 px-4 font-bold">Alasan</th><th class="py-3 px-4 font-bold text-right">Kehadiran</th>
                </tr></thead>
                <tbody id="in-risks" class="divide-y divide-gray-100"></tbody>
            </table></div>
        </section>

        <section aria-labelledby="in-discipline-title" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
            <h3 id="in-discipline-title" class="text-lg font-bold text-gray-800 mb-1">Kedisiplinan Laporan</h3>
            <p class="text-sm text-gray-500 mb-4">Tepat waktu berarti dibuat paling lambat 1 hari setelah tanggal laporan. Urutan dimulai dari yang paling perlu perhatian.</p>
            <div class="overflow-auto max-h-96 rounded-2xl border border-gray-100"><table class="w-full min-w-max text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50 sticky top-0"><tr>
                    <th class="py-3 px-4 font-bold">Mahasiswa</th><th class="py-3 px-4 font-bold">Startup</th><th class="py-3 px-4 font-bold text-right">Wajib</th><th class="py-3 px-4 font-bold text-right">Terisi</th><th class="py-3 px-4 font-bold text-right">% Terisi</th><th class="py-3 px-4 font-bold text-right">% Tepat Waktu</th>
                </tr></thead>
                <tbody id="in-discipline" class="divide-y divide-gray-100"></tbody>
            </table></div>
        </section>

        <section aria-labelledby="in-pattern-title" class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
            <h3 id="in-pattern-title" class="text-lg font-bold text-gray-800 mb-1">Pola Keterlambatan</h3>
            <p class="text-sm text-gray-500 mb-4">Jumlah presensi terlambat menurut hari dan jam masuk. Warna lebih gelap berarti lebih banyak.</p>
            <div class="overflow-x-auto rounded-2xl border border-gray-100"><table class="w-full min-w-max text-sm text-gray-600">
                <thead id="in-pattern-head" class="text-xs text-gray-800 bg-gray-50/50"></thead>
                <tbody id="in-pattern"></tbody>
            </table></div>
        </section>
    </div>
</div>

<script>
(() => {
    const page = document.getElementById('page-insight');
    const $ = (selector) => page.querySelector(selector);
    const HEAT = [['#eef2ff', '#1e1b4b'], ['#c7d2fe', '#1e1b4b'], ['#a5b4fc', '#1e1b4b'], ['#4f46e5', '#ffffff'], ['#312e81', '#ffffff']];
    let report = null, loadSeq = 0, loaded = false;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = (value) => (value === null || value === undefined ? '-' : Number(value).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }));
    const pct = (value) => (value === null || value === undefined ? '-' : `${num(value)}%`);
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const longDate = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    const periodText = (p) => `${longDate(p.start)} - ${longDate(p.end)}`;
    const signed = (value) => (value === null || value === undefined ? '-' : `${value > 0 ? '+' : ''}${num(value)}`);
    const emptyRow = (cols, text) => `<tr><td colspan="${cols}" class="py-6 text-center text-gray-400">${text}</td></tr>`;

    function presetRange(preset) {
        const now = new Date();
        if (preset === 'this-month') return [new Date(now.getFullYear(), now.getMonth(), 1), now];
        if (preset === 'last-month') return [new Date(now.getFullYear(), now.getMonth() - 1, 1), new Date(now.getFullYear(), now.getMonth(), 0)];
        const monday = new Date(now);
        monday.setDate(now.getDate() - ((now.getDay() + 6) % 7) - 7);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        return [monday, sunday];
    }

    async function load() {
        const seq = ++loadSeq;
        $('#in-period-label').textContent = 'Menyusun laporan...';
        $('#in-export-excel').disabled = $('#in-export-pdf').disabled = true;
        let result;
        try {
            result = await api('?ajax=admin_get_period_insight', { start: $('#in-start').value, end: $('#in-end').value }, { method: 'GET', suppressModal: true });
        } catch (e) {
            result = { ok: false, message: 'Koneksi atau respons server bermasalah. Silakan coba lagi.' };
        }
        if (seq !== loadSeq) return;
        if (!result.ok) {
            report = null;
            $('#in-content').classList.add('hidden');
            $('#in-period-label').textContent = result.message || 'Laporan gagal disusun.';
            return;
        }
        report = result.data;
        render();
    }

    function deltaBadge(card) {
        if (!report.period.has_previous || card.delta === null) return '<span class="text-xs text-gray-500">Belum ada pembanding</span>';
        if (card.better === null) return '<span class="text-xs font-semibold text-gray-600">Tidak berubah</span>';
        const direction = card.delta > 0 ? '▲' : '▼';
        return `<span class="text-xs font-semibold ${card.better ? 'text-emerald-700' : 'text-red-700'}">${direction} ${num(Math.abs(card.delta))} poin, ${card.better ? 'membaik' : 'memburuk'}</span>`;
    }

    function renderPattern() {
        const { hours, rows } = report.pattern;
        const max = Math.max(1, ...rows.flatMap((r) => r.hours));
        $('#in-pattern-head').innerHTML = `<tr><th scope="col" class="py-3 px-4 text-left font-bold">Hari</th>${hours.map((h) => `<th scope="col" class="py-3 px-2 font-bold">${esc(h)}</th>`).join('')}<th scope="col" class="py-3 px-4 text-right font-bold">Total Terlambat</th><th scope="col" class="py-3 px-4 text-right font-bold">% Terlambat</th></tr>`;
        $('#in-pattern').innerHTML = rows.map((r) => `<tr>
            <th scope="row" class="py-1 px-4 text-left font-semibold text-gray-800">${esc(r.day)}</th>
            ${r.hours.map((count, i) => {
                const [bg, ink] = count ? HEAT[Math.min(4, Math.ceil((count / max) * 5) - 1)] : ['#f9fafb', '#9ca3af'];
                return `<td class="p-0.5"><div title="${esc(r.day)}, ${esc(hours[i])}: ${count} presensi terlambat" class="rounded text-center py-2 font-semibold" style="background:${bg};color:${ink}">${count}</div></td>`;
            }).join('')}
            <td class="py-1 px-4 text-right">${r.late}</td><td class="py-1 px-4 text-right font-semibold text-gray-800">${pct(r.late_rate)}</td>
        </tr>`).join('') || emptyRow(hours.length + 3, 'Belum ada data presensi.');
    }

    function render() {
        const p = report.period;
        $('#in-period-label').textContent = `${periodText(p)} · ${report.student_count} mahasiswa${p.capped ? ' · data sampai kemarin' : ''}${p.has_previous ? ` · dibandingkan dengan ${longDate(p.previous_start)} - ${longDate(p.previous_end)}` : ''}`;
        $('#in-quality').innerHTML = report.quality.map((q) => `<details class="border border-amber-200 bg-amber-50 rounded-xl">
            <summary class="cursor-pointer p-3 text-sm font-semibold text-amber-900">${esc(q.title)}: ${q.count}</summary>
            <div class="px-3 pb-3 text-sm text-gray-700">
                <p class="mb-2">${esc(q.action)}</p>
                <ul class="max-h-48 overflow-y-auto space-y-1">${q.samples.map((s) => `<li>${esc(s.nama)}${s.detail ? ` <span class="text-gray-500">(${esc(s.detail)})</span>` : ''}</li>`).join('')}</ul>
                ${q.count > q.samples.length ? `<p class="mt-2 text-xs text-gray-500">Menampilkan ${q.samples.length} dari ${q.count} data.</p>` : ''}
            </div>
        </details>`).join('') || '<p class="text-sm font-semibold text-emerald-700">✓ Tidak ada masalah data pada periode ini.</p>';

        $('#in-cards').innerHTML = report.summary.map((c) => `<div class="bg-gray-50 rounded-2xl p-4">
            <div class="text-xs font-semibold text-gray-600">${esc(c.label)}</div>
            <div class="text-2xl font-bold text-gray-900 my-1">${pct(c.value)}</div>
            ${deltaBadge(c)}
        </div>`).join('');
        $('#in-conclusions').innerHTML = report.conclusions.map((line) => `<li>${esc(line)}</li>`).join('');

        $('#in-startups').innerHTML = report.startups.map((s, i) => `<tr>
            <td class="py-2.5 px-4">${i + 1}</td><td class="py-2.5 px-4 font-semibold text-gray-800">${esc(s.name)}</td><td class="py-2.5 px-4 text-right">${s.students}</td>
            <td class="py-2.5 px-4 text-right font-semibold text-gray-800">${pct(s.attendance_rate)}</td><td class="py-2.5 px-4 text-right">${signed(s.attendance_delta)}</td>
            <td class="py-2.5 px-4 text-right">${pct(s.late_rate)}</td><td class="py-2.5 px-4 text-right">${s.alpha}</td><td class="py-2.5 px-4 text-right">${pct(s.report_rate)}</td><td class="py-2.5 px-4 text-right">${pct(s.timely_rate)}</td>
        </tr>`).join('');
        $('#in-risks').innerHTML = report.risks.map((r) => `<tr>
            <td class="py-2.5 px-4 font-semibold text-gray-800">${esc(r.nama)}<div class="text-xs font-normal text-gray-500">${esc(r.nim)}</div></td><td class="py-2.5 px-4">${esc(r.startup)}</td>
            <td class="py-2.5 px-4 whitespace-normal">${r.reasons.map(esc).join('<br>')}</td><td class="py-2.5 px-4 text-right">${pct(r.attendance_rate)}</td>
        </tr>`).join('') || emptyRow(4, 'Tidak ada mahasiswa aktif yang berisiko.');
        $('#in-discipline').innerHTML = report.discipline.map((d) => `<tr>
            <td class="py-2.5 px-4 font-semibold text-gray-800">${esc(d.nama)}</td><td class="py-2.5 px-4">${esc(d.startup)}</td><td class="py-2.5 px-4 text-right">${d.report_due}</td><td class="py-2.5 px-4 text-right">${d.report_filled}</td>
            <td class="py-2.5 px-4 text-right">${pct(d.report_rate)}</td><td class="py-2.5 px-4 text-right">${pct(d.timely_rate)}</td>
        </tr>`).join('') || emptyRow(6, 'Belum ada laporan wajib pada periode ini.');
        renderPattern();

        $('#in-content').classList.remove('hidden');
        $('#in-export-excel').disabled = $('#in-export-pdf').disabled = false;
    }

    // Satu sumber isi untuk Excel dan PDF.
    function exportSections() {
        return [
            { name: 'Ringkasan', title: 'Ringkasan', head: ['Indikator', 'Periode Ini (%)', 'Periode Sebelumnya (%)', 'Perubahan (poin)'], rows: report.summary.map((c) => [c.label, c.value, c.previous, c.delta]) },
            { name: 'Kesimpulan', title: 'Kesimpulan', head: ['Kesimpulan'], rows: report.conclusions.map((line) => [line]) },
            { name: 'Pemeriksaan Data', title: 'Pemeriksaan Data', head: ['Masalah', 'Jumlah', 'Tindak Lanjut'], rows: report.quality.map((q) => [q.title, q.count, q.action]) },
            { name: 'Startup', title: 'Perbandingan Startup', head: ['Startup', 'Mahasiswa', 'Kehadiran (%)', 'Perubahan (poin)', 'Terlambat (%)', 'Hari Alpha', 'Laporan Terisi (%)', 'Tepat Waktu (%)'], rows: report.startups.map((s) => [s.name, s.students, s.attendance_rate, s.attendance_delta, s.late_rate, s.alpha, s.report_rate, s.timely_rate]) },
            { name: 'Mahasiswa Berisiko', title: 'Mahasiswa Berisiko', head: ['Nama', 'NIM', 'Startup', 'Alasan', 'Kehadiran (%)'], rows: report.risks.map((r) => [r.nama, r.nim, r.startup, r.reasons.join('; '), r.attendance_rate]) },
            { name: 'Kedisiplinan Laporan', title: 'Kedisiplinan Laporan', head: ['Nama', 'NIM', 'Startup', 'Wajib', 'Terisi', 'Terisi (%)', 'Tepat Waktu (%)'], rows: report.discipline.map((d) => [d.nama, d.nim, d.startup, d.report_due, d.report_filled, d.report_rate, d.timely_rate]) },
            { name: 'Pola Keterlambatan', title: 'Pola Keterlambatan', head: ['Hari', ...report.pattern.hours, 'Total Terlambat', 'Terlambat (%)'], rows: report.pattern.rows.map((r) => [r.day, ...r.hours, r.late, r.late_rate]) },
        ];
    }

    const fileName = (ext) => `laporan_ringkasan_${report.period.start}_${report.period.end}.${ext}`;

    function exportExcel() {
        const xml = (value) => String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const cell = (value, style = '') => (value === null || value === undefined
            ? `<Cell${style}><Data ss:Type="String">-</Data></Cell>`
            : `<Cell${style}><Data ss:Type="${typeof value === 'number' ? 'Number' : 'String'}">${xml(value)}</Data></Cell>`);
        const sheets = exportSections().map((s) => `<Worksheet ss:Name="${xml(s.name)}"><Table>
            <Row>${cell(`${s.title} (${periodText(report.period)})`, ' ss:StyleID="h"')}</Row><Row></Row>
            <Row>${s.head.map((h) => cell(h, ' ss:StyleID="h"')).join('')}</Row>
            ${s.rows.map((r) => `<Row>${r.map((v) => cell(v)).join('')}</Row>`).join('')}
        </Table></Worksheet>`).join('');
        const declaration = '<' + '?xml version="1.0"?' + '><' + '?mso-application progid="Excel.Sheet"?' + '>';
        const workbook = `${declaration}<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="h"><Font ss:Bold="1"/></Style></Styles>${sheets}</Workbook>`;
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([workbook], { type: 'application/vnd.ms-excel' }));
        link.download = fileName('xls');
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function exportPdf() {
        if (!window.jspdf) {
            showNotif('Library PDF belum siap, coba muat ulang halaman.', false);
            return;
        }
        const doc = new window.jspdf.jsPDF('l', 'mm', 'a4');
        doc.setFontSize(16);
        doc.setFont(undefined, 'bold');
        doc.text('Laporan Ringkasan Presensi', 14, 16);
        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.text(`${periodText(report.period)} | ${report.student_count} mahasiswa`, 14, 23);
        let y = 30;
        exportSections().forEach((s) => {
            if (!s.rows.length) return;
            if (y > 175) { doc.addPage(); y = 16; }
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text(s.title, 14, y);
            doc.autoTable({
                startY: y + 3,
                head: [s.head],
                body: s.rows.map((r) => r.map((v) => (typeof v === 'number' && !Number.isInteger(v) ? num(v) : (v ?? '-')))),
                styles: { fontSize: 8, cellPadding: 1.5 },
                headStyles: { fillColor: [67, 56, 202] },
                margin: { left: 14, right: 14 },
            });
            y = doc.lastAutoTable.finalY + 10;
        });
        doc.save(fileName('pdf'));
    }

    page.querySelectorAll('.in-preset').forEach((button) => button.addEventListener('click', () => {
        const [start, end] = presetRange(button.dataset.preset);
        $('#in-start').value = iso(start);
        $('#in-end').value = iso(end);
        load();
    }));
    $('#in-form').addEventListener('submit', (e) => { e.preventDefault(); load(); });
    $('#in-export-excel').addEventListener('click', exportExcel);
    $('#in-export-pdf').addEventListener('click', exportPdf);

    window.renderPeriodInsight = () => {
        if (loaded) return;
        loaded = true;
        page.querySelector('[data-preset="last-month"]').click();
    };
})();
</script>
