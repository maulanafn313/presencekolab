
// App (logged in)
document.addEventListener('click', (e) => {
    const target = e.target;
    
    // Refresh Handler
    const refreshBtn = target.closest('#refresh-kpi');
    if (refreshBtn) {
        const loading = qs('#kpi-loading');
        const empty = qs('#kpi-empty');
        if (loading) loading.classList.remove('hidden');
        if (empty) empty.classList.add('hidden');
        loadKPIData(true);
        return;
    }
    
    // Export Handler
    const exportBtn = target.closest('#btn-export-kpi');
    if (exportBtn) {
        e.preventDefault();
        
        try {
            const fType = document.getElementById('kpi-filter-type');
            const fMonth = document.getElementById('kpi-filter-month');
            const fYear = document.getElementById('kpi-filter-year');
            
            const filterType = fType ? fType.value : 'period';
            const params = new URLSearchParams();
            params.append('filter_type', filterType);
            
            if (filterType === 'monthly' && fMonth && fYear) {
                params.append('month', fMonth.value);
                params.append('year', fYear.value);
            }
            
            const exportUrl = `/export/kpi?${params.toString()}`;
            window.location.href = exportUrl;
            
        } catch (err) {
            console.error('Export error:', err);
        }
    }
});
const pages = { rekap: qs('#page-rekap'), 'laporan-bulanan': qs('#page-laporan-bulanan'), members: qs('#page-members'), laporan: qs('#page-laporan'), 'admin-monthly': qs('#page-admin-monthly'), dashboard: qs('#page-dashboard'), settings: qs('#page-settings'), 'help-requests': qs('#tab-help-requests') };
qsa('.tab-link').forEach(btn=>{
    btn.addEventListener('click', ()=> showPage(btn.dataset.tab));
});

// Mobile sidebar tab links
qsa('.mobile-tab-link').forEach(btn=>{
    btn.addEventListener('click', ()=> {
        showPage(btn.dataset.tab);
        closeMobileSidebar(); // Close sidebar after clicking
    });
});

// Mobile sidebar functions
function openMobileSidebar() {
    const sidebar = qs('#mobile-sidebar');
    const overlay = qs('#mobile-sidebar-overlay');
    if (sidebar) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
    }
    if (overlay) {
        overlay.classList.remove('hidden');
    }
    // Prevent body scroll when sidebar is open
    document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
    const sidebar = qs('#mobile-sidebar');
    const overlay = qs('#mobile-sidebar-overlay');
    if (sidebar) {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
    }
    if (overlay) {
        overlay.classList.add('hidden');
    }
    // Restore body scroll
    document.body.style.overflow = '';
}

// Cache lifecycle is managed by sw.js; do not unregister it on each page load.

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = qs('#mobile-menu-toggle');
    const sidebarClose = qs('#mobile-sidebar-close');
    const overlay = qs('#mobile-sidebar-overlay');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', openMobileSidebar);
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeMobileSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }
    
    // Close sidebar on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeMobileSidebar();
        }
    });
});

function showPage(name, pushState = true){ 
    const isAlreadyActive = pages[name] && pages[name].style.display === 'block';

    Object.values(pages).forEach(p=> p && (p.style.display='none')); 
    
    if(pages[name]) {
        pages[name].style.display='block'; 
    } else {
        // Fallback to dashboard if page not found
        if(pages['dashboard']) pages['dashboard'].style.display='block';
        name = 'dashboard';
    }
    
    // Update active state for desktop tabs
    qsa('.tab-link').forEach(btn => {
        if (btn.dataset.tab === name) {
            btn.classList.add('active-tab');
            btn.classList.remove('text-gray-600', 'hover:bg-indigo-50', 'hover:text-indigo-600');
            // Ensure icons and spans inherit white from the CSS reset I added
        } else {
            btn.classList.remove('active-tab');
            btn.classList.add('text-gray-600', 'hover:bg-indigo-50', 'hover:text-indigo-600');
        }
    });
    
    // Update active state for mobile tabs
    qsa('.mobile-tab-link').forEach(btn => {
        if (btn.dataset.tab === name) {
            btn.classList.add('bg-indigo-600', 'text-white');
            btn.classList.remove('text-gray-700', 'hover:bg-indigo-50', 'hover:text-indigo-600');
        } else {
            btn.classList.remove('bg-indigo-600', 'text-white');
            btn.classList.add('text-gray-700', 'hover:bg-indigo-50', 'hover:text-indigo-600');
        }
    });

    // Update URL parameter without reload
    if (pushState) {
        const url = new URL(window.location.href);
        const currentPage = url.searchParams.get('page');
        
        // Only push state if the page param is different or doesn't exist for private pages
        // index.php defaults private pages to admin/pegawai based on auth, but we want the specific tab
        if (currentPage !== name) {
            url.searchParams.set('page', name);
            history.pushState({ tab: name }, "", url);
        }
    }
    
    if(name==='members') renderMembers(); 
    if(name==='laporan') { loadStartupOptions(); renderLaporan(); } 
    if(name==='rekap' && !isAlreadyActive) initRekapPage(); 
    if(name==='laporan-bulanan') renderMonthly(); 
    if(name==='admin-monthly') renderAdminMonthly(); 
    if(name==='dashboard') renderDashboard(); 
    if(name==='help-requests') loadAllHelpRequests();
    if(name==='settings' && !isAlreadyActive) { renderSettings(); initAddressSearch(); if(typeof loadBackupFiles === 'function') loadBackupFiles(); } 
}

// Handle Browser Back/Forward buttons
window.addEventListener('popstate', (e) => {
    const url = new URL(window.location.href);
    const pageParam = url.searchParams.get('page');
    
    if (pageParam && pages[pageParam]) {
        showPage(pageParam, false);
    } else if (e.state && e.state.tab && pages[e.state.tab]) {
        showPage(e.state.tab, false);
    }
});

// Ensure initial page sets based on URL or defaults
document.addEventListener('DOMContentLoaded', () => {
    const url = new URL(window.location.href);
    const pageParam = url.searchParams.get('page');
    
    // Standalone pages that are NOT part of the main SPA dashboard tabs
    const standalonePages = ['login', 'register', 'presensi-masuk', 'presensi-pulang', 'forgot-password', 'verify-otp', 'reset-password'];
    if (pageParam && standalonePages.includes(pageParam)) {
        console.log('Standalone page detected, skipping SPA routing: ' + pageParam);
        return; 
    }

    if (pageParam && pages[pageParam]) {
        showPage(pageParam, false);
    } else {
        
        if (pages['dashboard']) showPage('dashboard', false);
        
        if (pages['rekap']) showPage('rekap', false);
        
    }
});

// Header buttons for employees - navigate to landing page presensi with return parameter
document.addEventListener('DOMContentLoaded', () => {
    const btnHeaderMasuk = qs('#btn-header-presensi-masuk');
    const btnHeaderPulang = qs('#btn-header-presensi-pulang');
    
    if (btnHeaderMasuk) {
        btnHeaderMasuk.addEventListener('click', () => {
            window.location.href = '?page=landing&return=app&mode=masuk';
        });
    }
    
    if (btnHeaderPulang) {
        btnHeaderPulang.addEventListener('click', () => {
            window.location.href = '?page=landing&return=app&mode=pulang';
        });
    }

    // Initialize month/year selectors for rekap page
    const monthSel = qs('#rekap-month');
    const yearSel = qs('#rekap-year');
    
    if (monthSel) {
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        months.forEach((month, index) => {
            const option = document.createElement('option');
            option.value = String(index + 1);
            option.textContent = month;
            if (index === new Date().getMonth()) {
                option.selected = true;
            }
            monthSel.appendChild(option);
        });
    }
    
    if (yearSel) {
        const currentYear = new Date().getFullYear();
        for (let year = currentYear - 2; year <= currentYear + 1; year++) {
            const option = document.createElement('option');
            option.value = String(year);
            option.textContent = String(year);
            if (year === currentYear) {
                option.selected = true;
            }
            yearSel.appendChild(option);
        }
    }
    
    // Initialize rekap page only if on the rekap page and allowed
    const isPublicPage = ['presensi-masuk', 'presensi-pulang', 'landing'].includes(new URLSearchParams(window.location.search).get('page'));
    
    if (qs('#page-rekap') && !isPublicPage) {
        // Safe check for function existence
        if (typeof initRekapPage === 'function') {
            initRekapPage();
        }
    }
});

// Presensi page for logged-in employees
let presensiVideo = null;
let presensiCanvas = null;
let presensiIsCameraActive = false;
let presensiVideoInterval = null;
let presensiScanMode = '';
let presensiProcessedLabels = new Map();
let presensiIsProcessingRecognition = false;
let presensiLabeledFaceDescriptors = [];
let presensiIsPresensiSuccess = false;

function initPresensiPage() {
    presensiVideo = qs('#video-presensi');
    presensiCanvas = qs('#canvas-presensi');
    
    // Reset state
    presensiIsCameraActive = false;
    presensiVideoInterval = null;
    presensiScanMode = '';
    presensiProcessedLabels = new Map();
    presensiIsProcessingRecognition = false;
    presensiIsPresensiSuccess = false;
    
    // Hide video container initially
    const videoContainer = qs('#video-container-presensi');
    const statusDiv = qs('#presensi-status-presensi');
    const btnBack = qs('#btn-back-presensi');
    const btnStop = qs('#btn-stop-detection-presensi');
    const btnStart = qs('#btn-start-detection-presensi');
    
    if (videoContainer) videoContainer.classList.add('hidden');
    if (statusDiv) statusDiv.classList.add('hidden');
    if (btnBack) btnBack.classList.add('hidden');
    if (btnStop) btnStop.classList.add('hidden');
    if (btnStart) btnStart.classList.add('hidden');
    
    // Button handlers
    const btnMasuk = qs('#btn-presensi-masuk');
    const btnPulang = qs('#btn-presensi-pulang');
    
    if (btnMasuk) {
        btnMasuk.onclick = () => startPresensi('masuk');
    }
    if (btnPulang) {
        btnPulang.onclick = () => startPresensi('pulang');
    }
    if (btnBack) {
        btnBack.onclick = () => {
            stopPresensiCamera();
            videoContainer.classList.add('hidden');
            btnBack.classList.add('hidden');
            btnStop.classList.add('hidden');
            btnStart.classList.add('hidden');
            if (statusDiv) {
                statusDiv.classList.add('hidden');
                statusDiv.textContent = '';
            }
            // Return to employee presensi page (show the buttons again)
            // The page-presensi is already visible, we just need to ensure buttons are visible
            // The buttons are always visible when video container is hidden
        };
    }
    if (btnStop) {
        btnStop.onclick = () => {
            stopPresensiCamera();
            btnStop.classList.add('hidden');
            btnStart.classList.remove('hidden');
        };
    }
    if (btnStart) {
        btnStart.onclick = () => {
            if (!presensiScanMode) return;
            startPresensiCamera();
            btnStart.classList.add('hidden');
            btnStop.classList.remove('hidden');
        };
    }
}

async function startPresensi(mode) {
    presensiScanMode = mode;
    presensiIsPresensiSuccess = false;
    
    // Force request camera and location permissions BEFORE starting
    try {
        // Request camera permission explicitly
        const cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
        // Stop it immediately - we just want to trigger the permission request
        cameraStream.getTracks().forEach(track => track.stop());
        
        // Request location permission explicitly  
        if (!navigator.geolocation) {
            showModalNotif('GPS tidak tersedia di perangkat Anda. Pastikan GPS aktif.', false, 'Izin Lokasi');
            return;
        }
        
        // Request location permission by trying to get position
        await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                () => resolve(true),
                (err) => {
                    if (err.code === err.PERMISSION_DENIED) {
                        showModalNotif('Izin lokasi diperlukan untuk presensi. Silakan aktifkan izin lokasi di pengaturan browser.', false, 'Izin Lokasi');
                        reject(new Error('Location permission denied'));
                    } else {
                        // Other errors are okay (timeout, etc) - we'll retry later
                        resolve(true);
                    }
                },
                { timeout: 5000, enableHighAccuracy: true }
            );
        });
    } catch (error) {
        if (error.name === 'NotAllowedError' || error.message === 'Location permission denied') {
            // Permission denied - user needs to enable it
            return; // Don't proceed
        } else if (error.name === 'NotFoundError') {
            showModalNotif('Kamera tidak ditemukan. Pastikan kamera terhubung.', false, 'Kamera Tidak Tersedia');
            return;
        } else {
            // Other errors - might be timeout, we'll proceed anyway
            console.warn('Permission check warning:', error);
        }
    }
    
    // Show video container
    const videoContainer = qs('#video-container-presensi');
    const btnBack = qs('#btn-back-presensi');
    const btnStop = qs('#btn-stop-detection-presensi');
    const btnStart = qs('#btn-start-detection-presensi');
    
    if (videoContainer) {
        videoContainer.classList.remove('hidden');
    }
    if (btnBack) btnBack.classList.remove('hidden');
    if (btnStop) btnStop.classList.remove('hidden');
    if (btnStart) btnStart.classList.add('hidden');
    
    // Load face recognition models and start camera
    await loadPresensiFaceModels();
    startPresensiCamera();
}

async function loadPresensiFaceModels() {
    if (window.faceApiModelsLoaded) return;
    
    const MODEL_PATH = window.FACEAPI_MODEL_URL || 'assets/face-models';
    
    try {
        console.log('🚀 Pre-warming: loading face models...');
        // Parallel load all 3 models
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_PATH),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH)
        ]);
        window.faceApiModelsLoaded = true;
        console.log('✅ Face models loaded. Now loading member embeddings...');
        
        // Load face descriptors from database
        const res = await fetch('?ajax=get_members');
        const j = await res.json();
        const memberData = j.data || []; // Use memberData NOT members to avoid shadowing global!
        
        // Populate the GLOBAL members array for name lookup in attendance.js
        if (typeof members !== 'undefined') {
            members = memberData;
        }

        presensiLabeledFaceDescriptors = [];
        labeledFaceDescriptors = []; // ALSO fill attendance.js's descriptor array
        
        for (const m of memberData) {
            try {
                const nim = m.nim || m[3] || '';
                const nama = m.nama || m[4] || '';
                const embeddingStr = m.face_embedding || m[8] || null;
                const foto = m.foto_base64 || m[7] || null;
                const label = String(nim || nama || m.id || m[0] || '');

                if (embeddingStr) {
                    const desc = new Float32Array(JSON.parse(embeddingStr));
                    const ld = new faceapi.LabeledFaceDescriptors(label, [desc]);
                    presensiLabeledFaceDescriptors.push(ld);
                    labeledFaceDescriptors.push(ld); // Mirror to attendance.js global
                } else if (foto) {
                    const img = await faceapi.fetchImage(foto);
                    const detection = await faceapi.detectSingleFace(img,
                        new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 })
                    ).withFaceLandmarks().withFaceDescriptor();
                    if (detection) {
                        const ld = new faceapi.LabeledFaceDescriptors(label, [detection.descriptor]);
                        presensiLabeledFaceDescriptors.push(ld);
                        labeledFaceDescriptors.push(ld);
                    }
                }
            } catch (err) {
                console.warn('Error processing member for face sync:', err);
            }
        }
        console.log(`✅ Pre-warm done: ${labeledFaceDescriptors.length} face descriptors ready.`);
    } catch (error) {
        console.error('Error loading face models:', error);
    }
}
window.loadPresensiFaceModels = loadPresensiFaceModels;

function startPresensiCamera() {
    if (presensiIsCameraActive) return;
    
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            presensiVideo.srcObject = stream;
            presensiIsCameraActive = true;
            
            presensiVideo.addEventListener('loadedmetadata', () => {
                presensiCanvas.width = presensiVideo.videoWidth;
                presensiCanvas.height = presensiVideo.videoHeight;
                startPresensiDetection();
            });
        })
        .catch(err => {
            console.error('Error accessing camera:', err);
            showModalNotif('Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.', false, 'Error Kamera');
        });
}

function stopPresensiCamera() {
    if (presensiVideo && presensiVideo.srcObject) {
        presensiVideo.srcObject.getTracks().forEach(track => track.stop());
        presensiVideo.srcObject = null;
    }
    presensiIsCameraActive = false;
    if (presensiVideoInterval) {
        clearInterval(presensiVideoInterval);
        presensiVideoInterval = null;
    }
}

function startPresensiDetection() {
    if (!presensiIsCameraActive || presensiIsPresensiSuccess) return;
    if (presensiVideoInterval) clearInterval(presensiVideoInterval);
    
    presensiVideoInterval = setInterval(async () => {
        if (presensiIsPresensiSuccess || presensiIsProcessingRecognition) return;
        
        try {
            const detections = await faceapi
                .detectAllFaces(presensiVideo, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptors();
            
            if (detections.length === 0 || presensiLabeledFaceDescriptors.length === 0) {
                const ctx = presensiCanvas.getContext('2d');
                ctx.clearRect(0, 0, presensiCanvas.width, presensiCanvas.height);
                return;
            }
            
            // Use adjusted threshold based on device type (more lenient for mobile)
            const adjustedThreshold = getAdjustedFaceMatcherThreshold();
            const faceMatcher = new faceapi.FaceMatcher(presensiLabeledFaceDescriptors, adjustedThreshold);
            const resizedDetections = faceapi.resizeResults(detections, {
                width: presensiVideo.videoWidth,
                height: presensiVideo.videoHeight
            });
            
            const ctx = presensiCanvas.getContext('2d');
            ctx.clearRect(0, 0, presensiCanvas.width, presensiCanvas.height);
            
            resizedDetections.forEach(detection => {
                const bestMatch = faceMatcher.findBestMatch(detection.descriptor);
                
                if (bestMatch.label !== 'unknown' && bestMatch.distance < 0.4) {
                    const box = detection.detection.box;
                    ctx.strokeStyle = '#00ff00';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                    ctx.fillStyle = '#00ff00';
                    ctx.font = '16px Arial';
                    ctx.fillText(bestMatch.label, box.x, box.y - 5);
                    
                    // Process recognition
                    if (!presensiProcessedLabels.has(bestMatch.label)) {
                        processPresensiRecognition(bestMatch.label);
                    }
                }
            });
        } catch (error) {
            console.error('Detection error:', error);
        }
    }, 100);
}

async function processPresensiRecognition(nim) {
    if (presensiIsProcessingRecognition || presensiIsPresensiSuccess) return;
    if (presensiProcessedLabels.has(nim)) return;
    
    presensiIsProcessingRecognition = true;
    presensiProcessedLabels.set(nim, Date.now());
    
    try {
        // Get GPS location with better error handling
        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    if (pos.coords.accuracy <= 50) {
                        resolve(pos);
                    } else {
                        // GPS accuracy accepted regardless of value
                        resolve(pos);
                    }
                },
                (error) => {
                    // Check permission state before rejecting
                    if (navigator.permissions) {
                        navigator.permissions.query({ name: 'geolocation' }).then(result => {
                            if (result.state === 'denied') {
                                reject(new Error('Izin lokasi ditolak'));
                            } else {
                                reject(error);
                            }
                        }).catch(() => reject(error));
                    } else {
                        reject(error);
                    }
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
        
        // Take screenshot
        const screenshot = await new Promise((resolve) => {
            try {
                const tmp = document.createElement('canvas');
                tmp.width = 240;
                tmp.height = 240;
                const tctx = tmp.getContext('2d');
                tctx.drawImage(presensiVideo, 0, 0, tmp.width, tmp.height);
                resolve(tmp.toDataURL('image/jpeg', 0.5));
            } catch (e) {
                resolve(null);
            }
        });
        
        // Submit attendance
        const data = {
            nim: nim,
            mode: presensiScanMode,
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            gps_accuracy: position.coords.accuracy,
            screenshot: screenshot
        };
        
        const response = await api('?ajax=save_attendance', data, { suppressModal: true });
        
        if (response.ok) {
            presensiIsPresensiSuccess = true;
            stopPresensiCamera();
            
            const btnStop = qs('#btn-stop-detection-presensi');
            const btnStart = qs('#btn-start-detection-presensi');
            
            if (btnStop) {
                btnStop.classList.add('hidden');
            }
            if (btnStart) {
                btnStart.classList.remove('hidden');
                // Remove existing listeners and add new one
                const newBtnStart = btnStart.cloneNode(true);
                btnStart.parentNode.replaceChild(newBtnStart, btnStart);
                newBtnStart.addEventListener('click', () => {
                    presensiIsPresensiSuccess = false;
                    presensiProcessedLabels.delete(nim);
                    startPresensiCamera();
                    newBtnStart.classList.add('hidden');
                    if (btnStop) btnStop.classList.remove('hidden');
                });
            }
            
            const statusDiv = qs('#presensi-status-presensi');
            if (statusDiv) {
                statusDiv.classList.remove('hidden');
                statusDiv.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-green-100 text-green-700';
                statusDiv.textContent = response.message || 'Presensi berhasil!';
            }
        } else {
            const statusDiv = qs('#presensi-status-presensi');
            if (statusDiv) {
                statusDiv.classList.remove('hidden');
                statusDiv.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-red-100 text-red-700';
                statusDiv.textContent = response.message || 'Presensi gagal. Silakan coba lagi.';
            }
            presensiProcessedLabels.delete(nim);
        }
    } catch (error) {
        console.error('Presensi error:', error);
        const statusDiv = qs('#presensi-status-presensi');
        if (statusDiv) {
            statusDiv.classList.remove('hidden');
            statusDiv.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-red-100 text-red-700';
            let errorMsg = 'Presensi gagal. Silakan coba lagi.';
            
            if (error.message.includes('Izin lokasi ditolak')) {
                errorMsg = 'Izin lokasi ditolak. Silakan aktifkan izin lokasi di pengaturan browser.';
            } else if (error.message.includes('GPS accuracy') || error.message.includes('GPS')) {
                // Check if permission is granted but GPS accuracy is low
                if (navigator.permissions) {
                    navigator.permissions.query({ name: 'geolocation' }).then(result => {
                        if (result.state === 'granted') {
                            statusDiv.textContent = errorMsg;
                            statusDiv.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-yellow-100 text-yellow-700';
                        } else {
                            statusDiv.textContent = errorMsg;
                        }
                    }).catch(() => {
                        statusDiv.textContent = errorMsg;
                    });
                } else {
                    statusDiv.textContent = errorMsg;
                    statusDiv.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-yellow-100 text-yellow-700';
                }
            } else if (error.message.includes('timeout')) {
                // Check if permission is granted before showing timeout error
                if (navigator.permissions) {
                    navigator.permissions.query({ name: 'geolocation' }).then(result => {
                        if (result.state === 'granted') {
                            statusDiv.textContent = 'Mendapatkan lokasi memakan waktu lama. Pastikan GPS aktif dan berada di area terbuka.';
                            statusDiv.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-yellow-100 text-yellow-700';
                        } else {
                            statusDiv.textContent = 'Izin lokasi diperlukan. Silakan aktifkan izin lokasi.';
                        }
                    }).catch(() => {
                        statusDiv.textContent = errorMsg;
                    });
                } else {
                    statusDiv.textContent = errorMsg;
                }
            } else {
                statusDiv.textContent = errorMsg;
            }
        }
        presensiProcessedLabels.delete(nim);
    } finally {
        presensiIsProcessingRecognition = false;
    }
}

// Face recognition functions are handled in the landing page section
// The logged-in app focuses on admin/employee dashboard functionality

// Members (Admin)
async function renderMembers(){
    const j = await api('?ajax=get_members&light=1&no_embeddings=1', {}, { suppressModal: true, cache: true });
    const members = (j.data||[]);
    const term = (qs('#search-member')?.value||'').toLowerCase();
    const filtered = members.filter(m=> (m.nama||'').toLowerCase().includes(term) || (m.nim||'').toLowerCase().includes(term));
    
    renderPaginatedTable('table-members-body', filtered, (m) => {
        const tr = document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        tr.innerHTML = `
            <td class="py-2 px-4">
                ${m.has_foto ? 
                    `<div id="member-photo-container-${m.id}" data-id="${m.id}" class="lazy-member-photo h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden border border-gray-200 cursor-pointer" title="Klik untuk memperbesar">
                        <i class="fi fi-sr-spinner animate-spin text-gray-400 text-[10px]"></i>
                    </div>` : 
                    `<div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-[10px] border border-gray-200 leading-tight text-center">No Pic</div>`
                }
            </td>
            <td class="py-2 px-4">${m.nim||''}</td>
            <td class="py-2 px-4">${m.nama||''}</td>
            <td class="py-2 px-4">${m.prodi||''}</td>
            <td class="py-2 px-4">${m.startup||'-'}</td>
            <td class="py-2 px-4 text-center">
                <button class="btn-ga-qr bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition" data-id="${m.id}" data-email="${m.email || ''}" title="Lihat QR Code Google Authenticator">
                    <i class="fi fi-sr-qr-code mr-1"></i>QR Code
                </button>
            </td>
            <td class="py-2 px-4 text-center">
                <button class="btn-edit-member text-yellow-600 font-bold" data-id="${m.id}" data-json='${JSON.stringify(m).replace(/'/g,"&apos;")}' title="Edit"><i class="fi fi-sr-pen-square"></i></button>
                <button class="btn-work-schedule text-green-600 font-bold ml-2" data-id="${m.id}" data-name="${m.nama}" title="Kelola Jadwal Kerja"><i class="fi fi-sr-calendar"></i></button>
                <button class="btn-delete-member text-red-600 font-bold ml-2" data-id="${m.id}" title="Hapus"><i class="fi fi-ss-trash"></i></button>
            </td>`;
        if (m.has_foto) {
            setTimeout(() => {
                const el = qs(`#member-photo-container-${m.id}`);
                if (el && window.memberPhotoObserver) window.memberPhotoObserver.observe(el);
                else if (el && !window.memberPhotoObserver && window.lazyLoadMemberPhoto) window.lazyLoadMemberPhoto(m.id, `member-photo-container-${m.id}`);
            }, 0);
        }
        return tr;
    }, {
        colSpan: 7,
        emptyMessage: 'Tidak ada data member.',
        onPageChange: renderMembers
    });
}

// End of member photo setup

qs('#search-member') && qs('#search-member').addEventListener('input', () => { resetTablePage('table-members-body'); renderMembers(); });

const memberModal = qs('#member-modal');
const btnAddMember = qs('#btn-add-member');
const btnCancelModal = qs('#btn-cancel-modal');
const memberForm = qs('#member-form');

const modalVideoContainer = qs('#modal-video-container');
const modalVideo = qs('#modal-video');
const modalCanvas = qs('#modal-canvas');
const btnStartCamera = qs('#btn-start-camera');
const btnTakePhoto = qs('#btn-take-photo');
const btnUploadPhoto = qs('#btn-upload-photo');
const photoFileInput = qs('#photo-file-input');
const fotoPreview = qs('#foto-preview');
const fotoDataUrlInput = qs('#foto-data-url');
let modalStream = null;

function resetModalCamera(){ stopModalCamera(); modalVideoContainer.classList.add('hidden'); btnTakePhoto.classList.add('hidden'); btnStartCamera.classList.remove('hidden'); btnStartCamera.textContent='Buka Kamera untuk Foto'; fotoPreview.classList.add('hidden'); fotoDataUrlInput.value=''; }
function stopModalCamera(){ if(modalStream){ modalStream.getTracks().forEach(t=>t.stop()); modalStream=null; } }

btnStartCamera && btnStartCamera.addEventListener('click', async ()=>{
    try{ modalStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } }); modalVideo.srcObject = modalStream; modalVideoContainer.classList.remove('hidden'); btnTakePhoto.classList.remove('hidden'); btnStartCamera.classList.add('hidden'); fotoPreview.classList.add('hidden'); }catch(err){ showNotif('Tidak bisa mengakses kamera.'); console.error(err); }
});

