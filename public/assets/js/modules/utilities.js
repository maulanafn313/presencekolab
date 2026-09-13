// Extracted from layout_footer.blade.php — Pure JS utilities
// Pagination, notifications, speech, face detection, IndexedDB cache, etc.

// ==========================================
// UNIVERSAL TABLE PAGINATION ENGINE
// ==========================================
window.tablePaginationState = window.tablePaginationState || {};

window.resetTablePage = function(tbodyId) {
    if (window.tablePaginationState && window.tablePaginationState[tbodyId]) {
        window.tablePaginationState[tbodyId].currentPage = 1;
    }
};

window.renderPaginatedTable = function(tbodyId, items, renderFn, options = {}) {
    const body = qs('#' + tbodyId);
    if (!body) return;

    if (!window.tablePaginationState[tbodyId]) {
        window.tablePaginationState[tbodyId] = {
            currentPage: 1,
            pageSize: options.pageSize || 10
        };
    }

    const state = window.tablePaginationState[tbodyId];
    const pageSize = state.pageSize;
    const totalItems = items.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

    if (state.currentPage > totalPages) state.currentPage = totalPages;
    if (state.currentPage < 1) state.currentPage = 1;

    const currentPage = state.currentPage;
    body.innerHTML = '';

    if (totalItems === 0) {
        const colSpan = options.colSpan || 12;
        const emptyMsg = options.emptyMessage || 'Tidak ada data.';
        body.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-6 text-gray-500 font-medium">${emptyMsg}</td></tr>`;
        window.renderPaginationUI(tbodyId, 0, 1, pageSize, 0, 0, () => {}, () => {});
        return;
    }

    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, totalItems);
    const pageItems = items.slice(startIndex, endIndex);

    pageItems.forEach((item, index) => {
        const actualIndex = startIndex + index;
        const row = renderFn(item, actualIndex);
        if (typeof row === 'string') {
            body.insertAdjacentHTML('beforeend', row);
        } else if (row instanceof HTMLElement) {
            body.appendChild(row);
        }
    });

    window.renderPaginationUI(
        tbodyId,
        totalItems,
        currentPage,
        pageSize,
        startIndex,
        endIndex,
        (newPage) => {
            state.currentPage = newPage;
            if (options.onPageChange) options.onPageChange();
        },
        (newSize) => {
            state.pageSize = newSize;
            state.currentPage = 1;
            if (options.onPageChange) options.onPageChange();
        }
    );
};

window.renderPaginationUI = function(tbodyId, totalItems, currentPage, pageSize, startIndex, endIndex, onPageChange, onSizeChange) {
    const body = qs('#' + tbodyId);
    if (!body) return;

    const table = body.closest('table');
    if (!table) return;

    const tableWrapper = table.closest('.overflow-x-auto') || table.parentElement;
    const paginationId = tbodyId + '-pagination';
    let container = document.getElementById(paginationId);

    if (!container) {
        container = document.createElement('div');
        container.id = paginationId;
        container.className = 'table-pagination-container flex flex-col sm:flex-row items-center justify-between gap-4 mt-4 px-4 py-3 bg-white border border-gray-200 rounded-xl shadow-xs text-sm text-gray-600';
        tableWrapper.insertAdjacentElement('afterend', container);
    }

    if (totalItems === 0) {
        container.innerHTML = `<div class="text-xs text-gray-500 italic">Menampilkan 0 data</div>`;
        return;
    }

    const totalPages = Math.ceil(totalItems / pageSize);
    const startNum = startIndex + 1;
    const endNum = endIndex;

    let pageNumbers = [];
    if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pageNumbers.push(i);
    } else {
        if (currentPage <= 4) {
            pageNumbers = [1, 2, 3, 4, 5, '...', totalPages];
        } else if (currentPage >= totalPages - 3) {
            pageNumbers = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pageNumbers = [1, '...', currentPage - 1, currentPage, currentPage + 1, '...', totalPages];
        }
    }

    let buttonsHTML = `
        <button type="button" data-page="1" ${currentPage === 1 ? 'disabled' : ''} class="pag-btn px-2.5 py-1.5 rounded-lg border text-xs font-semibold ${currentPage === 1 ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 cursor-pointer'}">
            &laquo;
        </button>
        <button type="button" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''} class="pag-btn px-3 py-1.5 rounded-lg border text-xs font-semibold ${currentPage === 1 ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 cursor-pointer'}">
            Sebelumnya
        </button>
    `;

    pageNumbers.forEach(p => {
        if (p === '...') {
            buttonsHTML += `<span class="px-2 py-1 text-gray-400 text-xs">...</span>`;
        } else {
            const isActive = p === currentPage;
            buttonsHTML += `
                <button type="button" data-page="${p}" class="pag-btn px-3 py-1.5 rounded-lg border text-xs font-semibold ${isActive ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 cursor-pointer'}">
                    ${p}
                </button>
            `;
        }
    });

    buttonsHTML += `
        <button type="button" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''} class="pag-btn px-3 py-1.5 rounded-lg border text-xs font-semibold ${currentPage === totalPages ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 cursor-pointer'}">
            Selanjutnya
        </button>
        <button type="button" data-page="${totalPages}" ${currentPage === totalPages ? 'disabled' : ''} class="pag-btn px-2.5 py-1.5 rounded-lg border text-xs font-semibold ${currentPage === totalPages ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 cursor-pointer'}">
            &raquo;
        </button>
    `;

    container.innerHTML = `
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-600">
                Menampilkan <span class="font-bold text-gray-800">${startNum}</span> - <span class="font-bold text-gray-800">${endNum}</span> dari <span class="font-bold text-gray-800">${totalItems}</span> data
            </span>
            <div class="flex items-center gap-1.5 ml-2 border-l border-gray-200 pl-3">
                <label class="text-xs text-gray-500">Per halaman:</label>
                <select class="pag-size-select text-xs border border-gray-300 rounded-md px-2 py-1 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="10" ${pageSize === 10 ? 'selected' : ''}>10</option>
                    <option value="25" ${pageSize === 25 ? 'selected' : ''}>25</option>
                    <option value="50" ${pageSize === 50 ? 'selected' : ''}>50</option>
                    <option value="100" ${pageSize === 100 ? 'selected' : ''}>100</option>
                </select>
            </div>
        </div>
        <div class="flex items-center gap-1 flex-wrap">
            ${buttonsHTML}
        </div>
    `;

    container.querySelectorAll('.pag-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (btn.disabled) return;
            const page = parseInt(btn.getAttribute('data-page'));
            if (page && page !== currentPage) {
                onPageChange(page);
            }
        });
    });

    const sizeSelect = container.querySelector('.pag-size-select');
    if (sizeSelect) {
        sizeSelect.addEventListener('change', (e) => {
            const newSize = parseInt(e.target.value);
            if (newSize) {
                onSizeChange(newSize);
            }
        });
    }
};

