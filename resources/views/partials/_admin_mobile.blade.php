<style>
    .mobile-pager { display: none; }

    /* Di HP, tabel admin tampil sebagai kartu dan setiap daftar dibagi per halaman. */
    @media (max-width: 767px) {
        table.mobile-cards { display: block; width: 100%; min-width: 0 !important; }
        table.mobile-cards thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        table.mobile-cards tbody { display: block; }
        table.mobile-cards tbody > tr { display: block; margin: 10px; padding: 6px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; }
        table.mobile-cards tbody > tr > td { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 4px 0 !important; border: 0; text-align: right !important; white-space: normal !important; overflow-wrap: break-word; }
        table.mobile-cards tbody > tr > td > * { min-width: 0; }
        table.mobile-cards tbody > tr > td:has(> div + div) { display: block; text-align: left !important; }
        table.mobile-cards tbody > tr > td:has(> div + div)::before { display: block; margin-bottom: 2px; }
        table.mobile-cards tbody > tr > td::before { content: attr(data-label); flex: 0 0 38%; font-size: 12px; font-weight: 600; color: #6b7280; text-align: left; }
        table.mobile-cards tbody > tr > td[data-label=""]::before { display: none; }
        table.mobile-cards tbody > tr > td[colspan] { display: block; text-align: center !important; }
        table.mobile-cards tbody > tr > td[colspan]::before { display: none; }

        .mobile-page-hidden { display: none !important; }
        .mobile-pager.is-active { display: flex; flex-shrink: 0; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; }
        .mobile-pager button { min-height: 44px; padding: 0 14px; border-radius: 12px; background: #eef2ff; color: #4338ca; font-size: 13px; font-weight: 700; }
        .mobile-pager button:disabled { background: #f3f4f6; color: #9ca3af; }
        .mobile-pager span { font-size: 12px; font-weight: 600; color: #4b5563; text-align: center; }
        .table-pagination-container div:has(> .pag-size-select) { display: none; }
    }
</style>
<script>
(() => {
    const PHONE_PAGE_SIZE = 5;
    const isPhone = window.matchMedia('(max-width: 767px)').matches;

    // Tabel yang sudah punya pagination cukup memakai jumlah baris yang lebih sedikit di HP.
    const paginatedTables = ['table-laporan-body', 'table-members-body', 'ac-body', 'am-body', 'table-requests-body', 'kpi-table-body'];
    window.tablePaginationState = window.tablePaginationState || {};
    if (isPhone) {
        paginatedTables.forEach((id) => { window.tablePaginationState[id] = { currentPage: 1, pageSize: PHONE_PAGE_SIZE }; });
    }

    const cardTables = [...paginatedTables, 'rl-body', 'st-body', 'st-cleanup-body', 'in-startups', 'in-risks', 'in-discipline'];
    const pagedLists = {
        'rl-body': PHONE_PAGE_SIZE, 'rl-modal-body': 3, 'st-body': PHONE_PAGE_SIZE, 'st-cleanup-body': PHONE_PAGE_SIZE,
        'cal-body': 8, 'in-quality': PHONE_PAGE_SIZE, 'in-conclusions': PHONE_PAGE_SIZE, 'in-startups': PHONE_PAGE_SIZE,
        'in-risks': PHONE_PAGE_SIZE, 'in-discipline': PHONE_PAGE_SIZE, 'action-center-list': 4,
        'daily-report-employees-list': PHONE_PAGE_SIZE, 'most-late-list': PHONE_PAGE_SIZE, 'most-attentive-list': PHONE_PAGE_SIZE,
        'list-kelompok-aktif': 3, 'list-kelompok-arsip': 3, 'mh-table-body': PHONE_PAGE_SIZE, 'mh-body': PHONE_PAGE_SIZE,
        'backup-files-list': PHONE_PAGE_SIZE,
    };

    function labelCards(body) {
        const table = body.closest('table');
        table.classList.add('mobile-cards');
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        const label = () => body.querySelectorAll(':scope > tr').forEach((row) => {
            [...row.children].forEach((cell, index) => { cell.dataset.label = headers[index] || ''; });
        });
        label();
        new MutationObserver(label).observe(body, { childList: true });
    }

    function addPager(list, size) {
        const anchor = list.tagName === 'TBODY' ? (list.closest('.overflow-x-auto, .overflow-auto') || list.closest('table')) : list;
        const pager = document.createElement('nav');
        pager.className = 'mobile-pager';
        pager.setAttribute('aria-label', 'Halaman data');
        pager.innerHTML = '<button type="button" data-step="-1">‹ Sebelumnya</button><span role="status"></span><button type="button" data-step="1">Berikutnya ›</button>';
        anchor.after(pager);

        let page = 1, total = -1;
        const apply = () => {
            const items = [...list.children];
            if (items.length !== total) {
                total = items.length;
                page = 1;
            }
            const pages = Math.max(1, Math.ceil(total / size));
            page = Math.min(page, pages);
            items.forEach((item, index) => item.classList.toggle('mobile-page-hidden', Math.floor(index / size) !== page - 1));
            pager.classList.toggle('is-active', pages > 1);
            pager.querySelector('span').textContent = `Halaman ${page} dari ${pages}`;
            pager.querySelector('[data-step="-1"]').disabled = page === 1;
            pager.querySelector('[data-step="1"]').disabled = page === pages;
        };
        pager.addEventListener('click', (e) => {
            const button = e.target.closest('[data-step]');
            if (!button) return;
            page += Number(button.dataset.step);
            apply();
            list.scrollTop = 0;
            anchor.scrollIntoView({ block: 'start' });
        });
        apply();
        new MutationObserver(apply).observe(list, { childList: true });
    }

    document.addEventListener('DOMContentLoaded', () => {
        cardTables.forEach((id) => {
            const body = document.getElementById(id);
            if (body) labelCards(body);
        });
        Object.entries(pagedLists).forEach(([id, size]) => {
            const list = document.getElementById(id);
            if (list) addPager(list, size);
        });
    });
})();
</script>
