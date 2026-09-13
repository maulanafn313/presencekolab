<header class="bg-white/80 backdrop-blur-md sticky top-0 z-30 border-b border-gray-100">
        <div class="w-full px-4 lg:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-toggle" class="md:hidden p-2 hover:bg-gray-100 rounded-xl text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold">
                        <i class="fi fi-sr-admin-alt text-sm"></i>
                    </div>
                    <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-800 to-gray-600 tracking-tight hidden sm:block">SPBW <span class="text-gray-400 font-light">|</span> ADMIN</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <!-- Admin Notifications -->
                <div class="relative">
                    <button id="btn-notifications" class="relative p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all group">
                        <i class="fi fi-sr-bell text-xl"></i>
                        <span id="notif-badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full hidden"></span>
                    </button>
                    <!-- Notifications Dropdown -->
                    <div id="dropdown-notifications" class="fixed sm:absolute left-4 right-4 sm:left-auto sm:right-0 top-16 sm:top-full mt-3 sm:w-96 bg-white rounded-2xl shadow-xl border border-gray-100 hidden z-50 overflow-hidden animate-fade-in-up">
                        <div class="p-4 border-b border-gray-50 flex items-center justify-between">
                            <h3 class="font-bold text-gray-800">Permintaan Bantuan</h3>
                            <span id="notif-count" class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full font-bold">0</span>
                        </div>
                        <div id="notif-items" class="max-h-96 overflow-y-auto p-2 space-y-1">
                            <!-- Items populated by JS -->
                            <div class="p-8 text-center text-gray-400">
                                <i class="fi fi-sr-inbox text-3xl mb-2 block"></i>
                                <p class="text-xs">Tidak ada permintaan baru</p>
                            </div>
                        </div>
                        <div class="p-3 bg-gray-50 border-t border-gray-100">
                            <button data-tab="help-requests" class="tab-link w-full text-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors uppercase tracking-wider py-2 bg-indigo-50 rounded-full">
                                Lihat Semua Riwayat
                            </button>
                        </div>
                    </div>
                </div>
                <div class="relative flex-shrink-0">
                    <button id="btn-profile" class="flex items-center gap-3 p-1.5 px-5 bg-white border border-gray-200 hover:border-indigo-300 rounded-full transition-all shadow-sm hover:shadow-md group whitespace-nowrap">
                        <?php 
                        $nama = $_SESSION['user']['nama'] ?? 'Admin';
                        $initials = '';
                        $words = explode(' ', $nama);
                        foreach ($words as $w) { if (!empty($w)) $initials .= strtoupper($w[0]); }
                        $initials = substr($initials, 0, 2);
                        ?>
                        <?php if (!empty($_SESSION['user']['foto_base64'])): ?>
                            <img src="<?php echo getAvatarUrl($_SESSION['user']['foto_base64'], $_SESSION['user']['nama'] ?? 'A'); ?>" class="profile-avatar-img ring-2 ring-white" alt="profile">
                        <?php else: ?>
                            <div class="avatar-initials"><?php echo $initials; ?></div>
                        <?php endif; ?>
                        <span class="text-sm font-semibold text-gray-700 group-hover:text-indigo-600 transition-colors hidden sm:inline"><?php echo htmlspecialchars($nama); ?></span>
                        <i class="fi fi-sr-angle-small-down text-gray-400 group-hover:text-indigo-500 transition-colors"></i>
                    </button>
                    <div id="dropdown-profile" class="absolute right-4 sm:right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 hidden z-50 overflow-hidden animate-fade-in-up">
                        <?php if(isset($_SESSION['user'])): ?>
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                                <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Signed in as</p>
                                <p class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></p>
                            </div>
                            <a href="?page=logout" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2">
                                <i class="fi fi-sr-sign-out-alt"></i> Logout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        

    </header>