function resetTablePage(tbodyId) { return window.resetTablePage(tbodyId); }
function renderPaginatedTable(tbodyId, items, renderFn, options) { return window.renderPaginatedTable(tbodyId, items, renderFn, options); }
function renderPaginationUI(tbodyId, totalItems, currentPage, pageSize, startIndex, endIndex, onPageChange, onSizeChange) { return window.renderPaginationUI(tbodyId, totalItems, currentPage, pageSize, startIndex, endIndex, onPageChange, onSizeChange); }


// Global state logic
if (typeof dashboardCharts === 'undefined') {
    window.dashboardCharts = {}; // Global chart instance holder
}
if (typeof isInitRekapRunning === 'undefined') {
    window.isInitRekapRunning = false;
}
// SPBW_SYSTEM_FIX_MARKER: POLICY_SYNC_V4
if (typeof currentRekapData === 'undefined') {
    window.currentRekapData = null; // Store rekap data for week filtering
}

// --- RESTORED CORE JS LOGIC ---

/**
 * Cek apakah sebuah tanggal masih dalam kurun waktu 10 hari kerja terakhir.
 * Digunakan untuk validasi tampilan bukti presensi (screenshot/landmark).
 */
window.isWithin10WorkingDays = function(dateString) {
    if (!dateString) return false;
    const recordDate = new Date(dateString);
    recordDate.setHours(0, 0, 0, 0);
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (recordDate > today) return true;
    
    let workingDaysCount = 0;
    let tempDate = new Date(recordDate);
    
    while (tempDate <= today) {
        const day = tempDate.getDay();
        if (day !== 0 && day !== 6) {
            workingDaysCount++;
        }
        tempDate.setDate(tempDate.getDate() + 1);
    }
    
    return workingDaysCount <= 11;
};

window.showExpiredModal = function() {
    if (typeof showModalNotif === 'function') {
        showModalNotif('Bukti Kadaluarsa', 'Maaf, foto bukti presensi ini sudah dihapus dari sistem karena sudah melewati batas penyimpanan 10 hari kerja.', 'info');
    } else {
        alert('Foto bukti presensi sudah expired (melebihi 10 hari kerja).');
    }
};

window.translateExpression = function(exp) {
    if (!exp) return 'Netral';
    const dict = {
        'neutral': 'Netral',
        'happy': 'Bahagia',
        'sad': 'Sedih',
        'angry': 'Marah',
        'fearful': 'Takut',
        'disgusted': 'Jijik',
        'surprised': 'Terkejut'
    };
    return dict[exp.toLowerCase()] || exp;
};

// Global Lazy Loading for Member Photos
window.lazyLoadMemberPhoto = async function(id, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    try {
        const res = await api(`?ajax=get_member_photo&id=${id}`);
        if (res.ok && res.image) {
            container.innerHTML = `<img src="${res.image}" class="h-full w-full object-cover transition-transform duration-200 hover:scale-110" onclick="showScreenshotModal('${res.image}', 'Foto Member')">`;
        } else {
            container.innerHTML = `<span class="text-[10px] text-red-500">Gagal</span>`;
        }
    } catch (e) {
        console.error('Error loading member photo:', e);
        container.innerHTML = `<span class="text-[10px] text-red-500">Error</span>`;
    }
};

