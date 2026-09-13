// Extracted from layout_footer.blade.php — Common feature scripts
// Monthly reports, help requests, notifications, manual holidays


// Tambahkan event listener untuk tombol-tombol di tabel laporan bulanan
document.addEventListener('click', async (e) => {
    const target = e.target.closest('.btn-create-month, .btn-edit-month, .page-btn');
    if (!target) return;

    if (target.classList.contains('page-btn')) {
        currentMonthlyPageYear = parseInt(target.dataset.year);
        renderMonthly();
        return;
    }

    // Tampilkan form di modal
    pageMonthlyForm.classList.remove('hidden');
    pageMonthlyForm.classList.add('flex');

    let isViewOnly = false;
    
    let year, month, reportData = null;

    if (target.classList.contains('btn-create-month')) {
        year = parseInt(target.dataset.year);
        month = parseInt(target.dataset.month);
        qs('#monthly-form-title').textContent = `Buat Laporan Bulan ${monthName(month-1)} ${year}`;
    } else { // Edit
        reportData = JSON.parse(target.dataset.json.replace(/&apos;/g, "'"));
        year = parseInt(reportData.year) || 0;
        month = parseInt(reportData.month) || 0;
        qs('#monthly-form-title').textContent = `Edit Laporan Bulan ${monthName(month-1)} ${year}`;
    }

    // Set info pegawai di form
    qs('#pegawai-info-monthly-form').innerHTML = qs('#pegawai-info-monthly').innerHTML;
    
    // Reset dan isi form
    qs('#form-monthly-report').reset();
    qs('#table-achievements-body').innerHTML = '';
    qs('#table-obstacles-body').innerHTML = '';
    qs('#monthly-report-year').value = year;
    qs('#monthly-report-month').value = month;

    if (reportData) {
        qs('#monthly-summary').value = reportData.summary || '';
        const achievements = JSON.parse(reportData.achievements || '[]');
        const obstacles = JSON.parse(reportData.obstacles || '[]');
        achievements.forEach(addAchievementRow);
        obstacles.forEach(addObstacleRow);
    } else {
        // Tambah satu baris kosong saat membuat baru
        addAchievementRow();
        addObstacleRow();
    }
    
    // Field disabled jika view only
    const fields = qsa('#form-monthly-report input, #form-monthly-report textarea, #form-monthly-report button');
    fields.forEach(field => {
        // Jangan disable tombol kembali
        if(field.id !== 'btn-back-to-monthly-list') {
            field.disabled = isViewOnly;
        }
    });

    // Sembunyikan tombol simpan jika view only
    qs('#btn-save-draft').style.display = isViewOnly ? 'none' : 'inline-block';
    qs('button[type="submit"]', qs('#form-monthly-report')).style.display = isViewOnly ? 'none' : 'inline-block';
});

// Register Service Worker for offline functionality
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Work Schedule Modal Functions
async function openWorkScheduleModal(userId, userName) {
    const modal = qs('#work-schedule-modal');
    const userSelect = qs('#work-schedule-user');
    const form = qs('#work-schedule-form');
    const startDateInput = qs('#work-start-date');
    
    // Load members for dropdown
    const membersData = await api('?ajax=get_members&light=1&no_embeddings=1', {}, { suppressModal: true, cache: true });
    const members = membersData.data || [];
    
    // Populate user dropdown
    userSelect.innerHTML = '<option value="">Pilih pegawai...</option>';
    members.forEach(member => {
        const option = document.createElement('option');
        option.value = member.id;
        option.textContent = `${member.nama} (${member.nim})`;
        if (member.id == userId) {
            option.selected = true;
        }
        userSelect.appendChild(option);
    });
    
    // Load schedule for selected user
    if (userId) {
        await loadWorkSchedule(userId);
        form.classList.remove('hidden');
        // Preload current start date from member JSON if available
        try{
            const md = await api('?ajax=get_members&light=1&no_embeddings=1', {}, { suppressModal: true, cache: true });
            const m = (md.data||[]).find(x=>x.id==userId);
            if(m && m.created_at && startDateInput){ startDateInput.value = (m.work_start_date||m.created_at||'').slice(0,10); }
        }catch{}
    } else {
        form.classList.add('hidden');
    }
    
    modal.classList.remove('hidden');
}

async function loadWorkSchedule(userId) {
    try {
        const response = await api('?ajax=admin_get_work_schedule', { user_id: userId });
        if (response.ok) {
            const schedule = response.data;
            renderWorkScheduleDays(schedule);
        } else {
            showNotif('Gagal memuat jadwal kerja', false);
        }
    } catch (error) {
        console.error('Error loading work schedule:', error);
        showNotif('Gagal memuat jadwal kerja', false);
    }
}

