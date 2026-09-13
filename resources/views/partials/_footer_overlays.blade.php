<!-- Loading Overlay for model -->
<div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-75 flex flex-col items-center justify-center z-[60] hidden">
    <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-24 w-24 mb-4"></div>
    <h2 class="text-center text-white text-xl font-semibold">Memuat Sistem Presensi...</h2>
    <p class="w-1/3 text-center text-white text-sm">Memuat model AI dan database wajah. Mohon tunggu sebentar.</p>
    <div class="mt-4 text-white text-xs opacity-75">
        <div id="loading-progress">Memulai...</div>
    </div>
</div>

<div id="notif-bar" class="fixed top-4 left-1/2 transform -translate-x-1/2 bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg z-[70] hidden"></div>

<!-- Global Notification Modal -->
<div id="global-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[9999] hidden">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl p-0 overflow-hidden animate-fade-in-up">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4">
            <div id="global-modal-title" class="text-lg font-bold text-white">Notifikasi</div>
        </div>
        <div class="p-6">
            <div id="global-modal-message" class="text-gray-700 text-base leading-relaxed"></div>
        </div>
        <div id="global-modal-actions" class="px-6 pb-6 flex gap-3 justify-end">
            <button id="global-modal-cancel" class="hidden px-5 py-2.5 rounded-xl font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 transition-all">
                Batal
            </button>
            <button id="global-modal-ok" class="px-5 py-2.5 rounded-xl font-semibold bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white transition-all shadow-md hover:shadow-lg">
                OK
            </button>
        </div>
    </div>
</div>