// Global Intersection Observer for Member Photos
window.memberPhotoObserver = window.IntersectionObserver ? new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const el = entry.target;
            const id = el.dataset.id;
            const containerId = el.id;
            if (id && !el.dataset.loaded) {
                el.dataset.loaded = "true";
                if (window.lazyLoadMemberPhoto) window.lazyLoadMemberPhoto(id, containerId);
            }
        }
    });
}, { rootMargin: '50px' }) : null;


// Initialize speech synthesis for offline use
window.initializeSpeechSynthesis = function() {
    try {
        if ('speechSynthesis' in window) {
            // Pre-load voices for offline use
            const loadVoices = () => {
                const voices = speechSynthesis.getVoices();
                console.log('Available voices:', voices.length);
                
                const indonesianVoices = voices.filter(voice => 
                    voice.lang.startsWith('id') || 
                    voice.lang.includes('Indonesian') ||
                    voice.name.includes('Indonesian')
                );
                
                if (indonesianVoices.length > 0) {
                    console.log('Indonesian voices found:', indonesianVoices.map(v => v.name));
                } else {
                    console.log('No Indonesian voices found, will use default voice');
                }
            };

            if (speechSynthesis.getVoices().length > 0) {
                loadVoices();
            } else {
                speechSynthesis.addEventListener('voiceschanged', loadVoices, { once: true });
            }
            
            console.log('Speech synthesis initialized for offline use');
        } else {
            console.warn('Speech synthesis not supported in this browser');
        }
    } catch (error) {
        console.error('Failed to initialize speech synthesis:', error);
    }
};

/**
 * Render landmark titik wajah ke canvas
 */
window.renderLandmarkOnCanvas = function(canvas, landmarkData, width, height) {
    if (!canvas || !landmarkData) return;
    const ctx = canvas.getContext('2d');
    let points = [];
    try {
        points = typeof landmarkData === 'string' ? JSON.parse(landmarkData) : landmarkData;
    } catch (e) { return; }
    
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#6366f1'; // Indigo-500
    points.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x * width, p.y * height, 1, 0, 2 * Math.PI);
        ctx.fill();
    });
};

// Speak helper function — robust version that handles Chrome speechSynthesis quirks
window.speak = function(text, rate = 1.0) {
    if (!('speechSynthesis' in window)) return;
    if (!text) return;

    // Cancel any ongoing speech first
    window.speechSynthesis.cancel();

    // Chrome has a bug: speaking immediately after cancel() silently fails.
    // A small delay (50ms) fixes this reliably.
    setTimeout(() => {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = rate;
        utterance.volume = window.appVolume !== undefined ? window.appVolume : 1.0;

        // Try to use Indonesian voice, fallback to first available
        const voices = window.speechSynthesis.getVoices();
        const idVoice = voices.find(v => v.lang.startsWith('id'));
        if (idVoice) {
            utterance.voice = idVoice;
            utterance.lang = idVoice.lang;
        } else {
            utterance.lang = 'id-ID'; // Still set lang even without matching voice
        }

        utterance.onerror = (e) => {
            if (e.error !== 'interrupted') console.warn('Speech error:', e.error);
        };

        window.speechSynthesis.speak(utterance);
    }, 50);
};

// BALANCED ACCURACY: Detection config optimized for good accuracy while still detecting faces reliably
window.detectionConfig = window.detectionConfig || {};
Object.assign(window.detectionConfig, {
    faceMatcherThreshold: 0.38,
    recognitionThreshold: 0.38,
    inputSize: 416,
    scoreThreshold: 0.35,
    minFaceSize: 70,
    maxFaces: 1,
    confidenceThreshold: 0.7,
    detectionThrottle: 2,
    qualityThreshold: 0.55,
    landmarkThreshold: 0.55,
    expressionThreshold: 0.55,
    landmarkWeight: 0.5,
    descriptorWeight: 0.5,
    genderValidation: true,
    multiAttemptValidation: true,
    strictMode: true
});

// Load face recognition settings from backend
window.loadFaceRecognitionSettings = async function() {
    try {
        const settingsJson = await api('?ajax=get_settings', {}, { suppressModal: true, cache: false });
        if (settingsJson.ok && settingsJson.data) {
            const settings = settingsJson.data;
            if (settings.face_recognition_threshold?.value) {
                window.detectionConfig.faceMatcherThreshold = parseFloat(settings.face_recognition_threshold.value) || 0.38;
                window.detectionConfig.recognitionThreshold = parseFloat(settings.face_recognition_threshold.value) || 0.38;
            }
            if (settings.face_recognition_min_confidence?.value) {
                window.detectionConfig.minConfidencePercent = parseFloat(settings.face_recognition_min_confidence.value) || 65;
                console.log(`[Settings] Min confidence loaded: ${window.detectionConfig.minConfidencePercent}%`);
            }
            if (settings.face_recognition_input_size?.value) {
                window.detectionConfig.inputSize = parseInt(settings.face_recognition_input_size.value) || 416;
            }
            if (settings.face_recognition_score_threshold?.value) {
                window.detectionConfig.scoreThreshold = parseFloat(settings.face_recognition_score_threshold.value) || 0.35;
            }
            if (settings.face_recognition_quality_threshold?.value) {
                window.detectionConfig.qualityThreshold = parseFloat(settings.face_recognition_quality_threshold.value) || 0.55;
            }
        }
    } catch (e) {
        console.warn('Failed to load face recognition settings, using defaults:', e);
    }
};

