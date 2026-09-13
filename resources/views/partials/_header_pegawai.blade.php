<header class="bg-white/80 backdrop-blur-md sticky top-0 z-30 border-b border-gray-100">
        <div class="w-full px-4 lg:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-toggle" class="md:hidden p-2 hover:bg-gray-100 rounded-xl text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">
                        <i class="fi fi-sr-shield-check text-sm"></i>
                    </div>
                    <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-800 to-gray-600 tracking-tight hidden sm:block">SPBW <span class="text-gray-400 font-light">|</span> <?php echo isAdmin() ? 'ADMIN' : 'PEGAWAI'; ?></h1>
                </div>
            </div>
            
            <div class="flex items-center gap-4">

                <div class="relative">
                    <button id="btn-profile" class="flex items-center gap-3 p-1 pr-4 bg-white border border-gray-200 hover:border-blue-300 rounded-full transition-all shadow-sm hover:shadow-md group">
                        <?php 
                        $avatar_src = getAvatarUrl($_SESSION['user']['foto_base64'] ?? '', $_SESSION['user']['nama'] ?? 'A');
                        ?>
                        <img src="<?php echo $avatar_src; ?>" class="avatar w-9 h-9 rounded-full object-cover ring-2 ring-white" alt="profile">
                         <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline"><?php echo htmlspecialchars($_SESSION['user']['nama'] ?? 'Akun'); ?></span>
                         <i class="fi fi-sr-angle-small-down text-gray-400"></i>
                    </button>
                    <!-- Dropdown Code Preserved -->
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