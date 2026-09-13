<?php
$origFile = 'C:\Users\Maulana\.gemini\antigravity-ide\brain\e009db91-ca73-43a8-9cea-3f33004a0619\scratch\qa_panel_orig.blade.php';
$destFile = 'c:\laragon\www\Absen\resources\views\pages\qa_panel.blade.php';

$orig = file_get_contents($origFile);

// Fix the user name
$orig = str_replace("\$qa_user   = \$_SESSION['user']['nama'] ?? 'Admin';", "\$qa_user   = 'admin';", $orig);

// Replace UI (around line 72)
$searchUI = '<p class="text-xs text-gray-400 mt-3 text-center">Halaman presensi dibuka di tab baru dengan akun Anda</p>';
$replaceUI = '<p class="text-xs text-gray-400 mt-3 text-center">Simulasi presensi di halaman yang sama</p>
      
      <!-- Setup Wajah Admin -->
      <div class="mt-4 p-4 border border-blue-100 bg-blue-50 rounded-xl">
        <p class="text-xs font-semibold text-blue-800 mb-2">Belum mendaftarkan wajah? (Wajib untuk tes presensi)</p>
        
        <div id="qa-cam-container" class="hidden mb-3 relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
            <video id="qa-video" autoplay playsinline class="w-full h-full object-cover transform scale-x-[-1]"></video>
            <canvas id="qa-canvas" class="hidden"></canvas>
            <button id="qa-btn-snap" class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white text-blue-600 rounded-full px-4 py-2 font-bold shadow-lg hover:bg-gray-50 flex items-center gap-2 text-sm z-10">
                <i class="fi fi-sr-camera"></i> Ambil Foto
            </button>
        </div>
        
        <div class="flex flex-col gap-2" id="qa-action-buttons">
            <button id="qa-btn-start-cam" class="w-full py-2 bg-white border border-blue-200 text-blue-700 hover:bg-blue-100 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                <i class="fi fi-sr-camera"></i> Buka Kamera
            </button>
            <input type="file" id="qa-face-upload" accept="image/*" class="hidden">
            <button onclick="document.getElementById(\'qa-face-upload\').click()" class="w-full py-2 bg-transparent text-blue-600 hover:bg-blue-100 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i class="fi fi-sr-folder-upload"></i> Atau Upload File Foto
            </button>
        </div>
        
        <p id="qa-face-status" class="text-[10px] text-blue-600 mt-2 text-center hidden"></p>
      </div>';
$orig = str_replace($searchUI, $replaceUI, $orig);

// Change target blank for presensi
$orig = str_replace('target="_blank"', '', $orig);

// Inject JS
$replaceJS = "document.addEventListener('DOMContentLoaded',function(){var p=document.getElementById('page-qa-panel');if(p&&!p.classList.contains('hidden'))qaLoadAtt();
    var qaStream = null;
    var btnStart = document.getElementById('qa-btn-start-cam');
    var btnSnap = document.getElementById('qa-btn-snap');
    var video = document.getElementById('qa-video');
    var canvas = document.getElementById('qa-canvas');
    var container = document.getElementById('qa-cam-container');
    var actionBtns = document.getElementById('qa-action-buttons');
    var statusEl = document.getElementById('qa-face-status');
    
    if(btnStart) {
        btnStart.addEventListener('click', async function(){
            try {
                qaStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } });
                video.srcObject = qaStream;
                container.classList.remove('hidden');
                actionBtns.classList.add('hidden');
                if(statusEl) { statusEl.textContent = 'Silakan posisikan wajah di kamera lalu klik Ambil Foto'; statusEl.className = 'text-[10px] text-blue-600 mt-2 text-center block'; statusEl.classList.remove('hidden'); }
            } catch(e) {
                if(statusEl) { statusEl.textContent = 'Gagal akses kamera: ' + e.message; statusEl.className = 'text-[10px] text-red-600 mt-2 text-center block'; statusEl.classList.remove('hidden'); }
            }
        });
    }
    
    if(btnSnap) {
        btnSnap.addEventListener('click', async function(){
            var ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            var dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            
            if(qaStream){ qaStream.getTracks().forEach(t=>t.stop()); qaStream=null; }
            container.classList.add('hidden');
            actionBtns.classList.remove('hidden');
            btnStart.innerHTML = '<i class=\"fi fi-sr-camera\"></i> Ambil Ulang Foto';
            
            await uploadFaceQA(dataUrl);
        });
    }
    
    var qaFaceInput = document.getElementById('qa-face-upload');
    if (qaFaceInput) {
        qaFaceInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = async function(evt) {
                await uploadFaceQA(evt.target.result);
            };
            reader.readAsDataURL(file);
        });
    }
    
    async function uploadFaceQA(dataUrl) {
        if(statusEl) { statusEl.textContent = 'Memproses data wajah...'; statusEl.className = 'text-[10px] text-blue-600 mt-2 text-center block font-bold'; statusEl.classList.remove('hidden'); }
        try {
            var fd = new URLSearchParams();
            fd.append('image', dataUrl);
            var r = await fetch('/?ajax=generate_face_embedding', {
                method: 'POST',
                body: fd,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            var d = await r.json();
            if (d.ok || d.success || d.message) {
                statusEl.textContent = 'Wajah berhasil didaftarkan! Anda siap tes presensi.';
                statusEl.className = 'text-[10px] text-green-600 mt-2 text-center block font-bold';
            } else {
                statusEl.textContent = 'Gagal: ' + (d.error || d.message || 'Unknown error');
                statusEl.className = 'text-[10px] text-red-600 mt-2 text-center block font-bold';
            }
        } catch(e) {
            statusEl.textContent = 'Error koneksi: ' + e.message;
            statusEl.className = 'text-[10px] text-red-600 mt-2 text-center block font-bold';
        }
    }
});";

$orig = preg_replace("/document\.addEventListener\('DOMContentLoaded',function\(\)\{.*?qaLoadAtt\(\);\}\);/s", $replaceJS, $orig);

file_put_contents($destFile, $orig);
echo "File updated cleanly.\n";