// Initialize face recognition system
window.initializeFaceRecognition = async function() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        let page = urlParams.get('page');
        if (!page) {
            const pathParts = window.location.pathname.split('/').filter(Boolean);
            page = pathParts[pathParts.length - 1];
        }
        const isScanPage = page === 'presensi-masuk' || page === 'presensi-pulang';
        
        if (!isScanPage) {
            console.log('Skipping face recognition initialization on non-attendance page:', page);
            return;
        }

        console.log('Initializing face recognition system (settings load only)...');
        if (typeof window.loadFaceRecognitionSettings === 'function') {
            await window.loadFaceRecognitionSettings();
        }
        
        // Delegate actual model loading to attendance.js when the video scanning starts.
        console.log('✅ Settings loaded. Model pre-load delegated to attendance.js');
    } catch (error) {
        console.error('❌ Failed to initialize face recognition:', error);
    }
};


// Supporting functions for face recognition
window.fetchMembers = async function() {
    try {
        const res = await api('?ajax=get_members&light=1');
        return res.data || [];
    } catch (e) {
        console.error('Failed to fetch members:', e);
        return [];
    }
};

window.loadLabeledFaceDescriptors = async function() {
    // Guard: prevent concurrent calls
    if (window._loadingDescriptors) {
        console.log('⏳ Descriptor load already in progress, waiting...');
        while (window._loadingDescriptors) {
            await new Promise(r => setTimeout(r, 100));
        }
        return;
    }
    if (labeledFaceDescriptors.length > 0 && faceMatcher) {
        return; // Already fully loaded, skip
    }
    window._loadingDescriptors = true;
    
    try {
    const membersList = await fetchMembers();
    const totalMembers = membersList.length;
    let processedCount = 0;

    const updateProgress = (pct, msg) => {
        if (typeof updateLoadingProgress === 'function') {
            updateLoadingProgress(pct, msg);
        } else {
            const el = qs('#loading-progress');
            if (el) el.textContent = msg;
            const bar = qs('#loading-progress-bar');
            if (bar) bar.style.width = pct + '%';
            const pctText = qs('#loading-progress-pct');
            if (pctText) pctText.textContent = pct + '%';
        }
    };

    // CRITICAL: Always populate the global members array for name lookup
    // The attendance.js detection loop uses `members` to find m.nama from the matched label
    if (typeof members !== 'undefined') {
        members = membersList;
    }

    // --- Fast path: Use IndexedDB cache if available ---
    const versionKey = typeof computeMembersVersionKey === 'function' ? await computeMembersVersionKey(membersList) : null;
    if (versionKey && typeof idbGetDescriptors === 'function') {
        const cached = await idbGetDescriptors(versionKey);

        if (cached && Array.isArray(cached) && cached.length > 0) {
            labeledFaceDescriptors = cached.map(item => new faceapi.LabeledFaceDescriptors(
                item.label,
                item.descriptors.map(d => new Float32Array(d))
            ));
            console.log('✅ Loaded face descriptors from IDB cache:', labeledFaceDescriptors.length, '| members:', membersList.length);
            updateProgress(100, `✅ Sistem siap! ${labeledFaceDescriptors.length} wajah dimuat dari cache.`);
            window._loadingDescriptors = false;
            return;
        }

    }

    // --- Medium path: Use pre-computed face_embedding from server DB (fast, no image loading) ---
    labeledFaceDescriptors = [];
    const membersWithValidEmbedding = membersList.filter(m => {
        if (!m.face_embedding) return false;
        try {
            const emb = JSON.parse(m.face_embedding);
            return Array.isArray(emb) && emb.length === 128;
        } catch (e) { return false; }
    });
    
    const membersNeedingCompute = membersList.filter(m => {
        const hasCompatibleEmbedding = m.face_embedding && (JSON.parse(m.face_embedding).length === 128);
        return !hasCompatibleEmbedding && (m.foto_base64 || m.has_foto);
    });

    if (membersWithValidEmbedding.length > 0) {
        console.log(`⚡ Loading ${membersWithValidEmbedding.length} pre-computed 128-dim embeddings from server...`);
        for (const m of membersWithValidEmbedding) {
            try {
                const desc = new Float32Array(JSON.parse(m.face_embedding));
                const label = String(m.nim || m.nama || m.id);
                labeledFaceDescriptors.push(new faceapi.LabeledFaceDescriptors(label, [desc]));
                
                processedCount++;
                if (totalMembers > 0) {
                    const pct = Math.round((processedCount / totalMembers) * 100);
                    updateProgress(pct, `Memuat data wajah: ${processedCount}/${totalMembers} (${pct}%)`);
                }
            } catch (e) { console.warn('Failed to parse embedding for', m.nama); }
        }
        console.log(`✅ Loaded ${labeledFaceDescriptors.length} embeddings instantly from server.`);
    }

    // --- Slow path: Only compute from image for members missing an embedding ---
    if (membersNeedingCompute.length > 0) {
        console.log(`🐢 Computing ${membersNeedingCompute.length} missing embeddings from photos (fallback)...`);
        for (const m of membersNeedingCompute) {
            processedCount++;
            let pct = 0;
            if (totalMembers > 0) {
                pct = Math.round((processedCount / totalMembers) * 100);
            }
            updateProgress(pct, `Menghitung vektor wajah: ${m.nama} (${processedCount}/${totalMembers} - ${pct}%)`);
            
            try {
                let photo = m.foto_base64;
                if (!photo && m.has_foto && typeof api === 'function') {
                    const photoRes = await api(`?ajax=get_member_photo&id=${m.id}`);
                    if (photoRes && photoRes.ok) photo = photoRes.image;
                }
                
                if (!photo) continue;
                
                const img = await faceapi.fetchImage(photo);
                const det = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 }))
                    .withFaceLandmarks().withFaceDescriptor();
                if (det) {
                    const label = String(m.nim || m.nama || m.id);
                    labeledFaceDescriptors.push(new faceapi.LabeledFaceDescriptors(label, [det.descriptor]));
                    
                    // Save to server database so next time is instant for everyone
                    const formData = new FormData();
                    formData.append('ajax', 'save_face_embedding');
                    formData.append('id', m.id);
                    formData.append('embedding', JSON.stringify(Array.from(det.descriptor)));
                    formData.append('landmarks', JSON.stringify(det.landmarks.positions));
                    
                    if (window.USER_ROLE === 'admin') {
                        api('?ajax=save_face_embedding', formData).catch(err => {
                            console.error('Failed to save embedding for', m.nama, err);
                        });
                    }
                }
            } catch (err) { console.warn('Detection failed for', m.nama, err); }
        }
    }

    // Save to IDB for next time
    if (versionKey && typeof idbSetDescriptors === 'function' && labeledFaceDescriptors.length > 0) {
        const toStore = labeledFaceDescriptors.map(ld => ({
            label: ld.label,
            descriptors: ld.descriptors.map(arr => Array.from(arr))
        }));
        idbSetDescriptors(versionKey, toStore).catch(() => {});
    }

    updateProgress(100, `✅ Sistem siap! ${labeledFaceDescriptors.length} wajah dimuat.`);
    console.log('✅ Total face descriptors loaded:', labeledFaceDescriptors.length);
    } catch(e) {
        console.error('loadLabeledFaceDescriptors failed:', e);
    } finally {
        window._loadingDescriptors = false;
    }
};