btnTakePhoto && btnTakePhoto.addEventListener('click', ()=>{
    const ctx = modalCanvas.getContext('2d'); modalCanvas.width = modalVideo.videoWidth; modalCanvas.height = modalVideo.videoHeight; ctx.drawImage(modalVideo,0,0,modalCanvas.width,modalCanvas.height);
    const dataUrl = modalCanvas.toDataURL('image/jpeg'); fotoPreview.src = dataUrl; fotoDataUrlInput.value = dataUrl; fotoPreview.classList.remove('hidden'); stopModalCamera(); modalVideoContainer.classList.add('hidden'); btnTakePhoto.classList.add('hidden'); btnStartCamera.classList.remove('hidden'); btnStartCamera.textContent='Ambil Ulang Foto';
});

btnUploadPhoto && btnUploadPhoto.addEventListener('click', ()=>{
    photoFileInput.click();
});

photoFileInput && photoFileInput.addEventListener('change', (e)=>{
    const file = e.target.files[0];
    if (file) {
        // Validasi tipe file
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            showNotif('❌ Format foto tidak didukung. Gunakan JPG atau PNG.', false);
            e.target.value = '';
            return;
        }
        // Validasi ukuran file (maks 2MB)
        const maxSizeMB = 2;
        if (file.size > maxSizeMB * 1024 * 1024) {
            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(1);
            showNotif(`❌ Foto terlalu besar (${fileSizeMB}MB). Ukuran maksimal ${maxSizeMB}MB. Silakan kompres foto terlebih dahulu.`, false);
            e.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            const dataUrl = e.target.result;
            fotoPreview.src = dataUrl;
            fotoDataUrlInput.value = dataUrl;
            fotoPreview.classList.remove('hidden');
            stopModalCamera();
            modalVideoContainer.classList.add('hidden');
            btnTakePhoto.classList.add('hidden');
            btnStartCamera.classList.remove('hidden');
            btnStartCamera.textContent='Ambil Ulang Foto';
        };
        reader.readAsDataURL(file);
    }
});

btnAddMember && btnAddMember.addEventListener('click', ()=>{
    memberForm.reset(); qs('#modal-title').textContent='Tambah Member Baru'; qs('#member-id').value=''; qs('#nim').readOnly=false; resetModalCamera(); btnStartCamera.textContent='Buka Kamera untuk Foto'; memberModal.classList.remove('hidden'); qs('#password-admin-wrapper').classList.remove('hidden');
});

btnCancelModal && btnCancelModal.addEventListener('click', ()=>{ stopModalCamera(); memberModal.classList.add('hidden'); });

// QR Code Modal
const gaQrModal = qs('#ga-qr-modal');
const btnCloseGaQr = qs('#btn-close-ga-qr');
if(btnCloseGaQr && gaQrModal){
    btnCloseGaQr.addEventListener('click', ()=>{
        gaQrModal.classList.add('hidden');
    });
    // Close modal when clicking outside
    gaQrModal.addEventListener('click', (e)=>{
        if(e.target === gaQrModal){
            gaQrModal.classList.add('hidden');
        }
    });
}

document.addEventListener('click', async (e)=>{
    const btnEdit = e.target.closest('.btn-edit-member');
    const btnDelete = e.target.closest('.btn-delete-member');
    const btnWorkSchedule = e.target.closest('.btn-work-schedule');
    const btnGaQr = e.target.closest('.btn-ga-qr');
    const btnViewDr = e.target.closest('.btn-view-dr-admin');
    const btnEditAtt = e.target.closest('.btn-edit-att');
    const btnDeleteLaporan = e.target.closest('.btn-delete-laporan');
    const btnViewMonth = e.target.closest('.btn-view-month');
    const btnAmApprove = e.target.closest('.btn-am-approve');
    const btnAmDisapprove = e.target.closest('.btn-am-disapprove');
    const btnViewMonthDetail = e.target.closest('.btn-view-month-detail');
    const btnViewKet = e.target.closest('.btn-view-ket');
    
    if(btnGaQr){
        const userId = btnGaQr.getAttribute('data-id');
        const email = btnGaQr.getAttribute('data-email');
        const qrModal = qs('#ga-qr-modal');
        const qrImage = qs('#ga-qr-image');
        const qrEmail = qs('#ga-qr-email');
        
        qrModal.classList.remove('hidden');
        qrEmail.textContent = 'Email: ' + email;
        qrImage.src = '';
        qrImage.alt = 'Loading QR Code...';
        
        try {
            const r = await api('?ajax=get_ga_qr&user_id=' + userId, {});
            if(r.ok && r.qr_url){
                qrImage.src = r.qr_url;
                qrImage.alt = 'QR Code Google Authenticator';
            } else {
                showNotif(r.message || 'Gagal memuat QR code', false);
                qrModal.classList.add('hidden');
            }
        } catch(err) {
            showNotif('Gagal memuat QR code', false);
            qrModal.classList.add('hidden');
        }
    }

    if(btnEdit){
        const data = JSON.parse(btnEdit.getAttribute('data-json').replace(/&apos;/g, "'"));
        resetModalCamera();
        qs('#modal-title').textContent='Edit Member';
        qs('#member-id').value = data.id;
        qs('#email').value = data.email || '';
        qs('#email').readOnly = false;
        qs('#nim').value = data.nim || '';
        qs('#nim').readOnly = true;
        qs('#nama').value = data.nama || '';
        qs('#prodi').value = data.prodi || '';
        qs('#startup').value = data.startup || '';
        fotoPreview.src = data.foto_base64 || '';
        if(data.foto_base64) fotoPreview.classList.remove('hidden');
        btnStartCamera.textContent='Ambil Ulang Foto';
        qs('#password-admin-wrapper').classList.add('hidden');
        memberModal.classList.remove('hidden');
    }

    if(btnDelete){
        const id = btnDelete.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus member ini?', async ()=>{
            await api('?ajax=delete_member', { id });
            renderMembers(); 
            if (typeof loadLabeledFaceDescriptors === 'function') {
                loadLabeledFaceDescriptors();
            }
        });
    }

    if(btnWorkSchedule){
        const userId = btnWorkSchedule.getAttribute('data-id');
        const userName = btnWorkSchedule.getAttribute('data-name');
        await openWorkScheduleModal(userId, userName);
    }

    if(btnDeleteLaporan){
        const id = btnDeleteLaporan.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus data kehadiran ini?', async ()=>{ await api('?ajax=delete_attendance', { id }); renderLaporan(); });
    }
    
        if(btnEditAtt){
        const att = JSON.parse(btnEditAtt.getAttribute('data-json').replace(/&apos;/g, "'"));
        qs('#edit-att-id').value = att.id;
        qs('#edit-att-user-id').value = att.user_id || '';
        qs('#edit-att-date').value = (att.jam_masuk_iso||att.date||'').slice(0,10);
        qs('#edit-att-nama').value = att.nama || '';
        qs('#edit-att-jam-masuk').value = att.jam_masuk ? att.jam_masuk.substring(0, 5) : '';
        qs('#edit-att-jam-pulang').value = att.jam_pulang ? att.jam_pulang.substring(0, 5) : '';
        qs('#edit-att-ket').value = att.ket || 'hadir';
        qs('#edit-att-status').value = att.status || 'ontime';
        
        // Handle WFA and Overtime fields
        const wfaForm = qs('#edit-att-wfa-form');
        const overtimeForm = qs('#edit-att-overtime-form');
        if (wfaForm) wfaForm.classList.add('hidden');
        if (overtimeForm) overtimeForm.classList.add('hidden');
        
        if (att.ket === 'wfa' && wfaForm) {
            wfaForm.classList.remove('hidden');
            qs('#edit-att-alasan-wfa').value = att.alasan_wfa || '';
        } else if (att.ket === 'overtime' && overtimeForm) {
            overtimeForm.classList.remove('hidden');
            qs('#edit-att-alasan-overtime').value = att.alasan_overtime || '';
            qs('#edit-att-lokasi-overtime').value = att.lokasi_overtime || '';
        }
        
        // Handle existing screenshots (LAZY LOADING)
        qs('#edit-att-screenshot-masuk-preview').classList.add('hidden');
        qs('#edit-att-screenshot-pulang-preview').classList.add('hidden');
        editAttScreenshotMasuk = null;
        editAttScreenshotPulang = null;
        
        if (att.has_sm) {
            api('?ajax=get_attendance_evidence&id=' + att.id + '&type=masuk', {}, { suppressModal: true }).then(r => {
                if (r && r.ok && r.data) {
                    editAttScreenshotMasuk = r.data;
                    qs('#edit-att-screenshot-masuk-data').value = r.data;
                    qs('#edit-att-screenshot-masuk-img').src = r.data;
                    qs('#edit-att-screenshot-masuk-preview').classList.remove('hidden');
                }
            });
        }
        if (att.has_sp) {
            api('?ajax=get_attendance_evidence&id=' + att.id + '&type=pulang', {}, { suppressModal: true }).then(r => {
                if (r && r.ok && r.data) {
                    editAttScreenshotPulang = r.data;
                    qs('#edit-att-screenshot-pulang-data').value = r.data;
                    qs('#edit-att-screenshot-pulang-img').src = r.data;
                    qs('#edit-att-screenshot-pulang-preview').classList.remove('hidden');
                }
            });
        }
        
        editAttModal.classList.remove('hidden');
    }

    if(btnViewDr){
        const userId = btnViewDr.getAttribute('data-user'); const date = btnViewDr.getAttribute('data-date');
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date });
        const modal = qs('#dr-modal'); const content=qs('#dr-content'); const evalEl=qs('#dr-evaluation');
        modal.dataset.reportId = r?.data?.id || '';
        content.textContent = r?.data?.content || '(Belum ada laporan)';
        evalEl.value = r?.data?.evaluation || '';
        modal.classList.remove('hidden');
    }
    
        if(btnViewMonthDetail){
        const id = btnViewMonthDetail.getAttribute('data-id');
        const r = await api('?ajax=get_monthly_report_detail', { id });
        if(!r.ok) { showNotif(r.message || 'Laporan tidak ditemukan', false); return; }
        const item = r.data;
        if(!item) { showNotif('Laporan tidak ditemukan', false); return; }
        
        // Create modal if it doesn't exist
        let modal = qs('#monthly-detail-modal');
        if(!modal) {
            modal = document.createElement('div');
            modal.id = 'monthly-detail-modal';
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden';
            modal.innerHTML = `
                <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 id="monthly-detail-title" class="text-xl font-bold"></h3>
                        <button onclick="this.closest('#monthly-detail-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Ringkasan Pekerjaan:</h4>
                            <div class="bg-gray-50 p-3 rounded border">
                                <p id="monthly-detail-summary" class="text-gray-600 whitespace-pre-wrap"></p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Pencapaian dan Hasil Kerja:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Pencapaian</th>
                                            <th class="py-2 px-4">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-detail-achievements-table"></tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Kendala:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Kendala</th>
                                            <th class="py-2 px-4">Solusi</th>
                                            <th class="py-2 px-4">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-detail-obstacles-table"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const titleElement = qs('#monthly-detail-title');
        const summaryElement = qs('#monthly-detail-summary');
        
        if (titleElement) {
            titleElement.textContent = `Laporan Bulanan ${item.nama} - ${monthName(parseInt(item.month))} ${item.year}`;
        }
        if (summaryElement) {
            summaryElement.textContent = item.summary || '(Tidak ada ringkasan)';
        }
        
        // Parse achievements properly and fill table
        let achievements = [];
        try {
            achievements = JSON.parse(item.achievements || '[]');
        } catch (e) {
            achievements = [];
        }
        
        const achievementsTable = qs('#monthly-detail-achievements-table');
        if (achievementsTable) {
            if (achievements.length > 0) {
                achievementsTable.innerHTML = achievements.map((a, index) => {
                    const achievement = typeof a === 'object' ? (a.achievement || '') : a;
                    const detail = typeof a === 'object' ? (a.detail || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${achievement}</td>
                            <td class="py-2 px-4">${detail}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                achievementsTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="3" class="py-2 px-4 text-center text-gray-500">Tidak ada data pencapaian</td>
                    </tr>
                `;
            }
        }
        
        // Parse obstacles properly and fill table
        let obstacles = [];
        try {
            obstacles = JSON.parse(item.obstacles || '[]');
        } catch (e) {
            obstacles = [];
        }
        
        const obstaclesTable = qs('#monthly-detail-obstacles-table');
        if (obstaclesTable) {
            if (obstacles.length > 0) {
                obstaclesTable.innerHTML = obstacles.map((o, index) => {
                    const obstacle = typeof o === 'object' ? (o.obstacle || '') : o;
                    const solution = typeof o === 'object' ? (o.solution || '') : '';
                    const note = typeof o === 'object' ? (o.note || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${obstacle}</td>
                            <td class="py-2 px-4">${solution}</td>
                            <td class="py-2 px-4">${note}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                obstaclesTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="4" class="py-2 px-4 text-center text-gray-500">Tidak ada data kendala</td>
                </tr>
            `;
            }
        }
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    // Handle view monthly report for pegawai
    if(btnViewMonth){
        const data = JSON.parse(btnViewMonth.getAttribute('data-json').replace(/&apos;/g, "'"));
        if(!data) { showNotif('Data laporan tidak ditemukan', false); return; }
        
        // Create modal if it doesn't exist
        let modal = qs('#monthly-pegawai-view-modal');
        if(!modal) {
            modal = document.createElement('div');
            modal.id = 'monthly-pegawai-view-modal';
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden';
            modal.innerHTML = `
                <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 id="monthly-pegawai-view-title" class="text-xl font-bold"></h3>
                        <button onclick="this.closest('#monthly-pegawai-view-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-700 mb-2">Status Laporan:</h4>
                                <div id="monthly-pegawai-view-status" class="text-sm"></div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-700 mb-2">Tanggal Dibuat:</h4>
                                <div id="monthly-pegawai-view-created" class="text-sm text-gray-600"></div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Ringkasan Pekerjaan:</h4>
                            <div class="bg-gray-50 p-3 rounded border">
                                <p id="monthly-pegawai-view-summary" class="text-gray-600 whitespace-pre-wrap"></p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Pencapaian dan Hasil Kerja:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Pencapaian</th>
                                            <th class="py-2 px-4">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-pegawai-view-achievements-table"></tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Kendala:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Kendala</th>
                                            <th class="py-2 px-4">Solusi</th>
                                            <th class="py-2 px-4">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-pegawai-view-obstacles-table"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-6">
                            <button onclick="this.closest('#monthly-pegawai-view-modal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const monthName = (m) => ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][m-1];
        
        // Fill modal data
        const titleElement = qs('#monthly-pegawai-view-title');
        const statusElement = qs('#monthly-pegawai-view-status');
        const createdElement = qs('#monthly-pegawai-view-created');
        const summaryElement = qs('#monthly-pegawai-view-summary');
        
        if (titleElement) {
            titleElement.textContent = `Laporan Bulanan - ${monthName(parseInt(data.month))} ${data.year}`;
        }
        
        if (statusElement) {
            const statusMap = {
                'draft': '<span class="badge badge-gray">Draft</span>',
                'belum di approve': '<span class="badge badge-blue">Belum di Approve</span>',
                'approved': '<span class="badge badge-green">Di-approve</span>',
                'disapproved': '<span class="badge badge-red">Tidak di-approve</span>'
            };
            statusElement.innerHTML = statusMap[data.status] || '<span class="badge badge-gray">Unknown</span>';
        }
        
        if (createdElement) {
            const createdDate = new Date(data.created_at || data.updated_at);
            createdElement.textContent = createdDate.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        if (summaryElement) {
            summaryElement.textContent = data.summary || '(Tidak ada ringkasan)';
        }
        
        // Parse achievements and fill table
        let achievements = [];
        try {
            achievements = JSON.parse(data.achievements || '[]');
        } catch (e) {
            achievements = [];
        }
        
        const achievementsTable = qs('#monthly-pegawai-view-achievements-table');
        if (achievementsTable) {
            if (achievements.length > 0) {
                achievementsTable.innerHTML = achievements.map((a, index) => {
                    const achievement = typeof a === 'object' ? (a.achievement || '') : a;
                    const detail = typeof a === 'object' ? (a.detail || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${achievement}</td>
                            <td class="py-2 px-4">${detail}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                achievementsTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="3" class="py-2 px-4 text-center text-gray-500">Tidak ada data pencapaian</td>
                    </tr>
                `;
            }
        }
        
        // Parse obstacles and fill table
        let obstacles = [];
        try {
            obstacles = JSON.parse(data.obstacles || '[]');
        } catch (e) {
            obstacles = [];
        }
        
        const obstaclesTable = qs('#monthly-pegawai-view-obstacles-table');
        if (obstaclesTable) {
            if (obstacles.length > 0) {
                obstaclesTable.innerHTML = obstacles.map((o, index) => {
                    const obstacle = typeof o === 'object' ? (o.obstacle || '') : o;
                    const solution = typeof o === 'object' ? (o.solution || '') : '';
                    const note = typeof o === 'object' ? (o.note || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${obstacle}</td>
                            <td class="py-2 px-4">${solution}</td>
                            <td class="py-2 px-4">${note}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                obstaclesTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="4" class="py-2 px-4 text-center text-gray-500">Tidak ada data kendala</td>
                    </tr>
                `;
            }
        }
        
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    if(btnAmApprove){
        const id = btnAmApprove.getAttribute('data-id'); const status = 'approved';
        showConfirmModal('Yakin set status laporan bulanan?', async ()=>{ await api('?ajax=admin_set_monthly_status', { id, status }); renderAdminMonthly(); });
    }

    if(btnAmDisapprove){
        const id = btnAmDisapprove.getAttribute('data-id'); const status = 'disapproved';
        showConfirmModal('Yakin set status laporan bulanan?', async ()=>{ await api('?ajax=admin_set_monthly_status', { id, status }); renderAdminMonthly(); });
    }

    if(btnViewKet){
        const att = JSON.parse(btnViewKet.getAttribute('data-json').replace(/&apos;/g, "'"));
        const modal = qs('#ket-detail-modal');
        const title = qs('#ket-detail-title');
        const content = qs('#ket-detail-content');
        
        title.textContent = `Detail ${att.ket.toUpperCase()} - ${att.nama}`;
        
        if (att.ket === 'wfo' || att.ket === 'wfa') {
            // Show location map for WFO/WFA
            let mapContent = '';
            if (att.lat_masuk && att.lng_masuk && att.lokasi_masuk) {
                mapContent = `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Lokasi Presensi Masuk:</h4>
                        <p class="text-sm text-gray-600 mb-2">${att.lokasi_masuk}</p>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">
                                <strong>Koordinat:</strong> ${att.lat_masuk}, ${att.lng_masuk}
                            </div>
                            <a href="https://www.google.com/maps?q=${att.lat_masuk},${att.lng_masuk}" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                Buka di Google Maps
                            </a>
                        </div>
                    </div>
                `;
            }
            if (att.lat_pulang && att.lng_pulang && att.lokasi_pulang) {
                mapContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Lokasi Presensi Pulang:</h4>
                        <p class="text-sm text-gray-600 mb-2">${att.lokasi_pulang}</p>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">
                                <strong>Koordinat:</strong> ${att.lat_pulang}, ${att.lng_pulang}
                            </div>
                            <a href="https://www.google.com/maps?q=${att.lat_pulang},${att.lng_pulang}" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                Buka di Google Maps
                            </a>
                        </div>
                    </div>
                `;
            }
            if (att.ket === 'wfa' && att.alasan_wfa) {
                mapContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2 text-indigo-700"><i class="fi fi-rr-comment-info mr-2"></i>Alasan WFA:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-indigo-50/50 rounded-xl border border-indigo-100">${att.alasan_wfa}</p>
                    </div>
                `;
            }
            if (att.alasan_pulang_awal) {
                mapContent += `
                    <div class="mb-4 mt-4 pt-4 border-t border-gray-100">
                        <h4 class="font-semibold mb-2 text-rose-600"><i class="fi fi-rr-time-past mr-2"></i>Alasan Pulang Lebih Awal:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-rose-50/50 rounded-xl border border-rose-100 italic">"${att.alasan_pulang_awal}"</p>
                    </div>
                `;
            }
            if (att.alasan_lokasi_berbeda) {
                mapContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2 text-orange-600"><i class="fi fi-rr-map-marker-slash mr-2"></i>Alasan Lokasi Pulang Berbeda:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-orange-50/50 rounded-xl border border-orange-100 italic">"${att.alasan_lokasi_berbeda}"</p>
                    </div>
                `;
            }
            content.innerHTML = mapContent || '<p class="text-gray-500">Tidak ada data lokasi</p>';
        } else if (att.ket === 'overtime') {
            // Show location and reason for overtime
            let overtimeContent = '';
            if (att.lat_masuk && att.lng_masuk && att.lokasi_masuk) {
                overtimeContent = `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Lokasi Overtime:</h4>
                        <p class="text-sm text-gray-600 mb-2">${att.lokasi_overtime || att.lokasi_masuk}</p>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">
                                <strong>Koordinat:</strong> ${att.lat_masuk}, ${att.lng_masuk}
                            </div>
                            <a href="https://www.google.com/maps?q=${att.lat_masuk},${att.lng_masuk}" target="_blank" class="inline-block bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-sm">
                                Buka di Google Maps
                            </a>
                        </div>
                    </div>
                `;
            }
            if (att.alasan_overtime) {
                overtimeContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2 text-purple-700"><i class="fi fi-rr-comment-info mr-2"></i>Alasan Overtime:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-purple-50/50 rounded-xl border border-purple-100">${att.alasan_overtime}</p>
                    </div>
                `;
            }
            if (att.alasan_pulang_awal) {
                overtimeContent += `
                    <div class="mb-4 mt-4 pt-4 border-t border-gray-100">
                        <h4 class="font-semibold mb-2 text-rose-600"><i class="fi fi-rr-time-past mr-2"></i>Alasan Pulang Lebih Awal:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-rose-50/50 rounded-xl border border-rose-100 italic">"${att.alasan_pulang_awal}"</p>
                    </div>
                `;
            }
            if (att.alasan_lokasi_berbeda) {
                overtimeContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2 text-orange-600"><i class="fi fi-rr-map-marker-slash mr-2"></i>Alasan Lokasi Pulang Berbeda:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-orange-50/50 rounded-xl border border-orange-100 italic">"${att.alasan_lokasi_berbeda}"</p>
                    </div>
                `;
            }
            content.innerHTML = overtimeContent || '<p class="text-gray-500">Tidak ada data overtime</p>';
        } else if (att.ket === 'izin' || att.ket === 'sakit') {
            // Show proof and reason for izin/sakit
            let proofContent = '';
            if (att.has_bis || att.bukti_izin_sakit) {
                const proofId = att.id;
                const proofType = 'izin_sakit';
                proofContent = `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Bukti ${att.ket.toUpperCase()}:</h4>
                        <div class="flex justify-center" id="lazy-proof-container-${att.id}">
                            ${att.bukti_izin_sakit ? 
                                `<img src="${att.bukti_izin_sakit}" alt="Bukti ${att.ket}" class="max-w-full max-h-96 object-contain rounded border shadow-lg" style="max-width: 100%; height: auto;">` :
                                `<button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors" onclick="loadLazyProof('${att.id}', 'izin_sakit', 'lazy-proof-container-${att.id}')">
                                    <i class="fi fi-rr-picture mr-2"></i> Lihat Bukti Gambar
                                </button>`
                            }
                        </div>
                    </div>
                `;
            }
            if (att.alasan_izin_sakit) {
                proofContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2 text-gray-700"><i class="fi fi-rr-comment-info mr-2"></i>Keterangan:</h4>
                        <p class="text-sm text-gray-600 p-4 bg-gray-50 rounded-xl border border-gray-100">${att.alasan_izin_sakit}</p>
                    </div>
                `;
            }
            if (att.alasan_pulang_awal) {
                proofContent += `
                    <div class="mb-4 mt-4 pt-4 border-t border-gray-100">
                        <h4 class="font-semibold mb-2 text-rose-600"><i class="fi fi-rr-time-past mr-2"></i>Alasan Pulang Lebih Awal:</h4>
                        <p class="text-sm text-gray-700 p-4 bg-rose-50/50 rounded-xl border border-rose-100 italic">"${att.alasan_pulang_awal}"</p>
                    </div>
                `;
            }
            content.innerHTML = proofContent || '<p class="text-gray-500">Tidak ada bukti</p>';
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
});

memberForm && memberForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id = qs('#member-id').value;
    const foto = fotoDataUrlInput.value;
    const payload = {
        id,
        email: qs('#email').value,
        nim: qs('#nim').value,
        nama: qs('#nama').value,
        prodi: qs('#prodi').value,
        startup: qs('#startup').value,
        foto: foto,
    };
    if(!id){ payload.password = qs('#password-new').value; const confirm = qs('#password-confirm').value; if(!payload.password || payload.password!==confirm){ showNotif('Password admin untuk member baru wajib dan harus cocok'); return; } }
    
    // If photo is modified/supplied, compute face embedding & landmarks on the client
    if (foto && (foto.startsWith('data:image/') || foto.startsWith('blob:'))) {
        showNotif('Memproses foto & memverifikasi wajah... Mohon tunggu.', true);
        try {
            // Ensure face-api models are loaded
            if (!window.faceApiModelsLoaded) {
                const MODEL_PATH = window.FACEAPI_MODEL_URL || 'assets/face-models';
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_PATH),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH)
                ]);
                window.faceApiModelsLoaded = true;
            }
            
            const img = await faceapi.fetchImage(foto);
            const detection = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 }))
                .withFaceLandmarks().withFaceDescriptor();
                
            if (!detection) {
                if (typeof showModalNotif === 'function') {
                    showModalNotif('Wajah tidak terdeteksi pada foto! Pastikan wajah terlihat jelas, tegak lurus, dan pencahayaannya cukup.', false, 'Verifikasi Gagal');
                } else {
                    alert('Wajah tidak terdeteksi pada foto! Silakan gunakan foto lain.');
                }
                return;
            }
            
            // Normalize landmarks relative to face bounding box
            const box = detection.detection.box;
            const normLandmarks = detection.landmarks.positions.map(p => ({
                x: parseFloat(((p.x - box.x) / box.width).toFixed(4)),
                y: parseFloat(((p.y - box.y) / box.height).toFixed(4))
            }));
            
            payload.embedding = JSON.stringify(Array.from(detection.descriptor));
            payload.landmarks = JSON.stringify(normLandmarks);
            showNotif('Wajah terdeteksi dan data wajah berhasil dihitung!', true);
        } catch (err) {
            console.error('Error pre-computing face embedding:', err);
            showNotif('Gagal memproses data wajah. Menyimpan tanpa data wajah...', false);
        }
    }
    
    const r = await api('?ajax=save_member', payload);
    if(r.ok){ 
        renderMembers(); 
        if (typeof loadLabeledFaceDescriptors === 'function') {
            loadLabeledFaceDescriptors(); 
        }
        stopModalCamera(); 
        memberModal.classList.add('hidden'); 
    } else { 
        showNotif(r.message||'Gagal menyimpan'); 
    }
});

// Load startup options for filter
async function loadStartupOptions() {
    const filterStartup = qs('#filter-startup');
    if (filterStartup && filterStartup.options.length <= 1) {
        const res = await fetch('?ajax=get_startups');
        const j = await res.json();
        if (j.ok && j.data) {
            j.data.forEach(startup => {
                const o = document.createElement('option');
                o.value = startup;
                o.textContent = startup;
                filterStartup.appendChild(o);
            });
        }
    }
}


