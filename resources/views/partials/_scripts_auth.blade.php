<?php if ($page === 'login'): ?>
// Login
const loginForm = qs('#form-login');
if (loginForm) {
    loginForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const msg = qs('#login-msg');
        const submitBtn = e.target.querySelector('button');
        
        msg.className = 'text-blue-600 font-semibold';
        msg.textContent = '⏳ Memproses login...';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
        }
        
        try {
            const r = await api('?ajax=login', fd);
            if(r && r.ok){
                msg.className = 'text-green-600 font-semibold';
                msg.textContent = '✅ Login berhasil! Mengalihkan...';
                setTimeout(()=> location.href='?', 200); // Faster redirect
            } else {
                msg.className = 'text-red-600 font-semibold';
                msg.textContent = '❌ ' + ((r && r.message) ? r.message : 'Gagal login');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }
        } catch (error) {
            console.error('Login error:', error);
            msg.className = 'text-red-600 font-semibold';
            msg.textContent = '❌ ' + (error.message || 'Gagal login. Periksa koneksi internet Anda.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        }
    });
}
<?php elseif ($page === 'register'): ?>
// Register camera
const regStart = qs('#reg-start-camera');
const regTake = qs('#reg-take-photo');
const regUpload = qs('#reg-upload-photo');
const regRemove = qs('#reg-remove-photo');
const regVideo = qs('#reg-video');
const regCanvas = qs('#reg-canvas');
const regPreview = qs('#reg-foto-preview');
const regVidContainer = qs('#reg-video-container');
const regFotoData = qs('#reg-foto-data');
const regPhotoFileInput = qs('#reg-photo-file-input');
let regStream = null;

// Camera action containers
const regPhotoActions = qs('#photo-actions');
const regCameraActions = qs('#camera-actions');

if (regStart) {
    regStart.addEventListener('click', async ()=>{
        try{
            regStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } });
            regVideo.srcObject = regStream;
            regVidContainer.classList.remove('hidden');
            // FIX: Show #camera-actions container (which holds 'Ambil Foto' button) and hide #photo-actions
            if (regCameraActions) regCameraActions.classList.remove('hidden');
            if (regPhotoActions) regPhotoActions.classList.add('hidden');
            // Also show the take button itself (in case it's also toggled)
            if (regTake) regTake.classList.remove('hidden');
        }catch(err){ showNotif('Tidak bisa mengakses kamera: ' + err.message, false); console.error(err); }
    });
}

if (regTake) {
    regTake.addEventListener('click', ()=>{
        const ctx = regCanvas.getContext('2d');
        regCanvas.width = regVideo.videoWidth;
        regCanvas.height = regVideo.videoHeight;
        // Mirror the photo to match camera preview
        ctx.translate(regCanvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(regVideo, 0, 0, regCanvas.width, regCanvas.height);
        const dataUrl = regCanvas.toDataURL('image/jpeg', 0.9);
        regPreview.src = dataUrl;
        regPreview.classList.remove('hidden');
        regFotoData.value = dataUrl;
        // Stop stream
        if(regStream){ regStream.getTracks().forEach(t=>t.stop()); regStream=null; }
        regVidContainer.classList.add('hidden');
        // Hide camera-actions, show photo-actions again with updated text
        if (regCameraActions) regCameraActions.classList.add('hidden');
        if (regPhotoActions) regPhotoActions.classList.remove('hidden');
        // Show remove button, update start button text
        const regRemoveLocal = qs('#reg-remove-photo');
        if (regRemoveLocal) regRemoveLocal.classList.remove('hidden');
        if (regStart) { regStart.textContent = 'Ambil Ulang Foto'; }
    });
}

// Upload photo functionality
if (regUpload) {
    regUpload.addEventListener('click', ()=>{
        regPhotoFileInput.click();
    });
}

if (regPhotoFileInput) {
    regPhotoFileInput.addEventListener('change', (e)=>{
        const file = e.target.files[0];
        if (file) {
            // Validate file type
            const allowedReg = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedReg.includes(file.type)) {
                showNotif('❌ Format foto tidak didukung. Gunakan JPG atau PNG.', false);
                e.target.value = '';
                return;
            }
            
            // Validate file size (max 5MB untuk registrasi)
            if (file.size > 5 * 1024 * 1024) {
                const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
                showNotif(`❌ Foto terlalu besar (${sizeMB}MB). Ukuran maksimal 5MB. Silakan kompres foto terlebih dahulu.`, false);
                e.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;
                regPreview.src = dataUrl;
                regPreview.classList.remove('hidden');
                regFotoData.value = dataUrl;
                const regRemoveLocal = qs('#reg-remove-photo');
                if (regRemoveLocal) regRemoveLocal.classList.remove('hidden');
                if (regStart) regStart.textContent = 'Buka Kamera';
            };
            reader.readAsDataURL(file);
        }
    });
}