function showNotif(msg, success=true){
    const bar = qs('#notif-bar');
    if (!bar) return;
    bar.textContent = msg;
    bar.className = `fixed top-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg z-[9999] ${success?'bg-green-600':'bg-red-600'} text-white font-bold`;
    bar.classList.remove('hidden');
    setTimeout(()=> bar.classList.add('hidden'), 2000); 
}
function showModalNotif(message, success=true, title='Notifikasi'){
    const m = qs('#global-modal');
    const t = qs('#global-modal-title');
    const c = qs('#global-modal-message');
    const okBtn = qs('#global-modal-ok');
    const cancelBtn = qs('#global-modal-cancel');
    
    if(!m||!t||!c) return showNotif(message, success);
    
    t.textContent = title;
    c.textContent = message;
    
    const handleOk = () => {
        m.classList.add('hidden');
        okBtn.removeEventListener('click', handleOk);
    };
    
    // Show only OK button for alerts
    if(okBtn) {
        okBtn.classList.remove('hidden');
        okBtn.addEventListener('click', handleOk);
    }
    if(cancelBtn) cancelBtn.classList.add('hidden');
    
    m.classList.remove('hidden');
}

// Custom Alert (replaces alert())
function customAlert(message, title = 'Pemberitahuan') {
    return new Promise((resolve) => {
        const m = qs('#global-modal');
        const t = qs('#global-modal-title');
        const c = qs('#global-modal-message');
        const okBtn = qs('#global-modal-ok');
        const cancelBtn = qs('#global-modal-cancel');
        
        if(!m||!t||!c) {
            alert(message);
            resolve();
            return;
        }
        
        t.textContent = title;
        c.textContent = message;
        
        // Show only OK button
        if(okBtn) okBtn.classList.remove('hidden');
        if(cancelBtn) cancelBtn.classList.add('hidden');
        
        m.classList.remove('hidden');
        
        const handleOk = () => {
            m.classList.add('hidden');
            okBtn.removeEventListener('click', handleOk);
            resolve();
        };
        
        okBtn.addEventListener('click', handleOk);
    });
}