function renderWorkScheduleDays(schedule) {
    const container = qs('#work-schedule-days');
    container.innerHTML = '';
    
    const days = [
        { key: 'monday', label: 'Senin' },
        { key: 'tuesday', label: 'Selasa' },
        { key: 'wednesday', label: 'Rabu' },
        { key: 'thursday', label: 'Kamis' },
        { key: 'friday', label: 'Jumat' },
        { key: 'saturday', label: 'Sabtu' },
        { key: 'sunday', label: 'Minggu' }
    ];
    
    days.forEach(day => {
        const dayData = schedule[day.key] || {
            is_working_day: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].includes(day.key),
            start_time: '08:00:00',
            end_time: '17:00:00'
        };
        
        const row = document.createElement('div');
        row.className = 'grid grid-cols-7 gap-2 items-center p-2 border rounded';
        row.innerHTML = `
            <div class="font-medium">${day.label}</div>
            <div>
                <input type="checkbox" ${dayData.is_working_day ? 'checked' : ''} 
                       class="work-day-checkbox" data-day="${day.key}">
            </div>
            <div>
                <input type="time" value="${dayData.start_time}" 
                       class="work-start-time w-full p-1 border rounded text-sm" data-day="${day.key}">
            </div>
            <div>
                <input type="time" value="${dayData.end_time}" 
                       class="work-end-time w-full p-1 border rounded text-sm" data-day="${day.key}">
            </div>
            <div class="text-sm text-gray-600 work-duration" data-day="${day.key}">
                ${calculateDuration(dayData.start_time, dayData.end_time)}
            </div>
            <div class="text-sm">
                <span class="work-status px-2 py-1 rounded text-xs ${dayData.is_working_day ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}" data-day="${day.key}">
                    ${dayData.is_working_day ? 'Bekerja' : 'Libur'}
                </span>
            </div>
            <div>
                <button type="button" class="copy-schedule-btn text-blue-600 hover:text-blue-800 text-sm" data-day="${day.key}">
                    Copy
                </button>
            </div>
        `;
        
        container.appendChild(row);
    });
    
    // Add event listeners
    addWorkScheduleEventListeners();
}

function addWorkScheduleEventListeners() {
    // Handle checkbox changes
    qsa('.work-day-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const day = this.dataset.day;
            const statusSpan = qs(`.work-status[data-day="${day}"]`);
            const startTime = qs(`.work-start-time[data-day="${day}"]`);
            const endTime = qs(`.work-end-time[data-day="${day}"]`);
            
            if (this.checked) {
                statusSpan.textContent = 'Bekerja';
                statusSpan.className = 'work-status px-2 py-1 rounded text-xs bg-green-100 text-green-800';
                startTime.disabled = false;
                endTime.disabled = false;
            } else {
                statusSpan.textContent = 'Libur';
                statusSpan.className = 'work-status px-2 py-1 rounded text-xs bg-gray-100 text-gray-800';
                startTime.disabled = true;
                endTime.disabled = true;
            }
            updateDuration(day);
        });
    });
    
    // Handle time changes
    qsa('.work-start-time, .work-end-time').forEach(input => {
        input.addEventListener('change', function() {
            const day = this.dataset.day;
            updateDuration(day);
        });
    });
    
    // Handle copy buttons
    qsa('.copy-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const day = this.dataset.day;
            const checkbox = qs(`.work-day-checkbox[data-day="${day}"]`);
            const startTime = qs(`.work-start-time[data-day="${day}"]`);
            const endTime = qs(`.work-end-time[data-day="${day}"]`);
            
            // Copy to all other days
            qsa('.work-day-checkbox').forEach(otherCheckbox => {
                if (otherCheckbox.dataset.day !== day) {
                    otherCheckbox.checked = checkbox.checked;
                    otherCheckbox.dispatchEvent(new Event('change'));
                }
            });
            
            qsa('.work-start-time').forEach(otherStart => {
                if (otherStart.dataset.day !== day) {
                    otherStart.value = startTime.value;
                }
            });
            
            qsa('.work-end-time').forEach(otherEnd => {
                if (otherEnd.dataset.day !== day) {
                    otherEnd.value = endTime.value;
                }
            });
            
            // Update all durations
            qsa('.work-day-checkbox').forEach(cb => updateDuration(cb.dataset.day));
            
            showNotif('Jadwal berhasil disalin ke semua hari');
        });
    });
}

function updateDuration(day) {
    const startTime = qs(`.work-start-time[data-day="${day}"]`);
    const endTime = qs(`.work-end-time[data-day="${day}"]`);
    const durationSpan = qs(`.work-duration[data-day="${day}"]`);
    
    if (startTime && endTime && durationSpan) {
        durationSpan.textContent = calculateDuration(startTime.value, endTime.value);
    }
}

function calculateDuration(startTime, endTime) {
    if (!startTime || !endTime) return '0h 0m';
    
    const start = new Date(`2000-01-01 ${startTime}`);
    const end = new Date(`2000-01-01 ${endTime}`);
    
    if (end <= start) return '0h 0m';
    
    const diffMs = end - start;
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    
    return `${hours}h ${minutes}m`;
}

// Work Schedule Modal Event Listeners
qs('#work-schedule-close') && qs('#work-schedule-close').addEventListener('click', () => {
    qs('#work-schedule-modal').classList.add('hidden');
});

qs('#work-schedule-cancel') && qs('#work-schedule-cancel').addEventListener('click', () => {
    qs('#work-schedule-modal').classList.add('hidden');
});

qs('#work-schedule-user') && qs('#work-schedule-user').addEventListener('change', async function() {
    const userId = this.value;
    const form = qs('#work-schedule-form');
    
    if (userId) {
        await loadWorkSchedule(userId);
        form.classList.remove('hidden');
    } else {
        form.classList.add('hidden');
    }
});

