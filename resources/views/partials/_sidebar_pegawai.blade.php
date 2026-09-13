<!-- Mobile Sidebar Overlay -->
<div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden backdrop-blur-sm transition-all"></div>

<!-- Sidebar (Responsive: Fixed on Mobile, Relative on Desktop) -->
<aside id="sidebar-pegawai" class="fixed top-0 left-0 h-full w-64 bg-white shadow-2xl z-50 transform -translate-x-full transition-transform duration-300 md:relative md:translate-x-0 md:shadow-none md:border-r md:border-gray-100 font-outfit flex flex-col">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">
                <i class="fi fi-sr-user text-sm"></i>
            </div>
            <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-800 to-gray-600 tracking-tight">SPBW Pegawai</span>
        </div>
        <button id="mobile-sidebar-close" class="md:hidden p-2 hover:bg-gray-50 rounded-full text-gray-500 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    
    <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
        <nav class="space-y-1.5">
            <?php if (isAdmin()): ?>
                <button data-tab="dashboard" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-dashboard text-lg w-6"></i> Dashboard
                </button>
                <button data-tab="members" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-users text-lg w-6"></i> Kelola Member
                </button>
                <button data-tab="laporan" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-document text-lg w-6"></i> Data Presensi
                </button>
                <button data-tab="admin-monthly" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-calendar text-lg w-6"></i> Laporan Bulanan
                </button>
                <button data-tab="settings" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-settings text-lg w-6"></i> Settings
                </button>
            <?php else: ?>
                <button data-tab="rekap" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-list-check text-lg w-6"></i> Rekap Hadir
                </button>
                <button data-tab="laporan-bulanan" class="tab-link w-full text-left py-2.5 px-4 font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition duration-300 flex items-center gap-3">
                    <i class="fi fi-sr-document-signed text-lg w-6"></i> Laporan Bulanan
                </button>
            <?php endif; ?>
        </nav>
    </div>
</aside>