// Custom Confirm (replaces confirm())
function customConfirm(message, title = 'Konfirmasi') {
    return new Promise((resolve) => {
        const m = qs('#global-modal');
        const t = qs('#global-modal-title');
        const c = qs('#global-modal-message');
        const okBtn = qs('#global-modal-ok');
        const cancelBtn = qs('#global-modal-cancel');
        
        if(!m||!t||!c) {
            resolve(confirm(message));
            return;
        }
        
        t.textContent = title;
        c.textContent = message;
        
        // Show both buttons
        if(okBtn) okBtn.classList.remove('hidden');
        if(cancelBtn) cancelBtn.classList.remove('hidden');
        
        m.classList.remove('hidden');
        
        const handleOk = () => {
            m.classList.add('hidden');
            cleanup();
            resolve(true);
        };
        
        const handleCancel = () => {
            m.classList.add('hidden');
            cleanup();
            resolve(false);
        };
        
        const cleanup = () => {
            okBtn.removeEventListener('click', handleOk);
            cancelBtn.removeEventListener('click', handleCancel);
        };
        
        okBtn.addEventListener('click', handleOk);
        cancelBtn.addEventListener('click', handleCancel);
    });
}

// Close modal when clicking outside (for alerts only)
document.addEventListener('click', (e)=>{
    const m = qs('#global-modal');
    const cancelBtn = qs('#global-modal-cancel');
    
    // Only allow backdrop close for alerts (when cancel button is hidden)
    if(e.target.id==='global-modal' && cancelBtn && cancelBtn.classList.contains('hidden')){
        m.classList.add('hidden');
    }
});
function qs(sel){ return document.querySelector(sel); }
function qsa(sel){ return Array.from(document.querySelectorAll(sel)); }

// KPI Filters (Global) - Re-initialized here after utility functions are defined
let kpiFilterType, kpiFilterMonth, kpiFilterYear;
function initKpiGlobals() {
    kpiFilterType = qs('#kpi-filter-type');
    kpiFilterMonth = qs('#kpi-filter-month');
    kpiFilterYear = qs('#kpi-filter-year');
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKpiGlobals);
} else {
    initKpiGlobals();
}

// Screenshot modal functions
async function loadAndShowEvidence(id, type, title) {
    try {
        const modal = qs('#screenshot-modal');
        const modalImage = qs('#screenshot-modal-image');
        const modalTitle = qs('#screenshot-modal-title');
        
        if (modal && modalTitle) {
            modalTitle.textContent = 'Memuat ' + title + '...';
            if (modalImage) { modalImage.src = ''; }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        const r = await api('?ajax=get_attendance_evidence&id=' + id + '&type=' + type, {}, { suppressModal: true });
        
        // Handle landmark response (face geometry proof)
        if (r && r.ok && (r.landmark || r.type === 'landmark')) {
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            if (typeof showAdminLandmarkModal === 'function') {
                showAdminLandmarkModal(r.landmark, title);
            } else {
                showNotif('Landmark wajah tersedia tapi visualisasi tidak tersedia di halaman ini.', true);
            }
            return;
        }
        
        // Handle image response
        const imgData = r.data || r.image || r.evidence;
        if (r && r.ok && imgData) {
            showScreenshotModal(imgData, title);
        } else {
            if (modal) modal.classList.add('hidden');
            showNotif('Tidak ada bukti tersedia untuk record ini', false);
        }
    } catch (e) {
        console.error('Error loading evidence:', e);
        showNotif('Terjadi kesalahan saat memuat bukti', false);
    }
}

async function loadLazyProof(id, type, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    try {
        const r = await api('?ajax=get_attendance_evidence&id=' + id + '&type=' + type, {}, { suppressModal: true });
        
        // Handle image data (Prioritas Utama: Foto Wajah / Bukti Izin)
        const imgData = r.data || r.image || r.evidence;
        if (r && r.ok && imgData) {
            container.innerHTML = `<img src="${imgData}" class="w-full h-full object-contain rounded border shadow-sm hover:scale-110 transition-transform duration-200" onclick="showScreenshotModal('${imgData}', 'Bukti presensi')">` ;
            return;
        }

        // Handle landmark data (Fallback: Geometri Wajah)
        if (r && r.ok && r.has_landmark && r.landmark) {
            container.innerHTML = '';
            const c = document.createElement('canvas');
            c.style.cssText = 'border-radius:.5rem;cursor:pointer;width:100%;background:#0f172a';
            c.title = 'Klik untuk memperbesar visualisasi wajah';
            if (typeof renderLandmarkCanvas === 'function') {
                renderLandmarkCanvas(c, r.landmark, { width: 320, height: 240 });
                c.onclick = () => { if(typeof showAdminLandmarkModal === 'function') showAdminLandmarkModal(r.landmark, 'Bukti Presensi Wajah'); };
            }
            container.appendChild(c);
            return;
        }

        container.innerHTML = `<div class="text-xs text-gray-400 text-center">Tidak ada bukti</div>`;
    } catch (e) {
        container.innerHTML = `<div class="text-xs text-red-500 text-center">Error</div>`;
    }
}

// Setup lazy loading observer
let evidenceObserver = null;
if (window.IntersectionObserver) {
    evidenceObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const id = el.dataset.id;
                const type = el.dataset.type;
                const containerId = el.id;
                if (id && type && !el.dataset.loaded) {
                    el.dataset.loaded = "true";
                    loadLazyProof(id, type, containerId);
                }
            }
        });
    }, { rootMargin: '50px' });
}