// Laporan
async function renderLaporan(){
    const tglMulai = qs('#filter-tanggal-mulai')?.value || '';
    const tglSelesai = qs('#filter-tanggal-selesai')?.value || '';
    let url = '?ajax=get_attendance&limit=1000';
    if (tglMulai) url += '&start_date=' + tglMulai;
    if (tglSelesai) url += '&end_date=' + tglSelesai;
    const j = await api(url, {}, { suppressModal: true, cache: false });
    const list = (j.data||[]);
    const term = (qs('#search-laporan')?.value||'').toLowerCase();
    const startupFilter = qs('#filter-startup')?.value || '';
    const sortBy = qs('#sort-presensi')?.value || 'tanggal-desc';
    
    // NEW: Get new filter values
    const statusFilter = qs('#filter-status')?.value || '';
    const ketFilter = qs('#filter-ket')?.value || '';
    const laporanFilter = qs('#filter-laporan')?.value || '';
    
    // NEW: Check if showing today only (using 5 AM reset)
    const btnToggleToday = qs('#btn-toggle-today');
    const showTodayOnly = btnToggleToday && btnToggleToday.textContent.includes('Hari Ini');
    
    // Calculate "today" with 5 AM reset (not midnight)
    const now = new Date();
    const currentHour = now.getHours();
    let todayDate;
    if (currentHour < 5) {
        // Before 5 AM = still yesterday
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        todayDate = yesterday.toISOString().slice(0, 10);
    } else {
        todayDate = now.toISOString().slice(0, 10);
    }
    
    const filtered = list.filter(a=>{
        const nameMatch = (a.nama||'').toLowerCase().includes(term);
        const nimMatch = (a.nim||'').toLowerCase().includes(term);
        const startupMatch = !startupFilter || (a.startup||'') === startupFilter;
        const recordDate = a.jam_masuk_iso ? a.jam_masuk_iso.slice(0,10) : '';
        const dateMatch = (!tglMulai || recordDate>=tglMulai) && (!tglSelesai || recordDate<=tglSelesai);
        
        // NEW: Today filter (5 AM reset)
        const todayMatch = !showTodayOnly || recordDate === todayDate;
        
        // NEW: Status filter
        const statusMatch = !statusFilter || (a.status||'').toLowerCase() === statusFilter.toLowerCase();
        
        // NEW: Ket filter
        const ketMatch = !ketFilter || (a.ket||'').toLowerCase() === ketFilter.toLowerCase();
        
        // NEW: Laporan filter
        let laporanMatch = true;
        if (laporanFilter === 'belum-ada') {
            laporanMatch = !a.daily_report_status || a.daily_report_status === '';
        } else if (laporanFilter === 'pending') {
            laporanMatch = a.daily_report_status === 'pending' || a.daily_report_status === 'disapproved';
        } else if (laporanFilter === 'approved') {
            laporanMatch = a.daily_report_status === 'approved';
        }
        
        return (nameMatch||nimMatch) && startupMatch && dateMatch && todayMatch && statusMatch && ketMatch && laporanMatch;
    });
    
    // Sorting
    filtered.sort((a,b) => {
        switch(sortBy) {
            case 'tanggal-asc':
                return new Date(a.jam_masuk_iso||0) - new Date(b.jam_masuk_iso||0);
            case 'tanggal-desc':
                return new Date(b.jam_masuk_iso||0) - new Date(a.jam_masuk_iso||0);
            case 'jam-masuk-asc':
                return (a.jam_masuk||'').localeCompare(b.jam_masuk||'');
            case 'jam-masuk-desc':
                return (b.jam_masuk||'').localeCompare(a.jam_masuk||'');
            case 'nama-asc':
                return (a.nama||'').localeCompare(b.nama||'');
            case 'nama-desc':
                return (b.nama||'').localeCompare(a.nama||'');
            default:
                return new Date(b.jam_masuk_iso||0) - new Date(a.jam_masuk_iso||0);
        }
    });
    
        renderPaginatedTable('table-laporan-body', filtered, (att) => {
        const d = new Date(att.jam_masuk_iso);
        const tanggal = isNaN(d.getTime()) ? '-' : d.toLocaleDateString('id-ID', { year:'numeric', month:'long', day:'numeric'});
        const statusClass = att.status === 'terlambat' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
        const statusText = att.status === 'terlambat' ? 'Terlambat' : 'On Time';

        let dailyReportStatus = 'Belum ada laporan';
        let dailyReportClass = 'badge-orange';
        if(att.daily_report_status) {
            dailyReportStatus = att.daily_report_status === 'approved' ? 'Sudah di-approve' : (att.daily_report_status === 'disapproved' ? 'Tidak di-approve' : 'Belum di-approve');
            dailyReportClass = att.daily_report_status === 'approved' ? 'badge-green' : (att.daily_report_status === 'disapproved' ? 'badge-red' : 'badge-blue');
        }

        const tr = document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        
        const formatTime = (timeStr) => {
            if (!timeStr || timeStr === '-') return '-';
            if (timeStr === 'izin' || timeStr === 'sakit' || timeStr === 'wfa') return timeStr;
            return timeStr.substring(0, 5);
        };
        
        const jamMasuk = formatTime(att.jam_masuk);
        const jamPulang = formatTime(att.jam_pulang);
        
        const createBuktiDisplay = (attId, hasLandmarkFlag, landmarkData, fotoData, ekspresi, mode, attKet, dateIso, timeValue) => {
            if (!timeValue || timeValue === '-') {
                return '<div class="text-center text-gray-400">-</div>';
            }
            
            const isExpired = !isWithin10WorkingDays(dateIso);
            const label = translateExpression(ekspresi || 'neutral');

            if (isExpired) {
                return `<div class="text-center">
                    <button type="button" 
                        class="bg-gray-100 hover:bg-gray-200 text-gray-500 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase transition-all shadow-sm border border-gray-200 cursor-pointer"
                        onclick="showExpiredModal()"
                        title="Foto sudah dihapus (>10 hari kerja)">
                        ${label}
                    </button>
                </div>`;
            }

            if (attKet === 'izin' || attKet === 'sakit') {
                return `<div class="text-center text-gray-400 text-xs italic bg-gray-50 py-1 rounded-lg border border-dashed border-gray-200">Izin/Sakit</div>`;
            }

            if (fotoData && typeof fotoData === 'string' && fotoData.length > 10) {
                let imgSrc;
                if (fotoData.startsWith('data:image/')) {
                    imgSrc = fotoData;
                } else if (fotoData.startsWith('public/')) {
                    imgSrc = '/' + fotoData.substring(7);
                } else if (fotoData.startsWith('storage/')) {
                    imgSrc = '/' + fotoData;
                } else if (fotoData.startsWith('attendance/')) {
                    imgSrc = '/storage/' + fotoData;
                } else {
                    const cleanFoto = fotoData.trim();
                    if (cleanFoto === '' || cleanFoto === 'attendance/') {
                        return `<div class="text-center text-gray-400">-</div>`;
                    }
                    imgSrc = '/storage/attendance/' + cleanFoto;
                }
                
                return `<div class="flex justify-center">
                    <img src="${imgSrc}" 
                        class="w-12 h-10 object-cover rounded-lg border border-gray-200 shadow-sm cursor-pointer hover:scale-110 transition-transform" 
                        onclick="showScreenshotModal('${imgSrc}', 'Bukti ${mode === 'masuk' ? 'Masuk' : 'Pulang'}')"
                        onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=Err&background=fee2e2&color=ef4444';">
                </div>`;
            }

            if (hasLandmarkFlag) {
                const containerId = `proof-${mode}-${attId}`;
                const type = mode === 'masuk' ? 'masuk' : 'pulang';
                const html = `<div id="${containerId}" data-id="${attId}" data-type="${type}" class="lazy-evidence text-center w-12 h-10 mx-auto bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden cursor-pointer border border-gray-200 shadow-sm" title="Klik untuk memperbesar">
                    <i class="fi fi-rr-spinner animate-spin text-gray-400 text-[10px]"></i>
                </div>`;
                
                setTimeout(() => {
                    const el = document.getElementById(containerId);
                    if (el) {
                        if (window.evidenceObserver) window.evidenceObserver.observe(el);
                        else if (window.loadLazyProof) window.loadLazyProof(attId, type, containerId);
                    }
                }, 100);
                
                return html;
            }

            return `<div class="flex justify-center">
                <button type="button" 
                    class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-2 py-1 rounded-lg text-[10px] font-semibold uppercase transition-all shadow-sm border border-blue-200 cursor-pointer"
                    onclick="${isExpired ? 'showExpiredModal()' : ''}">
                    ${label}
                </button>
            </div>`;
        };
        
        const buktiMasuk  = createBuktiDisplay(att.id, att.has_sm, att.landmark_masuk, (att.foto_masuk || att.screenshot_masuk), att.ekspresi_masuk, 'masuk', att.ket, att.jam_masuk_iso, jamMasuk);
        const buktiPulang = createBuktiDisplay(att.id, att.has_sp, att.landmark_pulang, (att.foto_pulang || att.screenshot_pulang), att.ekspresi_pulang, 'pulang', att.ket, att.jam_pulang_iso || att.jam_masuk_iso, jamPulang);
        
        let ketButton = '';
        if (att.ket && (att.ket === 'wfo' || att.ket === 'wfa' || att.ket === 'izin' || att.ket === 'sakit' || att.ket === 'overtime')) {
            const ketColors = {
                'wfo': 'bg-green-500 hover:bg-green-600 text-white',
                'wfa': 'bg-amber-400 hover:bg-amber-500 text-white', 
                'izin': 'bg-yellow-500 hover:bg-yellow-600 text-white',
                'sakit': 'bg-yellow-500 hover:bg-yellow-600 text-white',
                'overtime': 'bg-orange-500 hover:bg-orange-600 text-white'
            };
            const colorClass = ketColors[att.ket] || 'bg-gray-500 hover:bg-gray-600 text-white';
            ketButton = `<button class="btn-view-ket ${colorClass} px-2 py-1 rounded-full text-xs font-medium transition-colors duration-200" data-json='${JSON.stringify(att).replace(/'/g,"&apos;")}' title="Lihat Detail ${att.ket.toUpperCase()}">${att.ket.toUpperCase()}</button>`;
        } else {
            ketButton = '<span class="text-gray-400">-</span>';
        }

        tr.innerHTML = `
            <td class="py-2 px-4">${tanggal}</td>
            <td class="py-2 px-4">${att.nim||''}</td>
            <td class="py-2 px-4">${att.nama||''}</td>
            <td class="py-2 px-4">${att.startup||'-'}</td>
            <td class="py-2 px-4">${jamMasuk}</td>
            <td class="py-2 px-4">${buktiMasuk}</td>
            <td class="py-2 px-4"><span class="badge ${statusClass}">${statusText}</span></td>
            <td class="py-2 px-4">${ketButton}</td>
            <td class="py-2 px-4">${jamPulang}</td>
            <td class="py-2 px-4">${buktiPulang}</td>
            <td class="py-2 px-4"><span class="badge ${dailyReportClass}">${dailyReportStatus}</span></td>
            <td class="py-2 px-4">
                <button title="Lihat Laporan" class="btn-view-dr-admin text-blue-600 font-bold" data-user="${att.user_id}" data-date="${(att.jam_masuk_iso||'').slice(0,10)}"><i class="fi fi-ss-eye"></i></button>
                <button title="Edit" class="btn-edit-att text-yellow-600 font-bold ml-1" data-json='${JSON.stringify(att).replace(/'/g,"&apos;")} '><i class="fi fi-sr-pen-square"></i></button>
                <button title="Hapus" class="btn-delete-laporan text-red-600 font-bold ml-1" data-id="${att.id}"><i class="fi fi-ss-trash"></i></button>
            </td>`;

        if (att.landmark_masuk) {
            setTimeout(() => {
                const cMasuk = document.getElementById(`lm-thumb-${att.id}-masuk`);
                if (cMasuk) {
                    renderLandmarkOnCanvas(cMasuk, att.landmark_masuk, 80, 60);
                    cMasuk._lmData = att.landmark_masuk;
                }
            }, 0);
        }
        if (att.landmark_pulang) {
            setTimeout(() => {
                const cPulang = document.getElementById(`lm-thumb-${att.id}-pulang`);
                if (cPulang) {
                    renderLandmarkOnCanvas(cPulang, att.landmark_pulang, 80, 60);
                    cPulang._lmData = att.landmark_pulang;
                }
            }, 0);
        }
        return tr;
    }, {
        colSpan: 12,
        emptyMessage: 'Tidak ada data kehadiran.',
        onPageChange: renderLaporan
    });
}

[qs('#search-laporan'), qs('#filter-startup'), qs('#filter-tanggal-mulai'), qs('#filter-tanggal-selesai'), qs('#sort-presensi'), qs('#filter-status'), qs('#filter-ket'), qs('#filter-laporan')].forEach(el=>{ if(el) el.addEventListener('input', () => { resetTablePage('table-laporan-body'); renderLaporan(); }); });

// NEW: Toggle today/all button
qs('#btn-toggle-today') && qs('#btn-toggle-today').addEventListener('click', function() {
    const btn = this;
    if (btn.textContent.includes('Hari Ini')) {
        btn.textContent = '📊 Lihat Semua';
        btn.classList.remove('bg-indigo-500', 'hover:bg-indigo-600');
        btn.classList.add('bg-purple-500', 'hover:bg-purple-600');
    } else {
        btn.textContent = '📅 Hari Ini';
        btn.classList.remove('bg-purple-500', 'hover:bg-purple-600');
        btn.classList.add('bg-indigo-500', 'hover:bg-indigo-600');
    }
    renderLaporan();
});


qs('#btn-show-all') && qs('#btn-show-all').addEventListener('click', ()=>{
    if(qs('#search-laporan')) qs('#search-laporan').value = '';
    if(qs('#filter-startup')) qs('#filter-startup').value = '';
    if(qs('#filter-tanggal-mulai')) qs('#filter-tanggal-mulai').value = '';
    if(qs('#filter-tanggal-selesai')) qs('#filter-tanggal-selesai').value = '';
    if(qs('#sort-presensi')) qs('#sort-presensi').value = 'tanggal-desc';
    renderLaporan();
});

// Absence modal handlers
let selectedUsers = new Set();
let allMembers = [];

function renderSelectedUsers() {
    const container = qs('#abs-selected-container');
    const list = qs('#abs-items-list');
    const configSection = qs('#abs-config-section');
    const countBadge = qs('#abs-count-badge');
    
    if (!container || !list) return;

    container.innerHTML = '';
    list.innerHTML = '';
    
    if (selectedUsers.size === 0) {
        container.innerHTML = '<p class="text-xs text-gray-400 italic w-full text-center py-2">Belum ada pegawai yang dipilih</p>';
        if (configSection) configSection.classList.add('hidden');
        if (countBadge) countBadge.classList.add('hidden');
        return;
    }
    
    if (configSection) configSection.classList.remove('hidden');
    if (countBadge) {
        countBadge.textContent = `${selectedUsers.size} pegawai dipilih`;
        countBadge.classList.remove('hidden');
    }

    const isGlobalAuto = qs('#abs-auto-time') ? qs('#abs-auto-time').checked : true;

    selectedUsers.forEach(userId => {
        const member = allMembers.find(m => m.id == userId);
        if (member) {
            const chip = document.createElement('div');
            chip.className = 'bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-medium flex items-center gap-1 border border-indigo-100 animate-fade-in-up';
            chip.innerHTML = `
                <span>${member.nama}</span>
                <button type="button" class="abs-remove-user hover:text-red-500 transition-colors" data-id="${userId}">
                    <i class="fi fi-rr-cross-small"></i>
                </button>
            `;
            container.appendChild(chip);

            const row = document.createElement('div');
            row.className = 'abs-user-item bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all space-y-3';
            row.dataset.userId = userId;
            row.innerHTML = `
                <div class="flex items-center justify-between border-b border-gray-50 pb-2 mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs uppercase">${member.nama.charAt(0)}</div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 leading-none">${member.nama}</p>
                            <p class="text-[10px] text-gray-400 uppercase font-bold mt-1">${member.nim}</p>
                        </div>
                    </div>
                    <select class="item-type text-[10px] font-bold uppercase px-2 py-1 bg-gray-100 border-none rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="wfo">WFO</option>
                        <option value="wfa">WFA</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                        <option value="overtime">Overtime</option>
                    </select>
                </div>

                <div class="item-time-toggle flex items-center gap-2 px-1">
                    <input type="checkbox" class="item-auto-time rounded w-3 h-3 text-indigo-600" ${isGlobalAuto ? 'checked' : ''}>
                    <span class="text-[9px] font-bold text-gray-500 uppercase">Set Jam Otomatis (Default)</span>
                </div>
                
                <div class="item-times grid grid-cols-2 gap-3 hidden">
                    <div>
                        <label class="block text-[9px] font-bold text-gray-400 uppercase mb-1">Jam Masuk</label>
                        <input type="time" class="item-jam-masuk w-full px-3 py-1.5 border border-gray-200 rounded-lg text-xs" value="08:00">
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-gray-400 uppercase mb-1">Jam Pulang</label>
                        <input type="time" class="item-jam-pulang w-full px-3 py-1.5 border border-gray-200 rounded-lg text-xs" value="17:00">
                    </div>
                </div>

                <div class="item-extra space-y-2">
                    <div>
                        <label class="block text-[9px] font-bold text-gray-400 uppercase mb-1 item-reason-label">Keterangan</label>
                        <textarea class="item-reason w-full px-3 py-2 border border-gray-200 rounded-lg text-xs resize-none" rows="1" placeholder="Opsional..."></textarea>
                    </div>
                    <div class="item-location-wrapper hidden">
                        <label class="block text-[9px] font-bold text-gray-400 uppercase mb-1">Lokasi Overtime</label>
                        <input type="text" class="item-location w-full px-3 py-1.5 border border-gray-200 rounded-lg text-xs" placeholder="Lokasi...">
                    </div>
                </div>
            `;
            
            const typeSelect = row.querySelector('.item-type');
            const autoTimeCheck = row.querySelector('.item-auto-time');
            const timeFields = row.querySelector('.item-times');
            const timeToggle = row.querySelector('.item-time-toggle');
            
            const updateUI = () => {
                const type = typeSelect.value;
                const isAuto = autoTimeCheck.checked;
                const reasonLabel = row.querySelector('.item-reason-label');
                const locationWrapper = row.querySelector('.item-location-wrapper');

                // Time fields logic
                if (type === 'izin' || type === 'sakit') {
                    timeToggle.classList.add('hidden');
                    timeFields.classList.add('hidden');
                } else {
                    timeToggle.classList.remove('hidden');
                    if (isAuto) {
                        timeFields.classList.add('hidden');
                    } else {
                        timeFields.classList.remove('hidden');
                    }
                }
                
                if (type === 'overtime') {
                    locationWrapper.classList.remove('hidden');
                    reasonLabel.textContent = 'Alasan Overtime';
                } else if (type === 'wfa') {
                    locationWrapper.classList.add('hidden');
                    reasonLabel.textContent = 'Alasan WFA';
                } else if (type === 'wfo') {
                    locationWrapper.classList.add('hidden');
                    reasonLabel.textContent = 'Keterangan WFO';
                } else {
                    locationWrapper.classList.add('hidden');
                    reasonLabel.textContent = 'Alasan Izin/Sakit';
                }
            };
            
            typeSelect.onchange = updateUI;
            autoTimeCheck.onchange = updateUI;
            updateUI();
            list.appendChild(row);
        }
    });
}

document.addEventListener('click', (e) => {
    if (e.target.closest('.abs-remove-user')) {
        const id = e.target.closest('.abs-remove-user').dataset.id;
        selectedUsers.delete(id);
        renderSelectedUsers();
        if (qs('#abs-select-all')) qs('#abs-select-all').checked = false;
    }
});

qs('#btn-open-absence') && qs('#btn-open-absence').addEventListener('click', async ()=>{
    const modal = qs('#absence-modal');
    const search = qs('#abs-search');
    const results = qs('#abs-search-results');
    
    selectedUsers.clear();
    renderSelectedUsers();
    if (qs('#abs-select-all')) qs('#abs-select-all').checked = false;
    if (search) {
        search.value = '';
        if (results) results.classList.add('hidden');
    }

    const r = await fetch('?ajax=get_members&light=1&no_embeddings=1'); const j = await r.json(); 
    allMembers = (j.data||[]);
    
    const fill = (term='')=>{ 
        if (!results) return;
        const filtered = allMembers.filter(m=> 
            (m.nama||'').toLowerCase().includes(term) || 
            (m.nim||'').toLowerCase().includes(term)
        ).slice(0, 30);

        if (filtered.length === 0) {
            results.innerHTML = '<div class="p-4 text-xs text-gray-400 text-center">Tidak ada hasil ditemukan</div>';
        } else {
            results.innerHTML = filtered.map(m => `
                <div class="abs-search-item flex items-center justify-between px-4 py-3 hover:bg-indigo-50/50 cursor-pointer transition-all border-b border-gray-50 last:border-0 ${selectedUsers.has(m.id.toString()) ? 'bg-indigo-50' : ''}" data-id="${m.id}">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            ${(m.nama||'').charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">${m.nama}</p>
                            <p class="text-[10px] text-gray-400 font-bold uppercase">${m.nim}</p>
                        </div>
                    </div>
                    <div class="checkbox-ui w-5 h-5 rounded-full border-2 ${selectedUsers.has(m.id.toString()) ? 'bg-indigo-600 border-indigo-600' : 'border-gray-200'} flex items-center justify-center transition-all">
                        ${selectedUsers.has(m.id.toString()) ? '<i class="fi fi-rr-check text-[10px] text-white"></i>' : ''}
                    </div>
                </div>
            `).join('');
        }
        results.classList.remove('hidden');
    };

    if (search) {
        search.oninput = () => fill(search.value.toLowerCase());
        search.onfocus = () => fill(search.value.toLowerCase());
    }
    
    modal.classList.remove('hidden');
});

// Handle clicking search results
document.addEventListener('click', (e) => {
    const item = e.target.closest('.abs-search-item');
    if (item) {
        const id = item.dataset.id.toString();
        if (selectedUsers.has(id)) {
            selectedUsers.delete(id);
        } else {
            selectedUsers.add(id);
        }
        renderSelectedUsers();
        // Refresh search list UI
        const search = qs('#abs-search');
        if (search) {
            const results = qs('#abs-search-results');
            const filtered = allMembers.filter(m=> 
                (m.nama||'').toLowerCase().includes(search.value.toLowerCase()) || 
                (m.nim||'').toLowerCase().includes(search.value.toLowerCase())
            ).slice(0, 30);
            
            // Just update the checkboxes in the results list without full re-render if possible, 
            // but full re-render is safer for now.
            const fill = (term='')=>{ 
                const results = qs('#abs-search-results');
                if (!results) return;
                const filtered = allMembers.filter(m=> (m.nama||'').toLowerCase().includes(term) || (m.nim||'').toLowerCase().includes(term)).slice(0, 30);
                results.innerHTML = filtered.map(m => `
                    <div class="abs-search-item flex items-center justify-between px-4 py-3 hover:bg-indigo-50/50 cursor-pointer transition-all border-b border-gray-50 last:border-0 ${selectedUsers.has(m.id.toString()) ? 'bg-indigo-50' : ''}" data-id="${m.id}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs">${(m.nama||'').charAt(0).toUpperCase()}</div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">${m.nama}</p>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">${m.nim}</p>
                            </div>
                        </div>
                        <div class="checkbox-ui w-5 h-5 rounded-full border-2 ${selectedUsers.has(m.id.toString()) ? 'bg-indigo-600 border-indigo-600' : 'border-gray-200'} flex items-center justify-center transition-all">
                            ${selectedUsers.has(m.id.toString()) ? '<i class="fi fi-rr-check text-[10px] text-white"></i>' : ''}
                        </div>
                    </div>
                `).join('');
            };
            fill(search.value.toLowerCase());
        }
        if (qs('#abs-select-all')) qs('#abs-select-all').checked = false;
        return;
    }

    // Hide search results when clicking outside
    const searchArea = e.target.closest('.group.relative');
    if (!searchArea) {
        const results = qs('#abs-search-results');
        if (results) results.classList.add('hidden');
    }
});

qs('#abs-select-all') && qs('#abs-select-all').addEventListener('change', (e) => {
    if (e.target.checked) {
        allMembers.forEach(m => selectedUsers.add(m.id.toString()));
    } else {
        selectedUsers.clear();
    }
    renderSelectedUsers();
    // Close search results if open
    const results = qs('#abs-search-results');
    if (results) results.classList.add('hidden');
});

qs('#abs-clear-selection') && qs('#abs-clear-selection').addEventListener('click', () => {
    selectedUsers.clear();
    renderSelectedUsers();
    if (qs('#abs-select-all')) qs('#abs-select-all').checked = false;
    const results = qs('#abs-search-results');
    if (results) results.classList.add('hidden');
});

// Sync global config to all rows
qs('#abs-apply-global') && qs('#abs-apply-global').addEventListener('click', () => {
    const type = qs('#abs-type').value;
    const isAuto = qs('#abs-auto-time').checked;
    const items = qsa('.abs-user-item');
    items.forEach(row => {
        const typeSelect = row.querySelector('.item-type');
        const autoCheck = row.querySelector('.item-auto-time');
        if (typeSelect) typeSelect.value = type;
        if (autoCheck) autoCheck.checked = isAuto;
        if (typeSelect) typeSelect.dispatchEvent(new Event('change'));
    });
});

// Handle global auto-time toggle
qs('#abs-auto-time') && qs('#abs-auto-time').addEventListener('change', (e) => {
    const items = qsa('.abs-user-item');
    items.forEach(row => {
        const autoCheck = row.querySelector('.item-auto-time');
        if (autoCheck) {
            autoCheck.checked = e.target.checked;
            autoCheck.dispatchEvent(new Event('change'));
        }
    });
});
// Manual holidays handlers
qs('#btn-manual-holidays') && qs('#btn-manual-holidays').addEventListener('click', async ()=>{
    await renderManualHolidays();
    qs('#manual-holidays-modal').classList.remove('hidden');
});
qs('#mh-close') && qs('#mh-close').addEventListener('click', ()=> qs('#manual-holidays-modal').classList.add('hidden'));

async function renderManualHolidays(){
    const start = new Date(new Date().getFullYear(),0,1).toISOString().slice(0,10);
    const end = new Date(new Date().getFullYear(),11,31).toISOString().slice(0,10);
    const r = await fetch(`?ajax=admin_get_manual_holidays&start=${start}&end=${end}`);
    const j = await r.json();
    const list = j.data||[];
    const tbodyId = qs('#mh-body') ? 'mh-body' : 'mh-table-body';
    
    renderPaginatedTable(tbodyId, list, (it) => {
        const tr=document.createElement('tr'); tr.className='border-b';
        tr.innerHTML = `<td class="py-2 px-3">${it.date}</td><td class="py-2 px-3">${it.name}</td><td class="py-2 px-3 text-center"><button class="mh-del bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded" data-id="${it.id}">Hapus</button></td>`;
        return tr;
    }, {
        colSpan: 3,
        emptyMessage: 'Belum ada data.',
        onPageChange: renderManualHolidays
    });
}

// Global handler for Bulk Fix Jam Pulang
document.addEventListener('click', async (e) => {
    // Check for Bulk Fix button
    const bulkFixBtn = e.target.closest('#btn-bulk-fix-checkout');
    if (bulkFixBtn) {
        // Get date from filter or default to today
        const dateInput = document.getElementById('filter-tanggal-mulai');
        const date = (dateInput && dateInput.value) ? dateInput.value : new Date().toISOString().split('T')[0];
        
        const confirmed = await customConfirm(`Anda yakin ingin mengisi jam pulang kosong untuk SEMUA data pegawai yang belum clock-out (keseluruhan data)?`, 'Konfirmasi Bulk Fix Global');
        if (!confirmed) return;
        
        bulkFixBtn.disabled = true;
        const originalContent = bulkFixBtn.innerHTML;
        bulkFixBtn.innerHTML = '<i class="fi fi-sr-spinner animate-spin"></i> Processing...';
        
        try {
            const res = await api('?ajax=admin_bulk_fix_empty_checkout', { date: date });
            if (res.ok) {
                showNotif(res.message || 'Berhasil memperbarui data', true);
                // Refresh table if on Laporan page
                if (typeof renderLaporan === 'function') renderLaporan();
            } else {
                showNotif(res.message || 'Gagal memperbarui data', false);
            }
        } catch (error) {
            console.error('Bulk fix error:', error);
            showNotif('Terjadi kesalahan sistem', false);
        } finally {
            bulkFixBtn.disabled = false;
            bulkFixBtn.innerHTML = originalContent;
        }
        return; // Handled
    }

    if(e.target && e.target.id==='mh-add'){
        const date = qs('#mh-date').value; const name = qs('#mh-name').value.trim();
        if(!date || !name){ showNotif('Isi tanggal dan keterangan', false); return; }
        
        try {
        const r = await api('?ajax=admin_add_manual_holiday', { date, name });
            if(r.ok){ 
                await renderManualHolidays(); 
                qs('#mh-name').value='';
                showNotif('Hari libur berhasil ditambahkan', true);
            } else {
                showNotif(r.message || 'Gagal menambahkan hari libur', false);
                console.error('API Error:', r);
            }
        } catch (error) {
            showNotif('Terjadi kesalahan: ' + error.message, false);
            console.error('Error adding manual holiday:', error);
        }
    }
    if(e.target && e.target.classList.contains('mh-del')){
        const id = e.target.getAttribute('data-id');
        showConfirmModal('Hapus hari libur ini?', async ()=>{ await api('?ajax=admin_delete_manual_holiday', { id }); await renderManualHolidays(); });
    }
});
qs('#abs-cancel') && qs('#abs-cancel').addEventListener('click', ()=> qs('#absence-modal').classList.add('hidden'));
// Add event listener for abs-type change
document.addEventListener('change', (e) => {
    if (e.target.id === 'abs-type') {
        const wfaForm = qs('#abs-wfa-form');
        const overtimeForm = qs('#abs-overtime-form');
        const type = e.target.value;
        
        // Hide all forms first
        wfaForm.classList.add('hidden');
        overtimeForm.classList.add('hidden');
        
        // Show appropriate form based on type
        if (type === 'wfa') {
            wfaForm.classList.remove('hidden');
        } else if (type === 'overtime') {
            overtimeForm.classList.remove('hidden');
        }
    }
});