qs('#work-schedule-save') && qs('#work-schedule-save').addEventListener('click', async function() {
    const userId = qs('#work-schedule-user').value;
    
    if (!userId) {
        showNotif('Pilih pegawai terlebih dahulu', false);
        return;
    }
    
    // Collect schedule data
    const schedule = {};
    qsa('.work-day-checkbox').forEach(checkbox => {
        const day = checkbox.dataset.day;
        const startTime = qs(`.work-start-time[data-day="${day}"]`).value;
        const endTime = qs(`.work-end-time[data-day="${day}"]`).value;
        
        schedule[day] = {
            is_working_day: checkbox.checked,
            start_time: startTime,
            end_time: endTime
        };
    });
    
    try {
        const response = await api('?ajax=admin_save_work_schedule', {
            user_id: userId,
            schedule: schedule
        });
        
        if (response.ok) {
            // Save per-user work start date setting if provided
            const startDateVal = qs('#work-start-date')?.value || '';
            if(startDateVal){ await api('?ajax=save_setting', { key: `work_start_date_user_${userId}`, value: startDateVal }); }
            showNotif('Jadwal kerja berhasil disimpan');
            qs('#work-schedule-modal').classList.add('hidden');
        } else {
            showNotif(response.message || 'Gagal menyimpan jadwal kerja', false);
        }
    } catch (error) {
        console.error('Error saving work schedule:', error);
        showNotif('Gagal menyimpan jadwal kerja', false);
    }
});