// Remove photo functionality
if (regRemove) {
    regRemove.addEventListener('click', ()=>{
        regPreview.src = '';
        regPreview.classList.add('hidden');
        regFotoData.value = '';
        regRemove.classList.add('hidden');
        regPhotoFileInput.value = '';
        if (regStart) regStart.textContent = 'Buka Kamera';
        
        // Stop camera if running
        if(regStream){ 
            regStream.getTracks().forEach(t=>t.stop()); 
            regStream=null; 
        }
        regVidContainer.classList.add('hidden');
        // Show photo-actions, hide camera-actions
        if (regPhotoActions) regPhotoActions.classList.remove('hidden');
        if (regCameraActions) regCameraActions.classList.add('hidden');
    });
}

const registerForm = qs('#form-register');
if (registerForm) {
    registerForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const msg = qs('#register-msg');
        const submitBtn = e.target.querySelector('button');
        
        // Show loading state
        msg.className = 'text-blue-600 font-semibold';
        msg.textContent = '⏳ Mendaftarkan akun... Mohon tunggu...';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
        }
        
        try {
            // Use suppressModal=true to control modal display ourselves
            const r = await api('?ajax=register', fd, { suppressModal: true });
            if(r && r.ok){ 
                msg.className='text-green-600 font-semibold';
                msg.textContent='✅ Registrasi berhasil! Mengalihkan ke halaman login...';
                
                // Show beautiful success modal in general language
                showModalNotif('Pendaftaran akun Anda berhasil dilakukan! Silakan klik tombol di bawah untuk masuk ke halaman login.', true, 'Pendaftaran Berhasil');
                setTimeout(()=>location.href='?page=login', 2500);
            } else {
                // Get backend raw message
                const rawMsg = (r && r.message) ? r.message : '';
                
                // Map backend errors to very user-friendly, general language messages
                let friendlyMsg = 'Pendaftaran gagal. Silakan periksa kembali data yang Anda masukkan dan coba lagi.';
                if (rawMsg.includes('password tidak cocok')) {
                    friendlyMsg = 'Kata sandi (password) baru dan konfirmasi kata sandi yang Anda masukkan tidak sama. Silakan ketik ulang dengan benar.';
                } else if (rawMsg.includes('wajib diisi')) {
                    friendlyMsg = 'Semua data formulir pendaftaran dan foto wajah wajib diisi dengan lengkap. Silakan periksa kembali kolom formulir yang masih kosong.';
                } else if (rawMsg.includes('email sudah terdaftar')) {
                    friendlyMsg = 'Alamat email yang Anda masukkan sudah terdaftar di sistem. Silakan gunakan email lain, atau masuk (login) jika sudah memiliki akun.';
                } else if (rawMsg.includes('NIM sudah terdaftar')) {
                    friendlyMsg = 'NIM atau NIP yang Anda masukkan sudah terdaftar di sistem. Silakan periksa kembali nomor identitas Anda.';
                } else if (rawMsg.includes('format alamat email')) {
                    friendlyMsg = 'Format alamat email yang Anda masukkan salah atau tidak valid (contoh: nama@domain.com). Silakan periksa kembali.';
                } else if (rawMsg.includes('minimal harus 6 karakter')) {
                    friendlyMsg = 'Kata sandi (password) yang Anda masukkan terlalu pendek. Password minimal harus terdiri dari 6 karakter.';
                } else if (rawMsg.includes('terlalu besar')) {
                    friendlyMsg = 'Ukuran foto wajah Anda terlalu besar (maksimal 1MB). Silakan kompres foto tersebut terlebih dahulu atau gunakan foto dengan resolusi lebih kecil.';
                } else if (rawMsg) {
                    friendlyMsg = rawMsg;
                }
                
                msg.className='text-red-600 font-semibold';
                msg.textContent='❌ ' + friendlyMsg;
                
                // Show modal error message
                showModalNotif(friendlyMsg, false, 'Pendaftaran Gagal');
                
                // Re-enable button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }
        } catch (error) {
            console.error('Registration error:', error);
            const errMsg = 'Tidak dapat terhubung ke server. Silakan pastikan perangkat Anda terhubung ke internet dan coba beberapa saat lagi.';
            msg.className='text-red-600 font-semibold';
            msg.textContent='❌ ' + errMsg;
            
            // Show modal network error message
            showModalNotif(errMsg, false, 'Koneksi Bermasalah');
            
            // Re-enable button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        }
    });
}
<?php elseif ($page === 'forgot-password'): ?>
// Forgot Password
const forgotPasswordForm = qs('#form-forgot-password');
if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const msg = qs('#forgot-password-msg');
        msg.className = 'text-blue-600';
        msg.textContent = 'Mengirim permintaan...';
        
        try {
            const r = await api('?ajax=forgot_password', fd);
            msg.className = r.ok ? 'text-green-600' : 'text-red-600';
            msg.textContent = r.message || (r.ok ? 'Permintaan terkirim.' : 'Permintaan gagal dikirim.');
            if (r.ok) e.target.reset();
        } catch (error) {
            msg.className = 'text-red-600';
            msg.textContent = 'Permintaan gagal dikirim. Coba lagi.';
            console.error('Forgot password error:', error);
        }
    });
}
<?php endif; ?>