qs('#abs-save') && qs('#abs-save').addEventListener('click', async ()=>{
    if (selectedUsers.size === 0) {
        showNotif('Pilih minimal satu pegawai', false);
        return;
    }

    const date = qs('#abs-date').value;
    const items = qsa('.abs-user-item');
    const bulk_data = [];
    
    let isValid = true;
    items.forEach(row => {
        const userId = row.dataset.userId;
        const typeSelect = row.querySelector('.item-type');
        const type = typeSelect ? typeSelect.value : 'wfo';
        const isAuto = row.querySelector('.item-auto-time').checked;
        const jam_masuk = row.querySelector('.item-jam-masuk').value;
        const jam_pulang = row.querySelector('.item-jam-pulang').value;
        const alasan = row.querySelector('.item-reason').value.trim();
        const lokasi = row.querySelector('.item-location').value.trim();
        
        // Basic validation for WFA/Overtime/WFO when NOT auto
        const needsTime = ['wfo', 'wfa', 'overtime'].includes(type);
        if (needsTime && !isAuto && (!jam_masuk || !jam_pulang)) {
            isValid = false;
            row.classList.add('ring-2', 'ring-red-500');
            setTimeout(() => row.classList.remove('ring-2', 'ring-red-500'), 3000);
        }
        
        bulk_data.push({
            user_id: userId,
            type: type,
            jam_masuk: (needsTime && !isAuto) ? jam_masuk : null,
            jam_pulang: (needsTime && !isAuto) ? jam_pulang : null,
            alasan: alasan,
            lokasi: lokasi
        });
    });
    
    if (!isValid) {
        showNotif('Harap lengkapi jam masuk & pulang atau gunakan fitur Otomatis', false);
        return;
    }

    const payload = {
        date: date,
        bulk_data: JSON.stringify(bulk_data)
    };
    
    const btn = qs('#abs-save');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fi fi-sr-spinner animate-spin"></i> Menyimpan...';

    const r = await api('?ajax=admin_add_absence', payload);
    btn.disabled = false;
    btn.innerHTML = originalText;

    if(r.ok){
        qs('#absence-modal').classList.add('hidden');
        selectedUsers.clear();
        renderSelectedUsers();
        if (qs('#abs-select-all')) qs('#abs-select-all').checked = false;
        
        if (typeof renderLaporan === 'function') renderLaporan();
        showNotif(r.message || 'Data berhasil disimpan', true);
    } else {
        showNotif(r.message||'Gagal simpan', false);
    }
});

// Update WFA locations button handler
qs('#btn-update-wfa-locations') && qs('#btn-update-wfa-locations').addEventListener('click', async ()=>{
    showConfirmModal('Apakah Anda yakin ingin memperbarui semua lokasi WFA yang masih dalam bentuk koordinat menjadi nama jalan? Proses ini mungkin memakan waktu beberapa saat.', async () => {
    
    const button = qs('#btn-update-wfa-locations');
    const originalText = button.textContent;
    button.textContent = 'Memproses...';
    button.disabled = true;
    
    try {
        const r = await api('?ajax=admin_update_wfa_locations', {});
        if (r.ok) {
            showNotif(r.message || 'Lokasi WFA berhasil diperbarui', true);
            renderLaporan(); // Refresh the table
        } else {
            showNotif(r.message || 'Gagal memperbarui lokasi WFA', false);
        }
    } catch (error) {
        showNotif('Terjadi kesalahan saat memperbarui lokasi WFA', false);
        console.error('Error updating WFA locations:', error);
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
    });
});

// Backup management handlers - moved to below for better integration with loadBackupFiles

qs('#btn-backup-status') && qs('#btn-backup-status').addEventListener('click', async ()=>{
    try {
        const r = await api('?ajax=get_backup_status', {});
        if (r.ok && r.data) {
            const data = r.data;
            let message = '';
            
            if (data.exists) {
                message = `Backup tersedia:\n`;
                message += `File: ${data.file}\n`;
                message += `Ukuran: ${data.size_formatted}\n`;
                message += `Dibuat: ${data.created}`;
            } else {
                message = 'Tidak ada file backup tersedia';
            }
            
            showNotif(message, false);
        } else {
            showNotif(r.message || 'Gagal mendapatkan status backup', false);
        }
    } catch (error) {
        showNotif('Terjadi kesalahan saat mendapatkan status backup', false);
        console.error('Error getting backup status:', error);
    }
});

// Load and render backup files list
async function loadBackupFiles() {
    const listContainer = qs('#backup-files-list');
    if (!listContainer) return;
    
    listContainer.innerHTML = `
        <div class="text-center text-gray-500 py-8">
            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
            <p class="mt-2">Memuat daftar file backup...</p>
        </div>
    `;
    
    try {
        const r = await api('?ajax=list_backup_files', {});
        if (r.ok && r.data) {
            const files = r.data;
            
            if (files.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        <i class="fi fi-sr-database text-4xl mb-2"></i>
                        <p>Tidak ada file backup tersedia</p>
                        <p class="text-sm mt-2">Klik "Buat Backup Baru" untuk membuat backup pertama</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="space-y-2">';
            files.forEach(file => {
                html += `
                    <div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200">
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800">${file.name}</div>
                            <div class="text-sm text-gray-600 mt-1">
                                <span class="mr-4"><i class="fi fi-sr-file"></i> ${file.size_formatted}</span>
                                <span><i class="fi fi-sr-calendar"></i> ${file.modified}</span>
                            </div>
                        </div>
                        <div>
                            <a href="?ajax=download_backup&file=${encodeURIComponent(file.name)}" 
                               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition inline-flex items-center">
                                <i class="fi fi-sr-download mr-2"></i> Download
                            </a>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            listContainer.innerHTML = html;
        } else {
            listContainer.innerHTML = `
                <div class="text-center text-red-500 py-8">
                    <i class="fi fi-sr-exclamation-triangle text-4xl mb-2"></i>
                    <p>Gagal memuat daftar file backup</p>
                    <p class="text-sm mt-2">${r.message || 'Terjadi kesalahan'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading backup files:', error);
        listContainer.innerHTML = `
            <div class="text-center text-red-500 py-8">
                <i class="fi fi-sr-exclamation-triangle text-4xl mb-2"></i>
                <p>Terjadi kesalahan saat memuat daftar file backup</p>
            </div>
        `;
    }
}

// Refresh backup list button
qs('#btn-refresh-backup-list') && qs('#btn-refresh-backup-list').addEventListener('click', () => {
    loadBackupFiles();
});

// Create backup button handler
qs('#btn-create-backup') && qs('#btn-create-backup').addEventListener('click', async () => {
    showConfirmModal('Apakah Anda yakin ingin membuat backup database? Proses ini mungkin memakan waktu beberapa saat.', async () => {
        const button = qs('#btn-create-backup');
        const originalText = button.textContent;
        button.textContent = 'Membuat Backup...';
        button.disabled = true;
        
        try {
            const r = await api('?ajax=create_backup', {});
            if (r.ok) {
                showNotif(r.message || 'Backup berhasil dibuat', true);
                // Refresh list after successful backup
                setTimeout(() => loadBackupFiles(), 500);
            } else {
                showNotif(r.message || 'Gagal membuat backup', false);
            }
        } catch (error) {
            showNotif('Terjadi kesalahan saat membuat backup', false);
            console.error('Error creating backup:', error);
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    });
});


// Daily report review modal
qs('#dr-close') && qs('#dr-close').addEventListener('click', ()=> qs('#dr-modal').classList.add('hidden'));
qs('#dr-approve') && qs('#dr-approve').addEventListener('click', ()=> handleDrApproveDisapprove('approved'));
qs('#dr-disapprove') && qs('#dr-disapprove').addEventListener('click', ()=> handleDrApproveDisapprove('disapproved'));
async function handleDrApproveDisapprove(status){
    const id = qs('#dr-modal').dataset.reportId; const evaluation = qs('#dr-evaluation').value;
    if(!id){ showNotif('Tidak ada laporan.'); return; }
    showConfirmModal('Yakin '+(status==='approved'?'approve':'disapprove')+'?', async ()=>{
        const r = await api('?ajax=admin_set_daily_status', { id, status, evaluation });
        if(r.ok){ qs('#dr-modal').classList.add('hidden'); renderLaporan(); } else { showNotif(r.message||'Gagal'); }
    });
}

const editAttModal = qs('#edit-att-modal');
qs('#edit-att-cancel') && qs('#edit-att-cancel').addEventListener('click', ()=> editAttModal.classList.add('hidden'));

// Handle change event for edit-att-ket to show/hide WFA and Overtime forms
document.addEventListener('change', (e) => {
    if (e.target.id === 'edit-att-ket') {
        const wfaForm = qs('#edit-att-wfa-form');
        const overtimeForm = qs('#edit-att-overtime-form');
        const ket = e.target.value;
        
        // Hide all forms first
        wfaForm.classList.add('hidden');
        overtimeForm.classList.add('hidden');
        
        // Show appropriate form based on ket
        if (ket === 'wfa') {
            wfaForm.classList.remove('hidden');
        } else if (ket === 'overtime') {
            overtimeForm.classList.remove('hidden');
        }
    }
});

// Handle screenshot upload for edit attendance modal
let editAttScreenshotMasuk = null;
let editAttScreenshotPulang = null;

// Upload screenshot masuk
qs('#edit-att-upload-masuk') && qs('#edit-att-upload-masuk').addEventListener('click', () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                editAttScreenshotMasuk = e.target.result;
                qs('#edit-att-screenshot-masuk-data').value = editAttScreenshotMasuk;
                qs('#edit-att-screenshot-masuk-img').src = editAttScreenshotMasuk;
                qs('#edit-att-screenshot-masuk-preview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    };
    input.click();
});

// Upload screenshot pulang
qs('#edit-att-upload-pulang') && qs('#edit-att-upload-pulang').addEventListener('click', () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                editAttScreenshotPulang = e.target.result;
                qs('#edit-att-screenshot-pulang-data').value = editAttScreenshotPulang;
                qs('#edit-att-screenshot-pulang-img').src = editAttScreenshotPulang;
                qs('#edit-att-screenshot-pulang-preview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    };
    input.click();
});

// Remove screenshot masuk
qs('#edit-att-remove-masuk') && qs('#edit-att-remove-masuk').addEventListener('click', () => {
    editAttScreenshotMasuk = null;
    qs('#edit-att-screenshot-masuk-data').value = '';
    qs('#edit-att-screenshot-masuk-preview').classList.add('hidden');
});

// Remove screenshot pulang
qs('#edit-att-remove-pulang') && qs('#edit-att-remove-pulang').addEventListener('click', () => {
    editAttScreenshotPulang = null;
    qs('#edit-att-screenshot-pulang-data').value = '';
    qs('#edit-att-screenshot-pulang-preview').classList.add('hidden');
});
qs('#edit-att-form') && qs('#edit-att-form').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id = qs('#edit-att-id').value;
    const jam_masuk = qs('#edit-att-jam-masuk').value || '';
    const jam_pulang = qs('#edit-att-jam-pulang').value || '';
    const ket = qs('#edit-att-ket').value || '';
    const status = qs('#edit-att-status').value || '';
    const foto_masuk = qs('#edit-att-screenshot-masuk-data').value || '';
    const foto_pulang = qs('#edit-att-screenshot-pulang-data').value || '';
    
    // Add seconds to time values
    const jam_masuk_with_seconds = jam_masuk ? jam_masuk + ':00' : '';
    const jam_pulang_with_seconds = jam_pulang ? jam_pulang + ':00' : '';
    
    const payload = { 
        id, 
        jam_masuk: jam_masuk_with_seconds, 
        jam_pulang: jam_pulang_with_seconds, 
        ket, 
        status,
        foto_masuk,
        foto_pulang
    };
    
    // Add WFA or Overtime fields based on ket
    if (ket === 'wfa') {
        payload.alasan_wfa = qs('#edit-att-alasan-wfa')?.value || '';
    } else if (ket === 'overtime') {
        payload.alasan_overtime = qs('#edit-att-alasan-overtime')?.value || '';
        payload.lokasi_overtime = qs('#edit-att-lokasi-overtime')?.value || '';
    }
    
    const r = await api('?ajax=admin_update_attendance', payload);
    showNotif(r.ok ? 'Berhasil disimpan.' : (r.message || 'Gagal menyimpan'), r.ok);
    if(r.ok){ 
        editAttModal.classList.add('hidden'); 
        renderLaporan(); 
    }
});

// Event listener untuk tombol "Tambahkan Laporan"
qs('#edit-att-add-report') && qs('#edit-att-add-report').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const nama = qs('#edit-att-nama').value;
    
    if (!userId || !date) {
        showNotif('Data tidak lengkap', false);
        return;
    }
    
    // Set info di modal laporan harian
    qs('#admin-dr-nama').textContent = nama;
    qs('#admin-dr-date').textContent = new Date(date).toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    });
    
    // Cek apakah sudah ada laporan
    try {
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date: date });
        if (r.ok && r.data && r.data.content) {
            qs('#admin-dr-content').value = r.data.content;
        } else {
            qs('#admin-dr-content').value = '';
        }
    } catch (error) {
        console.error('Error checking daily report:', error);
        qs('#admin-dr-content').value = '';
    }
    
    // Sembunyikan modal edit kehadiran dan tampilkan modal laporan harian
    editAttModal.classList.add('hidden');
    qs('#admin-daily-report-modal').classList.remove('hidden');
});

// Event listener untuk modal laporan harian admin
qs('#admin-dr-cancel') && qs('#admin-dr-cancel').addEventListener('click', ()=>{
    qs('#admin-daily-report-modal').classList.add('hidden');
    editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
});

qs('#admin-dr-save') && qs('#admin-dr-save').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const content = qs('#admin-dr-content').value;
    
    if (!content.trim()) {
        showNotif('Isi laporan tidak boleh kosong', false);
        return;
    }
    
    try {
        const r = await api('?ajax=admin_save_daily_report', { 
            user_id: userId, 
            date: date, 
            content: content 
        });
        
        if (r.ok) {
            showNotif('Laporan harian berhasil disimpan');
            qs('#admin-daily-report-modal').classList.add('hidden');
            editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
        } else {
            showNotif(r.message || 'Gagal menyimpan laporan', false);
        }
    } catch (error) {
        console.error('Error saving daily report:', error);
        showNotif('Terjadi kesalahan saat menyimpan', false);
    }
});

// Event listener untuk tombol "Tambahkan Laporan"
qs('#edit-att-add-report') && qs('#edit-att-add-report').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const nama = qs('#edit-att-nama').value;
    
    if (!userId || !date) {
        showNotif('Data tidak lengkap', false);
        return;
    }
    
    // Set info di modal laporan harian
    qs('#admin-dr-nama').textContent = nama;
    qs('#admin-dr-date').textContent = new Date(date).toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    });
    
    // Cek apakah sudah ada laporan
    try {
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date: date });
        if (r.ok && r.data && r.data.content) {
            qs('#admin-dr-content').value = r.data.content;
        } else {
            qs('#admin-dr-content').value = '';
        }
    } catch (error) {
        console.error('Error checking daily report:', error);
        qs('#admin-dr-content').value = '';
    }
    
    // Sembunyikan modal edit kehadiran dan tampilkan modal laporan harian
    editAttModal.classList.add('hidden');
    qs('#admin-daily-report-modal').classList.remove('hidden');
});

// Event listener untuk modal laporan harian admin
qs('#admin-dr-cancel') && qs('#admin-dr-cancel').addEventListener('click', ()=>{
    qs('#admin-daily-report-modal').classList.add('hidden');
    editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
});

qs('#admin-dr-save') && qs('#admin-dr-save').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const content = qs('#admin-dr-content').value;
    
    if (!content.trim()) {
        showNotif('Isi laporan tidak boleh kosong', false);
        return;
    }
    
    try {
        const r = await api('?ajax=admin_save_daily_report', { 
            user_id: userId, 
            date: date, 
            content: content 
        });
        
        if (r.ok) {
            showNotif('Laporan harian berhasil disimpan');
            qs('#admin-daily-report-modal').classList.add('hidden');
            editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
        } else {
            showNotif(r.message || 'Gagal menyimpan laporan', false);
        }
    } catch (error) {
        console.error('Error saving daily report:', error);
        showNotif('Terjadi kesalahan saat menyimpan', false);
    }
});

// Redundant click listener removed

// Removed duplicate showWFAModal as it is already defined earlier in this file

function submitAttendanceWithWFA(attendanceData, wfaReason) {
    // Add WFA reason to attendance data
    const dataWithWFA = {
        ...attendanceData,
        wfa_reason: wfaReason,
        is_wfa: true
    };
    
    // Submit attendance with WFA reason
    api('?ajax=save_attendance', dataWithWFA)
        .then(response => {
            if (response.ok) {
                statusMessage('Presensi berhasil dengan alasan WFA!', 'bg-green-100 text-green-700');
                // Clear pending data
                window.pendingWFAReson = null;
                window.pendingAttendanceData = null;
                isProcessingRecognition = false;
            } else {
                const errorMsg = response.message || 'Presensi gagal. Silakan coba lagi.';
                statusMessage('Gagal menyimpan presensi: ' + errorMsg, 'bg-red-100 text-red-700');
                isProcessingRecognition = false;
            }
        })
        .catch(error => {
            console.error('Error submitting attendance with WFA:', error);
            statusMessage('Terjadi kesalahan saat menyimpan presensi.', 'bg-red-100 text-red-700');
            isProcessingRecognition = false;
        });
}