// Admin Help Notifications & Requests Logic
(function() {
    const btnNotif = qs('#btn-notifications');
    const ddNotif = qs('#dropdown-notifications');
    const badgeNotif = qs('#notif-badge');
    const countNotif = qs('#notif-count');
    const itemsNotif = qs('#notif-items');

    if (btnNotif && ddNotif) {
        btnNotif.addEventListener('click', (e) => {
            e.stopPropagation();
            ddNotif.classList.toggle('hidden');
            if (!ddNotif.classList.contains('hidden')) {
                loadAdminNotifications();
            }
        });
        document.addEventListener('click', (e) => {
            if (!btnNotif.contains(e.target) && !ddNotif.contains(e.target)) ddNotif.classList.add('hidden');
        });
    }

    async function loadAdminNotifications() {
        try {
            const res = await api('?ajax=admin_get_help_notifications', {}, { cache: false, suppressModal: true });
            if (res.ok) {
                renderNotificationDropdown(res.data);
            }
        } catch (e) { console.error('Load notifications error:', e); }
    }
    window.loadAdminNotifications = loadAdminNotifications;

    function renderNotificationDropdown(items) {
        if (!itemsNotif) return;
        
        const pending = items.filter(i => i.status === 'pending');
        if (badgeNotif) badgeNotif.classList.toggle('hidden', pending.length === 0);
        if (countNotif) countNotif.textContent = pending.length;

        if (items.length === 0) {
            itemsNotif.innerHTML = `
                <div class="p-8 text-center text-gray-400">
                    <i class="fi fi-rr-inbox text-3xl mb-2 block"></i>
                    <p class="text-xs">Tidak ada permintaan baru</p>
                </div>`;
            return;
        }

        itemsNotif.innerHTML = items.map(item => `
            <div class="p-3 hover:bg-gray-50 rounded-xl transition-all cursor-pointer group border border-transparent hover:border-indigo-100" onclick="showRequestDetail(${item.id})">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-50 flex-shrink-0 flex items-center justify-center text-indigo-600 font-bold text-xs uppercase">
                        ${item.nama ? item.nama.charAt(0) : '?'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate">${item.nama}</p>
                        <p class="text-xs text-gray-500 truncate">${getRequestTypeLabel(item.request_type, item)}</p>
                        <p class="text-[10px] text-gray-400 mt-1">${formatTimeAgo(item.created_at)}</p>
                    </div>
                    ${item.status === 'pending' ? '<span class="w-2 h-2 bg-indigo-500 rounded-full mt-1.5 flex-shrink-0 animate-pulse"></span>' : ''}
                </div>
            </div>
        `).join('');
    }
    window.renderNotificationDropdown = renderNotificationDropdown;

    // Polling for notifications every 30 seconds
    if (isAdmin()) {
        setInterval(loadAdminNotifications, 30000);
        loadAdminNotifications();
    }

    // Help Requests Tab Logic
    window.allHelpRequests = [];
    const tableBody = qs('#table-requests-body');
    const emptyState = qs('#requests-empty');

    async function loadAllHelpRequests() {
        if (!tableBody) return;
        try {
            const res = await api('?ajax=admin_get_all_help_requests', {}, { cache: false });
            if (res.ok) {
                window.allHelpRequests = res.data;
                renderHelpRequests();
                updateRequestStats();
            }
        } catch (e) { console.error('Load all requests error:', e); }
    }
    window.loadAllHelpRequests = loadAllHelpRequests;

    function renderHelpRequests() {
        if (!tableBody) return;
        const statusFilter = document.querySelector('.filter-req.active')?.dataset.status || 'all';
        const search = qs('#search-requests')?.value.toLowerCase() || '';

        const filtered = window.allHelpRequests.filter(i => {
            const matchesStatus = statusFilter === 'all' || i.status === statusFilter;
            const nama = (i.nama || i.user_nama || '').toLowerCase();
            const nim = (i.nim || '').toLowerCase();
            const matchesSearch = nama.includes(search) || nim.includes(search);
            return matchesStatus && matchesSearch;
        });

        if (filtered.length === 0) {
            tableBody.innerHTML = '';
            emptyState?.classList.remove('hidden');
            renderPaginationUI('table-requests-body', 0, 1, 10, 0, 0, () => {}, () => {});
            return;
        }

        emptyState?.classList.add('hidden');
        renderPaginatedTable('table-requests-body', filtered, (i) => `
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <img src="https://ui-avatars.com/api/?background=6366f1&color=fff&name=${encodeURIComponent(i.nama)}&size=64" class="w-8 h-8 rounded-full border border-gray-100">
                        <div>
                            <p class="text-sm font-bold text-gray-800">${i.nama}</p>
                            <p class="text-xs text-gray-400 font-mono">${i.nim}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${getRequestTypeClass(i.request_type)}">
                        ${getRequestTypeLabel(i.request_type, i)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-gray-600">${formatTimestamp(i.created_at)}</p>
                    <p class="text-[10px] text-gray-400">${formatTimeAgo(i.created_at)}</p>
                </td>
                <td class="px-6 py-4">
                    <span class="flex items-center gap-1.5 text-xs font-bold ${getStatusClass(i.status)}">
                        <i class="fi ${getStatusIcon(i.status)}"></i>
                        ${i.status.charAt(0).toUpperCase() + i.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <button onclick="showRequestDetail(${i.id})" class="p-2 hover:bg-white text-indigo-600 rounded-xl transition-all shadow-sm border border-gray-100">
                        <i class="fi fi-sr-eye"></i>
                    </button>
                </td>
            </tr>
        `, {
            colSpan: 5,
            emptyMessage: 'Tidak ada request.',
            onPageChange: renderHelpRequests
        });
    }
    window.renderHelpRequests = renderHelpRequests;

    function updateRequestStats() {
        if (!qs('#stat-pending-requests')) return;
        qs('#stat-pending-requests').textContent = window.allHelpRequests.filter(i => i.status === 'pending').length;
        qs('#stat-approved-requests').textContent = window.allHelpRequests.filter(i => i.status === 'approved').length;
        qs('#stat-disapproved-requests').textContent = window.allHelpRequests.filter(i => i.status === 'disapproved').length;
    }

    // Modal Details Logic
    window.showRequestDetail = async function(id) {
        showNotif('Memuat detail...', true);
        let item = null;
        try {
            const res = await api('?ajax=admin_get_help_request_detail&id=' + id, {}, { suppressModal: true });
            if (res.ok && res.data) {
                item = res.data;
            }
        } catch (e) {
            console.error('Error fetching request detail:', e);
        }
        
        if (!item) {
            item = window.allHelpRequests.find(i => i.id === id) || (await fetchSingleRequest(id));
        }
        
        if (!item) {
            showNotif('Gagal memuat detail permintaan', false);
            return;
        }
        
        // Hide notif once loaded
        showNotif('Detail berhasil dimuat', true);

        const modal = qs('#request-detail-modal');
        const body = qs('#request-detail-body');
        const footer = qs('#request-action-footer');

        if (!modal || !body || !footer) return;

        let content = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Informasi Pegawai</h4>
                    <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-3xl">
                        <img src="https://ui-avatars.com/api/?background=6366f1&color=fff&name=${encodeURIComponent(item.nama)}&size=128" class="w-16 h-16 rounded-2xl border-4 border-white shadow-sm">
                        <div>
                            <h5 class="text-lg font-bold text-gray-800">${item.nama}</h5>
                            <p class="text-indigo-600 font-mono text-sm">${item.nim}</p>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Status Permintaan</h4>
                    <div class="p-4 rounded-3xl ${item.status === 'pending' ? 'bg-orange-50 text-orange-700' : (item.status === 'approved' || item.status === 'solved' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700')}">
                        <div class="flex items-center gap-2 font-bold mb-1">
                            <i class="fi ${getStatusIcon(item.status)}"></i>
                            <span class="uppercase tracking-wider text-sm">${getRequestStatusLabel(item.status, item.request_type)}</span>
                        </div>
                        <p class="text-xs opacity-75">${formatTimestamp(item.created_at)}</p>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Detail Permintaan</h4>
                <div class="bg-white border border-gray-100 rounded-3xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-gray-50 flex justify-between items-center">
                        <span class="text-sm font-bold text-gray-700">Jenis Layanan</span>
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-xs font-bold uppercase">${
                            item.request_type === 'late_attendance'
                                ? (item.attendance_reason && item.attendance_reason.startsWith('pulang_lebih_awal|') ? 'Pulang Lebih Awal'
                                    : item.attendance_type === 'wfa' ? 'Presensi WFA'
                                    : item.attendance_type === 'overtime' ? 'Presensi Overtime'
                                    : 'Presensi Manual')
                                : getRequestTypeLabel(item.request_type, item)
                        }</span>
                    </div>
                    <div class="p-6 space-y-4">
                        ${renderRequestTypeDetails(item)}
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Catatan Admin</h4>
                <textarea id="admin-req-note" class="w-full p-4 bg-gray-50 border-none rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm" rows="3" placeholder="Tulis catatan persetujuan atau penolakan...">${item.admin_note || ''}</textarea>
            </div>
        `;

        body.innerHTML = content;

        if (item.request_type === 'bug_report') {
            footer.innerHTML = `
                <button id="close-detail-btn" class="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-2xl hover:bg-gray-200 transition-all">Tutup</button>
                <button onclick="handleRequest(${item.id}, 'disapproved')" class="flex-[1.5] py-3 bg-white border border-red-100 text-red-600 font-bold rounded-2xl hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                    <i class="fi fi-sr-cross-circle"></i> Abaikan
                </button>
                <button onclick="handleRequest(${item.id}, 'solved')" class="flex-[1.5] py-3 bg-emerald-600 text-white font-bold rounded-2xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200 flex items-center justify-center gap-2">
                    <i class="fi fi-sr-check-circle"></i> Selesai
                </button>
            `;
        } else {
            // Show action buttons always (admin can change status even after approved/rejected)
            const statusNote = item.status !== 'pending'
                ? `<p class="text-[10px] text-amber-600 font-semibold mb-2 flex items-center gap-1"><i class="fi fi-sr-triangle-warning"></i> Status saat ini: ${getRequestStatusLabel(item.status, item.request_type)} — Anda dapat mengubah status.</p>`
                : '';
            footer.innerHTML = `
                ${statusNote}
                <div class="flex gap-3 w-full">
                    <button id="close-detail-btn" class="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-2xl hover:bg-gray-200 transition-all">Tutup</button>
                    <button onclick="handleRequest(${item.id}, 'disapproved')" class="flex-[1.5] py-3 bg-white border border-red-100 text-red-600 font-bold rounded-2xl hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                        <i class="fi fi-sr-cross-circle"></i> Tolak
                    </button>
                    <button onclick="handleRequest(${item.id}, 'approved')" class="flex-[1.5] py-3 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2">
                        <i class="fi fi-sr-check-circle"></i> Setujui
                    </button>
                </div>
            `;
        }

        // Add event listener for the dynamic button
        setTimeout(() => {
            const btn = qs('#close-detail-btn');
            if (btn) {
                btn.focus();
                btn.onclick = () => modal.classList.add('hidden');
            }
        }, 10);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    async function fetchSingleRequest(id) {
        try {
            const res = await api('?ajax=admin_get_help_request_detail&id=' + id, {}, { suppressModal: true });
            if (res.ok && res.data) {
                return res.data;
            }
        } catch (e) {
            console.error('fetchSingleRequest error:', e);
        }
        // Fallback to reload if not found
        await loadAllHelpRequests();
        return window.allHelpRequests.find(i => i.id === id);
    }

    window.handleRequest = async function(id, action) {
        const note = qs('#admin-req-note')?.value || '';
        if (action === 'disapproved' && !note.trim()) {
            showNotif('Mohon berikan catatan alasan penolakan terlebih dahulu.', false);
            qs('#admin-req-note')?.focus();
            return;
        }

        const actionLabel = action === 'approved' ? 'disetujui' : action === 'solved' ? 'diselesaikan' : 'ditolak';

        try {
            const res = await api('?ajax=admin_handle_help_request', { id, status: action, note });
            if (res.ok) {
                showNotif(`✅ Permintaan berhasil ${actionLabel}.`);
                qs('#request-detail-modal').classList.add('hidden');
                loadAllHelpRequests();
                loadAdminNotifications();
            } else {
                showNotif(res.message || 'Gagal memproses permintaan', false);
            }
        } catch (e) { showNotif('Gagal memproses permintaan: ' + e.message, false); }
    }

    function renderRequestTypeDetails(item) {
        if (item.request_type === 'past_attendance') {
            return `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Tanggal Absen</p>
                        <p class="text-sm font-bold text-gray-800">${formatDate(item.tanggal)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Jenis Izin</p>
                        <p class="text-sm font-bold text-gray-800 uppercase">${item.jenis_izin || '-'}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Alasan</p>
                    <p class="text-sm text-gray-700 leading-relaxed">${item.alasan_izin || '-'}</p>
                </div>
                ${item.bukti_izin ? (() => {
                    const bi = item.bukti_izin;
                    const biSrc = bi.startsWith('data:') ? bi : (bi.startsWith('/') ? bi : '/' + bi);
                    return `
                    <div>
                        <p class="text-xs text-gray-400 mb-2">Bukti Pendukung</p>
                        <img src="${biSrc}" class="w-full h-48 object-cover rounded-2xl cursor-pointer hover:opacity-90 transition-opacity" onclick="showScreenshotModal('${biSrc.replace(/'/g, '%27')}', 'Bukti Izin/Sakit')" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div style="display:none" class="w-full h-20 bg-red-50 rounded-2xl border border-red-200 flex items-center justify-center text-red-400 text-xs"><i class="fi fi-rr-picture mr-1"></i> Gambar tidak tersedia</div>
                    </div>`;
                })() : ''}
            `;
        } else if (item.request_type === 'late_attendance') {
            const tipeLabel = item.attendance_type === 'wfa' ? 'Work From Anywhere (WFA)'
                           : item.attendance_type === 'overtime' ? 'Lembur (Overtime)'
                           : 'Work From Office (WFO)';
            return `
                <div class="mb-4 pb-4 border-b border-gray-50">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-400 mb-1">Tanggal Request</p>
                            <p class="text-sm font-bold text-gray-800">${item.tanggal ? formatDate(item.tanggal) : '-'}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-1">Tipe Presensi</p>
                            <p class="text-sm font-bold text-indigo-700">${tipeLabel}</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Jam Masuk</p>
                        <p class="text-sm font-bold text-gray-800">${item.jam_masuk ? item.jam_masuk.substring(0,5) : '-'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Jam Pulang</p>
                        <p class="text-sm font-bold text-gray-800">${item.jam_pulang ? item.jam_pulang.substring(0,5) : '-'}</p>
                    </div>
                </div>
                ${(() => {
                    if (!item.attendance_reason) return '';
                    const isPulangLebihAwal = item.attendance_reason.startsWith('pulang_lebih_awal|');
                    const cleanReason = isPulangLebihAwal ? item.attendance_reason.replace('pulang_lebih_awal|', '') : item.attendance_reason;
                    if (!cleanReason) return '';
                    const reasonLabel = isPulangLebihAwal ? 'Alasan Pulang Lebih Awal'
                        : item.attendance_type === 'overtime' ? 'Alasan Overtime'
                        : 'Alasan WFA';
                    return `
                    <div>
                        <p class="text-xs text-gray-400 mb-1">${reasonLabel}</p>
                        <p class="text-sm text-gray-700 bg-indigo-50 p-3 rounded-xl italic">${cleanReason}</p>
                    </div>`;
                })()}
                <div>
                    <p class="text-xs text-gray-400 mb-1">Lokasi Verifikasi <span class="text-green-600">(otomatis dari GPS)</span></p>
                    <p class="text-xs text-gray-700 italic">${item.lokasi_presensi || '-'}</p>
                </div>
                ${item.bukti_presensi ? (() => {
                    const bp = item.bukti_presensi;
                    const bpSrc = bp.startsWith('data:') ? bp : (bp.startsWith('/') ? bp : '/' + bp);
                    return `
                    <div>
                        <p class="text-xs text-gray-400 mb-2">Bukti Wajah</p>
                        <img src="${bpSrc}" class="w-full h-48 object-cover rounded-2xl cursor-pointer hover:opacity-90 transition-opacity" onclick="showScreenshotModal('${bpSrc.replace(/'/g, '%27')}', 'Verifikasi Wajah')" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div style="display:none" class="w-full h-20 bg-red-50 rounded-2xl border border-red-200 flex items-center justify-center text-red-400 text-xs"><i class="fi fi-rr-picture mr-1"></i> Gambar tidak tersedia</div>
                    </div>`;
                })() : ''}
            `;
        } else if (item.request_type === 'bug_report') {
            return `
                <div>
                    <p class="text-xs text-gray-400 mb-2">Deskripsi Bug</p>
                    <div class="bg-gray-50 p-4 rounded-2xl text-sm text-gray-700 leading-relaxed italic border-l-4 border-indigo-200">
                        "${item.bug_description || 'Tidak ada deskripsi'}"
                    </div>
                </div>
                ${item.bug_proof ? `
                    <div>
                        <p class="text-xs text-gray-400 mb-2">Bukti Visual (Screenshot)</p>
                        <img src="${item.bug_proof}" class="w-full h-48 object-cover rounded-2xl cursor-pointer hover:opacity-90 transition-opacity" onclick="showScreenshotModal('${item.bug_proof}', 'Screenshot Bug')">
                    </div>
                ` : ''}
            `;
        } else if (item.request_type === 'diff_location_checkout') {
            const cleanReason = item.attendance_reason ? item.attendance_reason.replace('diff_location|', '') : '-';
            return `
                <div class="mb-4 pb-4 border-b border-gray-50">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-400 mb-1">Tanggal Request</p>
                            <p class="text-sm font-bold text-gray-800">${item.tanggal ? formatDate(item.tanggal) : '-'}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-1">Jam Pulang</p>
                            <p class="text-sm font-bold text-rose-700">${item.jam_pulang ? item.jam_pulang.substring(0,5) : '-'}</p>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Alasan Lokasi Berbeda</p>
                    <p class="text-sm text-gray-700 bg-rose-50 p-3 rounded-xl italic border-l-4 border-rose-300">${cleanReason}</p>
                </div>
                <div class="mt-4">
                    <p class="text-xs text-gray-400 mb-1">Lokasi Verifikasi <span class="text-green-600">(otomatis dari GPS)</span></p>
                    <p class="text-xs text-gray-700 italic">${item.lokasi_presensi || '-'}</p>
                    ${(item.lat_pulang && item.lng_pulang) ? `<p class="text-[10px] text-gray-500 mt-1">Koordinat: ${item.lat_pulang}, ${item.lng_pulang}</p>
                    <a href="https://maps.google.com/?q=${item.lat_pulang},${item.lng_pulang}" target="_blank" class="inline-block mt-2 text-xs font-bold text-indigo-600 hover:underline"><i class="fi fi-rr-map-marker"></i> Lihat di Maps</a>` : ''}
                </div>
                ${item.bukti_presensi ? (() => {
                    const bp = item.bukti_presensi;
                    const bpSrc = bp.startsWith('data:') ? bp : (bp.startsWith('/') ? bp : '/' + bp);
                    return `
                    <div class="mt-4">
                        <p class="text-xs text-gray-400 mb-2">Bukti Wajah</p>
                        <img src="${bpSrc}" class="w-full h-48 object-cover rounded-2xl cursor-pointer hover:opacity-90 transition-opacity" onclick="showScreenshotModal('${bpSrc.replace(/'/g, '%27')}', 'Verifikasi Wajah')" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div style="display:none" class="w-full h-20 bg-red-50 rounded-2xl border border-red-200 flex items-center justify-center text-red-400 text-xs"><i class="fi fi-rr-picture mr-1"></i> Gambar tidak tersedia</div>
                    </div>`;
                })() : ''}
            `;
        }
        return ``;
    }

    // Event Listeners for Tab Requests

    // Event Listeners for Tab Requests
    document.addEventListener('DOMContentLoaded', () => {
        qs('#btn-refresh-requests')?.addEventListener('click', loadAllHelpRequests);
        qs('#close-request-detail')?.addEventListener('click', () => qs('#request-detail-modal').classList.add('hidden'));
        
        qsa('.filter-req').forEach(btn => {
            btn.addEventListener('click', function() {
                qsa('.filter-req').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                renderHelpRequests();
            });
        });

        qs('#search-requests')?.addEventListener('input', renderHelpRequests);


    });

    // Handle profile dropdown logout with session clearing
    const logoutBtn = qs('a[href="?page=logout"]');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
             // Clear any local state if needed
             sessionStorage.removeItem('late_req_face_verified');
        });
    }

})();

    // Pegawai Notifications Logic
    (function() {
        const btnNotif = qs('#btn-pegawai-notif');
        const ddNotif = qs('#dropdown-pegawai-notif');
        const badgeNotif = qs('#notif-pegawai-badge');
        const itemsNotif = qs('#notif-pegawai-items');
        const btnMarkRead = qs('#btn-mark-all-read');

        let currentFilter = 'unread';

        if (btnNotif && ddNotif) {
            btnNotif.addEventListener('click', (e) => {
                e.stopPropagation();
                ddNotif.classList.toggle('hidden');
                if (!ddNotif.classList.contains('hidden')) {
                    loadPegawaiNotifications(currentFilter);
                }
            });
            document.addEventListener('click', (e) => {
                if (!btnNotif.contains(e.target) && !ddNotif.contains(e.target)) ddNotif.classList.add('hidden');
            });
        }

        const tabUnread = qs('#tab-notif-unread');
        const tabRead = qs('#tab-notif-read');

        if (tabUnread && tabRead) {
            tabUnread.onclick = (e) => {
                e.stopPropagation();
                currentFilter = 'unread';
                updateTabs();
                loadPegawaiNotifications('unread');
            };
            tabRead.onclick = (e) => {
                e.stopPropagation();
                currentFilter = 'read';
                updateTabs();
                loadPegawaiNotifications('read');
            };
        }

        function updateTabs() {
            if (currentFilter === 'unread') {
                tabUnread.className = 'flex-1 py-3 text-xs font-bold text-blue-600 border-b-2 border-blue-600 bg-white transition-all';
                tabRead.className = 'flex-1 py-3 text-xs font-bold text-gray-400 hover:text-gray-600 transition-all';
            } else {
                tabRead.className = 'flex-1 py-3 text-xs font-bold text-green-600 border-b-2 border-green-600 bg-white transition-all';
                tabUnread.className = 'flex-1 py-3 text-xs font-bold text-gray-400 hover:text-gray-600 transition-all';
            }
        }

        if (btnMarkRead) {
            btnMarkRead.addEventListener('click', async (e) => {
                e.stopPropagation();
                try {
                    const res = await api('?ajax=pegawai_mark_notifications_read', {}, { method: 'POST' });
                    if (res.ok) {
                        loadPegawaiNotifications(currentFilter);
                    }
                } catch (e) { console.error('Mark read error:', e); }
            });
        }

        async function loadPegawaiNotifications(filter = 'unread') {
            if (!isPegawai()) return;
            try {
                const res = await api('?ajax=pegawai_get_notifications&filter=' + filter, {}, { cache: false, suppressModal: true });
                if (res.ok) {
                    renderPegawaiNotificationDropdown(res.data);
                    // Update badge for unread only
                    if (filter === 'unread') {
                        if (badgeNotif) badgeNotif.classList.toggle('hidden', res.data.length === 0);
                    }
                }
            } catch (e) { console.error('Load pegawai notifications error:', e); }
        }
        window.loadPegawaiNotifications = loadPegawaiNotifications;

        function renderPegawaiNotificationDropdown(items) {
            if (!itemsNotif) return;
            
            if (items.length === 0) {
                itemsNotif.innerHTML = `
                    <div class="p-8 text-center text-gray-400">
                        <i class="fi fi-sr-inbox text-3xl mb-2 block"></i>
                        <p class="text-xs">Tidak ada notifikasi ${currentFilter === 'unread' ? '' : 'lama'}</p>
                    </div>`;
                return;
            }

            itemsNotif.innerHTML = items.map(item => {
                const isPositive = item.status === 'approved' || item.status === 'solved';
                const statusColor = isPositive ? 'text-emerald-600' : 'text-red-600';
                const statusIcon = isPositive ? 'fi-sr-check-circle' : 'fi-sr-cross-circle';
                const statusLabel = getRequestStatusLabel(item.status, item.request_type);
                
                return `
                <div class="p-4 hover:bg-gray-50 rounded-xl transition-all border border-transparent hover:border-blue-100 bg-white shadow-sm mb-1">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl ${isPositive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'} flex-shrink-0 flex items-center justify-center">
                            <i class="fi ${statusIcon} text-lg"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">${getRequestTypeLabel(item.request_type, item)}</span>
                                <span class="text-[10px] text-gray-400">${formatTimeAgo(item.created_at)}</span>
                            </div>
                            <p class="text-sm font-bold text-gray-800">Request Anda telah <span class="${statusColor} uppercase">${statusLabel}</span></p>
                            
                            ${(item.status === 'disapproved' || item.admin_note) ? `
                                <div class="mt-2 p-2.5 bg-gray-50 rounded-lg border-l-2 ${item.status === 'disapproved' ? 'border-red-400' : 'border-emerald-400'}">
                                    <p class="text-xs text-gray-500 font-bold mb-1">Catatan Admin:</p>
                                    <p class="text-xs text-gray-600 italic">"${item.admin_note || 'Tidak ada catatan'}"</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                `;
            }).join('');
        }
        window.renderPegawaiNotificationDropdown = renderPegawaiNotificationDropdown;

        // Polling for employees
        if (isPegawai()) {
            setInterval(() => loadPegawaiNotifications(currentFilter), 30000); // 30s
            loadPegawaiNotifications('unread');
        }
    })();

// Global Helper Functions for Help Requests
function getRequestStatusLabel(status, type) {
    if (!status) return 'WAITING';
    if (type === 'bug_report' && status === 'disapproved') return 'IGNORED';
    if (status === 'solved') return 'SOLVED';
    if (status === 'approved') return 'APPROVED';
    if (status === 'disapproved') return 'REJECTED';
    return status.toUpperCase();
}
function getRequestTypeLabel(type, item) {
    if (type === 'past_attendance') {
        if (item && item.jenis_izin === 'izin') return 'Request Izin';
        if (item && item.jenis_izin === 'sakit') return 'Request Sakit';
        return 'Absen/Izin Kemarin';
    }
    const labels = {
        'late_attendance': 'Presensi Terlambat',
        'bug_report': 'Laporan Bug',
        'diff_location_checkout': 'Lokasi Pulang Berbeda'
    };
    return labels[type] || type;
}

function getRequestTypeClass(type) {
    const classes = {
        'past_attendance': 'bg-blue-50 text-blue-600',
        'late_attendance': 'bg-purple-50 text-purple-600',
        'bug_report': 'bg-amber-50 text-amber-600',
        'diff_location_checkout': 'bg-rose-50 text-rose-600'
    };
    return classes[type] || 'bg-gray-50 text-gray-600';
}

function getStatusClass(status) {
    if (status === 'pending') return 'text-orange-500';
    if (status === 'approved' || status === 'solved') return 'text-emerald-500';
    return 'text-red-500';
}

function getStatusIcon(status) {
    if (status === 'pending') return 'fi-sr-clock';
    if (status === 'approved' || status === 'solved') return 'fi-sr-check-circle';
    return 'fi-sr-cross-circle';
}

function formatTimeAgo(timestamp) {
    const now = new Date();
    const date = new Date(timestamp);
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    return Math.floor(diff / 86400) + ' hari lalu';
}

function formatTimestamp(ts) {
    const d = new Date(ts);
    return d.toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) + ' ' + 
           d.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' });
}