function showScreenshotModal(imageSrc, title) {
    const modal = qs('#screenshot-modal');
    const modalTitle = qs('#screenshot-modal-title');
    const modalImage = qs('#screenshot-modal-image');
    
    if (modal && modalTitle && modalImage) {
        modalTitle.textContent = title;
        modalImage.src = imageSrc;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeScreenshotModal() {
    const modal = qs('#screenshot-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

window.showAdminLandmarkModal = function(landmarkData, title) {
    const modal = document.getElementById('landmark-modal');
    const canvas = document.getElementById('landmark-modal-canvas');
    const titleEl = document.getElementById('landmark-modal-title');
    
    if (modal && canvas) {
        if (titleEl) titleEl.innerHTML = `<i class="fi fi-sr-face-recognition text-blue-400"></i> ${title}`;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Wait for modal to be visible before rendering
        setTimeout(() => {
            if (typeof renderLandmarkCanvas === 'function') {
                renderLandmarkCanvas(canvas, landmarkData, { width: 640, height: 480 });
            }
        }, 50);
    }
};

window.closeLandmarkModal = function() {
    const modal = document.getElementById('landmark-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
};

// Close screenshot modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = qs('#screenshot-modal');
    if (modal && !modal.contains(e.target) && !e.target.closest('img[onclick*="showScreenshotModal"]')) {
        closeScreenshotModal();
    }
});
// Add global variables to manage speech synthesis
let currentSpeech = null;
let speechQueue = [];
let isSpeaking = false;
let speechInterval = null;

function speak(text) {
    try {
        // Check if speech synthesis is available
        if (!('speechSynthesis' in window)) {
            console.warn('Speech synthesis not supported');
            return;
        }

        // Add to queue instead of canceling immediately
        if (text && text.trim() && text !== lastSpokenMessage) {
            speechQueue.push(text);
            lastSpokenMessage = text;
        }

        // Start speech processing if not already running
        if (!isSpeaking) {
            processSpeechQueue();
        }
        return;

    } catch (e) {
        console.error('Speech synthesis error:', e);
        isSpeaking = false;
        speechQueue = [];
    }
}

function processSpeechQueue() {
    if (isSpeaking || speechQueue.length === 0) return;
    
    isSpeaking = true;
    const text = speechQueue.shift();
    
    try {
        // Cancel any ongoing speech
        speechSynthesis.cancel();
        
        // Wait for voices to be loaded
        const speakWithVoice = () => {
            const u = new SpeechSynthesisUtterance(text);
            u.lang = 'id-ID';
            u.rate = 0.9; // Faster rate for speed
            u.pitch = 1.0;
            u.volume = window.appVolume !== undefined ? window.appVolume : 1.0;

            // Try to use a local voice if available
            const voices = speechSynthesis.getVoices();
            const indonesianVoice = voices.find(voice => 
                voice.lang.startsWith('id') || 
                voice.lang.includes('Indonesian') ||
                voice.name.includes('Indonesian')
            );
            
            if (indonesianVoice) {
                u.voice = indonesianVoice;
            } else if (voices.length > 0) {
                // Use any available voice as fallback
                u.voice = voices[0];
            }

            u.onstart = () => {
                console.log('Speech started:', text);
            };

            u.onend = () => {
                console.log('Speech ended:', text);
                isSpeaking = false;
                
                // Process next in queue after a short delay
                setTimeout(() => {
                    if (speechQueue.length > 0) {
                        processSpeechQueue();
                    } else if (isCameraActive && !videoInterval && !isDetectionStopped) {
                        startVideoInterval();
                    }
                }, 200); // 200ms interval between speeches
            };

            u.onerror = (e) => {
                console.error('Speech error:', e);
                isSpeaking = false;
                
                // Skip this speech and continue with queue
                setTimeout(() => {
                    if (speechQueue.length > 0) {
                        processSpeechQueue();
                    } else if (isCameraActive && !videoInterval && !isDetectionStopped) {
                        startVideoInterval();
                    }
                }, 100);
            };

            speechSynthesis.speak(u);
            currentSpeech = u;
        };

        // If voices are already loaded, speak immediately
        if (speechSynthesis.getVoices().length > 0) {
            speakWithVoice();
        } else {
            // Wait for voices to load
            speechSynthesis.addEventListener('voiceschanged', speakWithVoice, { once: true });
            
            // Fallback if no voices
            if (speechSynthesis.getVoices().length === 0) {
                console.warn('No voices available, speaking with default settings');
                speakWithVoice();
            }
        }

    } catch (e) {
        console.error('Speech processing error:', e);
        isSpeaking = false;
        
        // Continue with queue
        setTimeout(() => {
            if (speechQueue.length > 0) {
                processSpeechQueue();
            }
        }, 100);
    }
}

// Modify the `statusMessage` function to use the improved `speak` function
let notifLockUntil = 0;
function statusMessage(text, cls) {
    if (!presensiStatus) return;
    
    // Show the text notification
    presensiStatus.textContent = text;
    presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md ' + cls;
    presensiStatus.classList.remove('hidden');

    // Hindari interupsi suara untuk pesan non-kritis
    const now = Date.now();
    const isCritical = /bg-(green|yellow|red)-100/.test(cls || '');
    if (isCritical || now > notifLockUntil) {
        // Hitung durasi lock berdasarkan panjang teks agar tidak terpotong
        const dur = Math.max(2500, Math.min(7000, text.length * 60));
        notifLockUntil = now + dur;
        speak(text);
    }
}



// ===== IndexedDB caching for face descriptors =====
function simpleHash(str){
    let h = 5381; for (let i=0;i<str.length;i++){ h = ((h<<5)+h) + str.charCodeAt(i); h |= 0; }
    return 'v' + (h >>> 0).toString(16);
}

async function computeMembersVersionKey(membersList){
    try{
        const basis = membersList.map(m=>[m.nim, m.foto||m.photo||m.image||'', m.nama||'']).sort((a,b)=>String(a[0]).localeCompare(String(b[0])));
        return simpleHash(JSON.stringify(basis)) + "-v3-members-fix"; // v3: force cache bust for members array fix
    }catch(e){ return 'v-default'; }
}

function idbOpen(){
    return new Promise((resolve,reject)=>{
        const req = indexedDB.open('presensi-cache', 1);
        req.onupgradeneeded = (e)=>{
            const db = e.target.result;
            if (!db.objectStoreNames.contains('descriptors')) {
                db.createObjectStore('descriptors');
            }
        };
        req.onsuccess = ()=> resolve(req.result);
        req.onerror = ()=> reject(req.error);
    });
}

async function idbGetDescriptors(versionKey){
    try{
        const db = await idbOpen();
        return await new Promise((resolve,reject)=>{
            const tx = db.transaction('descriptors','readonly');
            const store = tx.objectStore('descriptors');
            const getReq = store.get(versionKey);
            getReq.onsuccess = ()=> resolve(getReq.result||null);
            getReq.onerror = ()=> resolve(null);
        });
    }catch(e){ return null; }
}

async function idbSetDescriptors(versionKey, data){
    try{
        const db = await idbOpen();
        return await new Promise((resolve,reject)=>{
            const tx = db.transaction('descriptors','readwrite');
            const store = tx.objectStore('descriptors');
            const putReq = store.put(data, versionKey);
            putReq.onsuccess = ()=> resolve(true);
            putReq.onerror = ()=> resolve(false);
        });
    }catch(e){ return false; }
}

// ===== Dashboard Refresh Helper =====
async function refreshDashboardComponents() {
    console.log('[Dashboard] Refreshing all components and clearing cache...');
    
    // 1. Clear API Cache to force fresh data
    if (typeof apiCache !== 'undefined' && apiCache.clear) {
        apiCache.clear();
    }
    
    // 2. Refresh Rekap Page if on that page
    if (typeof initRekapPage === 'function') {
        initRekapPage();
    }
    
    // 3. Refresh Missing Reports Shortcut
    if (typeof loadMissingDailyReports === 'function') {
        await loadMissingDailyReports();
    }
    
    // 4. Refresh Robot Cat Character (with small delay for consistency)
    setTimeout(() => {
        if (typeof loadRobotCatCharacter === 'function') {
            loadRobotCatCharacter();
        }
    }, 500);
}

// ===== API Caching =====
const apiCache = {
    data: {},
    get: function(url, params) {
        const key = url + JSON.stringify(params || {});
        const entry = this.data[key];
        if (entry && Date.now() < entry.expiry) {
            return entry.response;
        }
        return null;
    },
    set: function(url, params, response, ttl = 60000) {
        const key = url + JSON.stringify(params || {});
        this.data[key] = {
            response: response,
            expiry: Date.now() + ttl
        };
    },
    clear: function() {
        this.data = {};
    }
};

// api() is shared through /assets/js/api-client.js.

// Port Detection and Fix
(function() {
    // Check if we're on the wrong port (Local development only)
    if (window.location.port === '3000' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
        console.warn('Detected port 3000, redirecting to correct XAMPP port...');
        // Try common XAMPP ports
        const xamppPorts = ['80', '8080', '8000'];
        let redirectAttempted = false;
        
        for (const port of xamppPorts) {
            if (!redirectAttempted) {
                const testUrl = `${window.location.protocol}//${window.location.hostname}:${port}${window.location.pathname}${window.location.search}`;
                fetch(testUrl, { method: 'HEAD' })
                    .then(response => {
                        if (response.ok && !redirectAttempted) {
                            redirectAttempted = true;
                            console.log(`Redirecting to port ${port}`);
                            window.location.href = testUrl;
                        }
                    })
                    .catch(() => {
                        // Port not available, try next
                    });
            }
        }
    }
})();



// Profile dropdown
(function(){
    const btn = qs('#btn-profile');
    const dd = qs('#dropdown-profile');
    if(btn && dd){
        btn.addEventListener('click', ()=> dd.classList.toggle('hidden'));
        document.addEventListener('click', (e)=>{ if(!btn.contains(e.target) && !dd.contains(e.target)) dd.classList.add('hidden'); });
    }
})();