function showConfirmModal(message, cb){
    const modal=qs('#confirm-modal');
    qs('#confirm-modal-message').textContent=message;
    onConfirmCallback=cb;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
qs('#btn-confirm-yes') && qs('#btn-confirm-yes').addEventListener('click', ()=>{
    if(typeof onConfirmCallback==='function') onConfirmCallback();
    const m = qs('#confirm-modal');
    if (m) {
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
    onConfirmCallback=null;
});
qs('#btn-confirm-no') && qs('#btn-confirm-no').addEventListener('click', ()=>{
    const m = qs('#confirm-modal');
    if (m) {
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
    onConfirmCallback=null;
});

// Pegawai app: setup Rekap and Monthly pages
const pageMonthlyList = qs('#page-laporan-bulanan');
const pageMonthlyForm = qs('#page-monthly-form');

function addAchievementRow(data = { achievement: '', detail: '' }) {
    const body = qs('#table-achievements-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.achievement}" placeholder="Capaian..."></td>
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.detail}" placeholder="Detail capaian..."></td>
        <td class="p-1 text-center"><button type="button" class="btn-delete-row text-red-500 font-bold">Hapus</button></td>
    `;
    body.appendChild(tr);
}

function addObstacleRow(data = { obstacle: '', solution: '', note: '' }) {
    const body = qs('#table-obstacles-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.obstacle}" placeholder="Kendala..."></td>
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.solution}" placeholder="Solusi..."></td>
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.note}" placeholder="Catatan..."></td>
        <td class="p-1 text-center"><button type="button" class="btn-delete-row text-red-500 font-bold">Hapus</button></td>
    `;
    body.appendChild(tr);
}

// Event listeners untuk tombol tambah baris
qs('#btn-add-achievement')?.addEventListener('click', () => addAchievementRow());
qs('#btn-add-obstacle')?.addEventListener('click', () => addObstacleRow());

// Event listener untuk hapus baris (delegation)
pageMonthlyForm?.addEventListener('click', e => {
    if (e.target.classList.contains('btn-delete-row')) {
        e.target.closest('tr').remove();
    }
});

// Kembali ke daftar (Tutup modal)
qs('#btn-back-to-monthly-list')?.addEventListener('click', () => {
    pageMonthlyForm.classList.add('hidden');
    pageMonthlyForm.classList.remove('flex');
});

// Close modal when clicking backdrop
qs('#monthly-modal-overlay') && qs('#monthly-modal-overlay').addEventListener('click', () => {
    pageMonthlyForm.classList.add('hidden');
    pageMonthlyForm.classList.remove('flex');
});

// Fungsi untuk menyimpan laporan (baik draft maupun submit)
async function saveMonthlyReport(isSubmit) {
    const year = qs('#monthly-report-year').value;
    const month = qs('#monthly-report-month').value;
    const summary = qs('#monthly-summary').value;

    const achievements = qsa('#table-achievements-body tr').map(tr => {
        const inputs = tr.querySelectorAll('input');
        return { achievement: inputs[0].value, detail: inputs[1].value };
    }).filter(item => item.achievement || item.detail);

    const obstacles = qsa('#table-obstacles-body tr').map(tr => {
        const inputs = tr.querySelectorAll('input');
        return { obstacle: inputs[0].value, solution: inputs[1].value, note: inputs[2].value };
    }).filter(item => item.obstacle || item.solution || item.note);

    const payload = {
        year: parseInt(year),
        month: parseInt(month),
        summary,
        achievements: JSON.stringify(achievements),
        obstacles: JSON.stringify(obstacles),
        submit: isSubmit
    };
    
    const r = await api('?ajax=save_monthly_report', payload);
    if (r.ok) {
        showNotif(isSubmit ? 'Laporan berhasil disubmit!' : 'Laporan berhasil disimpan sebagai draft.');
        
        // Clear API Cache to force fresh data in renderMonthly
        if (typeof apiCache !== 'undefined' && apiCache.clear) {
            apiCache.clear();
        }
        
        pageMonthlyForm.classList.add('hidden');
        pageMonthlyForm.classList.remove('flex');
        renderMonthly(); // Refresh list
    } else {
        showNotif(r.message || 'Gagal menyimpan laporan.');
    }
}

qs('#btn-save-draft')?.addEventListener('click', () => saveMonthlyReport(false));
qs('#form-monthly-report')?.addEventListener('submit', (e) => {
    e.preventDefault();
    saveMonthlyReport(true);
});
// --- End Monthly Report Form Logic ---

function getWeekNumberInMonth(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    const firstDayOfMonth = new Date(d.getFullYear(), d.getMonth(), 1);
    const firstDayOfWeek = firstDayOfMonth.getDay();
    const offsetDays = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Monday = 0, Sunday = 6
    const weekNumber = Math.ceil((d.getDate() + offsetDays) / 7);
    return weekNumber;
}

// Flag to prevent multiple calls

async function initRekapPage() {
    // SECURITY: Don't run on public pages or if not authenticated
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page');
    if (['presensi-masuk', 'presensi-pulang', 'landing'].includes(page)) return;

    if (isInitRekapRunning) {
        console.log('initRekapPage already running, skipping...');
        return;
    }
    
    isInitRekapRunning = true;
    
    // Load settings for max days back for daily reports
    try {
        const settingsJson = await api('?ajax=get_settings', {}, { cache: false });
        if (settingsJson.ok && settingsJson.data && settingsJson.data.max_daily_report_days_back) {
            window.maxDailyReportDaysBack = parseInt(settingsJson.data.max_daily_report_days_back.value) || 5;
        } else {
            window.maxDailyReportDaysBack = 5; // Default: 5 days
        }
    } catch (e) {
        window.maxDailyReportDaysBack = 5; // Default: 5 days on error
    }
    
    const m = parseInt(qs('#rekap-month')?.value || String(new Date().getMonth() + 1));
    const y = parseInt(qs('#rekap-year')?.value || String(new Date().getFullYear()));
    const viewMode = qs('#rekap-view-mode')?.value || 'monthly';
    
    console.log('Loading rekap for month:', m, 'year:', y, 'mode:', viewMode);
    const r = await api('?ajax=get_rekap', { month: m, year: y });
    console.log('Rekap data:', r);
    
    // Load missing daily reports
    await loadMissingDailyReports();

    const weekSel = qs('#rekap-week');
    if (weekSel) {
        // Toggle visibility based on mode
        if (viewMode === 'weekly') {
            weekSel.classList.remove('hidden');
        } else {
            weekSel.classList.add('hidden');
        }
    
        // Only repopulate if empty or we just switched context significantly? 
        // Better to always refresh just in case data changed, but try to preserve selection if valid.
        const currentVal = weekSel.value;
        weekSel.innerHTML = '';
        
        if (r.ok && r.data.length > 0) {
            const datesInMonth = r.data.map(d => new Date(d.date));
            const weeks = [...new Set(datesInMonth.map(d => getWeekNumberInMonth(d)))].sort((a, b) => a - b);
            
            if (weeks.length >= 1) {
                // Add "All Weeks" option
                const allOption = document.createElement('option');
                allOption.value = '0';
                allOption.textContent = 'Semua Minggu';
                weekSel.appendChild(allOption);
                
                weeks.forEach(w => {
                    const option = document.createElement('option');
                    option.value = w;
                    option.textContent = `Minggu ke-${w}`;
                    weekSel.appendChild(option);
                });
                
                // Restore selection or set default
                if (currentVal && weeks.includes(parseInt(currentVal))) {
                    weekSel.value = currentVal;
                } else if (!currentVal && m === (new Date().getMonth() + 1) && y === new Date().getFullYear()) {
                    // Default to current week if viewing current month
                    const currentWeek = getWeekNumberInMonth(new Date());
                    if (weeks.includes(currentWeek)) weekSel.value = currentWeek;
                }
            }
        }
    }

    // Get selected week
    let selectedWeek = parseInt(qs('#rekap-week')?.value || 0);
    
    // Force 0 if monthly mode
    if (viewMode === 'monthly') {
        selectedWeek = 0;
    }
    
    // Debug logging
    console.log('Selected week:', selectedWeek);
    
    const body = qs('#table-rekap-body');
    if (!body) {
        isInitRekapRunning = false;
        return;
    }
    body.innerHTML = '';
    if (!r.ok || !r.data || r.data.length === 0) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data.</td></tr>`;
        isInitRekapRunning = false;
        // Also clear/hide KPI?
        return;
    }

    // Store current data globally
    window.currentRekapData = r.data;
    
    // Render the table data
    renderRekapData(r.data, m, y);
    
    // Calculate custom dates for KPI if in weekly mode
    let customStart = null;
    let customEnd = null;
    
    if (viewMode === 'weekly' && selectedWeek > 0) {
        // Filter data to find date range
        const weekData = r.data.filter(d => getWeekNumberInMonth(new Date(d.date)) === selectedWeek);
        if (weekData.length > 0) {
             // Sort by date
             weekData.sort((a, b) => new Date(a.date) - new Date(b.date));
             customStart = weekData[0].date;
             customEnd = weekData[weekData.length - 1].date;
             
             // Expand range to cover full week (optional/bonus)?
             // For now, strict range of ATTENDANCE/HOLIDAY days known to system is safer.
        }
    }
    
    // Load KPI data with appropriate filter
    // This replaces loadEmployeeKPIData()
    loadKPIChart(m, y, customStart, customEnd);
    
    // Reset flag
    isInitRekapRunning = false;
}

// Load KPI data for employee
async function loadEmployeeKPIData() {
    try {
        const response = await fetch('?ajax=get_kpi_data');
        const result = await response.json();
        
        if (result.ok && result.data) {
            renderEmployeeKPIChart(result.data);
        } else {
            console.error('Failed to load KPI data:', result.message);
        }
    } catch (error) {
        console.error('Error loading KPI data:', error);
    }
}

// Render KPI chart for employee
function renderEmployeeKPIChart(kpiData) {
    const ctx = qs('#kpi-chart');
    const summary = qs('#kpi-summary');
    
    if (!ctx || !summary) return;
    
    // Destroy existing chart if it exists
    if (window.employeeKPIChart) {
        try {
            window.employeeKPIChart.destroy();
        } catch (e) {
            console.log('Chart destroy error (ignored):', e);
        }
        window.employeeKPIChart = null;
    }
    
    // Create bar chart data
    const labels = ['Ontime', 'Terlambat', 'Izin/Sakit', 'Alpha', 'Overtime'];
    const data = [
        kpiData.ontime_count || 0,
        kpiData.late_count || 0,
        kpiData.izin_sakit_count || 0,
        kpiData.alpha_count || 0,
        kpiData.overtime_count || 0
    ];
    
    const colors = [
        '#22c55e', // Green for ontime
        '#ef4444', // Red for late
        '#eab308', // Yellow for izin/sakit
        '#6b7280', // Gray for alpha
        '#10b981'  // Emerald for overtime
    ];
    
    window.employeeKPIChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Hari',
                data: data,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: `KPI Score: ${kpiData.kpi_score}% - ${kpiData.status}`
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Update summary cards
    summary.innerHTML = `
        <div class="bg-green-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-green-600">${kpiData.ontime_count || 0}</div>
            <div class="text-sm text-green-700">Ontime</div>
        </div>
        <div class="bg-red-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-red-600">${kpiData.late_count || 0}</div>
            <div class="text-sm text-red-700">Terlambat</div>
        </div>
        <div class="bg-yellow-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-yellow-600">${kpiData.izin_sakit_count || 0}</div>
            <div class="text-sm text-yellow-700">Izin/Sakit</div>
        </div>
        <div class="bg-gray-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-gray-600">${kpiData.alpha_count || 0}</div>
            <div class="text-sm text-gray-700">Alpha</div>
        </div>
        <div class="bg-emerald-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-emerald-600">${kpiData.overtime_count || 0}</div>
            <div class="text-sm text-emerald-700">Overtime</div>
        </div>
        <div class="bg-indigo-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-indigo-600">${kpiData.kpi_score || 0}%</div>
            <div class="text-sm text-indigo-700">KPI Score</div>
        </div>
    `;
}

function renderRekapData(data, m, y) {
    const body = qs('#table-rekap-body');
    if (!body) return;
    body.innerHTML = '';
    
    if (!data || data.length === 0) {
        body.innerHTML = `<div class="col-span-7 text-center py-8 text-gray-500">Tidak ada data.</div>`;
        return;
    }

    const currentWeek = getWeekNumberInMonth(new Date());
    let selectedWeek = parseInt(qs('#rekap-week')?.value || 0);
    if (!qs('#rekap-week') || qs('#rekap-week').classList.contains('hidden')) {
        selectedWeek = 0; 
    }

    // --- Calendar Padding Logic ---
    // Only pad if showing full month (selectedWeek === 0)
    if (selectedWeek === 0) {
        const firstDay = new Date(data[0].date);
        let dayOfWeek = firstDay.getDay(); // 0 Sun, 1 Mon ... 6 Sat
        // Adjust to 0 Mon, 1 Tue ... 6 Sun (Monday starts at index 0)
        let offset = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
        
        for (let i = 0; i < offset; i++) {
            const empty = document.createElement('div');
            empty.className = 'mood-item empty-slot opacity-20';
            body.appendChild(empty);
        }
    }

    let dataToShow = data;
    if (selectedWeek > 0) {
        dataToShow = data.filter(row => getWeekNumberInMonth(new Date(row.date)) === selectedWeek);
    }

    // --- Modern Formal SVG Emoji Icons (No Cat Ears) ---
    const icons = {
        happy: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-happy)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <rect x="20" y="32" width="26" height="18" rx="6" fill="#0F172A"/>
            <rect x="54" y="32" width="26" height="18" rx="6" fill="#0F172A"/>
            <path d="M46 38H54" stroke="#0F172A" stroke-width="4" stroke-linecap="round"/>
            <path d="M24 36H32" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.4"/>
            <path d="M58 36H66" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.4"/>
            <path d="M38 64C38 70.6 43.4 76 50 76C56.6 76 62 70.6 62 64" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/>
            <circle cx="16" cy="58" r="6" fill="#FFFFFF" fill-opacity="0.25"/>
            <circle cx="84" cy="58" r="6" fill="#FFFFFF" fill-opacity="0.25"/>
        </svg>`,
        sleeping: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-sleeping)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <path d="M22 45C25 41 31 41 34 45" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round"/>
            <path d="M66 45C69 41 75 41 78 45" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round"/>
            <path d="M42 62H58" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/>
            <path d="M78 16C75 16 71 18 70 21C70 24 72 27 75 27C78 27 80 25 80 22C80 18.5 78 16 78 16Z" fill="#FDE047" opacity="0.8"/>
        </svg>`,
        energetic: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-energetic)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <rect x="18" y="34" width="28" height="20" rx="6" stroke="#FFFFFF" stroke-width="4" fill="none"/>
            <rect x="54" y="34" width="28" height="20" rx="6" stroke="#FFFFFF" stroke-width="4" fill="none"/>
            <path d="M46 42H54" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/>
            <circle cx="32" cy="44" r="4.5" fill="#FFFFFF"/>
            <circle cx="68" cy="44" r="4.5" fill="#FFFFFF"/>
            <path d="M38 66C42 70 58 70 62 66" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round"/>
        </svg>`,
        bored: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-bored)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <path d="M22 46H36" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round"/>
            <path d="M64 46H78" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round"/>
            <circle cx="50" cy="66" r="7" stroke="#FFFFFF" stroke-width="4" fill="none"/>
        </svg>`,
        unknown: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-unknown)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <text x="50" y="68" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif" font-size="52" font-weight="900" fill="#FFFFFF">?</text>
        </svg>`,
        wfa: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-unknown)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <path d="M14 44C14 24 28 16 50 16C72 16 86 24 86 44" stroke="#334155" stroke-width="5" stroke-linecap="round" fill="none"/>
            <rect x="10" y="38" width="8" height="20" rx="4" fill="#334155"/>
            <rect x="82" y="38" width="8" height="20" rx="4" fill="#334155"/>
            <circle cx="34" cy="46" r="5" fill="#FFFFFF"/>
            <circle cx="66" cy="46" r="5" fill="#FFFFFF"/>
            <path d="M40 64C40 68 60 68 60 64" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" fill="none"/>
        </svg>`,
        overtime: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-overtime-side)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <path d="M22 42L36 44" stroke="#FFFFFF" stroke-width="5.5" stroke-linecap="round"/>
            <path d="M78 42L64 44" stroke="#FFFFFF" stroke-width="5.5" stroke-linecap="round"/>
            <circle cx="29" cy="49" r="4" fill="#FFFFFF"/>
            <circle cx="71" cy="49" r="4" fill="#FFFFFF"/>
            <path d="M35 65C40 61 60 61 65 65" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" fill="none"/>
            <path d="M84 14L78 22H85L79 30" stroke="#FEF08A" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        </svg>`,
        future: `<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="30" fill="url(#lg-future)"/>
            <path d="M5 35C5 20 20 5 35 5H65C80 5 95 20 95 35C65 30 35 30 5 35Z" fill="white" fill-opacity="0.12"/>
            <circle cx="50" cy="50" r="22" stroke="white" stroke-width="4" fill="none" stroke-opacity="0.7"/>
            <path d="M50 32V50L62 58" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.9"/>
        </svg>`
    };

    // --- Counters for Stats ---
    const stats = { happy: 0, leave: 0, wfo: 0, wfa: 0, overtime: 0, alpha: 0, total: 0 };

    dataToShow.forEach(row => {
        const d = new Date(row.date);
        const today = new Date().toISOString().slice(0, 10);
        const isFuture = row.date > today;
        const isToday = row.date === today;
        
        const isManualHoliday = row.is_manual_holiday || false;
        const isWorkingDay = row.is_working_day !== undefined ? row.is_working_day : true;
        const isWeekend = row.is_weekend || false;
        const isHoliday = isManualHoliday || !isWorkingDay || isWeekend;
        
        const dr = row.daily_report;
        const ket = (row.ket || '').toLowerCase();
        
        let moodClass = 'mood-gray';
        let icon = icons.bored;
        let bubbleText = '...';
        let type = 'unknown';

        if (isHoliday) {
            if (ket === 'wfo') {
                moodClass = 'mood-green';
                icon = icons.energetic;
                bubbleText = 'WFO Hard!';
                type = 'work';
                stats.wfo++;
            } else if (ket === 'wfa') {
                moodClass = 'mood-yellow';
                icon = icons.wfa;
                bubbleText = 'WFA Chill!';
                type = 'work';
                stats.wfa++;
            } else if (ket === 'overtime') {
                moodClass = 'mood-orange';
                icon = icons.overtime;
                bubbleText = 'Overtime!';
                type = 'work';
                stats.overtime++;
            } else {
                moodClass = 'mood-blue-bright';
                icon = icons.happy;
                bubbleText = 'holi-yay!';
                type = 'holiday';
                stats.happy++;
            }
        } else if (ket === 'sakit' || ket === 'izin') {
            moodClass = 'mood-purple-dark';
            icon = icons.sleeping;
            bubbleText = 'on leave zzz..';
            type = 'leave';
            stats.leave++;
        } else if (ket === 'wfo') {
            moodClass = 'mood-green';
            icon = icons.energetic;
            bubbleText = 'WFO Hard!';
            type = 'work';
            stats.wfo++;
        } else if (ket === 'wfa') {
            moodClass = 'mood-yellow';
            icon = icons.wfa;
            bubbleText = 'WFA Chill!';
            type = 'work';
            stats.wfa++;
        } else if (ket === 'overtime') {
            moodClass = 'mood-orange';
            icon = icons.overtime;
            bubbleText = 'Overtime!';
            type = 'work';
            stats.overtime++;
        } else if (!isFuture && isWorkingDay && (!ket || ket === 'na')) {
            if (isToday) {
                moodClass = 'mood-today-empty';
                icon = icons.unknown;
                bubbleText = 'Belum presensi';
                type = 'today_empty';
            } else {
                moodClass = 'mood-red';
                icon = icons.bored;
                bubbleText = 'missing...';
                type = 'alpha';
                stats.alpha++;
            }
        } else if (isFuture) {
            moodClass = 'mood-gray opacity-40';
            icon = icons.future;
            bubbleText = 'coming soon';
            type = 'future';
        }

        if (!isFuture) stats.total++;

        let dotClass = 'dot-gray';
        if (dr) {
            if (dr.status === 'approved') dotClass = 'dot-green';
            else if (dr.status === 'disapproved') dotClass = 'dot-red';
            else dotClass = 'dot-blue';
        }

        const moodItem = document.createElement('div');
        moodItem.className = `mood-item ${isToday ? 'today-active z-10' : ''}`;
        
        const dayInt = d.getDate();
        
        moodItem.innerHTML = `
            <div class="mood-bubble">${bubbleText}</div>
            <button class="mood-button ${moodClass}" data-date="${row.date}" data-type="${type}">
                ${icon}
                ${(type === 'work' || type === 'leave') ? `<div class="mood-status-dot ${dotClass}"></div>` : ''}
            </button>
            <div class="mood-date">${dayInt}</div>
        `;

        const btn = moodItem.querySelector('.mood-button');
        btn.addEventListener('click', () => {
            if (type === 'holiday') {
                if (window.confetti) {
                    confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 }, colors: ['#0ea5e9', '#38bdf8', '#7dd3fc'] });
                }
            } else if (type === 'alpha') {
                const overlay = qs('#angry-cat-overlay');
                if (overlay) { overlay.style.display = 'flex'; setTimeout(() => overlay.style.display = 'none', 2500); }
            } else if (type === 'work') {
                showWorkModal(row);
            } else if (type === 'leave') {
                showLeaveModal(row);
            } else if (type === 'today_empty') {
                window.showAttendanceNoteModal();
            }
        });

        body.appendChild(moodItem);
    });

    // Update Stats DOM
    if (qs('#stat-happy-count')) qs('#stat-happy-count').textContent = stats.happy;
    if (qs('#stat-leave-count')) qs('#stat-leave-count').textContent = stats.leave;
    if (qs('#stat-wfo-count')) qs('#stat-wfo-count').textContent = stats.wfo;
    if (qs('#stat-wfa-count')) qs('#stat-wfa-count').textContent = stats.wfa;
    if (qs('#stat-overtime-count')) qs('#stat-overtime-count').textContent = stats.overtime;
    if (qs('#stat-alpha-count')) qs('#stat-alpha-count').textContent = stats.alpha;
    if (qs('#stat-total-count')) qs('#stat-total-count').textContent = stats.total + ' Hari';

    isInitRekapRunning = false;
    loadKPIChart(m, y);
}

window.showAttendanceNoteModal = function() {
    const modal = qs('#izin-sakit-modal');
    if (modal) {
        modal.classList.remove('hidden');
    } else {
        showNotif('Silakan input keterangan presensi hari ini.');
    }
};

window.showWorkModal = function(row) {
    const d = new Date(row.date);
    const dayMap = { Monday: 'Senin', Tuesday: 'Selasa', Wednesday: 'Rabu', Thursday: 'Kamis', Friday: 'Jumat', Saturday: 'Sabtu', Sunday: 'Minggu' };
    const dayName = dayMap[d.toLocaleDateString('en-US', { weekday: 'long' })] || '';
    const tanggal = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    
    const dr = row.daily_report;
    let statusText = 'Belum ada laporan';
    let statusClass = 'text-gray-500';
    if (dr) {
        if (dr.status === 'approved') { statusText = 'Disetujui'; statusClass = 'text-green-600'; }
        else if (dr.status === 'disapproved') { statusText = 'Ditolak'; statusClass = 'text-red-600'; }
        else { statusText = 'Menunggu Persetujuan'; statusClass = 'text-blue-600'; }
    }

    const content = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Hari / Tanggal</p>
                    <p class="font-semibold text-gray-800">${dayName}, ${tanggal}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Keterangan Presensi</p>
                    <p class="font-semibold text-gray-800">${(row.ket || '').toUpperCase()}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Jam Masuk</p>
                    <p class="font-semibold text-gray-800">${row.jam_masuk || '-'}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Jam Keluar</p>
                    <p class="font-semibold text-gray-800">${row.jam_pulang || '-'}</p>
                </div>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">Status Laporan</p>
                <p class="font-bold ${statusClass}">${statusText}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Laporan Harian</p>
                <div id="modal-dr-column" class="p-3 bg-gray-50 rounded-xl border border-gray-200 min-h-[100px] cursor-pointer hover:bg-white transition-colors" onclick="makeDrEditable(this, '${row.date}')">
                    ${dr ? (dr.content || '<span class="text-gray-400 italic">Isi laporan kosong...</span>') : '<span class="text-gray-400 italic">Belum ada isi laporan...</span>'}
                </div>
                <div id="dr-edit-actions" class="hidden mt-2 flex justify-end gap-2">
                    <button onclick="saveDrFromModal('${row.date}')" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold">Simpan</button>
                </div>
            </div>
        </div>
    `;

    customAlert(content, 'Detail Kehadiran & Laporan');
    const modalMessage = qs('#global-modal-message');
    if (modalMessage) { modalMessage.innerHTML = content; }
};

window.makeDrEditable = function(el, date) {
    if (el.querySelector('textarea')) return;
    const currentText = (el.innerText === 'Belum ada isi laporan...' || el.innerText === 'Isi laporan kosong...') ? '' : el.innerText;
    el.innerHTML = `<textarea id="modal-dr-textarea" class="w-full h-32 p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Tulis laporan harian Anda...">${currentText}</textarea>`;
    qs('#dr-edit-actions').classList.remove('hidden');
    el.onclick = null;
};

window.saveDrFromModal = async function(date) {
    const textarea = qs('#modal-dr-textarea');
    if (!textarea) return;
    const val = textarea.value;
    
    try {
        const res = await api('?ajax=save_daily_report', { date: date, content: val });
        if (res.ok) {
            const container = qs('#modal-dr-column');
            container.innerHTML = val || '<span class="text-gray-400 italic">Belum ada isi laporan...</span>';
            qs('#dr-edit-actions').classList.add('hidden');
            container.onclick = () => makeDrEditable(container, date);
            showNotif('Laporan berhasil disimpan');
            initRekapPage();
        } else {
            showNotif(res.message || 'Gagal menyimpan laporan', false);
        }
    } catch (e) {
        showNotif('Terjadi kesalahan', false);
    }
};

window.showLeaveModal = function(row) {
    const tanggal = new Date(row.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    
    let evidenceHtml = '<p class="text-gray-400 italic">Tidak ada bukti</p>';
    const evidenceId = row.note_id || row.attendance_id;
    const evidenceType = row.note_id ? 'note' : 'masuk';
    
    if (evidenceId) {
        evidenceHtml = `<div class="mt-2 w-full h-48 bg-gray-100 rounded-xl overflow-hidden relative cursor-pointer" onclick="loadAndShowEvidence('${evidenceType === 'note' ? 'note_' + evidenceId : evidenceId}', '${evidenceType}', 'Bukti Izin/Sakit')">
            <div id="leave-proof-container-${evidenceId}" class="w-full h-full flex items-center justify-center">
                <i class="fi fi-rr-picture text-3xl text-gray-300"></i>
            </div>
        </div>`;
        setTimeout(() => loadLazyProof(evidenceId, evidenceType, `leave-proof-container-${evidenceId}`), 100);
    }

    const content = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Tanggal</p>
                    <p class="font-semibold text-gray-800">${tanggal}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Tipe</p>
                    <p class="font-bold text-indigo-600">${(row.ket || '').toUpperCase()}</p>
                </div>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">Keterangan</p>
                <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-200">
                    ${row.daily_report ? (row.daily_report.content || '-') : '-'}
                </div>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Bukti Izin/Sakit</p>
                ${evidenceHtml}
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">Status Laporan</p>
                <p class="font-bold text-indigo-600">Terdaftar</p>
            </div>
        </div>
    `;

    customAlert(content, 'Detail Izin / Sakit');
    const modalMessage = qs('#global-modal-message');
    if (modalMessage) { modalMessage.innerHTML = content; }
};

// Global variable to store chart instance
let kpiChartInstance = null;

// Function to load and display KPI chart
async function loadKPIChart(month, year, customStart = null, customEnd = null) {
    try {
        console.log('Loading KPI chart for month:', month, 'year:', year, 'customRange:', customStart, 'to', customEnd);
        
        // Get period start and end dates
        let periodStart, periodEnd;
        
        if (customStart && customEnd) {
            periodStart = customStart;
            periodEnd = customEnd;
        } else {
            periodStart = `${year}-${String(month).padStart(2, '0')}-01`;
            const lastDay = new Date(year, month, 0).getDate();
            periodEnd = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        }
        
        console.log('KPI period:', periodStart, 'to', periodEnd);
        
        // Fetch KPI data - check if we're viewing a specific user's data
        const urlParams = new URLSearchParams(window.location.search);
        const userId = urlParams.get('user_id') || (window.currentUserId || '2'); // Default to user 2 for testing
        const kpiUrl = userId ? 
            `?ajax=get_kpi_data&period_start=${periodStart}&period_end=${periodEnd}&user_id=${userId}&t=${Date.now()}` :
            `?ajax=get_kpi_data&period_start=${periodStart}&period_end=${periodEnd}&t=${Date.now()}`;
        
        console.log('KPI URL:', kpiUrl);
        console.log('Using user_id:', userId);
        const response = await api(kpiUrl);
        
        console.log('KPI response:', response);
        
        if (response && response.ok && response.data) {
            const kpiData = response.data;
            console.log('KPI data received:', kpiData);
            console.log('Izin/Sakit count:', kpiData.izin_sakit_count);
            
            // Show KPI chart section
            const kpiSection = qs('#kpi-chart-section');
            if (kpiSection) {
                kpiSection.classList.remove('hidden');
                console.log('KPI section shown');
            } else {
                console.error('KPI section element not found');
            }
            
            // Render KPI chart
            renderKPIChart(kpiData);
            console.log('KPI chart rendered');
            
            // Render KPI summary
            renderKPISummary(kpiData);
            console.log('KPI summary rendered');
        } else {
            console.error('No KPI data in response:', response);
            // Hide KPI section if no data
            const kpiSection = qs('#kpi-chart-section');
            if (kpiSection) {
                kpiSection.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Error loading KPI chart:', error);
        // Hide KPI section on error
        const kpiSection = qs('#kpi-chart-section');
        if (kpiSection) {
            kpiSection.classList.add('hidden');
        }
    }
}

// Function to render KPI chart
function renderKPIChart(kpiData) {
    const canvas = qs('#kpi-chart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    if (kpiChartInstance) {
        kpiChartInstance.destroy();
    }
    
    // Prepare data
    const labels = [
        'Total WFO', 
        'Total WFA', 
        'Hadir Ontime', 
        'Terlambat', 
        'Izin/Sakit', 
        'Alpha'
    ];
    
    const dataValues = [
        kpiData.wfo_count || 0,
        kpiData.wfa_count || 0,
        kpiData.ontime_count || 0,
        kpiData.late_count || 0,
        kpiData.izin_sakit_count || 0,
        kpiData.alpha_count || 0
    ];
    
    // Create reference line data (Total Hari Kerja repeated for each label)
    const totalDays = kpiData.total_working_days || 0;
    
    // Colors matching the cards
    const colors = [
        '#10b981', // Emerald (WFO)
        '#06b6d4', // Cyan (WFA)
        '#22c55e', // Green (Ontime)
        '#eab308', // Yellow (Late)
        '#3b82f6', // Blue (Izin)
        '#ef4444'  // Red (Alpha)
    ];
    
    console.log('Rendering Chart with:', { labels, dataValues, totalDays });
    
    // Create chart
    kpiChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Jumlah Hari',
                    data: dataValues,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1,
                    borderRadius: 6, // Rounded bars
                    barPercentage: 0.6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f3f4f6'
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            family: "'Inter', sans-serif"
                        }
                    },
                    max: totalDays > 0 ? totalDays : undefined, // Set max to total working days
                    title: {
                        display: true,
                        text: `Total Hari Kerja: ${totalDays}`,
                        font: {
                            family: "'Inter', sans-serif",
                            weight: 'bold',
                            size: 13
                        },
                        color: '#4b5563'
                    }
                },
                x: {
                   grid: {
                       display: false
                   },
                   ticks: {
                       font: {
                           family: "'Inter', sans-serif",
                           size: 11
                       }
                   }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    titleFont: {
                        family: "'Inter', sans-serif",
                        weight: 'bold'
                    },
                    bodyFont: {
                        family: "'Inter', sans-serif"
                    },
                    padding: 10,
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    displayColors: true,
                    boxPadding: 4,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw;
                        }
                    }
                }
            }
        }
    });
}

// Function to render KPI summary (Header only)
function renderKPISummary(kpiData) {
    const summaryHeader = qs('#kpi-score-header');
    if (!summaryHeader) return;
    
    // Show header
    summaryHeader.classList.remove('hidden');
    
    // Update Score
    const scoreEl = qs('#kpi-score-value');
    if (scoreEl) scoreEl.textContent = kpiData.kpi_score || 0;
    
    // Update Status with color
    const statusEl = qs('#kpi-status-value');
    if (statusEl) {
        statusEl.textContent = kpiData.status || 'N/A';
        
        statusEl.className = 'text-lg font-bold'; // Reset classes
        if (kpiData.status === 'Excellent') statusEl.classList.add('text-green-600');
        else if (kpiData.status === 'Good') statusEl.classList.add('text-blue-600');
        else if (kpiData.status === 'Fair') statusEl.classList.add('text-yellow-600');
        else statusEl.classList.add('text-red-600');
    }
}

// Load missing daily reports for shortcut
async function loadMissingDailyReports() {
    try {
        const result = await api('?ajax=get_missing_daily_reports', {}, { suppressModal: true, cache: true, ttl: 30000 });
        
        if (!result.ok || !result.data) {
            qs('#missing-daily-reports-shortcut')?.classList.add('hidden');
            return;
        }
        
        const missingDates = result.data;
        const shortcutDiv = qs('#missing-daily-reports-shortcut');
        const countSpan = qs('#missing-reports-count');
        const listDiv = qs('#missing-reports-list');
        
        if (!shortcutDiv || !countSpan || !listDiv) return;
        
        let count = missingDates.length;
        
        // --- Dynamic Robot Logic ---
        const robotSenang = document.getElementById('robot-senang');
        const robotSedih = document.getElementById('robot-sedih');
        const robotMarah = document.getElementById('robot-marah');
        
        if (robotSenang && robotSedih && robotMarah) {
            robotSenang.classList.add('hidden');
            robotSedih.classList.add('hidden');
            robotMarah.classList.add('hidden');
            
            if (count > 5) {
                robotMarah.classList.remove('hidden');
            } else if (count > 0 && count <= 5) {
                robotSedih.classList.remove('hidden');
            } else {
                robotSenang.classList.remove('hidden');
            }
        }
        
        if (count === 0) {
            shortcutDiv.classList.add('hidden');
            return;
        }
        
        shortcutDiv.classList.remove('hidden');
        countSpan.textContent = count;
        
        // Format dates and create buttons
        listDiv.innerHTML = missingDates.map(date => {
            const dateObj = new Date(date + 'T00:00:00');
            const dayName = dateObj.toLocaleDateString('id-ID', { weekday: 'short' });
            const day = dateObj.getDate();
            const month = dateObj.toLocaleDateString('id-ID', { month: 'short' });
            const formattedDate = `${dayName}, ${day} ${month}`;
            
            return `
                <button 
                    class="missing-report-date-btn"
                    data-date="${date}"
                    title="Klik untuk mengisi laporan harian tanggal ${formattedDate}">
                    <i class="fi fi-rr-document-signed"></i>
                    <span>${formattedDate}</span>
                </button>
            `;
        }).join('');
        
        // Add event listeners to buttons
        listDiv.querySelectorAll('.missing-report-date-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const date = btn.getAttribute('data-date');
                await openDailyReportEditModal(date);
            });
        });
        
    } catch (error) {
        console.error('Error loading missing daily reports:', error);
        qs('#missing-daily-reports-shortcut')?.classList.add('hidden');
    }
}

// Initialize rekap page controls
const rekapControls = qs('#rekap-controls');
if (rekapControls) {
    console.log('Initializing rekap controls...');
    
    // Helper to handle view mode change
    const handleViewModeChange = () => {
        const mode = qs('#rekap-view-mode').value;
        const weekSel = qs('#rekap-week');
        if (mode === 'weekly') {
            weekSel.classList.remove('hidden');
        } else {
            weekSel.classList.add('hidden');
        }
        initRekapPage();
    };

    // Add event listeners
    qs('#rekap-view-mode') && qs('#rekap-view-mode').addEventListener('change', handleViewModeChange);
    
    qs('#rekap-month') && qs('#rekap-month').addEventListener('change', () => {
        console.log('Month changed to:', qs('#rekap-month').value);
        initRekapPage();
    });
    
    qs('#rekap-year') && qs('#rekap-year').addEventListener('change', () => {
        console.log('Year changed to:', qs('#rekap-year').value);
        initRekapPage();
    });
    
    qs('#rekap-week') && qs('#rekap-week').addEventListener('change', () => {
        console.log('Week selector changed to:', qs('#rekap-week').value);
        // Force full reload to update KPI data with new week filter
        initRekapPage(); 
    });
    
    qs('#btn-load-rekap') && qs('#btn-load-rekap').addEventListener('click', () => {
        console.log('Load rekap button clicked');
        initRekapPage();
    });
}