function isAdmin() {
    return window.USER_ROLE === 'admin';
}
function isPegawai() {
    return window.USER_ROLE === 'pegawai';
}

// Manual Holidays Logic
(function(){
    const tBody = document.getElementById('mh-table-body');
    if(!tBody) return; // Not on settings page or table missing

    const loadHolidays = async () => {
        tBody.innerHTML = '<tr><td colspan="3" class="p-4 text-center"><i class="fi fi-sr-spinner animate-spin"></i> Loading...</td></tr>';
        try {
            const res = await api('?ajax=get_manual_holidays');
            if(res.ok) {
                const holidays = res.data;
                if(holidays.length === 0) {
                     tBody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-gray-400">Belum ada hari libur manual</td></tr>';
                } else {
                    tBody.innerHTML = holidays.map(h => `
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4 border-b border-gray-50">${new Date(h.date).toLocaleDateString('id-ID', {weekday:'long', year:'numeric', month:'long', day:'numeric'})}</td>
                            <td class="p-4 border-b border-gray-50 font-medium text-gray-800">${h.name || h.description}</td>
                            <td class="p-4 border-b border-gray-50 text-center">
                                <button onclick="deleteHoliday(${h.id})" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-all">
                                    <i class="fi fi-sr-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } else {
                 tBody.innerHTML = `<tr><td colspan="3" class="p-4 text-center text-red-500">${res.message || 'Gagal memuat data'}</td></tr>`;
            }
        } catch(e) {
             tBody.innerHTML = `<tr><td colspan="3" class="p-4 text-center text-red-500">${e.message}</td></tr>`;
        }
    };

    const addBtn = document.getElementById('add-holiday');
    if(addBtn) {
        addBtn.addEventListener('click', async () => {
             const dParams = {
                 date: document.getElementById('mh-date-input').value,
                 description: document.getElementById('mh-name-input').value
             };
             if(!dParams.date || !dParams.description) {
                 showNotif('Mohon lengkapi data', false);
                 return;
             }
             
             addBtn.innerHTML = '<i class="fi fi-sr-spinner animate-spin"></i>';
             addBtn.disabled = true;
             
             try {
                 const res = await api('?ajax=add_manual_holiday', dParams);
                 if(res.ok) {
                     showNotif('Hari libur ditambahkan');
                     document.getElementById('mh-date-input').value = '';
                     document.getElementById('mh-name-input').value = '';
                     loadHolidays();
                 } else {
                     showNotif(res.message || 'Gagal', false);
                 }
             } catch(e) { showNotif(e.message, false); }
             finally {
                 addBtn.innerHTML = '<i class="fi fi-sr-plus"></i> Tambah';
                 addBtn.disabled = false;
             }
        });
    }

    // Expose delete function to window
    window.deleteHoliday = async (id) => {
        if(!await customConfirm('Hapus hari libur ini?')) return;
        try {
            const res = await api('?ajax=delete_manual_holiday', {id});
            if(res.ok) {
                showNotif('Hari libur dihapus');
                loadHolidays();
            } else {
                showNotif(res.message || 'Gagal', false);
            }
        } catch(e) { showNotif(e.message, false); }
    };

    // Initial load
    loadHolidays();
})();

/**
 * Show a full-size image in a modal (Base64 or Path)
 */
window.showImageModal = function(src, title) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title || 'Bukti Presensi',
            imageUrl: src,
            imageAlt: 'Bukti Presensi',
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Tutup',
            width: 'auto',
            imageWidth: 600,
            customClass: {
                image: 'rounded-xl shadow-lg border border-gray-100'
            }
        });
    } else {
        window.open(src, '_blank');
    }
};