// Modal View Laporan Harian (hanya lihat, tidak bisa edit)
const drUserViewModal = document.createElement('div');
drUserViewModal.id='dr-user-view-modal';
drUserViewModal.className='fixed inset-0 bg-black/50 hidden items-center justify-center z-50';
drUserViewModal.innerHTML = `
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
        <h3 class="text-xl font-bold mb-2">Laporan Harian</h3>
        <div class="text-sm text-gray-500 mb-2" id="dr-user-view-date"></div>
        
        
        <div id="dr-user-view-bukti-section" class="mb-4 hidden">
        <label class="block text-sm text-gray-600 mb-2">Bukti Izin/Sakit:</label>
            <div id="dr-user-view-bukti-container" class="mb-2">
            
        </div>
        </div>
        
        <div id="dr-user-view-content" class="whitespace-pre-wrap border p-3 rounded bg-gray-50 mb-4 min-h-[200px]"></div>
        
        <div id="dr-user-view-evaluation-container" class="mt-4 hidden">
            <h4 class="text-sm font-bold text-gray-700 mb-1">Evaluasi Admin:</h4>
            <p id="dr-user-view-evaluation" class="whitespace-pre-wrap border p-3 rounded bg-gray-100"></p>
    </div>
    
        <div class="flex justify-end gap-2 mt-4">
            <button id="dr-user-view-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
        </div>
    </div>`;
document.body.appendChild(drUserViewModal);

// Modal Edit Laporan Harian (bisa edit, tanpa tombol hapus bukti)
const drUserEditModal = document.createElement('div');
drUserEditModal.id='dr-user-edit-modal';
drUserEditModal.className='fixed inset-0 bg-black/50 hidden items-center justify-center z-50';
drUserEditModal.innerHTML = `
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
        <h3 class="text-xl font-bold mb-2">Laporan Harian</h3>
        <div class="text-sm text-gray-500 mb-2" id="dr-user-edit-date"></div>
        
        
        <div id="dr-user-edit-bukti-section" class="mb-4 hidden">
            <label class="block text-sm text-gray-600 mb-2">Bukti Izin/Sakit:</label>
            <div id="dr-user-edit-bukti-container" class="mb-2">
                
            </div>
            <div id="dr-user-edit-bukti-actions" class="flex gap-2 hidden">
                <button type="button" id="dr-user-edit-bukti-btn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Ganti Bukti</button>
            </div>
        </div>
        
        <textarea id="dr-user-edit-content" class="w-full border rounded p-2" rows="8" placeholder="Tulis detail pekerjaan hari ini..."></textarea>
        
        <div id="dr-user-edit-evaluation-container" class="mt-4 hidden">
        <h4 class="text-sm font-bold text-gray-700 mb-1">Evaluasi Admin:</h4>
            <p id="dr-user-edit-evaluation" class="whitespace-pre-wrap border p-3 rounded bg-gray-100"></p>
    </div>
        
    <div class="flex justify-end gap-2 mt-4">
            <button id="dr-user-edit-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
            <button id="dr-user-edit-save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Simpan</button>
    </div>
    </div>`;
document.body.appendChild(drUserEditModal);

// Izin/Sakit modal handlers
const izinSakitModal = qs('#izin-sakit-modal');
const izinSakitForm = qs('#izin-sakit-form');
const izinSakitBukti = qs('#izin-sakit-bukti');
const izinSakitPreview = qs('#izin-sakit-preview');
const izinSakitPreviewImg = qs('#izin-sakit-preview-img');

// File upload preview with size validation
izinSakitBukti && izinSakitBukti.addEventListener('change', (e) => {
    const file = e.target.files[0];
    const errorDiv = qs('#izin-sakit-error');
    
    if (file) {
        // Check file size (5MB = 5 * 1024 * 1024 bytes)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            errorDiv.textContent = `File terlalu besar. Maksimal 5MB. Ukuran saat ini: ${(file.size / (1024 * 1024)).toFixed(2)}MB`;
            errorDiv.classList.remove('hidden');
            izinSakitPreview.classList.add('hidden');
            return;
        }
        
        // Check file type
        if (!file.type.startsWith('image/')) {
            errorDiv.textContent = 'File harus berupa gambar (JPG, PNG, GIF)';
            errorDiv.classList.remove('hidden');
            izinSakitPreview.classList.add('hidden');
            return;
        }
        
        // Clear error and show preview
        errorDiv.classList.add('hidden');
        const reader = new FileReader();
        reader.onload = (e) => {
            izinSakitPreviewImg.src = e.target.result;
            izinSakitPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        errorDiv.classList.add('hidden');
        izinSakitPreview.classList.add('hidden');
    }
});

// Cancel button
qs('#izin-sakit-cancel') && qs('#izin-sakit-cancel').addEventListener('click', () => {
    izinSakitModal.classList.add('hidden');
    izinSakitForm.reset();
    izinSakitPreview.classList.add('hidden');
});

// Form submit
izinSakitForm && izinSakitForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const type = qs('#izin-sakit-type').value;
    const alasan = qs('#izin-sakit-alasan').value;
    const file = izinSakitBukti.files[0];
    
    if (!type || !alasan || !file) {
        showNotif('Semua field harus diisi', false);
        return;
    }
    
    // Convert file to base64
    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const r = await api('?ajax=submit_izin_sakit', {
                type: type,
                alasan: alasan,
                bukti: e.target.result
            });
            
            if (r.ok) {
                showNotif(r.message, true);
                izinSakitModal.classList.add('hidden');
                izinSakitForm.reset();
                izinSakitPreview.classList.add('hidden');
                
                // Refresh all components
                refreshDashboardComponents();
            } else {
                showNotif(r.message || 'Gagal menyimpan', false);
            }
        } catch (error) {
            console.error('Error submitting izin/sakit:', error);
            showNotif('Terjadi kesalahan', false);
        }
    };
    reader.readAsDataURL(file);
});

// Input keterangan button handler
document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('btn-input-keterangan')) {
        const date = e.target.getAttribute('data-date');
        izinSakitModal.classList.remove('hidden');
        izinSakitModal.classList.add('flex');
    }
});

// Fungsi untuk membuka modal view laporan harian
async function openDailyReportViewModal(date) {
    qs('#dr-user-view-date').textContent = 'Tanggal: ' + date;
        
        const r = await api('?ajax=get_rekap', { month: new Date(date).getMonth()+1, year: new Date(date).getFullYear() });
        const item = (r.data||[]).find(x=> x.date===date);
    
        if(item && item.daily_report){
        qs('#dr-user-view-content').textContent = item.daily_report.content||'';
                if (item.daily_report.evaluation) {
            qs('#dr-user-view-evaluation').textContent = item.daily_report.evaluation;
            qs('#dr-user-view-evaluation-container').classList.remove('hidden');
        } else {
            qs('#dr-user-view-evaluation-container').classList.add('hidden');
                }
            } else {
        qs('#dr-user-view-content').textContent = 'Belum ada laporan harian untuk tanggal ini.';
        qs('#dr-user-view-evaluation-container').classList.add('hidden');
    }
    
    // Cek apakah ada bukti izin/sakit untuk tanggal ini
    if (item && (item.ket === 'izin' || item.ket === 'sakit')) {
        // Get attendance data to find bukti
        const attendanceData = await api('?ajax=get_attendance');
        if (attendanceData.ok && attendanceData.data) {
            const todayRecord = attendanceData.data.find(att => 
                att.jam_masuk_iso && 
                att.jam_masuk_iso.slice(0, 10) === date &&
                (att.ket === 'izin' || att.ket === 'sakit') &&
                att.bukti_izin_sakit
            );
            
            if (todayRecord) {
                // Tampilkan bukti izin/sakit (view only)
                qs('#dr-user-view-bukti-section').classList.remove('hidden');
                qs('#dr-user-view-bukti-container').innerHTML = `
                    <div class="flex justify-center">
                        <img src="${todayRecord.bukti_izin_sakit}" alt="Bukti ${todayRecord.ket}" class="max-w-full max-h-64 object-contain rounded border shadow-lg" style="max-width: 100%; height: auto;">
                    </div>
                    <p class="text-sm text-gray-600 mt-2 text-center">Bukti ${todayRecord.ket.toUpperCase()}</p>
                `;
            } else {
                qs('#dr-user-view-bukti-section').classList.add('hidden');
            }
        }
    } else {
        qs('#dr-user-view-bukti-section').classList.add('hidden');
    }
    
    qs('#dr-user-view-modal').classList.remove('hidden'); 
    qs('#dr-user-view-modal').classList.add('flex');
}

// Fungsi untuk membuka modal edit laporan harian
async function openDailyReportEditModal(date) {
    qs('#dr-user-edit-date').textContent = 'Tanggal: ' + date;
    qs('#dr-user-edit-modal').dataset.date = date;
    
    const r = await api('?ajax=get_rekap', { month: new Date(date).getMonth()+1, year: new Date(date).getFullYear() });
    const item = (r.data||[]).find(x=> x.date===date);
    
    if(item && item.daily_report){
        qs('#dr-user-edit-content').value = item.daily_report.content||'';
        if (item.daily_report.evaluation) {
            qs('#dr-user-edit-evaluation').textContent = item.daily_report.evaluation;
            qs('#dr-user-edit-evaluation-container').classList.remove('hidden');
        } else {
            qs('#dr-user-edit-evaluation-container').classList.add('hidden');
        }
    } else {
        qs('#dr-user-edit-content').value = '';
        qs('#dr-user-edit-evaluation-container').classList.add('hidden');
        }
        
        // Cek apakah ada bukti izin/sakit untuk tanggal ini
        if (item && (item.ket === 'izin' || item.ket === 'sakit')) {
            // Get attendance data to find bukti
            const attendanceData = await api('?ajax=get_attendance');
            if (attendanceData.ok && attendanceData.data) {
                const todayRecord = attendanceData.data.find(att => 
                    att.jam_masuk_iso && 
                    att.jam_masuk_iso.slice(0, 10) === date &&
                    (att.ket === 'izin' || att.ket === 'sakit') &&
                    att.bukti_izin_sakit
                );
                
                if (todayRecord) {
                // Tampilkan bukti izin/sakit (edit mode)
                qs('#dr-user-edit-bukti-section').classList.remove('hidden');
                qs('#dr-user-edit-bukti-container').innerHTML = `
                        <div class="flex justify-center">
                            <img src="${todayRecord.bukti_izin_sakit}" alt="Bukti ${todayRecord.ket}" class="max-w-full max-h-64 object-contain rounded border shadow-lg" style="max-width: 100%; height: auto;">
                        </div>
                        <p class="text-sm text-gray-600 mt-2 text-center">Bukti ${todayRecord.ket.toUpperCase()}</p>
                    `;
                // Show edit button
                qs('#dr-user-edit-bukti-actions').classList.remove('hidden');
                qs('#dr-user-edit-bukti-btn').dataset.date = date;
                } else {
                qs('#dr-user-edit-bukti-section').classList.add('hidden');
                qs('#dr-user-edit-bukti-actions').classList.add('hidden');
                }
            }
        } else {
        qs('#dr-user-edit-bukti-section').classList.add('hidden');
        qs('#dr-user-edit-bukti-actions').classList.add('hidden');
    }
    
    qs('#dr-user-edit-modal').classList.remove('hidden'); 
    qs('#dr-user-edit-modal').classList.add('flex');
}

// Event listener untuk tombol laporan harian
document.addEventListener('click', async (e)=>{
    const target = e.target.closest('.btn-create-dr, .btn-edit-dr, .btn-view-dr');
    if(target){
        const date = target.getAttribute('data-date');
        const isView = target.classList.contains('btn-view-dr');
        const isEdit = target.classList.contains('btn-edit-dr');
        
        if (isView) {
            await openDailyReportViewModal(date);
        } else if (isEdit) {
            await openDailyReportEditModal(date);
        } else {
            // Create new report - use edit modal
            await openDailyReportEditModal(date);
        }
    }
});
// Event handlers untuk modal view laporan harian
qs('#dr-user-view-cancel') && qs('#dr-user-view-cancel').addEventListener('click', ()=>{ 
    qs('#dr-user-view-modal').classList.add('hidden'); 
    qs('#dr-user-view-modal').classList.remove('flex'); 
});

// Event handlers untuk modal edit laporan harian
qs('#dr-user-edit-cancel') && qs('#dr-user-edit-cancel').addEventListener('click', ()=>{ 
    qs('#dr-user-edit-modal').classList.add('hidden'); 
    qs('#dr-user-edit-modal').classList.remove('flex'); 
});

qs('#dr-user-edit-save') && qs('#dr-user-edit-save').addEventListener('click', async ()=>{
    const date = qs('#dr-user-edit-modal').dataset.date; 
    const content = qs('#dr-user-edit-content').value;
    const r = await api('?ajax=save_daily_report', { date, content });
    if(r.ok){ 
        showNotif('Laporan harian disimpan', true);
        qs('#dr-user-edit-modal').classList.add('hidden'); 
        qs('#dr-user-edit-modal').classList.remove('flex'); 
        
        // Refresh all components
        refreshDashboardComponents();
    } else { 
        showNotif(r.message||'Gagal simpan', false); 
    }
});

// Event handler untuk ganti bukti izin/sakit (modal edit)
qs('#dr-user-edit-bukti-btn') && qs('#dr-user-edit-bukti-btn').addEventListener('click', () => {
    const date = qs('#dr-user-edit-bukti-btn').dataset.date;
    // Open edit bukti modal
    qs('#edit-bukti-modal').classList.remove('hidden');
    qs('#edit-bukti-modal').classList.add('flex');
    qs('#edit-bukti-save').dataset.date = date;
    
    // Show current bukti if exists
    const currentImg = qs('#dr-user-edit-bukti-container img');
    if (currentImg) {
        qs('#edit-bukti-current').classList.remove('hidden');
        qs('#edit-bukti-current-img').src = currentImg.src;
    } else {
        // If no current bukti, hide current section
        qs('#edit-bukti-current').classList.add('hidden');
    }
    
    // Reset file input and preview
    qs('#edit-bukti-file').value = '';
    qs('#edit-bukti-preview').classList.add('hidden');
});

// Event handler untuk modal edit bukti
qs('#edit-bukti-cancel') && qs('#edit-bukti-cancel').addEventListener('click', () => {
    qs('#edit-bukti-modal').classList.add('hidden');
    qs('#edit-bukti-modal').classList.remove('flex');
    qs('#edit-bukti-file').value = '';
    qs('#edit-bukti-preview').classList.add('hidden');
    qs('#edit-bukti-current').classList.add('hidden');
});

qs('#edit-bukti-save') && qs('#edit-bukti-save').addEventListener('click', async () => {
    const date = qs('#edit-bukti-save').dataset.date;
    const file = qs('#edit-bukti-file').files[0];
    
    if (!file) {
        showNotif('Pilih file gambar terlebih dahulu', false);
        return;
    }
    
    // Check file size (5MB = 5 * 1024 * 1024 bytes)
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        showNotif(`File terlalu besar. Maksimal 5MB. Ukuran saat ini: ${(file.size / (1024 * 1024)).toFixed(2)}MB`, false);
        return;
    }
    
    // Check file type
    if (!file.type.startsWith('image/')) {
        showNotif('File harus berupa gambar (JPG, PNG, GIF)', false);
        return;
    }
    
    // Convert file to base64
    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const r = await api('?ajax=update_bukti_izin_sakit', {
                date: date,
                action_type: 'update',
                bukti: e.target.result
            });
            
            if (r.ok) {
                showNotif('Bukti berhasil diperbarui');
                qs('#edit-bukti-modal').classList.add('hidden');
                qs('#edit-bukti-modal').classList.remove('flex');
                qs('#edit-bukti-file').value = '';
                qs('#edit-bukti-preview').classList.add('hidden');
                qs('#edit-bukti-current').classList.add('hidden');
                
                // Refresh all components
                refreshDashboardComponents();
                
                // Refresh the daily report modal to show updated bukti if it's open
                const drEditModal = qs('#dr-user-edit-modal');
                if (drEditModal && !drEditModal.classList.contains('hidden')) {
                    openDailyReportEditModal(date);
                }
            } else {
                showNotif(r.message || 'Gagal memperbarui bukti', false);
            }
        } catch (error) {
            console.error('Error updating bukti:', error);
            showNotif('Terjadi kesalahan', false);
        }
    };
    reader.readAsDataURL(file);
});

// File upload preview for edit bukti modal
qs('#edit-bukti-file') && qs('#edit-bukti-file').addEventListener('change', (e) => {
    const file = e.target.files[0];
    const preview = qs('#edit-bukti-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            qs('#edit-bukti-preview').src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('hidden');
    }
});

// Helper function for month names
function monthName(monthIndex) {
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return months[monthIndex] || '';
}

// Tambahkan state untuk paginasi di atas fungsi renderMonthly
let currentMonthlyPageYear = new Date().getFullYear();

async function renderMonthly() {
    // Load settings for max months back and end year
    let monthlyReportEndYear = 2026; // Default: 2026
    try {
        const settingsJson = await api('?ajax=get_settings', {}, { suppressModal: true, cache: false }); // Settings fetched fresh
        if (settingsJson.ok && settingsJson.data) {
            if (settingsJson.data.max_monthly_report_months_back) {
                window.maxMonthlyReportMonthsBack = parseInt(settingsJson.data.max_monthly_report_months_back.value) || 999;
            } else {
                window.maxMonthlyReportMonthsBack = 999; // Default: no limit
            }
            if (settingsJson.data.monthly_report_end_year) {
                monthlyReportEndYear = parseInt(settingsJson.data.monthly_report_end_year.value) || 2026;
            }
        } else {
            window.maxMonthlyReportMonthsBack = 999; // Default: no limit
        }
    } catch (e) {
        window.maxMonthlyReportMonthsBack = 999; // Default: no limit on error
    }
    
    // Validate currentMonthlyPageYear - should be between 2025 and monthlyReportEndYear
    if (currentMonthlyPageYear < 2025) {
        currentMonthlyPageYear = 2025;
    }
    if (currentMonthlyPageYear > monthlyReportEndYear) {
        currentMonthlyPageYear = monthlyReportEndYear;
    }
    
    const j = await api('?ajax=get_monthly_reports', {}, { suppressModal: true, cache: false });
    const list = (j.data || []);
    const body = qs('#table-monthly-body');
    if (!body) return;
    body.innerHTML = ''; // Kosongkan tabel body

    const monthName = (m) => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][m - 1];

    const year = currentMonthlyPageYear; // Gunakan tahun dari state
    const allMonths = Array.from({ length: 12 }, (_, i) => i + 1);

    // Logic untuk aturan waktu (2 bulan terakhir)
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1; // 1-12

    allMonths.forEach(m => {
        // Handle case where year/month might be 0 or invalid
        let item = list.find(it => {
            const itemYear = parseInt(it.year) || 0;
            const itemMonth = parseInt(it.month) || 0;
            return itemMonth === m && itemYear === year;
        });
        
        // If no item found for this month, check if there's a record with year=0 or month=0 for this month
        if (!item && m === 8 && year === 2025) {
            item = list.find(it => {
                const itemYear = parseInt(it.year) || 0;
                const itemMonth = parseInt(it.month) || 0;
                return (itemYear === 0 || itemMonth === 0) && it.status === 'approved';
            });
        }
        
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50 text-center';
        const label = `${monthName(m)} ${year}`;

        let actionBtn;
        let statusBadge;

        // Cek apakah bulan ini valid untuk diedit/dibuat
        // Check settings for max months back (default: no limit, allow all months)
        // For now, allow all months - can be restricted via settings later
        const maxMonthsBack = window.maxMonthlyReportMonthsBack || 999; // Default: no limit
        const reportDate = new Date(year, m - 1, 1);
        const todayDate = new Date(currentYear, currentMonth - 1, 1);
        const monthsDiff = (todayDate.getFullYear() - reportDate.getFullYear()) * 12 + (todayDate.getMonth() - reportDate.getMonth());
        const isEditableTime = monthsDiff <= maxMonthsBack; // Allow all months by default

        if (item) { // Jika laporan sudah ada
            const isApproved = item.status === 'approved';
            const isDraft = item.status === 'draft';
            const isSubmitted = item.status === 'belum di approve';
            
            if (isApproved) {
                // Jika sudah di-approve, hanya bisa view (regardless of timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
            } else if (isDraft) {
                // Jika draft, bisa view dan edit (jika dalam timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
                if (isEditableTime) {
                    actionBtn += ` <button class="btn-edit-month text-yellow-600 font-bold ml-2" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-sr-pen-square"></i> Edit Draft</button>`;
                }
            } else if (isSubmitted) {
                // Jika belum di approve, bisa view dan edit (jika dalam timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
                if (isEditableTime) {
                    actionBtn += ` <button class="btn-edit-month text-yellow-600 font-bold ml-2" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-sr-pen-square"></i> Edit</button>`;
                }
            } else {
                // Jika disapproved, bisa view dan edit (jika dalam timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
                if (isEditableTime) {
                    actionBtn += ` <button class="btn-edit-month text-yellow-600 font-bold ml-2" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-sr-pen-square"></i> Edit</button>`;
                }
            }
            
            // Status badge
            if (isApproved) {
                statusBadge = `<span class="badge badge-green">Di-approve</span>`;
            } else if (item.status === 'disapproved') {
                statusBadge = `<span class="badge badge-red">Tidak di-approve</span>`;
            } else if (isDraft) {
                statusBadge = `<span class="badge badge-gray">Draft</span>`;
            } else if (isSubmitted) {
                statusBadge = `<span class="badge badge-blue">Belum di Approve</span>`;
            } else {
                statusBadge = `<span class="badge badge-gray">${item.status}</span>`;
            }
        } else { // Jika laporan belum ada
            if (isEditableTime) {
                actionBtn = `<button class="btn-create-month bg-emerald-500 hover:bg-emerald-600 text-white btn-pill" data-year="${year}" data-month="${m}">Buat</button>`;
            } else {
                actionBtn = `<span class="text-gray-400">Not Available</span>`;
            }
            statusBadge = `<span class="badge badge-orange">Belum ada laporan</span>`;
        }

        tr.innerHTML = `
            <td class="py-2 px-4">${label}</td>
            <td class="py-2 px-4">${actionBtn}</td>
            <td class="py-2 px-4">${statusBadge}</td>`;
        body.appendChild(tr);
    });
    
    // Hapus dan buat ulang tombol paginasi - generate from 2025 to monthlyReportEndYear
    let paginationDiv = qs('#monthly-pagination');
    if (paginationDiv) paginationDiv.remove();
    
    paginationDiv = document.createElement('div');
    paginationDiv.id = 'monthly-pagination';
    paginationDiv.className = 'mt-4 flex justify-center gap-2 flex-wrap';
    
    // Generate year buttons from 2025 to monthlyReportEndYear
    const yearButtons = [];
    for (let y = 2025; y <= monthlyReportEndYear; y++) {
        yearButtons.push(`<button data-year="${y}" class="page-btn px-4 py-2 rounded ${currentMonthlyPageYear === y ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300'}">${y}</button>`);
    }
    paginationDiv.innerHTML = yearButtons.join('');
    body.closest('.overflow-x-auto').insertAdjacentElement('afterend', paginationDiv);
}


async function renderAdminMonthly(){
    const mSel = qs('#am-month'); const ySel = qs('#am-year'); const sSel = qs('#am-startup');
    if(mSel && mSel.options.length<=2){
        const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        months.forEach((m,i)=>{ const o=document.createElement('option'); o.value=String(i+1); o.textContent=m; mSel.appendChild(o); });
        const yNow=new Date().getFullYear(); for(let y=yNow-2;y<=yNow+1;y++){ const o=document.createElement('option'); o.value=String(y); o.textContent=String(y); ySel.appendChild(o);}
    }
    if(sSel && sSel.options.length<=1){
        const j = await api('?ajax=get_startups', {}, { suppressModal: true, cache: true, ttl: 300000 });
        if(j.ok && j.data){
            j.data.forEach(startup => {
                const o = document.createElement('option');
                o.value = startup;
                o.textContent = startup;
                sSel.appendChild(o);
            });
        }
    }
    const payload = { term: qs('#am-search')?.value||'', startup: qs('#am-startup')?.value||'', month: qs('#am-month')?.value||'', year: qs('#am-year')?.value||'' };
    const r = await api('?ajax=admin_get_monthly_reports', payload);
    const j = r.data||[];
    const filteredReports = j.filter(it => it.status !== 'draft');
    const monthName=(m)=>['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][m-1];

    renderPaginatedTable('am-body', filteredReports, (it) => {
        const tr=document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        const label = `${monthName(parseInt(it.month))} ${it.year}`;
        const detailBtn = `<button class="btn-view-month-detail text-blue-600 font-bold text-center" data-id="${it.id}"><i class="fi fi-ss-eye text-xl"></i></button>`;
        const statusBadge = it.status==='approved'? `<span class="badge badge-green">Di-approve</span>`:(it.status==='disapproved'?`<span class="badge badge-red">Tidak di-approve</span>`:`<span class="badge badge-blue">Belum di Approve</span>`);
        const actions = (it.status === 'belum di approve' || it.status === 'approved' || it.status === 'disapproved') ?
            `<button class="btn-am-approve bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded mr-1" data-id="${it.id}">Approve</button>
            <button class="btn-am-disapprove bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded" data-id="${it.id}">Disapprove</button>` : '';

        tr.innerHTML = `
            <td class="py-2 px-4">${label}</td>
            <td class="py-2 px-4">${it.nama||''}</td>
            <td class="py-2 px-4">${it.startup||'-'}</td>
            <td class="py-2 px-4">${detailBtn}</td>
            <td class="py-2 px-4">${statusBadge}</td>
            <td class="py-2 px-4">${actions}</td>`;
        return tr;
    }, {
        colSpan: 6,
        emptyMessage: 'Tidak ada data.',
        onPageChange: renderAdminMonthly
    });
}

['#am-search','#am-startup','#am-month','#am-year'].forEach(sel=>{ if(qs(sel)) qs(sel).addEventListener('input', () => { resetTablePage('am-body'); renderAdminMonthly(); }); });
qs('#am-reset') && qs('#am-reset').addEventListener('click', ()=>{ if(qs('#am-search')) qs('#am-search').value=''; if(qs('#am-startup')) qs('#am-startup').value=''; if(qs('#am-month')) qs('#am-month').value=''; if(qs('#am-year')) qs('#am-year').value=''; renderAdminMonthly(); });

// Export event handlers (Delegated)
// Global Export Wrappers for Inline Onclick
window.openExportDailyModal = function() {
    qs('#export-presensi-modal').classList.remove('hidden');
};

window.triggerExportMonthly = function() {
    const startup = qs('#am-startup')?.value || '';
    const month = qs('#am-month')?.value || '';
    const year = qs('#am-year')?.value || '';
    const term = qs('#am-search')?.value || '';
    
    const params = new URLSearchParams({
        startup: startup,
        month: month,
        year: year,
        term: term,
        format: 'per_employee'
    });
    
    window.location.href = `/export/monthly?${params.toString()}`;
};

window.triggerExportKPI = function() {
    initKpiGlobals();
    const type = kpiFilterType ? kpiFilterType.value : 'period';
    const month = kpiFilterMonth ? kpiFilterMonth.value : '';
    const year = kpiFilterYear ? kpiFilterYear.value : '';
    
    const params = new URLSearchParams();
    params.append('filter_type', type);
    if (type === 'monthly' && month && year) {
        params.append('month', month);
        params.append('year', year);
    }
    const url = `/export/kpi?${params.toString()}`;
    console.log('Exporting KPI (Global):', url);
    window.location.href = url;
};

// Keep delegation as backup
document.addEventListener('click', (e) => {
    // Handlers kept for redundancy
});


// Modal Logic
qs('#export-p-range') && qs('#export-p-range').addEventListener('change', (e) => {
    const opts = qs('#export-p-monthly-opts');
    if (e.target.value === 'monthly') opts.classList.remove('hidden');
    else opts.classList.add('hidden');
});

qs('#export-presensi-form') && qs('#export-presensi-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const range = qs('#export-p-range').value;
    const year = qs('#export-p-year').value;
    const format = qs('input[name="export_format"]:checked').value;
    
    const selectedMonths = Array.from(document.querySelectorAll('.export-month-cb:checked')).map(cb => cb.value);
    
    const params = new URLSearchParams();
    params.append('filter_type', range);
    if(range === 'monthly') {
        if (selectedMonths.length === 0) {
            customAlert('Silakan pilih minimal satu bulan', 'Peringatan');
            return;
        }
        params.append('months', selectedMonths.join(','));
        params.append('year', year);
    }
    params.append('format', format);
    
    window.location.href = `/export/daily?${params.toString()}`;
    qs('#export-presensi-modal').classList.add('hidden');
});

// Settings functions
async function renderSettings() {
    try {
        const result = await api('?ajax=get_settings', {}, { suppressModal: true, cache: false });
        
        if (result.ok && result.data) {
            const settings = result.data;
            
            // Format hour (e.g., "8") to "08:00" for type="time"
            const formatTime = (h) => {
                if(!h) return h;
                let hour = parseInt(h);
                if(isNaN(hour)) return h;
                return (hour < 10 ? '0' : '') + hour + ':00';
            };

            qs('#max-ontime-hour').value = formatTime(settings.max_ontime_hour?.value || '8');
            qs('#min-checkout-hour').value = formatTime(settings.min_checkout_hour?.value || '17');
            if(qs('#wfo-address')) qs('#wfo-address').value = settings.wfo_address?.value || '';
            if(qs('#wfo-radius')) qs('#wfo-radius').value = settings.wfo_radius_m?.value || '1200';
            if(qs('#attendance-period-end')) qs('#attendance-period-end').value = settings.attendance_period_end?.value || '';
            if(qs('#kpi-late-penalty')) qs('#kpi-late-penalty').value = settings.kpi_late_penalty_per_minute?.value || '1';
            if(qs('#kpi-izin-sakit')) qs('#kpi-izin-sakit').value = settings.kpi_izin_sakit_score?.value || '85';
            if(qs('#kpi-alpha')) qs('#kpi-alpha').value = settings.kpi_alpha_score?.value || '0';
            if(qs('#kpi-overtime-bonus')) qs('#kpi-overtime-bonus').value = settings.kpi_overtime_bonus?.value || '5';
            if(qs('#kpi-late-max-deduction')) qs('#kpi-late-max-deduction').value = settings.kpi_late_max_deduction?.value || '100';
            if(qs('#kpi-late-tolerance')) qs('#kpi-late-tolerance').value = settings.kpi_late_tolerance_minutes?.value || '0';
            if(qs('#max-daily-report-days-back')) qs('#max-daily-report-days-back').value = settings.max_daily_report_days_back?.value || '5';
            if(qs('#max-monthly-report-months-back')) qs('#max-monthly-report-months-back').value = settings.max_monthly_report_months_back?.value || '999';
            if(qs('#monthly-report-end-year')) qs('#monthly-report-end-year').value = settings.monthly_report_end_year?.value || '2026';
            if(qs('#face-recognition-threshold')) qs('#face-recognition-threshold').value = settings.face_recognition_threshold?.value || '0.38';
            if(qs('#face-recognition-min-confidence')) qs('#face-recognition-min-confidence').value = settings.face_recognition_min_confidence?.value || '65';
            if(qs('#face-recognition-input-size')) qs('#face-recognition-input-size').value = settings.face_recognition_input_size?.value || '416';
            if(qs('#face-recognition-score-threshold')) qs('#face-recognition-score-threshold').value = settings.face_recognition_score_threshold?.value || '0.35';
            if(qs('#face-recognition-quality-threshold')) qs('#face-recognition-quality-threshold').value = settings.face_recognition_quality_threshold?.value || '0.55';
            if(qs('#geocode-timeout')) qs('#geocode-timeout').value = settings.geocode_timeout?.value || '3';
            if(qs('#geocode-accuracy-radius')) qs('#geocode-accuracy-radius').value = settings.geocode_accuracy_radius?.value || '50';
            
            if(qs('#help-wa-number')) qs('#help-wa-number').value = settings.help_wa_number?.value || '6287890004465';
            if(qs('#help-wa-message')) qs('#help-wa-message').value = settings.help_wa_message?.value || 'Hai Admin, Saya ingin meminta bantuan terkait ....';
            
            // WFO API settings
            if(qs('#wfo-mode')) qs('#wfo-mode').value = settings.wfo_mode?.value || 'api';
            if(qs('#wfo-api-provider')) qs('#wfo-api-provider').value = settings.wfo_api_provider?.value || 'ipinfo';
            if(qs('#wfo-api-token')) qs('#wfo-api-token').value = settings.wfo_api_token?.value || '';
            if(qs('#wfo-api-org-keywords')) qs('#wfo-api-org-keywords').value = settings.wfo_api_org_keywords?.value || '';
            if(qs('#wfo-api-asn-list')) qs('#wfo-api-asn-list').value = settings.wfo_api_asn_list?.value || '';
            if(qs('#wfo-api-cidr-list')) qs('#wfo-api-cidr-list').value = settings.wfo_api_cidr_list?.value || '';
            if(qs('#wfo-wifi-ssids')) qs('#wfo-wifi-ssids').value = settings.wfo_wifi_ssids?.value || 'Telkom University,TelU,WiFi Telkom University';
            if(qs('#wfo-require-wifi')) qs('#wfo-require-wifi').value = settings.wfo_require_wifi?.value || '1';
            
            // Trigger toggle to show relevant fields
            if (typeof toggleWfoFields === 'function') {
                toggleWfoFields();
            }
        }
    } catch (error) {
        console.error('Error loading settings:', error);
        showNotif('Gagal memuat pengaturan', false);
    }
}

// Address search functionality
let addressSearchTimeout;
let selectedAddress = null;

// Initialize address search when settings page loads
function initAddressSearch() {
    const addressInput = qs('#wfo-address');
    const suggestionsDiv = qs('#address-suggestions');
    
    if (!addressInput || !suggestionsDiv) return;
    
    addressInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        if (addressSearchTimeout) {
            clearTimeout(addressSearchTimeout);
        }
        
        // Hide suggestions if query is empty
        if (query.length < 3) {
            suggestionsDiv.classList.add('hidden');
            return;
        }
        
        // Debounce search
        addressSearchTimeout = setTimeout(() => {
            searchAddresses(query);
        }, 300);
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!addressInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.classList.add('hidden');
        }
    });
    
    // Handle keyboard navigation
    addressInput.addEventListener('keydown', (e) => {
        const suggestions = suggestionsDiv.querySelectorAll('.suggestion-item');
        const activeSuggestion = suggestionsDiv.querySelector('.suggestion-item.active');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const next = activeSuggestion.nextElementSibling;
                if (next) {
                    next.classList.add('active');
                } else {
                    suggestions[0]?.classList.add('active');
                }
            } else {
                suggestions[0]?.classList.add('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const prev = activeSuggestion.previousElementSibling;
                if (prev) {
                    prev.classList.add('active');
                } else {
                    suggestions[suggestions.length - 1]?.classList.add('active');
                }
            } else {
                suggestions[suggestions.length - 1]?.classList.add('active');
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.click();
            }
        } else if (e.key === 'Escape') {
            suggestionsDiv.classList.add('hidden');
        }
    });
}

async function searchAddresses(query) {
    try {
        const res = await fetch(`?ajax=search_address&q=${encodeURIComponent(query)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const json = await res.json();
        
        if (!json.ok) throw new Error(json.message || 'Search failed');
        
        let allResults = json.data || [];
        
        // If still no results, create a manual entry
        if (allResults.length === 0) {
            allResults = [{
                display_name: query,
                lat: '',
                lon: '',
                place_id: 'manual',
                type: 'manual'
            }];
        }
        
        displayAddressSuggestions(allResults.slice(0, 5)); // Limit to 5 results
        
    } catch (error) {
        console.error('Error searching addresses:', error);
        // Fallback: show a simple suggestion
        displayAddressSuggestions([{
            display_name: query,
            lat: '',
            lon: '',
            place_id: 'manual',
            type: 'manual'
        }]);
    }
}

function displayAddressSuggestions(results) {
    const suggestionsDiv = qs('#address-suggestions');
    if (!suggestionsDiv) return;
    
    if (results.length === 0) {
        suggestionsDiv.innerHTML = '<div class="p-3 text-gray-500 text-sm">Tidak ada hasil ditemukan</div>';
    } else {
        suggestionsDiv.innerHTML = results.map((result, index) => {
            const isManual = result.type === 'manual' || result.place_id === 'manual';
            const hasCoordinates = result.lat && result.lon;
            
            return `
                <div class="suggestion-item p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 ${index === 0 ? 'active' : ''}" 
                     data-address="${result.display_name}" 
                     data-lat="${result.lat || ''}" 
                     data-lon="${result.lon || ''}">
                    <div class="font-medium text-sm">${result.display_name}</div>
                    ${hasCoordinates ? 
                        `<div class="text-xs text-gray-500 mt-1">Koordinat: ${result.lat}, ${result.lon}</div>` : 
                        `<div class="text-xs text-orange-500 mt-1">${isManual ? 'Manual entry - koordinat akan diisi otomatis' : 'Koordinat tidak tersedia'}</div>`
                    }
                    ${isManual ? '<div class="text-xs text-blue-500 mt-1">💡 Pilih untuk menggunakan alamat ini</div>' : ''}
                </div>
            `;
        }).join('');
        
        // Add click handlers
        suggestionsDiv.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                selectAddress(item);
            });
            
            item.addEventListener('mouseenter', () => {
                suggestionsDiv.querySelectorAll('.suggestion-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });
    }
    
    suggestionsDiv.classList.remove('hidden');
}

async function selectAddress(item) {
    const address = item.dataset.address;
    let lat = item.dataset.lat;
    let lon = item.dataset.lon;
    
    // Coordinates are now provided by searchAddresses or handled server-side during save
    
    // Update input field
    const addressInput = qs('#wfo-address');
    if (addressInput) {
        addressInput.value = address;
    }
    
    // Store selected address data
    selectedAddress = {
        address: address,
        lat: lat,
        lon: lon
    };
    
    // Show selected address info
    const infoDiv = qs('#selected-address-info');
    const addressText = qs('#selected-address-text');
    const coordinatesSpan = qs('#selected-coordinates');
    
    if (infoDiv && addressText && coordinatesSpan) {
        addressText.textContent = address;
        if (lat && lon) {
            coordinatesSpan.textContent = `${lat}, ${lon}`;
        } else {
            coordinatesSpan.textContent = 'Koordinat akan diisi otomatis saat disimpan';
        }
        infoDiv.classList.remove('hidden');
    }
    
    // Hide suggestions
    const suggestionsDiv = qs('#address-suggestions');
    if (suggestionsDiv) {
        suggestionsDiv.classList.add('hidden');
    }
}

// Settings save logic moved to settings.php for isolation and robustness

qs('#reset-settings') && qs('#reset-settings').addEventListener('click', () => {
    qs('#max-ontime-hour').value = '8';
    qs('#min-checkout-hour').value = '17';
    if(qs('#help-wa-number')) qs('#help-wa-number').value = '6287890004465';
    if(qs('#help-wa-message')) qs('#help-wa-message').value = 'Hai Admin, Saya ingin meminta bantuan terkait ....';
    if(qs('#kpi-late-penalty')) qs('#kpi-late-penalty').value = '1';
    if(qs('#kpi-izin-sakit')) qs('#kpi-izin-sakit').value = '85';
    if(qs('#kpi-alpha')) qs('#kpi-alpha').value = '0';
    if(qs('#kpi-overtime-bonus')) qs('#kpi-overtime-bonus').value = '5';
    if(qs('#kpi-late-max-deduction')) qs('#kpi-late-max-deduction').value = '100';
    if(qs('#kpi-late-tolerance')) qs('#kpi-late-tolerance').value = '0';
    showNotif('Pengaturan direset ke default', true);
});

// Auto-detect WFO button handler
qs('#auto-detect-wfo') && qs('#auto-detect-wfo').addEventListener('click', async () => {
    const button = qs('#auto-detect-wfo');
    const resultDiv = qs('#auto-detect-result');
    const orgDiv = qs('#detect-org');
    const asnDiv = qs('#detect-asn');
    const ipDiv = qs('#detect-ip');
    
    button.disabled = true;
    button.textContent = '🔄 Mendeteksi...';
    
    try {
        // Get current IP
        const ipResponse = await fetch('https://api.ipify.org?format=json');
        const ipData = await ipResponse.json();
        const currentIp = ipData.ip;
        
        // Get IP info using current provider setting
        const provider = qs('#wfo-api-provider')?.value || 'ipinfo';
        const token = qs('#wfo-api-token')?.value || '';
        
        let apiUrl = '';
        if (provider === 'ipinfo') {
            apiUrl = `https://ipinfo.io/${currentIp}/json${token ? `?token=${token}` : ''}`;
        } else if (provider === 'ipapi') {
            apiUrl = `https://ipapi.co/${currentIp}/json/`;
        } else {
            apiUrl = `http://ip-api.com/json/${currentIp}?fields=status,message,org,as,asname,query`;
        }
        
        const headers = {};
        if (provider === 'ipapi' && token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const infoResponse = await fetch(apiUrl, { headers });
        const infoData = await infoResponse.json();
        
        // Extract organization and ASN based on provider
        let org = '';
        let asn = '';
        
        if (provider === 'ipinfo') {
            org = infoData.company?.name || infoData.org || '';
            asn = infoData.org ? infoData.org.split(' ')[0] : '';
        } else if (provider === 'ipapi') {
            org = infoData.org || infoData.company || '';
            asn = infoData.asn || infoData.as || '';
        } else {
            org = infoData.org || infoData.asname || '';
            asn = infoData.as || '';
        }
        
        // Display results with guards
        if (ipDiv) ipDiv.innerHTML = `<strong>IP:</strong> ${currentIp}`;
        if (orgDiv) orgDiv.innerHTML = `<strong>Organisasi:</strong> ${org || 'Tidak ditemukan'}`;
        if (asnDiv) asnDiv.innerHTML = `<strong>ASN:</strong> ${asn || 'Tidak ditemukan'}`;
        
        if (resultDiv) resultDiv.classList.remove('hidden');
        
        // Auto-fill if organization contains Telkom University
        if (org && org.toLowerCase().includes('telkom')) {
            const orgKeywordsEl = qs('#wfo-api-org-keywords');
            if (orgKeywordsEl) {
                const currentOrgKeywords = orgKeywordsEl.value || '';
                if (!currentOrgKeywords.includes(org)) {
                    const newKeywords = currentOrgKeywords ? `${currentOrgKeywords}, ${org}` : org;
                    orgKeywordsEl.value = newKeywords;
                    showNotif(`Organisasi "${org}" ditambahkan ke kata kunci WFO`, true);
                }
            }
        }
        
        if (asn && asn.startsWith('AS')) {
            const asnListEl = qs('#wfo-api-asn-list');
            if (asnListEl) {
                const currentAsnList = asnListEl.value || '';
                if (!currentAsnList.includes(asn)) {
                    const newAsnList = currentAsnList ? `${currentAsnList}, ${asn}` : asn;
                    asnListEl.value = newAsnList;
                    showNotif(`ASN "${asn}" ditambahkan ke daftar ASN WFO`, true);
                }
            }
        }
        
    } catch (error) {
        console.error('Error detecting WFO:', error);
        if (typeof showNotif === 'function') {
            showNotif('Gagal mendeteksi informasi IP. Periksa koneksi internet atau token API.', false);
        }
        if (resultDiv) resultDiv.classList.add('hidden');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Auto-Detect WFO dari IP Admin Saat Ini';
        }
    }
});

// Dashboard functions
// dashboardCharts is declared globally at top

function updateDashboardClock() {
    const clock = qs('#dash-realtime-clock');
    if (!clock) return;
    
    // Use server offset if available, else fallback to browser time
    const now = window.serverTimeOffset ? new Date(Date.now() + window.serverTimeOffset) : new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    clock.textContent = `${h}:${m}`;
}
setInterval(updateDashboardClock, 60000); // Update every minute

async function renderDashboard() {
    try {
        const result = await api('?ajax=get_dashboard_data', {}, { suppressModal: true, cache: true, ttl: 30000 });
        
        if (!result.ok) {
            showNotif('Gagal memuat data dashboard', false);
            return;
        }
        
        const data = result.data;
        
        // Update summary cards with guards
        const setElText = (id, text) => { const el = qs(id); if (el) el.textContent = text; };
        setElText('#totalEmployees', data.summary.total_employees);
        setElText('#presentToday', data.summary.present_today);
        setElText('#lateToday', data.summary.late_today);
        setElText('#absentToday', data.summary.absent_today);
        
        // Update daily report statistics
        if (data.daily_report_stats) {
            setElText('#employeesWithoutReports', data.daily_report_stats.employees_without_reports || 0);
            setElText('#totalMissingReports', data.daily_report_stats.total_missing_reports || 0);
            
            // Render employee list
            const employeeListDiv = qs('#daily-report-employees-list');
            if (employeeListDiv && data.daily_report_stats.employee_details) {
                const employees = data.daily_report_stats.employee_details;
                if (employees.length > 0) {
                    employeeListDiv.innerHTML = `
                        <div class="bg-white rounded-2xl p-4 border border-orange-100">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Pegawai Belum Isi Laporan (Top 10)</h4>
                            <div class="space-y-2 max-h-64 overflow-y-auto pr-1 custom-scrollbar">
                                ${employees.map((emp, index) => `
                                    <div class="flex items-center justify-between p-2 hover:bg-orange-50 rounded-xl transition-all border border-transparent hover:border-orange-100">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <div class="relative flex-shrink-0">
                                                <img src="${emp.foto_base64 || 'https://ui-avatars.com/api/?background=f97316&color=fff&name=' + encodeURIComponent(emp.nama) + '&size=80'}" 
                                                     alt="${emp.nama}" 
                                                     class="w-10 h-10 rounded-full border-2 border-white shadow-sm" style="object-fit: cover;">
                                                <div class="absolute -top-1 -right-1 bg-orange-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold">
                                                    ${index + 1}
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-bold text-gray-800 truncate">${emp.nama}</p>
                                                <p class="text-[10px] text-gray-500">${emp.missing_count} laporan hilang</p>
                                            </div>
                                        </div>
                                        <div class="ml-2">
                                            <span class="bg-orange-100 text-orange-700 text-[10px] font-bold px-2 py-1 rounded-lg">
                                                ${emp.missing_count}
                                            </span>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } else {
                employeeListDiv.innerHTML = `
                        <div class="bg-gray-50 rounded-2xl p-8 border border-dashed border-gray-200 text-center text-gray-400">
                            <i class="fi fi-rr-badge-check text-3xl mb-2 text-emerald-400"></i>
                            <p class="text-sm font-medium">Semua pegawai sudah mengisi laporan harian</p>
                        </div>
                    `;
                }
            }
        }
        
        // Render charts
        renderTodayLateChart(data.today_late);
        renderMonthlyPerformanceCharts(data.monthly_stats);
        renderAttendanceTrendChart(data.attendance_trend);
        
        // Initialize KPI filter options first
        initKPIFilterOptions();
        
        // Load KPI data
        if (qs('#kpi-table-body')) loadKPIData();
        
        // Update clock immediately
        updateDashboardClock();
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showNotif('Gagal memuat data dashboard', false);
    }
}

function renderTodayLateChart(todayLateData) {
    const ctx = qs('#todayLateChart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (dashboardCharts.todayLate) {
        dashboardCharts.todayLate.destroy();
    }
    
    if (todayLateData.length === 0) {
        ctx.style.display = 'none';
        ctx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada pegawai yang terlambat hari ini</div>';
        return;
    }
    
    ctx.style.display = 'block';
    
    // Create a horizontal bar chart with employee photos
    const chartContainer = ctx.parentElement;
    chartContainer.innerHTML = `
        <div class="space-y-3">
            ${todayLateData.map((item, index) => {
                const checkInTime = item.jam_masuk ? item.jam_masuk.substring(0, 5) : 'N/A';
                const delayMinutes = item.jam_masuk ? 
                    Math.max(0, (parseInt(item.jam_masuk.split(':')[0]) - 8) * 60 + parseInt(item.jam_masuk.split(':')[1])) : 0;
                
                return `
                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between group">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="relative flex-shrink-0">
                                <img src="${item.foto_base64 || 'https://ui-avatars.com/api/?background=ef4444&color=fff&name=' + encodeURIComponent(item.nama) + '&size=128'}" 
                                     alt="${item.nama}" 
                                     class="w-12 h-12 rounded-2xl border-2 border-white shadow-sm object-cover group-hover:scale-105 transition-transform">
                                <div class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] rounded-lg px-1.5 py-0.5 font-bold shadow-sm">
                                    #${index + 1}
                                </div>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-bold text-gray-800 truncate">${item.nama}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-red-500 bg-red-50 px-2 py-0.5 rounded-lg">Terlambat</span>
                                    <span class="text-[10px] text-gray-400">${delayMinutes} menit</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0 pl-4">
                            <div class="text-lg font-black text-red-600 leading-none">${checkInTime}</div>
                            <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-1">Check-in</div>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderMonthlyPerformanceCharts(monthlyStats) {
    // Helper function to convert time string to seconds for comparison
    const timeToSeconds = (timeStr) => {
        if (!timeStr) return 0;
        const parts = timeStr.split(':');
        return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseInt(parts[2] || 0);
    };
    
    // Most Frequently Late Chart
    const mostLateCtx = qs('#most-late-list');
    if (mostLateCtx) {
        if (dashboardCharts.mostLate) {
            dashboardCharts.mostLate.destroy();
        }
        
        // Sort by late_count DESC, then by avg_late_time DESC (most late time first)
        const sortedLate = monthlyStats
            .filter(item => item.late_count > 0)
            .sort((a, b) => {
                if (b.late_count !== a.late_count) {
                    return b.late_count - a.late_count;
                }
                // If counts are equal, sort by average late time (later time = more late)
                const timeA = timeToSeconds(a.avg_late_time);
                const timeB = timeToSeconds(b.avg_late_time);
                return timeB - timeA; // Later time (higher seconds) comes first
            });
        
        const topLate = sortedLate.slice(0, 5);
        
        if (topLate.length === 0) {
            mostLateCtx.style.display = 'none';
            mostLateCtx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada data keterlambatan bulan ini</div>';
        } else {
            mostLateCtx.style.display = 'block';
            
            // Create bar chart with employee photos
            const lateContainer = mostLateCtx.parentElement;
            lateContainer.innerHTML = `
                <div class="grid grid-cols-1 gap-3">
                    ${topLate.map((item, index) => {
                        const maxLate = Math.max(...topLate.map(x => x.late_count));
                        const percentage = (item.late_count / maxLate) * 100;
                        
                        return `
                            <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm group hover:shadow-md transition-all">
                                <div class="flex items-center gap-4 mb-3">
                                    <div class="relative flex-shrink-0">
                                        <img src="${item.foto_base64 || 'https://ui-avatars.com/api/?background=ef4444&color=fff&name=' + encodeURIComponent(item.nama) + '&size=96'}" 
                                             alt="${item.nama}" 
                                             class="w-12 h-12 rounded-xl border-2 border-white shadow-sm object-cover group-hover:rotate-3 transition-transform">
                                        <div class="absolute -top-2 -right-2 bg-red-600 text-white text-[10px] rounded-lg w-5 h-5 flex items-center justify-center font-black shadow-sm">
                                            ${index + 1}
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-bold text-gray-800 truncate">${item.nama}</h4>
                                        <p class="text-[10px] text-red-500 font-bold uppercase tracking-wider">${item.late_count}x Terlambat</p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="text-xl font-black text-red-600 leading-none">${item.late_count}</div>
                                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-1">Total</div>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-gradient-to-r from-red-400 to-red-600 h-full rounded-full transition-all duration-1000" 
                                         style="width: ${percentage}%"></div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
    }
    
    // Most Attentive Chart
    const mostAttentiveCtx = qs('#most-attentive-list');
    if (mostAttentiveCtx) {
        if (dashboardCharts.mostAttentive) {
            dashboardCharts.mostAttentive.destroy();
        }
        
        // Sort by ontime_count DESC, then by avg_ontime_time ASC (earlier time = better)
        const topAttentive = monthlyStats
            .filter(item => item.ontime_count > 0)
            .sort((a, b) => {
                if (b.ontime_count !== a.ontime_count) {
                    return b.ontime_count - a.ontime_count;
                }
                // If counts are equal, sort by average ontime (earlier time = better)
                const timeA = timeToSeconds(a.avg_ontime_time) || 86400; // Default to 23:59:59 if null
                const timeB = timeToSeconds(b.avg_ontime_time) || 86400;
                return timeA - timeB; // Earlier time (lower seconds) comes first
            })
            .slice(0, 5);
        
        if (topAttentive.length === 0) {
            mostAttentiveCtx.style.display = 'none';
            mostAttentiveCtx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada data kehadiran bulan ini</div>';
        } else {
            mostAttentiveCtx.style.display = 'block';
            
            // Create pie chart style layout with employee photos
            const attentiveContainer = mostAttentiveCtx.parentElement;
            const totalOnTime = topAttentive.reduce((sum, item) => sum + item.ontime_count, 0);
            
            attentiveContainer.innerHTML = `
                <div class="grid grid-cols-1 gap-3">
                    ${topAttentive.map((item, index) => {
                        const percentage = ((item.ontime_count / totalOnTime) * 100).toFixed(1);
                        const colors = ['#10b981', '#059669', '#047857', '#065f46', '#064e3b'];
                        
                        return `
                            <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm group hover:shadow-md transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="relative flex-shrink-0">
                                        <div class="w-14 h-14 rounded-full flex items-center justify-center p-1 group-hover:scale-105 transition-transform" 
                                             style="background: conic-gradient(${colors[index]} 0deg ${percentage * 3.6}deg, #f3f4f6 ${percentage * 3.6}deg 360deg)">
                                            <img src="${item.foto_base64 || 'https://ui-avatars.com/api/?background=10b981&color=fff&name=' + encodeURIComponent(item.nama) + '&size=96'}" 
                                                 alt="${item.nama}" 
                                                 class="w-11 h-11 rounded-full border-2 border-white shadow-sm object-cover">
                                        </div>
                                        <div class="absolute -top-1 -right-1 bg-emerald-500 text-white text-[10px] rounded-lg px-1.5 py-0.5 font-bold shadow-sm">
                                            #${index + 1}
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-bold text-gray-800 truncate">${item.nama}</h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg">${item.ontime_count}x On-Time</span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0 pr-1">
                                        <div class="text-xl font-black text-emerald-600 leading-none">${percentage}%</div>
                                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-1">Share</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
    }
}

function renderAttendanceTrendChart(trendData) {
    const ctx = qs('#attendanceTrendChart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (dashboardCharts.attendanceTrend) {
        dashboardCharts.attendanceTrend.destroy();
    }
    
    if (!trendData || trendData.length === 0) {
        ctx.style.display = 'none';
        ctx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada data tren kehadiran</div>';
        return;
    }
    
    ctx.style.display = 'block';
    
    const labels = trendData.map(item => item.day);
    const presentData = trendData.map(item => item.present);
    const lateData = trendData.map(item => item.late);
    const absentData = trendData.map(item => item.absent);
    
    dashboardCharts.attendanceTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Kejadian On-Time',
                    data: presentData,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#22c55e',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                },
                {
                    label: 'Kejadian Terlambat',
                    data: lateData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                },
                {
                    label: 'Kejadian Tidak Hadir',
                    data: absentData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#ffffff',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// KPI Functions
// Global KPI Data
let kpiGlobalData = null;
let kpiOverviewChart = null;

async function loadKPIData(forceRefresh = false) {
    try {
        console.log('Loading KPI data (forceRefresh = ' + forceRefresh + ')...');
        
        // Get filter parameters
        const filterType = kpiFilterType ? kpiFilterType.value : 'period';
        const month = kpiFilterMonth ? kpiFilterMonth.value : '';
        const year = kpiFilterYear ? kpiFilterYear.value : '';
        
        // Build query parameters
        const params = new URLSearchParams();
        if (filterType === 'monthly' && month && year) {
            params.append('filter_type', 'monthly');
            params.append('month', month);
            params.append('year', year);
            console.log('KPI Filter: Monthly mode -', month, year);
        } else {
            params.append('filter_type', 'period');
            console.log('KPI Filter: Period mode');
        }
        
        if (forceRefresh) {
            params.append('force_refresh', '1');
        }
        
        const result = await api('?ajax=get_kpi_data', Object.fromEntries(params), { suppressModal: true, cache: !forceRefresh });
        
        console.log('KPI response:', result);
        
        if (!result.ok) {
            console.error('KPI API error:', result.message);
            const errorMsg = result.message || 'Gagal memuat data KPI. Silakan refresh halaman.';
            showNotif('Gagal memuat data KPI: ' + errorMsg, false);
            return;
        }
        
        if (!result.data || !result.data.kpi_data) {
            console.error('No KPI data in response');
            showNotif('Tidak ada data KPI tersedia', false);
            return;
        }
        
        // Store globally
        kpiGlobalData = result.data;
        
        console.log('KPI data loaded:', result.data.kpi_data.length, 'employees');
        
        // Render Table (Default)
        renderKPITable(result.data);
        
        // Initialize View Controls (Idempotent)
        initKPIViewControls();
        
        // If graph view is active, update it
        if (qs('#kpi-graph-view') && !qs('#kpi-graph-view').classList.contains('hidden')) {
            renderKPIOverviewChart();
        }
        
    } catch (error) {
        console.error('Error loading KPI data:', error);
        showNotif('Gagal memuat data KPI: ' + error.message, false);
    }
}

function initKPIViewControls() {
    const btnTable = qs('#view-toggle-table');
    const btnGraph = qs('#view-toggle-graph');
    const viewTable = qs('#kpi-table-view');
    const viewGraph = qs('#kpi-graph-view');
    const btnExportPDF = qs('#btn-export-kpi-pdf');
    
    if (!btnTable || !btnGraph || !viewTable || !viewGraph) return;
    
    // Remove old listeners to avoid duplicates (naive approach, assume reliable replacement)
    // Better: just overwrite onclick or use a flag. 
    // We'll use onclick for simplicity in this context or standard event listeners
    
    btnTable.onclick = () => {
        viewTable.classList.remove('hidden');
        viewGraph.classList.add('hidden');
        
        // Update button styles
        btnTable.className = 'px-4 py-2 rounded-lg text-sm font-bold bg-white text-indigo-600 shadow-sm transition-all flex items-center gap-2';
        btnGraph.className = 'px-4 py-2 rounded-lg text-sm font-bold text-gray-500 hover:text-indigo-600 transition-all flex items-center gap-2';
    };
    
    btnGraph.onclick = () => {
        viewTable.classList.add('hidden');
        viewGraph.classList.remove('hidden');
        
        // Update button styles
        btnGraph.className = 'px-4 py-2 rounded-lg text-sm font-bold bg-white text-indigo-600 shadow-sm transition-all flex items-center gap-2';
        btnTable.className = 'px-4 py-2 rounded-lg text-sm font-bold text-gray-500 hover:text-indigo-600 transition-all flex items-center gap-2';
        
        // Render chart
        renderKPIOverviewChart();
    };
    
    if (btnExportPDF) {
        btnExportPDF.onclick = exportKPIPDF;
    }
}

function renderKPIOverviewChart() {
    if (!kpiGlobalData || !kpiGlobalData.kpi_data) return;
    
    const ctx = qs('#kpi-overview-chart');
    if (!ctx) return;
    
    if (kpiOverviewChart) {
        kpiOverviewChart.destroy();
    }
    
    const employees = kpiGlobalData.kpi_data;
    const names = employees.map(e => e.nama);
    
    // Datasets
    // Stacking WFO, WFA, Izin, Alpha
    const wfoData = employees.map(e => e.wfo_count || 0);
    const wfaData = employees.map(e => e.wfa_count || 0);
    const izinData = employees.map(e => e.izin_sakit_count || 0);
    const alphaData = employees.map(e => e.alpha_count || 0);
    
    const maxDays = Math.max(...employees.map(e => e.total_working_days)) || 20;
    
    kpiOverviewChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: names,
            datasets: [
                {
                    label: 'WFO',
                    data: wfoData,
                    backgroundColor: '#10b981', // Emerald
                    stack: 'Stack 0'
                },
                {
                    label: 'WFA',
                    data: wfaData,
                    backgroundColor: '#06b6d4', // Cyan
                    stack: 'Stack 0'
                },
                {
                    label: 'Izin/Sakit',
                    data: izinData,
                    backgroundColor: '#eab308', // Yellow
                    stack: 'Stack 0'
                },
                {
                    label: 'Alpha',
                    data: alphaData,
                    backgroundColor: '#ef4444', // Red
                    stack: 'Stack 0'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        font: { family: "'Inter', sans-serif", size: 11 }
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    max: maxDays + 2,
                    title: {
                        display: true,
                        text: 'Total Hari Kerja',
                        font: { family: "'Inter', sans-serif", weight: 'bold' }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        afterBody: function(context) {
                            // Add extra info like Ontime/Late
                            const idx = context[0].dataIndex;
                            const emp = employees[idx];
                            return `\nDetail:\nOntime: ${emp.ontime_count}\nTerlambat: ${emp.late_count}\nOvertime: ${emp.overtime_count || 0}`;
                        }
                    }
                }
            }
        }
    });
}

async function exportKPIPDF() {
    if (!kpiGlobalData || !kpiGlobalData.kpi_data) {
        showNotif('Tidak ada data untuk diexport', false);
        return;
    }
    
    if (!window.jspdf) {
        showNotif('Library PDF belum siap, coba refresh halaman', false);
        return;
    }
    
    showNotif('Sedang memuat data foto pegawai...', true);
    
    let employees = [];
    try {
        // Build query parameters based on current filter state
        const filterType = kpiFilterType ? kpiFilterType.value : 'period';
        const month = kpiFilterMonth ? kpiFilterMonth.value : '';
        const year = kpiFilterYear ? kpiFilterYear.value : '';
        
        const params = new URLSearchParams();
        if (filterType === 'monthly' && month && year) {
            params.append('filter_type', 'monthly');
            params.append('month', month);
            params.append('year', year);
        } else {
            params.append('filter_type', 'period');
        }
        params.append('include_photos', '1'); // Request photos for PDF export
        
        const result = await api('?ajax=get_kpi_data', Object.fromEntries(params), { suppressModal: true });
        if (!result.ok || !result.data || !result.data.kpi_data) {
            showNotif('Gagal memuat foto pegawai untuk PDF. Menggunakan data tanpa foto...', false);
            employees = kpiGlobalData.kpi_data;
        } else {
            employees = result.data.kpi_data;
        }
    } catch (e) {
        console.error('Error fetching photos for PDF:', e);
        employees = kpiGlobalData.kpi_data;
    }
    
    showNotif('Sedang men-generate PDF...', true);
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4'); // Portrait, mm, A4
    
    // Helper to add chart to PDF
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = 800; // High res
    tempCanvas.height = 400;
    tempCanvas.style.display = 'none';
    document.body.appendChild(tempCanvas);
    
    for (let i = 0; i < employees.length; i++) {
        const emp = employees[i];
        
        if (i > 0) doc.addPage();
        
        // --- 1. Header with branding ---
        doc.setFillColor(67, 56, 202); // Indigo 700
        doc.rect(0, 0, 210, 24, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(14);
        doc.setFont(undefined, 'bold');
        doc.text('LAPORAN PERFORMANSI PEGAWAI', 15, 16);
        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.text(new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }), 195, 16, { align: 'right' });

        // --- 2. Profile Section (Modern Card) ---
        doc.setFillColor(248, 250, 252); // Slate 50
        doc.setDrawColor(226, 232, 240); // Slate 200
        doc.roundedRect(15, 35, 180, 55, 3, 3, 'FD'); // Background Box

        // Photo (Left)
        let hasPhoto = false;
        if (emp.foto_base64 && emp.foto_base64.length > 100) {
             try {
                // Helper to crop image to square (prevent "gepeng"/stretching)
                const cropToSquare = (base64) => {
                    return new Promise((resolve) => {
                        const img = new Image();
                        img.onload = () => {
                            const size = Math.min(img.width, img.height);
                            const canvas = document.createElement('canvas');
                            canvas.width = size;
                            canvas.height = size;
                            const ctx = canvas.getContext('2d');
                            
                            // Center crop
                            const sx = (img.width - size) / 2;
                            const sy = (img.height - size) / 2;
                            
                            ctx.drawImage(img, sx, sy, size, size, 0, 0, size, size);
                            resolve(canvas.toDataURL('image/png'));
                        };
                        img.onerror = () => resolve(null);
                        img.src = base64;
                    });
                };
                
                // Await the cropped image
                // Note: since this is an async function inside a loop, we need to be careful. 
                // exportKPIPDF is async, so we can await.
                const croppedImg = await cropToSquare(emp.foto_base64);
                
                if (croppedImg) {
                    doc.addImage(croppedImg, 'PNG', 22, 42, 40, 40); 
                    // Draw border
                    doc.setDrawColor(203, 213, 225);
                    doc.setLineWidth(0.5);
                    doc.rect(22, 42, 40, 40); 
                    hasPhoto = true;
                }
             } catch(e) { console.error('Image err', e); }
        }
        
        if (!hasPhoto) {
             // Placeholder Avatar
             doc.setFillColor(226, 232, 240);
             doc.circle(42, 62, 20, 'F');
             doc.setTextColor(148, 163, 184);
             doc.setFontSize(8);
             doc.text('No Photo', 42, 62, { align: 'center' });
        }

        // Info Text (Middle)
        doc.setTextColor(30, 41, 59); // Slate 800
        doc.setFontSize(16);
        doc.setFont(undefined, 'bold');
        doc.text(emp.nama || 'Nama Tidak Tersedia', 70, 48);
        
        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(100, 116, 139); // Slate 500
        
        const labels = ['NIM / ID', 'Startup / Divisi', 'Tot. Hari Kerja'];
        const values = [
            emp.nim || emp.user_id || '-', 
            emp.startup || '-', 
            (emp.total_working_days || 0) + ' Hari'
        ];
        
        let yPos = 58;
        labels.forEach((label, idx) => {
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text(label, 70, yPos);
            
            doc.setFont(undefined, 'bold');
            doc.setTextColor(51, 65, 85);
            doc.text(`:  ${values[idx]}`, 105, yPos);
            yPos += 7;
        });

        // KPI Score Badge (Right)
        const score = emp.kpi_score;
        let scoreColor = [220, 38, 38]; // Red
        if (score >= 90) scoreColor = [22, 163, 74]; // Green
        else if (score >= 80) scoreColor = [37, 99, 235]; // Blue
        else if (score >= 70) scoreColor = [202, 138, 4]; // Yellow
        else if (score >= 60) scoreColor = [217, 119, 6]; // Orange
        
        // Circular Score
        doc.setDrawColor(...scoreColor);
        doc.setLineWidth(2);
        doc.setFillColor(255, 255, 255);
        doc.circle(170, 62, 18, 'FD');
        
        doc.setTextColor(...scoreColor);
        doc.setFontSize(16);
        doc.setFont(undefined, 'bold');
        doc.text(`${score}`, 170, 64, { align: 'center' });
        doc.setFontSize(7);
        doc.text('KPI SCORE', 170, 72, { align: 'center' });
        
        doc.setTextColor(...scoreColor);
        doc.setFontSize(10);
        doc.setFont(undefined, 'bold');
        doc.text(getKPIStatusText(score).toUpperCase(), 170, 40, { align: 'center' });

        // --- 3. Chart Section ---
        doc.setTextColor(30, 41, 59);
        doc.setFontSize(12);
        doc.setFont(undefined, 'bold');
        doc.text('Grafik Metrik Kehadiran', 15, 105);
        
        // Render Separated Bar Chart
        const chartCtx = tempCanvas.getContext('2d');
        chartCtx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
        chartCtx.fillStyle = '#ffffff';
        chartCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
        
        const empChart = new Chart(chartCtx, {
            type: 'bar',
            data: {
                labels: ['WFO', 'WFA', 'Hadir (Ontime)', 'Terlambat', 'Izin/Sakit', 'Alpha'],
                datasets: [{
                    label: 'Jumlah Hari',
                    data: [
                        emp.wfo_count || 0,
                        emp.wfa_count || 0,
                        emp.ontime_count || 0,
                        emp.late_count || 0,
                        emp.izin_sakit_count || 0,
                        emp.alpha_count || 0
                    ],
                    backgroundColor: [
                        '#10b981', // WFO - Emerald
                        '#06b6d4', // WFA - Cyan
                        '#22c55e', // Ontime - Green
                        '#eab308', // Late - Yellow
                        '#3b82f6', // Izin - Blue
                        '#ef4444'  // Alpha - Red
                    ],
                    borderWidth: 0,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                animation: false,
                responsive: false,
                plugins: { 
                    legend: { display: false },
                    datalabels: { display: true, color: 'black', anchor: 'end', align: 'top' } 
                },
                scales: {
                     x: {
                        grid: { display: false },
                        ticks: { font: { size: 14, weight: 'bold' } }
                     },
                     y: {
                        beginAtZero: true,
                        max: (emp.total_working_days || 20) + 2,
                        title: { 
                            display: true, 
                            text: 'Total Hari Kerja',
                            font: { size: 14, weight: 'bold' }
                        },
                        ticks: { font: { size: 14 } }
                     }
                }
            }
        });
        
        const chartImg = empChart.toBase64Image();
        empChart.destroy();
        doc.addImage(chartImg, 'PNG', 15, 110, 180, 90);
        
        // --- 4. Detailed Table ---
        doc.autoTable({
            startY: 210,
            margin: { left: 15, right: 15 },
            head: [['Metrik', 'Jumlah', 'Keterangan']],
            body: [
                ['Total Hari Kerja', emp.total_working_days, 'Total hari kerja dalam periode ini'],
                ['WFO (Work From Office)', emp.wfo_count, ''],
                ['WFA (Work From Anywhere)', emp.wfa_count, ''],
                ['Hadir Ontime', emp.ontime_count, 'Tepat waktu sesuai jadwal'],
                ['Terlambat', emp.late_count, `Total keterlambatan: ${emp.total_late_minutes || 0} menit`],
                ['Izin / Sakit', emp.izin_sakit_count, 'Ketidakhadiran dengan keterangan'],
                ['Alpha', emp.alpha_count, 'Ketidakhadiran tanpa keterangan'],
                ['Laporan Harian Kosong', emp.missing_daily_reports_count, 'Hari hadir tapi tidak isi laporan'],
                ['Overtime / Lembur', emp.overtime_count || 0, '']
            ],
            theme: 'grid',
            headStyles: { 
                fillColor: [79, 70, 229],
                fontSize: 10,
                fontStyle: 'bold',
                halign: 'center'
            },
            columnStyles: {
                0: { cellWidth: 80 },
                1: { cellWidth: 30, halign: 'center', fontStyle: 'bold' },
                2: { cellWidth: 'auto' }
            },
            styles: {
                fontSize: 9,
                cellPadding: 3
            },
            alternateRowStyles: {
                fillColor: [248, 250, 252]
            }
        });
        
        // Footer Number
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text(`Halaman ${i + 1} dari ${employees.length}`, 195, 290, { align: 'right' });
    }
    
    document.body.removeChild(tempCanvas);
    doc.save(`KPI_Report_Lengkap_${new Date().toISOString().slice(0,10)}.pdf`);
    
    showNotif('PDF berhasil didownload', true);
}

function renderKPITable(kpiData) {
    const tbody = qs('#kpi-table-body');
    const loading = qs('#kpi-loading');
    const empty = qs('#kpi-empty');
    const periodRange = qs('#kpi-period-range');
    
    if (!tbody || !loading || !empty || !periodRange) return;
    
    loading.style.display = 'none';
    
    const filterType = kpiFilterType ? kpiFilterType.value : 'period';
    if (filterType === 'monthly') {
        const month = kpiFilterMonth ? kpiFilterMonth.value : '';
        const year = kpiFilterYear ? kpiFilterYear.value : '';
        if (month && year) {
            const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            periodRange.textContent = `${monthNames[parseInt(month)]} ${year}`;
        } else {
            periodRange.textContent = 'Pilih bulan dan tahun';
        }
    } else {
        if (kpiData.period_start && kpiData.period_end) {
            periodRange.textContent = `${kpiData.period_start} - ${kpiData.period_end}`;
        } else {
            periodRange.textContent = 'Seluruh Periode';
        }
    }

    if (!kpiData.kpi_data || kpiData.kpi_data.length === 0) {
        empty.style.display = 'block';
        tbody.innerHTML = '';
        renderPaginationUI('kpi-table-body', 0, 1, 10, 0, 0, () => {}, () => {});
        return;
    }
    
    empty.style.display = 'none';
    
    renderPaginatedTable('kpi-table-body', kpiData.kpi_data, (employee, index) => {
        const statusClass = getKPIStatusClass(employee.kpi_score);
        const statusText = getKPIStatusText(employee.kpi_score);
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-900">${index + 1}</td>
                <td class="px-4 py-3 text-gray-900 font-medium">${employee.nama}</td>
                <td class="px-4 py-3 text-center text-gray-700">${employee.total_working_days}</td>
                <td class="px-4 py-3 text-center text-green-600 font-semibold">${employee.ontime_count}</td>
                <td class="px-4 py-3 text-center text-blue-600 font-semibold">
                    ${employee.wfa_count > 0 ? `<span class="cursor-pointer hover:underline text-blue-600" onclick="showKPIDatesModal('${employee.nama.replace(/'/g, "\\'")}', 'WFA', '${employee.wfa_dates || ''}')">${employee.wfa_count}</span>` : 0}
                </td>
                <td class="px-4 py-3 text-center text-red-600 font-semibold">${employee.late_count}</td>
                <td class="px-4 py-3 text-center text-yellow-600 font-semibold">
                    ${employee.izin_sakit_count > 0 ? `<span class="cursor-pointer hover:underline text-yellow-600" onclick="showKPIDatesModal('${employee.nama.replace(/'/g, "\\'")}', 'Izin / Sakit', '${employee.izin_sakit_dates || ''}')">${employee.izin_sakit_count}</span>` : 0}
                </td>
                <td class="px-4 py-3 text-center text-gray-600 font-semibold">
                    ${employee.alpha_count > 0 ? `<span class="cursor-pointer hover:underline text-gray-600" onclick="showKPIDatesModal('${employee.nama.replace(/'/g, "\\'")}', 'Alpha', '${employee.alpha_dates || ''}')">${employee.alpha_count}</span>` : 0}
                </td>
                <td class="px-4 py-3 text-center text-emerald-600 font-semibold">${employee.overtime_count || 0}</td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-sm font-semibold ${employee.missing_daily_reports_count > 0 ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'}">
                        ${employee.missing_daily_reports_count || 0}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-sm font-semibold ${statusClass}">
                        ${employee.kpi_score}%
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-sm ${statusClass}">${statusText}</span>
                </td>
            </tr>
        `;
    }, {
        colSpan: 12,
        emptyMessage: 'Tidak ada data KPI.',
        onPageChange: () => renderKPITable(kpiData)
    });
}

function getKPIStatusClass(score) {
    if (score >= 90) return 'bg-green-100 text-green-800';
    if (score >= 80) return 'bg-blue-100 text-blue-800';
    if (score >= 70) return 'bg-yellow-100 text-yellow-800';
    if (score >= 60) return 'bg-orange-100 text-orange-800';
    return 'bg-red-100 text-red-800';
}

function getKPIStatusText(score) {
    if (score >= 90) return 'Excellent';
    if (score >= 80) return 'Good';
    if (score >= 70) return 'Fair';
    if (score >= 60) return 'Poor';
    return 'Very Poor';
}

window.showKPIDatesModal = function(employeeName, type, datesStr) {
    let modal = document.getElementById('kpi-dates-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'kpi-dates-modal';
        modal.className = 'fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-[9999] hidden animate-fade-in';
        modal.innerHTML = `
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden transform transition-all scale-95 duration-200" id="kpi-dates-modal-container">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" id="kpi-modal-title">Detail Tanggal</h3>
                        <p class="text-xs text-gray-500 mt-0.5" id="kpi-modal-subtitle">Pegawai</p>
                    </div>
                    <button onclick="closeKPIDatesModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <i class="fi fi-rr-cross text-xs"></i>
                    </button>
                </div>
                <div class="p-6 max-h-[350px] overflow-y-auto" id="kpi-modal-content">
                    
                </div>
                <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button onclick="closeKPIDatesModal()" class="px-4 py-2 bg-gray-200 text-gray-700 hover:bg-gray-300 transition-colors rounded-xl text-sm font-semibold">
                        Tutup
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeKPIDatesModal();
        });
    }

    const titleEl = modal.querySelector('#kpi-modal-title');
    const subtitleEl = modal.querySelector('#kpi-modal-subtitle');
    const contentEl = modal.querySelector('#kpi-modal-content');
    const container = modal.querySelector('#kpi-dates-modal-container');

    titleEl.textContent = `Detail Tanggal ${type}`;
    subtitleEl.textContent = `Pegawai: ${employeeName}`;

    let dates = datesStr ? datesStr.split(',').map(d => d.trim()).filter(Boolean) : [];
    
    if (dates.length === 0) {
        contentEl.innerHTML = `
            <div class="flex flex-col items-center justify-center py-6 text-gray-400">
                <i class="fi fi-rr-calendar-ban text-4xl mb-2"></i>
                <p class="text-sm">Tidak ada data tanggal</p>
            </div>
        `;
    } else {
        const formatIndonesianDate = (dateString) => {
            const date = new Date(dateString);
            if (isNaN(date)) return dateString;
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('id-ID', options);
        };

        const listHtml = dates.map(date => {
            return `
                <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100 hover:bg-gray-100/70 transition-colors mb-2 last:mb-0">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-indigo-50 text-indigo-600">
                        <i class="fi fi-rr-calendar-check text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">${formatIndonesianDate(date)}</p>
                        <p class="text-xs text-gray-400 mt-0.5">${date}</p>
                    </div>
                </div>
            `;
        }).join('');

        contentEl.innerHTML = `<div class="space-y-1">${listHtml}</div>`;
    }

    modal.classList.remove('hidden');
    setTimeout(() => {
        container.classList.remove('scale-95');
        container.classList.add('scale-100');
    }, 10);
};

window.closeKPIDatesModal = function() {
    const modal = document.getElementById('kpi-dates-modal');
    if (!modal) return;
    const container = modal.querySelector('#kpi-dates-modal-container');
    container.classList.remove('scale-100');
    container.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
};

// KPI Handlers moved to top of app block for reliability


// KPI Filter handlers
// KPI Filter handlers
// Declarations moved to top


// Initialize month and year options
function initKPIFilterOptions() {
    if (!kpiFilterMonth || !kpiFilterYear || !kpiFilterType) {
        console.warn('KPI filter elements not found:', {
            type: !!kpiFilterType,
            month: !!kpiFilterMonth,
            year: !!kpiFilterYear
        });
        return;
    }
    
    console.log('Initializing KPI filter options...');
    
    // Hide/show based on current filter type
    const isMonthly = kpiFilterType.value === 'monthly';
    const monthlyControls = document.getElementById('kpi-monthly-controls');
    if (monthlyControls) {
        if (isMonthly) {
            monthlyControls.classList.remove('hidden');
            monthlyControls.style.display = 'flex';
        } else {
            monthlyControls.classList.add('hidden');
            monthlyControls.style.display = 'none';
        }
    }
    
    const currentDateObj = new Date();
    const currentMonthNum = currentDateObj.getMonth() + 1; // 1-12
    const currentYearNum = currentDateObj.getFullYear();
    
    // Populate months
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    kpiFilterMonth.innerHTML = '<option value="">Pilih Bulan</option>';
    months.forEach((month, index) => {
        const option = document.createElement('option');
        option.value = index + 1;
        option.textContent = month;
        if (index + 1 === currentMonthNum) {
            option.selected = true;
        }
        kpiFilterMonth.appendChild(option);
    });
    
    // Populate years (current year and previous 2 years)
    kpiFilterYear.innerHTML = '<option value="">Pilih Tahun</option>';
    for (let year = currentYearNum; year >= currentYearNum - 2; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        if (year === currentYearNum) {
            option.selected = true;
        }
        kpiFilterYear.appendChild(option);
    }
    
    // If values are still empty, force set them
    if (!kpiFilterMonth.value) kpiFilterMonth.value = currentMonthNum;
    if (!kpiFilterYear.value) kpiFilterYear.value = currentYearNum;
    
    // ===== ATTACH EVENT LISTENERS HERE (after elements are confirmed to exist) =====
    
    // Filter type change listener
    kpiFilterType.addEventListener('change', (e) => {
        const isMonthly = e.target.value === 'monthly';
        console.log('=== KPI FILTER CHANGE ===');
        console.log('Filter type changed to:', e.target.value);
        console.log('isMonthly:', isMonthly);
        
        const monthlyControls = document.getElementById('kpi-monthly-controls');
        const monthSelect = document.getElementById('kpi-filter-month');
        const yearSelect = document.getElementById('kpi-filter-year');
        
        console.log('Elements found:', {
            container: !!monthlyControls,
            month: !!monthSelect,
            year: !!yearSelect
        });
        
        if (monthlyControls) {
            // Use multiple methods to ensure visibility
            if (isMonthly) {
                monthlyControls.classList.remove('hidden');
                monthlyControls.style.display = 'flex';
                console.log('SHOWING monthly controls');
            } else {
                monthlyControls.classList.add('hidden');
                monthlyControls.style.display = 'none';
                console.log('HIDING monthly controls');
            }
            
            // Verify the change
            setTimeout(() => {
                const isHidden = monthlyControls.classList.contains('hidden');
                const displayStyle = window.getComputedStyle(monthlyControls).display;
                console.log('After toggle - Hidden class:', isHidden, 'Display style:', displayStyle);
            }, 100);
        } else {
            console.warn('Monthly controls container NOT FOUND!');
            // Fallback to individual selects
            if (monthSelect) {
                monthSelect.style.display = isMonthly ? 'block' : 'none';
            }
            if (yearSelect) {
                yearSelect.style.display = isMonthly ? 'block' : 'none';
            }
        }
        
        if (isMonthly) {
            // Set current month and year as default if empty
            const now = new Date();
            if (monthSelect && !monthSelect.value) {
                monthSelect.value = now.getMonth() + 1;
            }
            if (yearSelect && !yearSelect.value) {
                yearSelect.value = now.getFullYear();
            }
            console.log('Set default values - Month:', monthSelect?.value, 'Year:', yearSelect?.value);
        }
        
        // Reload data when filter type changes
        loadKPIData();
        console.log('=== END FILTER CHANGE ===');
    });
    
    // Month change listener
    kpiFilterMonth.addEventListener('change', () => {
        console.log('Month changed to:', kpiFilterMonth.value);
        if (kpiFilterType && kpiFilterType.value === 'monthly') {
            loadKPIData();
        }
    });
    
    // Year change listener
    kpiFilterYear.addEventListener('change', () => {
        console.log('Year changed to:', kpiFilterYear.value);
        if (kpiFilterType && kpiFilterType.value === 'monthly') {
            loadKPIData();
        }
    });
    
    console.log('KPI filter options initialized successfully');
}

document.addEventListener('click', async (e)=>{
    if(e.target.classList.contains('btn-am-approve')||e.target.classList.contains('btn-am-disapprove')){
        const id = e.target.getAttribute('data-id'); const status = e.target.classList.contains('btn-am-approve') ? 'approved' : 'disapproved';
        showConfirmModal('Yakin set status laporan bulanan?', async ()=>{ await api('?ajax=admin_set_monthly_status', { id, status }); renderAdminMonthly(); });
    }
});



