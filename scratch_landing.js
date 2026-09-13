
// Browser compatibility polyfills
(function() {
    // Polyfill for getUserMedia for older browsers
    if (!navigator.mediaDevices) {
        navigator.mediaDevices = {};
    }
    if (!navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia = function(constraints) {
            const getUserMedia = navigator.getUserMedia || 
                                 navigator.webkitGetUserMedia || 
                                 navigator.mozGetUserMedia || 
                                 navigator.msGetUserMedia;
            
            if (!getUserMedia) {
                return Promise.reject(new Error('getUserMedia is not supported in this browser'));
            }
            
            return new Promise(function(resolve, reject) {
                getUserMedia.call(navigator, constraints, resolve, reject);
            });
        };
    }
    
    // Polyfill for Promise if needed (for very old browsers)
    if (typeof Promise === 'undefined') {
        window.Promise = function(executor) {
            // Simple Promise polyfill
            const self = this;
            self.state = 'pending';
            self.value = undefined;
            self.handlers = [];
            
            function resolve(result) {
                if (self.state === 'pending') {
                    self.state = 'fulfilled';
                    self.value = result;
                    self.handlers.forEach(handle);
                    self.handlers = null;
                }
            }
            
            function reject(error) {
                if (self.state === 'pending') {
                    self.state = 'rejected';
                    self.value = error;
                    self.handlers.forEach(handle);
                    self.handlers = null;
                }
            }
            
            function handle(handler) {
                if (self.state === 'pending') {
                    self.handlers.push(handler);
                } else {
                    if (self.state === 'fulfilled' && typeof handler.onFulfilled === 'function') {
                        handler.onFulfilled(self.value);
                    }
                    if (self.state === 'rejected' && typeof handler.onRejected === 'function') {
                        handler.onRejected(self.value);
                    }
                }
            }
            
            self.then = function(onFulfilled, onRejected) {
                return new Promise(function(resolve, reject) {
                    handle({
                        onFulfilled: function(result) {
                            try {
                                resolve(onFulfilled ? onFulfilled(result) : result);
                            } catch (ex) {
                                reject(ex);
                            }
                        },
                        onRejected: function(error) {
                            try {
                                resolve(onRejected ? onRejected(error) : error);
                            } catch (ex) {
                                reject(ex);
                            }
                        }
                    });
                });
            };
            
            executor(resolve, reject);
        };
    }
    
    // Performance optimization: RequestIdleCallback polyfill
    if (!window.requestIdleCallback) {
        window.requestIdleCallback = function(callback, options) {
            const start = Date.now();
            return setTimeout(function() {
                callback({
                    didTimeout: false,
                    timeRemaining: function() {
                        return Math.max(0, 50 - (Date.now() - start));
                    }
                });
            }, 1);
        };
    }
    
    if (!window.cancelIdleCallback) {
        window.cancelIdleCallback = function(id) {
            clearTimeout(id);
        };
    }
    
    // Browser-specific fixes
    const ua = navigator.userAgent.toLowerCase();
    const isSafari = /safari/.test(ua) && !/chrome/.test(ua) && !/chromium/.test(ua);
    const isFirefox = /firefox/.test(ua);
    const isChrome = /chrome/.test(ua) && !/edge/.test(ua);
    const isMIBrowser = /miui/.test(ua) || /xiaomi/.test(ua);
    const isEdge = /edge/.test(ua);
    
    // Safari-specific fixes
    if (isSafari) {
        // Safari has issues with video autoplay - ensure video plays
        if (HTMLVideoElement.prototype.play) {
            const originalPlay = HTMLVideoElement.prototype.play;
            HTMLVideoElement.prototype.play = function() {
                const promise = originalPlay.call(this);
                if (promise && promise.catch) {
                    promise.catch(() => {
                        // Ignore autoplay errors in Safari
                    });
                }
                return promise;
            };
        }
        
        // Safari canvas fix for better performance
        if (HTMLCanvasElement.prototype.getContext) {
            const originalGetContext = HTMLCanvasElement.prototype.getContext;
            HTMLCanvasElement.prototype.getContext = function(contextType, attributes) {
                if (contextType === '2d' && attributes) {
                    attributes.willReadFrequently = false; // Better performance in Safari
                }
                return originalGetContext.call(this, contextType, attributes);
            };
        }
    }
    
    // Firefox-specific fixes
    if (isFirefox) {
        // Firefox may need explicit video play
        if (HTMLVideoElement.prototype.play) {
            const originalPlay = HTMLVideoElement.prototype.play;
            HTMLVideoElement.prototype.play = function() {
                const promise = originalPlay.call(this);
                if (promise && promise.catch) {
                    promise.catch(() => {
                        // Try to play with user interaction
                        this.muted = true;
                        return originalPlay.call(this);
                    });
                }
                return promise;
            };
        }
    }
    
    // MI Browser / Xiaomi Browser fixes
    if (isMIBrowser) {
        // MI Browser may have issues with getUserMedia - add extra fallback
        if (!navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia = function(constraints) {
                const getUserMedia = navigator.getUserMedia || 
                                   navigator.webkitGetUserMedia || 
                                   navigator.mozGetUserMedia || 
                                   navigator.msGetUserMedia;
                
                if (!getUserMedia) {
                    return Promise.reject(new Error('getUserMedia is not supported'));
                }
                
                return new Promise(function(resolve, reject) {
                    getUserMedia.call(navigator, constraints, resolve, reject);
                });
            };
        }
    }
    
    // Edge-specific fixes
    if (isEdge) {
        // Edge may need specific handling
        if (HTMLVideoElement.prototype.srcObject === undefined) {
            Object.defineProperty(HTMLVideoElement.prototype, 'srcObject', {
                get: function() {
                    return this.mozSrcObject || this.webkitSrcObject || null;
                },
                set: function(stream) {
                    if (this.mozSrcObject !== undefined) {
                        this.mozSrcObject = stream;
                    } else if (this.webkitSrcObject !== undefined) {
                        this.webkitSrcObject = stream;
                    } else {
                        this.src = window.URL.createObjectURL(stream);
                    }
                }
            });
        }
    }
    
    // Cross-browser canvas optimization
    if (HTMLCanvasElement.prototype.getContext) {
        const originalGetContext = HTMLCanvasElement.prototype.getContext;
        HTMLCanvasElement.prototype.getContext = function(contextType, attributes) {
            if (contextType === '2d') {
                // Optimize canvas for better performance across all browsers
                const optimizedAttributes = attributes || {};
                optimizedAttributes.alpha = true;
                optimizedAttributes.desynchronized = false;
                optimizedAttributes.willReadFrequently = false; // Better performance
                return originalGetContext.call(this, contextType, optimizedAttributes);
            }
            return originalGetContext.call(this, contextType, attributes);
        };
    }
    
    // Log browser detection
    console.log(`Browser detected: ${isSafari ? 'Safari' : isFirefox ? 'Firefox' : isChrome ? 'Chrome' : isMIBrowser ? 'MI Browser' : isEdge ? 'Edge' : 'Other'}`);
})();

// Landing page - Face recognition attendance
const videoContainer = qs('#video-container');
const video = qs('#video');
const canvas = qs('#canvas');
const presensiStatus = qs('#presensi-status');
const scanButtonsContainer = qs('#scan-buttons-container');
const btnScanMasuk = qs('#btn-scan-masuk');
const btnScanPulang = qs('#btn-scan-pulang');
const btnBackScan = qs('#btn-back-scan');
const loadingOverlay = qs('#loading-overlay');

let labeledFaceDescriptors = [];
let isCameraActive = false;
let videoInterval = null;
let scanMode = '';
let lastSpokenMessage = '';
let videoPlayListenerAdded = false;
let isPresensiSuccess = false; // Flag untuk menandai presensi sudah berhasil
let isDetectionStopped = false; // Flag untuk menandai detection dihentikan manual

// Optimasi: Performance monitoring variables
let performanceStats = {
    detectionCount: 0,
    totalDetectionTime: 0,
    averageDetectionTime: 0,
    lastDetectionTime: 0
};

var detectionConfig = window.detectionConfig;
var loadFaceRecognitionSettings = window.loadFaceRecognitionSettings;

// Detect if device is mobile/phone (including mobile simulators)
function isMobileDevice() {
    const ua = navigator.userAgent.toLowerCase();
    const isMobileUA = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(ua);
    const isMobileViewport = window.innerWidth <= 768;
    const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    // Check for mobile simulator extensions (common patterns)
    // More aggressive detection for simulators
    const isSimulator = ua.includes('mobile') || 
                       ua.includes('simulator') || 
                       ua.includes('phone') ||
                       window.screen.width <= 768 || 
                       (isMobileViewport && hasTouch) ||
                       (window.innerWidth <= 768 && window.innerHeight <= 1024);
    
    return isMobileUA || (isMobileViewport && hasTouch) || isSimulator;
}

// Detect device performance level (for optimization)
let devicePerformanceLevel = 'unknown'; // 'high', 'medium', 'low'
let devicePerformanceDetected = false;

function detectDevicePerformance() {
    if (devicePerformanceDetected) return devicePerformanceLevel;
    
    devicePerformanceDetected = true;
    const ua = navigator.userAgent.toLowerCase();
    
    // Detect low-end devices
    const isLowEndDevice = 
        // Android low-end indicators
        (ua.includes('android') && (
            ua.includes('samsung') && (ua.includes('sm-a') || ua.includes('sm-j') || ua.includes('sm-g')) ||
            ua.includes('xiaomi') && (ua.includes('redmi') || ua.includes('mi a')) ||
            ua.includes('oppo') && ua.includes('a') ||
            ua.includes('vivo') && ua.includes('y')
        )) ||
        // Old laptop indicators
        (ua.includes('windows') && (
            ua.includes('nt 10.0') && !ua.includes('edge') && !ua.includes('chrome') // Old Windows 10
        )) ||
        // Low memory/CPU indicators
        (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 2) ||
        (navigator.deviceMemory && navigator.deviceMemory <= 2);
    
    // Detect high-end devices
    const isHighEndDevice = 
        ua.includes('iphone') && (ua.includes('iphone15') || ua.includes('iphone14') || ua.includes('iphone13')) ||
        (navigator.hardwareConcurrency && navigator.hardwareConcurrency >= 8) ||
        (navigator.deviceMemory && navigator.deviceMemory >= 8);
    
    // Performance test
    const start = performance.now();
    for (let i = 0; i < 100000; i++) {
        Math.sqrt(i);
    }
    const testTime = performance.now() - start;
    
    if (isLowEndDevice || testTime > 5) {
        devicePerformanceLevel = 'low';
    } else if (isHighEndDevice || testTime < 1) {
        devicePerformanceLevel = 'high';
    } else {
        devicePerformanceLevel = 'medium';
    }
    
    console.log(`Device Performance: ${devicePerformanceLevel} (test: ${testTime.toFixed(2)}ms, cores: ${navigator.hardwareConcurrency || 'unknown'}, memory: ${navigator.deviceMemory || 'unknown'}GB)`);
    
    return devicePerformanceLevel;
}

// Get adjusted threshold based on device type
function getAdjustedRecognitionThreshold() {
    if (isMobileDevice()) {
        // Much more lenient threshold for mobile devices (0.55 instead of 0.38)
        // This allows distance up to 0.55 for mobile devices for easier detection
        return 0.55;
    }
    return detectionConfig.recognitionThreshold;
}

// Get adjusted face matcher threshold based on device type
function getAdjustedFaceMatcherThreshold() {
    if (isMobileDevice()) {
        // Much more lenient threshold for mobile devices (0.55 instead of 0.38)
        return 0.55;
    }
    return detectionConfig.faceMatcherThreshold;
}

// Get adjusted quality threshold based on device type
function getAdjustedQualityThreshold() {
    if (isMobileDevice()) {
        // Much more lenient quality threshold for mobile devices
        return 0.45; // Lowered from 0.50 to 0.45 for easier detection on mobile
    }
    return detectionConfig.qualityThreshold;
}

// Get adjusted landmark threshold based on device type
function getAdjustedLandmarkThreshold() {
    if (isMobileDevice()) {
        // Much more lenient landmark threshold for mobile devices
        return 0.45; // Lowered from 0.50 to 0.45 for easier detection on mobile
    }
    return detectionConfig.landmarkThreshold;
}
let logMasukData = [];
let logPulangData = [];
let members = []; // Global members array for gender validation

// WFA Modal functions for landing page

function showOvertimeModal(message) {
    // Create Overtime modal if it doesn't exist
    let overtimeModal = document.getElementById('overtimeModal');
    if (!overtimeModal) {
        overtimeModal = document.createElement('div');
        overtimeModal.id = 'overtimeModal';
        overtimeModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overtimeModal.style.display = 'flex';
        overtimeModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-2xl">
                <h3 class="text-lg font-semibold mb-4">Overtime</h3>
                <p class="text-gray-600 mb-4">${message}</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lokasi Overtime:</label>
                    <input type="text" id="overtimeLocation" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="Masukkan lokasi overtime..." required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Overtime:</label>
                    <textarea id="overtimeReason" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" rows="3" placeholder="Masukkan alasan overtime..." required></textarea>
                </div>
                <div class="flex space-x-3">
                    <button id="overtimeSubmit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        Submit
                    </button>
                    <button id="overtimeCancel" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overtimeModal);
        
        // Add event listeners
        document.getElementById('overtimeSubmit').addEventListener('click', () => {
            const location = document.getElementById('overtimeLocation').value.trim();
            const reason = document.getElementById('overtimeReason').value.trim();
            if (location && reason) {
                overtimeModal.style.display = 'none';
                overtimeModal.classList.add('hidden');
                // Store Overtime reason and location for next attendance submission
                window.pendingOvertimeReason = reason;
                window.pendingOvertimeLocation = location;
                // Retry attendance submission
                if (window.pendingAttendanceData) {
                    submitAttendanceWithOvertime(window.pendingAttendanceData, reason, location);
                }
            } else {
                showNotif('Harap isi lokasi dan alasan overtime terlebih dahulu.', false);
            }
        });
        
        document.getElementById('overtimeCancel').addEventListener('click', () => {
            overtimeModal.style.display = 'none';
            overtimeModal.classList.add('hidden');
            isProcessingRecognition = false;
            // Clear pending data
            window.pendingOvertimeReason = null;
            window.pendingOvertimeLocation = null;
            window.pendingAttendanceData = null;
        });
    } else {
        // Modal exists, just show it
        overtimeModal.style.display = 'flex';
        overtimeModal.classList.remove('hidden');
        // Update message if modal exists
        const messageEl = overtimeModal.querySelector('p.text-gray-600');
        if (messageEl && message) {
            messageEl.textContent = message;
        }
    }
    
    // Show modal and populate location from pending data if available
    const locationInput = document.getElementById('overtimeLocation');
    const reasonInput = document.getElementById('overtimeReason');
    if (locationInput && window.pendingAttendanceData && window.pendingAttendanceData.lokasi) {
        locationInput.value = window.pendingAttendanceData.lokasi;
    }
    // Clear reason input when showing modal
    if (reasonInput) {
        reasonInput.value = '';
    }
    if (locationInput) {
        setTimeout(() => locationInput.focus(), 100);
    }
}

function showWFAModal(message) {
    // Create WFA modal if it doesn't exist
    let wfaModal = document.getElementById('wfaModal');
    if (!wfaModal) {
        wfaModal = document.createElement('div');
        wfaModal.id = 'wfaModal';
        wfaModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
        wfaModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Work From Anywhere (WFA)</h3>
                <p class="text-gray-600 mb-4">${message}</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan WFA:</label>
                    <textarea id="wfaReason" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Masukkan alasan kerja di luar kantor..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button id="wfaSubmit" class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Submit
                    </button>
                    <button id="wfaCancel" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(wfaModal);
        
        // Add event listeners
        document.getElementById('wfaSubmit').addEventListener('click', () => {
            const reason = document.getElementById('wfaReason').value.trim();
            if (reason) {
                wfaModal.classList.add('hidden');
                // Store WFA reason for next attendance submission
                window.pendingWFAReson = reason;
                // Retry attendance submission
                if (window.pendingAttendanceData) {
                    submitAttendanceWithWFA(window.pendingAttendanceData, reason);
                }
            } else {
                showNotif('Harap isi alasan WFA terlebih dahulu.', false);
            }
        });
        
        document.getElementById('wfaCancel').addEventListener('click', () => {
            wfaModal.classList.add('hidden');
            isProcessingRecognition = false;
            // Clear pending data
            window.pendingWFAReson = null;
            window.pendingAttendanceData = null;
        });
    }
    
    // Show modal
    wfaModal.classList.remove('hidden');
    document.getElementById('wfaReason').focus();
}

// Show location confirmation modal
function showLocationConfirmation(lokasi, lat, lng, onRecheck = null) {
    return new Promise((resolve) => {
        // Create location confirmation modal if it doesn't exist
        let locationModal = document.getElementById('locationConfirmationModal');
        if (!locationModal) {
            locationModal = document.createElement('div');
            locationModal.id = 'locationConfirmationModal';
            locationModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            locationModal.style.display = 'flex';
            locationModal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">Konfirmasi Lokasi</h3>
                    <p class="text-gray-600 mb-4">Apakah lokasi berikut benar?</p>
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 mb-1">Lokasi Saat Ini:</p>
                        <p class="text-sm text-gray-900" id="location-confirmation-text">${lokasi}</p>
                        <p class="text-xs text-gray-500 mt-2" id="location-confirmation-coords">Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                    </div>
                    <div id="location-checking-indicator" class="hidden mb-2 text-sm text-blue-600">
                        <i class="fi fi-sr-spinner animate-spin mr-1"></i> Memeriksa lokasi ulang...
                    </div>
                    <div class="flex space-x-3">
                        <button id="locationConfirmYes" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Ya, Benar
                        </button>
                        <button id="locationConfirmNo" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Periksa Ulang
                        </button>
                        <button id="locationConfirmCancel" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Batal
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(locationModal);
        }
        
        // Update location text
        const locationText = document.getElementById('location-confirmation-text');
        const coordText = document.getElementById('location-confirmation-coords');
        const checkingIndicator = document.getElementById('location-checking-indicator');
        if (locationText) {
            locationText.textContent = lokasi;
        }
        if (coordText) {
            coordText.textContent = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        if (checkingIndicator) {
            checkingIndicator.classList.add('hidden');
        }
        locationModal.style.display = 'flex';
        locationModal.classList.remove('hidden');
        
        // Return promise that resolves when user clicks
        const yesBtn = document.getElementById('locationConfirmYes');
        const noBtn = document.getElementById('locationConfirmNo');
        const cancelBtn = document.getElementById('locationConfirmCancel');
        
        // Remove old listeners and add new ones
        const newYesBtn = yesBtn.cloneNode(true);
        const newNoBtn = noBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYesBtn, yesBtn);
        noBtn.parentNode.replaceChild(newNoBtn, noBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        // Store current values that can be updated
        let currentValues = { lokasi, lat, lng };
        
        newYesBtn.addEventListener('click', () => {
            locationModal.style.display = 'none';
            locationModal.classList.add('hidden');
            // Return updated values
            resolve({ confirmed: true, ...currentValues });
        });
        
        newCancelBtn.addEventListener('click', () => {
            locationModal.style.display = 'none';
            locationModal.classList.add('hidden');
            resolve({ confirmed: false });
        });
        
        newNoBtn.addEventListener('click', async () => {
            // If recheck callback is provided, call it to re-check location
            if (onRecheck && typeof onRecheck === 'function') {
                if (checkingIndicator) {
                    checkingIndicator.classList.remove('hidden');
                }
                newNoBtn.disabled = true;
                newYesBtn.disabled = true;
                newCancelBtn.disabled = true;
                
                try {
                    // Call recheck function - it should return new {lokasi, lat, lng}
                    const newLocation = await onRecheck();
                    if (newLocation && newLocation.lokasi && newLocation.lat && newLocation.lng) {
                        // Update modal with new location
                        if (locationText) {
                            locationText.textContent = newLocation.lokasi;
                        }
                        if (coordText) {
                            coordText.textContent = `Koordinat: ${newLocation.lat.toFixed(6)}, ${newLocation.lng.toFixed(6)}`;
                        }
                        // Update current values
                        currentValues = { lokasi: newLocation.lokasi, lat: newLocation.lat, lng: newLocation.lng };
                    } else {
                        // Recheck failed - show error
                        if (locationText) {
                            locationText.textContent = 'Gagal mendapatkan lokasi. Silakan coba lagi atau klik Batal.';
                        }
                    }
                } catch (error) {
                    console.error('Error rechecking location:', error);
                    if (locationText) {
                        locationText.textContent = 'Error: ' + (error.message || 'Gagal memeriksa lokasi');
                    }
                } finally {
                    if (checkingIndicator) {
                        checkingIndicator.classList.add('hidden');
                    }
                    newNoBtn.disabled = false;
                    newYesBtn.disabled = false;
                    newCancelBtn.disabled = false;
                }
                // Don't resolve - keep modal open for user to confirm new location
            } else {
                // No recheck function - just cancel
                locationModal.style.display = 'none';
                locationModal.classList.add('hidden');
                resolve({ confirmed: false });
            }
        });
    });
}

function submitAttendanceWithOvertime(attendanceData, overtimeReason, overtimeLocation) {
    // Add Overtime reason and location to attendance data
    const dataWithOvertime = {
        ...attendanceData,
        overtime_reason: overtimeReason,
        overtime_location: overtimeLocation,
        is_overtime: true
    };
    
    // Submit attendance with Overtime reason and location
    api('?ajax=save_attendance', dataWithOvertime, { suppressModal: true })
        .then(response => {
            if (response.ok) {
                statusMessage('Presensi overtime berhasil!', 'bg-purple-100 text-purple-700');
                // Clear pending data
                window.pendingOvertimeReason = null;
                window.pendingOvertimeLocation = null;
                window.pendingAttendanceData = null;
                isProcessingRecognition = false;
            } else {
                const errorMsg = response.message || 'Presensi gagal. Silakan coba lagi.';
                statusMessage('Gagal menyimpan presensi: ' + errorMsg, 'bg-red-100 text-red-700');
                isProcessingRecognition = false;
            }
        })
        .catch(error => {
            console.error('Error submitting overtime attendance:', error);
            statusMessage('Terjadi kesalahan saat menyimpan presensi overtime.', 'bg-red-100 text-red-700');
            isProcessingRecognition = false;
        });
}

function submitAttendanceWithWFA(attendanceData, wfaReason) {
    // Add WFA reason to attendance data
    const dataWithWFA = {
        ...attendanceData,
        wfa_reason: wfaReason,
        is_wfa: true
    };
    
    // Submit attendance with WFA reason
    api('?ajax=save_attendance', dataWithWFA, { suppressModal: true })
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

function showEarlyLeaveModal(message) {
    // Try to find existing modal from HTML first
    let earlyLeaveModal = document.getElementById('early-leave-reason-modal');
    
    if (!earlyLeaveModal) {
        // Create modal dynamically if not found in HTML
        earlyLeaveModal = document.createElement('div');
        earlyLeaveModal.id = 'early-leave-reason-modal';
        earlyLeaveModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
        earlyLeaveModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-2xl">
                <h3 class="text-lg font-semibold mb-4">Alasan Pulang Awal</h3>
                <p class="text-gray-600 mb-4">${message || 'Anda pulang sebelum jam yang ditentukan. Silakan isi alasan pulang awal untuk melanjutkan presensi pulang.'}</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Pulang Awal:</label>
                    <textarea id="earlyLeaveReason" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" rows="4" placeholder="Masukkan alasan pulang awal..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button id="earlyLeaveSubmit" class="flex-1 bg-orange-600 text-white py-2 px-4 rounded-lg hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500">
                        Kirim
                    </button>
                    <button id="earlyLeaveCancel" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(earlyLeaveModal);
        
        // Add event listeners for dynamically created modal
        document.getElementById('earlyLeaveSubmit').addEventListener('click', () => {
            const reason = document.getElementById('earlyLeaveReason').value.trim();
            if (reason) {
                earlyLeaveModal.classList.add('hidden');
                // Store early leave reason for next attendance submission
                window.pendingEarlyLeaveReason = reason;
                // Retry attendance submission
                if (window.pendingAttendanceData) {
                    submitAttendanceWithEarlyLeave(window.pendingAttendanceData, reason);
                }
            } else {
                showNotif('Harap isi alasan pulang awal terlebih dahulu.', false);
            }
        });
        
        document.getElementById('earlyLeaveCancel').addEventListener('click', () => {
            earlyLeaveModal.classList.add('hidden');
            isProcessingRecognition = false;
            // Clear pending data
            window.pendingEarlyLeaveReason = null;
            window.pendingAttendanceData = null;
        });
    } else {
        // Modal exists in HTML, use it and set up event listeners
        const reasonInput = document.getElementById('early-leave-reason-input');
        const submitBtn = document.getElementById('early-leave-reason-submit');
        const cancelBtn = document.getElementById('early-leave-reason-cancel');
        
        // Clear previous input
        if (reasonInput) {
            reasonInput.value = '';
        }
        
        // Update message if there's a message element (for dynamic modal)
        const messageEl = earlyLeaveModal.querySelector('p.text-gray-600');
        if (messageEl && message) {
            messageEl.textContent = message;
        }
        
        if (submitBtn && cancelBtn) {
            // Remove old event listeners by cloning
            const newSubmitBtn = submitBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            submitBtn.parentNode.replaceChild(newSubmitBtn, submitBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            
            // Add event listeners
            newSubmitBtn.addEventListener('click', () => {
                const reason = reasonInput ? reasonInput.value.trim() : '';
                if (reason) {
                    earlyLeaveModal.classList.add('hidden');
                    // Store early leave reason for next attendance submission
                    window.pendingEarlyLeaveReason = reason;
                    // Retry attendance submission
                    if (window.pendingAttendanceData) {
                        submitAttendanceWithEarlyLeave(window.pendingAttendanceData, reason);
                    }
                } else {
                    showNotif('Harap isi alasan pulang awal terlebih dahulu.', false);
                }
            });
            
            newCancelBtn.addEventListener('click', () => {
                earlyLeaveModal.classList.add('hidden');
                isProcessingRecognition = false;
                // Clear pending data
                window.pendingEarlyLeaveReason = null;
                window.pendingAttendanceData = null;
            });
        }
    }
    
    // Show modal
    earlyLeaveModal.classList.remove('hidden');
    const reasonInput = document.getElementById('earlyLeaveReason') || document.getElementById('early-leave-reason-input');
    if (reasonInput) {
        setTimeout(() => reasonInput.focus(), 100);
    }
}

function submitAttendanceWithEarlyLeave(attendanceData, earlyLeaveReason) {
    // Add early leave reason to attendance data
    const dataWithEarlyLeave = {
        ...attendanceData,
        alasan_pulang_awal: earlyLeaveReason,
        early_leave_reason: earlyLeaveReason
    };
    
    isProcessingRecognition = true;
    statusMessage('Menyimpan presensi pulang awal...', 'bg-blue-100 text-blue-700');

    // Submit attendance with early leave reason
    api('?ajax=save_attendance', dataWithEarlyLeave, { suppressModal: true })
        .then(response => {
            if (response.ok) {
                // --- Mirror exactly what submitFinalAttendance does on success ---
                statusMessage('Presensi pulang berhasil dengan alasan pulang awal!', 'bg-green-100 text-green-700');

                // 1. Mark as success so detection loop stops and camera stays visible
                isPresensiSuccess = true;
                isDetectionPaused = false;

                // 2. Play success voice
                if (typeof speak === 'function') {
                    speak('Presensi pulang berhasil disimpan. Terima kasih.');
                }

                // 3. Show "Next Scan" button
                const nextScanContainer = document.getElementById('next-scan-container');
                if (nextScanContainer) nextScanContainer.classList.remove('hidden');

                // 4. Refresh the attendance log
                const nim = attendanceData.nim || attendanceData.id || '';
                const mode = attendanceData.mode || 'pulang';
                if (typeof updateLogAfterAttendance === 'function') {
                    updateLogAfterAttendance(nim, mode);
                } else if (typeof loadLogPulang === 'function') {
                    setTimeout(loadLogPulang, 500); // Fallback: reload log table
                }

                // 5. Clear pending data
                window.pendingEarlyLeaveReason = null;
                window.pendingAttendanceData = null;
            } else {
                const errorMsg = response.message || 'Presensi gagal. Silakan coba lagi.';
                statusMessage('Gagal menyimpan presensi: ' + errorMsg, 'bg-red-100 text-red-700');
                if (typeof speak === 'function') speak('Gagal. ' + errorMsg);
            }
        })
        .catch(error => {
            console.error('Error submitting attendance with early leave:', error);
            statusMessage('Terjadi kesalahan saat menyimpan presensi.', 'bg-red-100 text-red-700');
        })
        .finally(() => {
            isProcessingRecognition = false;
        });
}

// Enhanced location detection with reverse geocoding - ALWAYS shows actual device location
async function getStreetNameFromCoordinates(lat, lng) {
    // ALWAYS use reverse geocoding to get actual location - never assume WFO location
    // This ensures the modal shows the real device location, not a preset location
    try {
        // Use PHP proxy to avoid CORS issues
        const result = await api('?ajax=reverse_geocode', { action: 'reverse_geocode', lat: lat, lng: lng }, { suppressModal: true });
        
        if (!result.ok || !result.data) {
            console.error('[GEOCODE] Invalid result format:', result);
            throw new Error('Reverse geocoding failed');
        }
        
        const data = result.data;
        
        if (data && data.address) {
            const address = data.address;
            const parts = [];
            
            // 1. Building name or house name (most specific) - prioritize this for places like malls, universities
            if (address.building) {
                parts.push(address.building);
            } else if (address.house_name) {
                parts.push(address.house_name);
            }
            
            // Check for known places in display_name (like Trans Studio Mall, Telkom University, etc.)
            if (data.display_name) {
                const displayName = data.display_name.toLowerCase();
                // Check for common place names
                if (displayName.includes('trans studio') || displayName.includes('transstudio')) {
                    parts.push('Trans Studio Mall Bandung');
                } else if (displayName.includes('telkom university') || displayName.includes('telkom university')) {
                    parts.push('Telkom University');
                } else if (displayName.includes('fakultas ilmu terapan')) {
                    parts.push('Fakultas Ilmu Terapan Telkom University');
                }
            }
            
            // 2. Road/Street with house number if available
            const roadParts = [];
            if (address.house_number) roadParts.push(address.house_number);
            if (address.road) roadParts.push(address.road);
            else if (address.pedestrian) roadParts.push(address.pedestrian);
            else if (address.footway) roadParts.push(address.footway);
            if (roadParts.length > 0) {
                parts.push('Jl. ' + roadParts.join(' '));
            }
            
            // 3. Suburb/Neighbourhood
            if (address.suburb) parts.push(address.suburb);
            else if (address.neighbourhood) parts.push(address.neighbourhood);
            
            // 4. City/Town/Village
            if (address.city) parts.push(address.city);
            else if (address.town) parts.push(address.town);
            else if (address.village) parts.push(address.village);
            
            // 5. State/Province
            if (address.state) parts.push(address.state);
            
            // 6. Postal code
            if (address.postcode) parts.push(address.postcode);
            
            if (parts.length > 0) {
                return parts.join(', ');
            }
            
            // Fallback to display_name with postal code
            if (data.display_name) {
                let cleanName = data.display_name.replace(/, Indonesia$/, '');
                // Remove redundant "Bandung" if already in parts
                if (address.postcode) {
                    cleanName += ', ' + address.postcode;
                }
                return cleanName;
            }
        }
        
        // If address parsing failed but display_name exists, use it
        if (data && data.display_name) {
            let cleanName = data.display_name.replace(/, Indonesia$/, '');
            return cleanName;
        }
    } catch (error) {
        // Silently fail - will use coordinates fallback
        console.warn('Reverse geocoding failed:', error);
    }
    
    // Final fallback: coordinates only (no distance info to avoid confusion)
    return `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

// Helper function to calculate distance between two coordinates
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Helper functions for image variations to improve recognition accuracy
function createRotatedImage(img, degrees) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Set canvas size to accommodate rotation
        const size = Math.max(img.width, img.height) * 1.5;
        canvas.width = size;
        canvas.height = size;
        
        // Center the image
        ctx.translate(size / 2, size / 2);
        ctx.rotate((degrees * Math.PI) / 180);
        ctx.drawImage(img, -img.width / 2, -img.height / 2);
        
        // Convert back to image
        const rotatedImg = new Image();
        rotatedImg.onload = () => resolve(rotatedImg);
        rotatedImg.src = canvas.toDataURL();
    });
}

function createScaledImage(img, scale) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        
        const scaledImg = new Image();
        scaledImg.onload = () => resolve(scaledImg);
        scaledImg.src = canvas.toDataURL();
    });
}


// Legacy attendance logic removed. Now using assets/js/attendance.js

if (btnScanMasuk) {
    btnScanMasuk.addEventListener('click', ()=> startScan('masuk'));
}
if (btnScanPulang) {
    btnScanPulang.addEventListener('click', ()=> startScan('pulang'));
}
if (btnBackScan) {
    btnBackScan.addEventListener('click', ()=>{ resetPresensiPage(); });
}

// Force request permissions on page load (for all devices)
document.addEventListener('DOMContentLoaded', async () => {
    // Request camera permission immediately on page load
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        // Stop immediately - we just want to trigger permission prompt
        stream.getTracks().forEach(track => track.stop());
    } catch (err) {
        // Permission denied or error - will be handled when user clicks button
        console.log('Camera permission request on load:', err.name);
    }
    
    // Request location permission immediately on page load
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            () => {}, // Success - permission granted
            () => {}, // Error - will be handled when needed
            { timeout: 3000, enableHighAccuracy: true }
        );
    }
    
    // Auto-start presensi if mode parameter is provided (from employee page)
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    if (mode === 'masuk' || mode === 'pulang') {
        // Wait a bit for page to fully load, then auto-start
        setTimeout(() => {
            startScan(mode);
        }, 500);
    }
});

// Add event listener for stop detection button
const btnStopDetection = qs('#btn-stop-detection');
if (btnStopDetection) {
    btnStopDetection.addEventListener('click', ()=>{ 
        stopDetection();
        const btnStart = qs('#btn-start-detection');
        if (btnStart) btnStart.classList.remove('hidden');
        btnStopDetection.classList.add('hidden');
        statusMessage('Deteksi dihentikan. Klik "Mulai Deteksi" untuk melanjutkan.', 'bg-yellow-100 text-yellow-700');
    });
}

function resetPresensiPage(){
    stopVideo();
    resetRecognitionSystem(); // Reset recognition system
    isPresensiSuccess = false; // Reset presensi success flag
    isDetectionStopped = false; // Reset stop detection flag
    processedLabels.clear(); // Clear processed labels
    scanButtonsContainer.classList.remove('hidden');
    videoContainer.classList.add('hidden');
    btnBackScan.classList.add('hidden');
    qs('#btn-stop-detection').classList.add('hidden');
    const btnStart = qs('#btn-start-detection');
    if (btnStart) btnStart.classList.add('hidden');
    
    // Check if we have return parameter - redirect to employee page
    const urlParams = new URLSearchParams(window.location.search);
    const returnParam = urlParams.get('return');
    if (returnParam === 'app') {
        // Redirect back to employee page (app)
        window.location.href = '?page=app';
        return;
    }
    
    // Show the two panel layout (text and image sections) again
    const twoPanelLayout = qs('#two-panel-layout');
    if (twoPanelLayout) {
        twoPanelLayout.classList.remove('hidden');
    }
    
    qs('#log-masuk-container').classList.add('hidden');
    qs('#log-pulang-container').classList.add('hidden');
    if (presensiStatus) {
        presensiStatus.classList.add('hidden');
        presensiStatus.textContent='';
    }
    videoPlayListenerAdded = false;
    if (window.presensiTimeout) {
        clearTimeout(window.presensiTimeout);
        window.presensiTimeout = null;
    }
    if (window.speechTimeout) {
        clearTimeout(window.speechTimeout);
        window.speechTimeout = null;
    }
    speechSynthesis.cancel();
    speechQueue = [];
    isSpeaking = false;
    
    // Advanced: Reset detection history for fresh start
    detectionHistory = [];
    lastSuccessfulDetection = null;
    detectionAttempts = 0;
    recognitionCompleted = false; // Reset recognition completion flag
}

function startVideo(){
    if (!video) return;
    
    // Browser compatibility: Try modern API first, then fallback
    const getUserMedia = navigator.mediaDevices?.getUserMedia || 
                        navigator.getUserMedia || 
                        navigator.webkitGetUserMedia || 
                        navigator.mozGetUserMedia || 
                        navigator.msGetUserMedia;
    
    if (!getUserMedia) {
        statusMessage('Browser tidak mendukung akses kamera. Silakan gunakan browser modern (Chrome, Firefox, Safari, Edge).', 'bg-red-100 text-red-700');
        return;
    }
    
    // Detect device performance and adjust video constraints
    const perfLevel = detectDevicePerformance();
    let videoConstraints = {
        video: {
            width: { ideal: detectDevicePerformance() === 'low' ? 320 : (detectDevicePerformance() === 'medium' ? 480 : 640), max: detectDevicePerformance() === 'low' ? 640 : 1280 },
            height: { ideal: detectDevicePerformance() === 'low' ? 240 : (detectDevicePerformance() === 'medium' ? 360 : 480), max: detectDevicePerformance() === 'low' ? 480 : 720 },
            frameRate: detectDevicePerformance() === 'low' ? { ideal: 10, max: 15 } : (detectDevicePerformance() === 'medium' ? { ideal: 12, max: 20 } : { ideal: 15, max: 30 }),
            facingMode: 'user'
        }
    };
    
    // Optimize video constraints for low-end devices - MORE AGGRESSIVE
    if (perfLevel === 'low') {
        videoConstraints = {
            video: {
                width: { ideal: 240, max: 480 }, // Reduced from 320 to 240
                height: { ideal: 180, max: 360 }, // Reduced from 240 to 180
                frameRate: { ideal: 8, max: 12 }, // Reduced from 10-15 to 8-12
                facingMode: 'user'
            }
        };
        console.log('Low-end device detected - using very low video resolution for better performance');
    } else if (perfLevel === 'medium') {
        videoConstraints = {
            video: {
                width: { ideal: 360, max: 720 }, // Reduced from 480 to 360
                height: { ideal: 270, max: 405 }, // Reduced from 360 to 270
                frameRate: { ideal: 10, max: 15 }, // Reduced from 12-20 to 10-15
                facingMode: 'user'
            }
        };
    }
    
    const constraints = videoConstraints;
    
    // Handle both modern and legacy APIs
    const handleStream = (stream) => {
        // Modern API uses srcObject
        if (video.srcObject !== undefined) {
            video.srcObject = stream;
        } else if (video.mozSrcObject !== undefined) {
            // Firefox legacy
            video.mozSrcObject = stream;
        } else if (video.src !== undefined) {
            // Very old browsers
            video.src = window.URL.createObjectURL(stream);
        }
        
        isCameraActive = true;
        // Mirror hanya video supaya tombol dan teks tidak terbalik
        if (video) video.classList.add('mirror-video');
        video.addEventListener('loadedmetadata', () => {
            video.play().catch(err => {
                console.warn('Video play error:', err);
            });
        });
    };
    
    const handleError = (err) => {
        console.error('Error camera', err);
        let errorMsg = 'Tidak dapat mengakses kamera.';
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            errorMsg = 'Izin kamera ditolak. Silakan aktifkan izin kamera di pengaturan browser.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            errorMsg = 'Kamera tidak ditemukan. Pastikan kamera terhubung.';
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            errorMsg = 'Kamera sedang digunakan oleh aplikasi lain.';
        }
        statusMessage('Error: ' + errorMsg, 'bg-red-100 text-red-700');
    };
    
    // Try modern API first with browser-specific handling
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia(constraints)
            .then(handleStream)
            .catch(err => {
                // Browser-specific error handling
                const ua = navigator.userAgent.toLowerCase();
                const isSafari = /safari/.test(ua) && !/chrome/.test(ua) && !/chromium/.test(ua);
                const isFirefox = /firefox/.test(ua);
                const isMIBrowser = /miui/.test(ua) || /xiaomi/.test(ua);
                
                // Safari may need different constraints
                if (isSafari && err.name === 'OverconstrainedError') {
                    // Try with simpler constraints for Safari
                    const simpleConstraints = { video: true };
                    navigator.mediaDevices.getUserMedia(simpleConstraints)
                        .then(handleStream)
                        .catch(handleError);
                } else if (isFirefox && err.name === 'NotReadableError') {
                    // Firefox may need explicit permission
                    handleError(new Error('Kamera sedang digunakan oleh aplikasi lain atau tidak dapat diakses.'));
                } else if (isMIBrowser && err.name === 'NotAllowedError') {
                    // MI Browser may need explicit permission request
                    handleError(new Error('Izin kamera diperlukan. Silakan aktifkan di pengaturan browser.'));
                } else {
                    handleError(err);
                }
            });
    } else {
        // Fallback to legacy API
        getUserMedia.call(navigator, constraints, handleStream, handleError);
    }
}

function stopVideo(){
    if(video && video.srcObject){ video.srcObject.getTracks().forEach(t=>t.stop()); video.srcObject=null; }
    isCameraActive=false; if(videoInterval) clearInterval(videoInterval); 
    
    // Clear speech queue and cancel any ongoing speech
    speechSynthesis.cancel();
    speechQueue = [];
    isSpeaking = false;
    
    if(canvas){ const ctx = canvas.getContext('2d'); ctx.clearRect(0,0,canvas.width,canvas.height); }
}

function startVideoInterval(){
    if(!isCameraActive || videoInterval || !video || isDetectionStopped) return;
    if (!faceapi.nets.tinyFaceDetector.isLoaded) {
        console.error('Face detection models not loaded');
        statusMessage('Model AI belum dimuat. Silakan refresh halaman.', 'bg-red-100 text-red-700');
        return;
    }
    const displaySize = { width: video.clientWidth, height: video.clientHeight };
    faceapi.matchDimensions(canvas, displaySize);
    
    // Ensure canvas size matches video display size exactly
    if (canvas.width !== displaySize.width || canvas.height !== displaySize.height) {
        canvas.width = displaySize.width;
        canvas.height = displaySize.height;
    }
    // Advanced: Optimized interval for maximum performance and accuracy
    let lastDetectionTime = 0;
    let detectionThrottle = detectionConfig.detectionThrottle; // Use config value
    
    // Fallback for detectDevicePerformance if attendance.js is not loaded
    const getPerfLevel = () => {
        if (typeof detectDevicePerformance === 'function') return detectDevicePerformance();
        const cores = navigator.hardwareConcurrency || 4;
        const memory = navigator.deviceMemory || 4;
        if (cores <= 4 && memory <= 4) return 'low';
        if (cores <= 8 && memory <= 8) return 'medium';
        return 'high';
    };

    const perfLevel = getPerfLevel();
    let optimizedInputSize = detectionConfig.inputSize;
    let optimizedThrottle = detectionThrottle;
    
    // Adjust based on device performance - MORE AGGRESSIVE for low-end devices
    if (perfLevel === 'low') {
        // Low-end devices: reduce resolution and increase throttle significantly
        optimizedInputSize = Math.min(224, detectionConfig.inputSize); // Reduced from 320 to 224
        optimizedThrottle = Math.max(10, detectionThrottle * 3); // Increased from 2x to 3x, minimum 10ms
        console.log('Low-end device detected - using aggressive optimized settings (inputSize: ' + optimizedInputSize + ', throttle: ' + optimizedThrottle + 'ms)');
    } else if (perfLevel === 'medium') {
        // Medium devices: moderate settings
        optimizedInputSize = Math.min(320, detectionConfig.inputSize); // Reduced from 416 to 320
        optimizedThrottle = Math.max(5, detectionThrottle * 2); // Increased from 1.5x to 2x
    } else {
        // High-end devices: use full settings
        optimizedInputSize = detectionConfig.inputSize;
        optimizedThrottle = detectionThrottle;
    }
    
    videoInterval = setInterval(async ()=>{
        // Check if detection is stopped manually
        if (isDetectionStopped || !isCameraActive || isPresensiSuccess) {
            return;
        }
        
        const now = Date.now();
        if (now - lastDetectionTime < optimizedThrottle) {
            return; // Skip detection jika terlalu cepat
        }
        
        // Continue detection for multi-person support
        // Only stop if explicitly requested
        lastDetectionTime = now;
        
        try {
            // Optimasi: Performance monitoring
            const detectionStartTime = performance.now();
            
            // ENHANCED: Optimized detection with adaptive resolution based on device performance
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
                inputSize: optimizedInputSize, // Use optimized size based on device performance
                scoreThreshold: detectionConfig.scoreThreshold
            })).withFaceLandmarks().withFaceDescriptors();
            
            // Get current display size in every frame to ensure accuracy
            const currentDisplaySize = { width: video.clientWidth, height: video.clientHeight };
            
            // Ensure canvas dimensions match display size
            if (canvas.width !== currentDisplaySize.width || canvas.height !== currentDisplaySize.height) {
                canvas.width = currentDisplaySize.width;
                canvas.height = currentDisplaySize.height;
                faceapi.matchDimensions(canvas, currentDisplaySize);
            }
            
            // BALANCED: Smart filtering for accuracy + speed (using adjusted threshold for mobile)
            const adjustedQualityThreshold = getAdjustedQualityThreshold();
            const qualityDetections = detections.filter(detection => {
                const quality = assessFaceQuality(detection);
                const box = detection.detection.box;
                const area = box.width * box.height;
                // More lenient filtering for mobile devices - allows detection but maintains quality
                return quality >= adjustedQualityThreshold && area >= (detectionConfig.minFaceSize * detectionConfig.minFaceSize * 0.9);
            });
            
            // Sort by quality and take best detections
            qualityDetections.sort((a, b) => assessFaceQuality(b) - assessFaceQuality(a));
            const bestDetections = qualityDetections.slice(0, detectionConfig.maxFaces);
            
            // Optimasi: Update performance stats
            const detectionTime = performance.now() - detectionStartTime;
            performanceStats.detectionCount++;
            performanceStats.totalDetectionTime += detectionTime;
            performanceStats.averageDetectionTime = performanceStats.totalDetectionTime / performanceStats.detectionCount;
            performanceStats.lastDetectionTime = detectionTime;
            
            // ULTRA-FAST: Skip performance logging for maximum speed
            if (performanceStats.detectionCount % 50 === 0) {
                // Dynamic throttle adjustment based on average performance
                if (perfLevel === 'low') {
                    // Low-end devices: VERY aggressive throttling
                    if (performanceStats.averageDetectionTime > 200) {
                        optimizedThrottle = Math.min(50, optimizedThrottle + 5); // Increased max from 30 to 50
                    } else if (performanceStats.averageDetectionTime > 150) {
                        optimizedThrottle = Math.min(40, optimizedThrottle + 3);
                    } else if (performanceStats.averageDetectionTime < 100 && optimizedThrottle > 10) {
                        optimizedThrottle = Math.max(10, optimizedThrottle - 1); // Increased min from 5 to 10
                    }
                } else if (perfLevel === 'medium') {
                    // Medium devices: moderate throttling
                    if (performanceStats.averageDetectionTime > 100) {
                        optimizedThrottle = Math.min(25, optimizedThrottle + 2); // Increased max from 20 to 25
                    } else if (performanceStats.averageDetectionTime < 50 && optimizedThrottle > 5) {
                        optimizedThrottle = Math.max(5, optimizedThrottle - 1); // Increased min from 3 to 5
                    }
                } else {
                    // High-end devices: minimal throttling
                    if (performanceStats.averageDetectionTime > 100) {
                        optimizedThrottle = Math.min(15, optimizedThrottle + 2);
                    } else if (performanceStats.averageDetectionTime < 50 && optimizedThrottle > 1) {
                        optimizedThrottle = Math.max(1, optimizedThrottle - 1);
                    }
                }
                detectionThrottle = optimizedThrottle; // Update for next cycle
            }
            const resized = faceapi.resizeResults(bestDetections, currentDisplaySize);
            // Optimize canvas operations for better performance
            const ctx = canvas.getContext('2d', { 
                willReadFrequently: false, // Better performance
                alpha: true 
            });
            
            // Use requestAnimationFrame for smoother rendering on low-end devices
            if (perfLevel === 'low') {
                requestAnimationFrame(() => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                });
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
            
            if (resized.length > 0) {
                if (labeledFaceDescriptors && labeledFaceDescriptors.length > 0) {
                    // Enhanced: Get best match with threshold
                    const adjustedThreshold = (typeof getAdjustedFaceMatcherThreshold === 'function') 
                        ? getAdjustedFaceMatcherThreshold() 
                        : (detectionConfig.faceMatcherThreshold || 0.45);
                    const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, adjustedThreshold);
                    
                    // Get results with both best and second best matches
                    const results = resized.map(d => {
                        // Validate descriptor exists and is valid
                        if (!d.descriptor || !d.descriptor.length || d.descriptor.length === 0) {
                            return {
                                label: 'unknown',
                                distance: Infinity,
                                secondBest: null,
                                confidenceGap: Infinity
                            };
                        }
                        
                        const bestMatch = faceMatcher.findBestMatch(d.descriptor);
                        
                        // Validate bestMatch structure
                        if (!bestMatch || typeof bestMatch !== 'object') {
                            return {
                                label: 'unknown',
                                distance: Infinity,
                                secondBest: null,
                                confidenceGap: Infinity
                            };
                        }
                        
                        // Ensure bestMatch has required properties
                        const validBestMatch = {
                            label: bestMatch.label || 'unknown',
                            distance: (typeof bestMatch.distance === 'number' && isFinite(bestMatch.distance)) ? bestMatch.distance : Infinity
                        };
                        
                        // Calculate second best match for confidence gap validation
                        let secondBestMatch = null;
                        let secondBestDistance = Infinity;
                        
                        // Find second best match (different person)
                        // labeledFaceDescriptors contains LabeledFaceDescriptors objects with:
                        // - label: string
                        // - descriptors: array of Float32Array (can have multiple descriptors per person)
                        for (const labeledDescriptor of labeledFaceDescriptors) {
                            if (labeledDescriptor && 
                                labeledDescriptor.label && 
                                labeledDescriptor.label !== validBestMatch.label && 
                                labeledDescriptor.descriptors && 
                                Array.isArray(labeledDescriptor.descriptors) && 
                                labeledDescriptor.descriptors.length > 0) {
                                
                                // Calculate distance to all descriptors for this person and take the best (smallest) one
                                for (const descriptor of labeledDescriptor.descriptors) {
                                    if (descriptor && 
                                        (descriptor instanceof Float32Array || Array.isArray(descriptor)) && 
                                        descriptor.length > 0 && 
                                        descriptor.length === d.descriptor.length) {
                                        try {
                                            const distance = faceapi.euclideanDistance(d.descriptor, descriptor);
                                            if (!isNaN(distance) && isFinite(distance) && distance < secondBestDistance) {
                                                secondBestDistance = distance;
                                                secondBestMatch = {
                                                    label: labeledDescriptor.label,
                                                    distance: distance
                                                };
                                            }
                                        } catch (err) {
                                            // Skip if descriptor is invalid or calculation fails
                                            console.warn('Error calculating distance for', labeledDescriptor.label, err);
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Calculate confidence gap safely
                        let confidenceGap = Infinity;
                        if (secondBestMatch && 
                            typeof secondBestMatch.distance === 'number' && 
                            isFinite(secondBestMatch.distance) &&
                            typeof validBestMatch.distance === 'number' && 
                            isFinite(validBestMatch.distance)) {
                            confidenceGap = secondBestMatch.distance - validBestMatch.distance;
                        }
                        
                        // Return validated result
                        return {
                            label: validBestMatch.label,
                            distance: validBestMatch.distance,
                            secondBest: secondBestMatch,
                            confidenceGap: confidenceGap
                        };
                    });
                    
                    // Reuse existing context for better performance
                    const ctx2 = ctx; // Use same context instead of creating new one
                    // Optimize canvas clearing for low-end devices
                    if (perfLevel === 'low') {
                        requestAnimationFrame(() => {
                            ctx2.clearRect(0, 0, canvas.width, canvas.height);
                        });
                    } else {
                        ctx2.clearRect(0, 0, canvas.width, canvas.height);
                    }
                    results.forEach((result, i) => {
                        const box = resized[i].detection.box;
                        const face = resized[i];
                        
                        // Karena video di-mirror dengan CSS scaleX(-1), tapi canvas tidak di-mirror,
                        // kita perlu membalik koordinat X agar kotak sesuai dengan posisi wajah di video yang terlihat
                        // Rumus: mirroredX = canvas.width - box.x - box.width
                        const mirroredX = canvas.width - box.x - box.width;
                        
                        // Gambar kotak dengan ukuran yang sesuai
                        ctx2.strokeStyle = '#22c55e';
                        ctx2.lineWidth = 2;
                        ctx2.strokeRect(mirroredX, box.y, box.width, box.height);
                        
                        // Label hasil (tidak terbalik)
                        // Safely get label from result
                        const resultLabel = (result && result.label) ? result.label : 'unknown';
                        const resultDistance = (result && typeof result.distance === 'number' && isFinite(result.distance)) 
                            ? result.distance.toFixed(2) 
                            : '?';
                        const shouldAccept = shouldAcceptDetection(result, face);
                        const label = `${resultLabel} (${resultDistance}) ${shouldAccept ? '✓' : '?'}`;
                        ctx2.font = '14px Inter, sans-serif';
                        ctx2.fillStyle = 'rgba(37, 99, 235, 0.9)';
                        const padding = 4;
                        const textWidth = ctx2.measureText(label).width;
                        ctx2.fillRect(mirroredX, Math.max(0, box.y - 20), textWidth + padding*2, 20);
                        ctx2.fillStyle = '#fff';
                        ctx2.fillText(label, mirroredX + padding, Math.max(12, box.y - 6));
                        
                        // Proses pengenalan
                        if (shouldAccept) {
                            // Recognition handled instantly in shouldAcceptDetection -> handleRecognition
                        }
                    });
                } else {
                    statusMessage('Database wajah kosong. Silakan tambah member.', 'bg-gray-200 text-gray-600');
                    console.warn('⚠️ No face descriptors available for recognition');
                }
            } else {
                if (presensiStatus && presensiStatus.textContent !== 'Arahkan wajah ke kamera') {
                    presensiStatus.textContent = 'Arahkan wajah ke kamera';
                    presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-blue-100 text-blue-700';
                    presensiStatus.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('Face detection error:', error);
            if (presensiStatus && presensiStatus.textContent !== 'Error deteksi wajah') {
                statusMessage('Error deteksi wajah. Coba refresh halaman.', 'bg-red-100 text-red-700');
            }
        }
    }, 10); // ULTRA-FAST interval for <2 second processing
}

if (video) {
    video.addEventListener('play', ()=>{
        if (!videoPlayListenerAdded) {
            startVideoInterval();
            videoPlayListenerAdded = true;
        }
    });
}

function getTopExpression(expressions){
    const map = { happy:'Senang', sad:'Sedih', neutral:'Biasa', angry:'Marah', disgusted:'Capek', surprised:'Ngantuk', fearful:'Laper' };
    let top='neutral', max=0; for(const [k,v] of Object.entries(expressions||{})){ if(v>max){ max=v; top=k; } }
    return map[top] || 'Biasa';
}

// Advanced: Enhanced face quality assessment with detailed analysis
function assessFaceQuality(face) {
    if (!face || !face.detection) return 0;
    
    const box = face.detection.box;
    const area = box.width * box.height;
    const aspectRatio = box.width / box.height;
    const isMobile = isMobileDevice();
    
    // Quality factors with detailed analysis
    let quality = 1.0;
    
    // 1. Size factor (prefer larger faces for better detail) - more lenient for mobile
    if (area < 15000) quality *= isMobile ? 0.4 : 0.3; // More lenient for mobile
    else if (area < 20000) quality *= isMobile ? 0.6 : 0.5; // More lenient for mobile
    else if (area < 30000) quality *= isMobile ? 0.85 : 0.75; // More lenient for mobile
    else if (area > 100000) quality *= 1.4; // Large and detailed - bonus
    else if (area > 60000) quality *= 1.2; // Good size - bonus
    
    // 2. Aspect ratio factor (prefer natural face proportions) - more lenient for mobile
    if (aspectRatio < 0.6 || aspectRatio > 1.6) quality *= isMobile ? 0.6 : 0.5; // More lenient for mobile
    else if (aspectRatio < 0.7 || aspectRatio > 1.4) quality *= isMobile ? 0.9 : 0.8; // More lenient for mobile
    else if (aspectRatio >= 0.8 && aspectRatio <= 1.2) quality *= 1.2; // Good proportions
    
    // 3. Position factor (prefer centered faces) - more lenient for mobile
    const centerX = box.x + box.width / 2;
    const centerY = box.y + box.height / 2;
    const canvasCenterX = 320; // Assuming 640px width
    const canvasCenterY = 240; // Assuming 480px height
    const distanceFromCenter = Math.sqrt(
        Math.pow(centerX - canvasCenterX, 2) + Math.pow(centerY - canvasCenterY, 2)
    );
    if (distanceFromCenter > 150) quality *= isMobile ? 0.5 : 0.4; // More lenient for mobile
    else if (distanceFromCenter > 100) quality *= isMobile ? 0.8 : 0.7; // More lenient for mobile
    else if (distanceFromCenter < 40) quality *= 1.3; // Well centered - bonus
    
    // 4. Enhanced landmark quality factor (if available) - more lenient for mobile
    if (face.landmarks) {
        const landmarkScore = assessEnhancedLandmarkQuality(face.landmarks);
        // For mobile, don't penalize landmark quality as much
        quality *= isMobile ? (0.75 + landmarkScore * 0.25) : (0.7 + landmarkScore * 0.3);
    }
    
    // 5. Expression quality factor (if available) - more lenient for mobile
    if (face.expressions) {
        const expressions = face.expressions;
        const maxExpression = Math.max(...Object.values(expressions));
        if (maxExpression > 0.8) quality *= 1.1; // Clear expression
        else if (maxExpression < 0.3) quality *= isMobile ? 0.95 : 0.9; // More lenient for mobile
    }
    
    // 6. Detection confidence factor - more lenient for mobile
    if (face.detection.score) {
        if (face.detection.score > 0.95) quality *= 1.4; // Very high confidence - bonus
        else if (face.detection.score > 0.85) quality *= 1.2; // High confidence - bonus
        else if (face.detection.score > 0.8) quality *= 1.1; // Good confidence
        else if (face.detection.score < 0.5) quality *= isMobile ? 0.7 : 0.6; // More lenient for mobile
        else if (face.detection.score < 0.6 && isMobile) quality *= 0.85; // Extra lenient for mobile
    }
    
    // 7. Face angle and symmetry factor (if landmarks available) - more lenient for mobile
    if (face.landmarks && face.landmarks.positions) {
        const landmarks = face.landmarks.positions;
        
        // Check eye symmetry
        if (landmarks[36] && landmarks[45]) {
            const leftEyeX = landmarks[36].x;
            const rightEyeX = landmarks[45].x;
            const eyeSymmetry = Math.abs(leftEyeX - rightEyeX);
            if (eyeSymmetry > 20) quality *= isMobile ? 0.8 : 0.7; // More lenient for mobile
            else if (eyeSymmetry < 10) quality *= 1.2; // Good symmetry - bonus
        }
        
        // Check nose position
        if (landmarks[30] && landmarks[36] && landmarks[45]) {
            const noseX = landmarks[30].x;
            const faceCenterX = (landmarks[36].x + landmarks[45].x) / 2;
            const noseOffset = Math.abs(noseX - faceCenterX);
            if (noseOffset > 15) quality *= isMobile ? 0.9 : 0.8; // More lenient for mobile
            else if (noseOffset < 5) quality *= 1.1; // Well centered nose - bonus
        }
    }
    
    return Math.max(0, Math.min(1.5, quality)); // Allow quality > 1 for excellent faces
}

// ENHANCED: Detailed facial feature assessment for better accuracy
function assessEnhancedLandmarkQuality(landmarks) {
    if (!landmarks || !landmarks.positions || landmarks.positions.length < 68) return 0;
    
    const positions = landmarks.positions;
    let featureScore = 0;
    
    // 1. Eye region analysis (points 36-47 for left eye, 42-47 for right eye)
    const leftEyePoints = positions.slice(36, 42);
    const rightEyePoints = positions.slice(42, 48);
    const eyeScore = assessEyeQuality(leftEyePoints, rightEyePoints);
    featureScore += eyeScore * 0.3; // 30% weight for eyes
    
    // 2. Nose analysis (points 27-35)
    const nosePoints = positions.slice(27, 36);
    const noseScore = assessNoseQuality(nosePoints);
    featureScore += noseScore * 0.25; // 25% weight for nose
    
    // 3. Eyebrow analysis (points 17-26)
    const leftEyebrow = positions.slice(17, 22);
    const rightEyebrow = positions.slice(22, 27);
    const eyebrowScore = assessEyebrowQuality(leftEyebrow, rightEyebrow);
    featureScore += eyebrowScore * 0.2; // 20% weight for eyebrows
    
    // 4. Mouth analysis (points 48-67)
    const mouthPoints = positions.slice(48, 68);
    const mouthScore = assessMouthQuality(mouthPoints);
    featureScore += mouthScore * 0.15; // 15% weight for mouth
    
    // 5. Face contour analysis (points 0-16)
    const contourPoints = positions.slice(0, 17);
    const contourScore = assessContourQuality(contourPoints);
    featureScore += contourScore * 0.1; // 10% weight for face shape
    
    return Math.min(1, featureScore);
}

function assessEyeQuality(leftEye, rightEye) {
    if (!leftEye || !rightEye || leftEye.length !== 6 || rightEye.length !== 6) return 0;
    
    let score = 1.0;
    
    // Check eye symmetry
    const leftEyeCenter = getCenterPoint(leftEye);
    const rightEyeCenter = getCenterPoint(rightEye);
    const eyeDistance = Math.abs(leftEyeCenter.x - rightEyeCenter.x);
    const eyeHeightDiff = Math.abs(leftEyeCenter.y - rightEyeCenter.y);
    
    // Good symmetry bonus
    if (eyeHeightDiff < eyeDistance * 0.05) score *= 1.2;
    else if (eyeHeightDiff > eyeDistance * 0.15) score *= 0.8;
    
    // Check eye shape consistency
    const leftEyeShape = getEyeShape(leftEye);
    const rightEyeShape = getEyeShape(rightEye);
    const shapeConsistency = 1 - Math.abs(leftEyeShape - rightEyeShape);
    score *= (0.5 + shapeConsistency * 0.5);
    
    return Math.min(1, score);
}

function assessNoseQuality(nosePoints) {
    if (!nosePoints || nosePoints.length !== 9) return 0;
    
    let score = 1.0;
    
    // Check nose alignment (should be roughly vertical)
    const noseTop = nosePoints[0];
    const noseBottom = nosePoints[6];
    const noseSlope = Math.abs((noseBottom.x - noseTop.x) / (noseBottom.y - noseTop.y));
    
    if (noseSlope < 0.1) score *= 1.2; // Very straight
    else if (noseSlope > 0.3) score *= 0.8; // Too tilted
    
    // Check nose width consistency
    const noseWidth = Math.abs(nosePoints[4].x - nosePoints[8].x);
    const noseHeight = Math.abs(noseBottom.y - noseTop.y);
    const noseRatio = noseWidth / noseHeight;
    
    if (noseRatio > 0.3 && noseRatio < 0.6) score *= 1.1; // Good proportions
    else if (noseRatio > 0.8 || noseRatio < 0.2) score *= 0.9; // Unusual proportions
    
    return Math.min(1, score);
}

function assessEyebrowQuality(leftEyebrow, rightEyebrow) {
    if (!leftEyebrow || !rightEyebrow || leftEyebrow.length !== 5 || rightEyebrow.length !== 5) return 0;
    
    let score = 1.0;
    
    // Check eyebrow symmetry
    const leftEyebrowCenter = getCenterPoint(leftEyebrow);
    const rightEyebrowCenter = getCenterPoint(rightEyebrow);
    const eyebrowHeightDiff = Math.abs(leftEyebrowCenter.y - rightEyebrowCenter.y);
    const eyebrowDistance = Math.abs(leftEyebrowCenter.x - rightEyebrowCenter.x);
    
    if (eyebrowHeightDiff < eyebrowDistance * 0.05) score *= 1.1;
    else if (eyebrowHeightDiff > eyebrowDistance * 0.15) score *= 0.9;
    
    // Check eyebrow shape consistency
    const leftShape = getEyebrowShape(leftEyebrow);
    const rightShape = getEyebrowShape(rightEyebrow);
    const shapeConsistency = 1 - Math.abs(leftShape - rightShape);
    score *= (0.7 + shapeConsistency * 0.3);
    
    return Math.min(1, score);
}

function assessMouthQuality(mouthPoints) {
    if (!mouthPoints || mouthPoints.length !== 20) return 0;
    
    let score = 1.0;
    
    // Check mouth symmetry
    const leftMouth = mouthPoints[0];
    const rightMouth = mouthPoints[6];
    const mouthCenter = mouthPoints[9];
    
    const leftDistance = Math.abs(leftMouth.x - mouthCenter.x);
    const rightDistance = Math.abs(rightMouth.x - mouthCenter.x);
    const symmetry = 1 - Math.abs(leftDistance - rightDistance) / Math.max(leftDistance, rightDistance);
    
    score *= (0.8 + symmetry * 0.2);
    
    return Math.min(1, score);
}

function assessContourQuality(contourPoints) {
    if (!contourPoints || contourPoints.length !== 17) return 0;
    
    let score = 1.0;
    
    // Check face shape consistency
    const chin = contourPoints[8];
    const leftJaw = contourPoints[4];
    const rightJaw = contourPoints[12];
    
    const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
    const faceHeight = Math.abs(chin.y - contourPoints[0].y);
    const faceRatio = jawWidth / faceHeight;
    
    if (faceRatio > 0.6 && faceRatio < 0.9) score *= 1.1; // Good face proportions
    else if (faceRatio > 1.2 || faceRatio < 0.4) score *= 0.9; // Unusual proportions
    
    return Math.min(1, score);
}

// Helper functions
function getCenterPoint(points) {
    const x = points.reduce((sum, p) => sum + p.x, 0) / points.length;
    const y = points.reduce((sum, p) => sum + p.y, 0) / points.length;
    return { x, y };
}

function getEyeShape(eyePoints) {
    const width = Math.abs(eyePoints[3].x - eyePoints[0].x);
    const height = Math.abs(eyePoints[1].y - eyePoints[4].y);
    return width / height;
}

function getEyebrowShape(eyebrowPoints) {
    const start = eyebrowPoints[0];
    const end = eyebrowPoints[4];
    const middle = eyebrowPoints[2];
    const arch = Math.abs(middle.y - (start.y + end.y) / 2);
    const length = Math.abs(end.x - start.x);
    return arch / length;
}

// Advanced: Multiple detection attempts for better accuracy
let detectionAttempts = 0;
// Multi-person detection queue system
let detectionHistory = [];
let recognitionQueue = [];
let isProcessingQueue = false;
let lastSuccessfulDetection = null;

function shouldAcceptDetection(result, face) {
    // Comprehensive validation of result object
    if (!result || typeof result !== 'object') {
        console.warn('Invalid result: result is not an object', result);
        return false;
    }
    
    if (!result.label || result.label === 'unknown') {
        return false;
    }
    
    // Safe toFixed helper to prevent errors
    const safeToFixed = (value, decimals = 3) => {
        if (typeof value !== 'number' || isNaN(value) || !isFinite(value)) return 'N/A';
        return value.toFixed(decimals);
    };
    
    // Validate result.distance exists and is a valid number
    if (typeof result.distance !== 'number' || isNaN(result.distance) || !isFinite(result.distance)) {
        console.warn('Invalid result.distance:', result.distance, 'for label:', result.label);
        return false;
    }
    
    // Skip if this label recently processed
    const lastTs = processedLabels.get(result.label) || 0;
    if (Date.now() - lastTs < processedCooldownMs) return false;
    
    const isMobile = isMobileDevice();
    
    // ENHANCED: Adaptive threshold based on confidence gap and face quality
    const baseThreshold = getAdjustedRecognitionThreshold();
    const quality = assessFaceQuality(face);
    
    // Validate quality is a valid number
    if (typeof quality !== 'number' || isNaN(quality) || !isFinite(quality)) {
        console.warn('Invalid quality:', quality);
        return false;
    }
    
    // Calculate adaptive threshold based on confidence gap
    // If confidence gap is large (best match is much better than second best), we can be more lenient
    // If confidence gap is small (best and second best are close), we need to be stricter
    const confidenceGap = (typeof result.confidenceGap === 'number' && isFinite(result.confidenceGap)) ? result.confidenceGap : 0;
    let adaptiveThreshold = baseThreshold;
    
    if (confidenceGap > 0.15) {
        // Large gap: best match is clearly better - can be more lenient (up to 0.05 more lenient)
        adaptiveThreshold = Math.min(baseThreshold + 0.05, 0.60);
    } else if (confidenceGap > 0.08) {
        // Medium gap: slightly more lenient
        adaptiveThreshold = Math.min(baseThreshold + 0.02, 0.55);
    } else if (confidenceGap > 0.03) {
        // Small gap: use base threshold
        adaptiveThreshold = baseThreshold;
    } else {
        // Very small gap (< 0.03): be stricter to prevent false positive
        adaptiveThreshold = Math.max(baseThreshold - 0.05, 0.30);
    }
    
    // Adjust threshold based on face quality
    // Higher quality = can be slightly more lenient, lower quality = need to be stricter
    if (quality > 0.7) {
        adaptiveThreshold = Math.min(adaptiveThreshold + 0.02, 0.60);
    } else if (quality < 0.4) {
        adaptiveThreshold = Math.max(adaptiveThreshold - 0.03, 0.30);
    }
    
    // CRITICAL: Confidence gap validation to prevent false positive
    // If second best match is too close to best match, reject to prevent misidentification
    if (result.secondBest && confidenceGap < 0.05) {
        // Confidence gap too small - best and second best are very close
        // This is a red flag for potential false positive
        const secondBestDistance = safeToFixed(result.secondBest?.distance);
        const secondBestLabel = result.secondBest?.label || 'unknown';
        console.log(`🚫 Confidence gap too small (${safeToFixed(confidenceGap)} < 0.05) - best: ${result.label} (${safeToFixed(result.distance)}), second: ${secondBestLabel} (${secondBestDistance})`);
        return false;
    }
    
    // Check distance against adaptive threshold
    if (result.distance > adaptiveThreshold) {
        console.log(`🚫 Distance ${safeToFixed(result.distance)} exceeds adaptive threshold ${safeToFixed(adaptiveThreshold)} (base: ${safeToFixed(baseThreshold)}, gap: ${safeToFixed(confidenceGap)}, quality: ${safeToFixed(quality)}, device: ${isMobile ? 'mobile' : 'desktop'})`);
        return false;
    }
    
    // SPECIAL CASE: For excellent distance (< 0.35), be very lenient with other checks
    // This is because excellent distance means very high confidence in face match
    const isExcellentDistance = result.distance < 0.35;
    const isVeryGoodDistance = result.distance < 0.45;
    
    // Enhanced quality check with facial feature analysis
    // Quality already calculated above, reuse it
    const adjustedQualityThreshold = getAdjustedQualityThreshold();
    
    // For mobile, use distance-based quality thresholds to maintain accuracy
    // Also consider confidence gap - larger gap means we can be more lenient
    let effectiveQualityThreshold = adjustedQualityThreshold;
    if (isMobile) {
        if (isExcellentDistance && confidenceGap > 0.10) {
            // Excellent distance + large gap = very high confidence, allow very low quality
            effectiveQualityThreshold = 0.10;
        } else if (isExcellentDistance) {
            // Excellent distance but smaller gap - still allow low quality
            effectiveQualityThreshold = 0.15;
        } else if (isVeryGoodDistance && confidenceGap > 0.08) {
            // Very good distance + medium gap = high confidence, allow low quality
            effectiveQualityThreshold = 0.20;
        } else if (isVeryGoodDistance) {
            // Very good distance but smaller gap
            effectiveQualityThreshold = 0.25;
        } else if (result.distance < 0.50 && confidenceGap > 0.08) {
            // Good distance + medium gap = moderate confidence, allow moderate quality
            effectiveQualityThreshold = 0.30;
        } else if (result.distance < 0.50) {
            effectiveQualityThreshold = 0.35;
        }
    } else {
        // Desktop: stricter but still consider confidence gap
        if (isExcellentDistance && confidenceGap > 0.10) {
            effectiveQualityThreshold = 0.20;
        } else if (isExcellentDistance) {
            effectiveQualityThreshold = 0.30;
        } else if (isVeryGoodDistance && confidenceGap > 0.08) {
            effectiveQualityThreshold = 0.35;
        }
    }
    
    if (quality < effectiveQualityThreshold) {
        // For excellent distance with large gap, allow much lower quality threshold
        if (isExcellentDistance && confidenceGap > 0.10 && quality > 0.08) {
            console.log(`⚠️ Quality ${safeToFixed(quality)} below standard threshold ${safeToFixed(adjustedQualityThreshold)}, but allowing due to excellent distance < 0.35 and large gap ${safeToFixed(confidenceGap)} (effective threshold: ${safeToFixed(effectiveQualityThreshold)})`);
        } else if (isExcellentDistance && quality > 0.12) {
            console.log(`⚠️ Quality ${safeToFixed(quality)} below standard threshold ${safeToFixed(adjustedQualityThreshold)}, but allowing due to excellent distance < 0.35 (effective threshold: ${safeToFixed(effectiveQualityThreshold)})`);
        } else if (isVeryGoodDistance && confidenceGap > 0.08 && quality > 0.15) {
            console.log(`⚠️ Quality ${safeToFixed(quality)} below standard threshold ${safeToFixed(adjustedQualityThreshold)}, but allowing due to very good distance < 0.45 and medium gap ${safeToFixed(confidenceGap)} (effective threshold: ${safeToFixed(effectiveQualityThreshold)})`);
        } else if (isMobile && result.distance < 0.50 && confidenceGap > 0.08 && quality > 0.25) {
            console.log(`⚠️ Quality ${safeToFixed(quality)} below standard threshold ${safeToFixed(adjustedQualityThreshold)}, but allowing due to good distance < 0.50 and medium gap (mobile, effective threshold: ${safeToFixed(effectiveQualityThreshold)})`);
        } else {
            console.log(`🚫 Quality ${safeToFixed(quality)} below threshold ${safeToFixed(effectiveQualityThreshold)} (device: ${isMobile ? 'mobile' : 'desktop'}, distance: ${safeToFixed(result.distance)}, gap: ${safeToFixed(confidenceGap)})`);
            return false;
        }
    }
    
    // ENHANCED: Facial feature consistency check with confidence gap consideration
    // More lenient for mobile - skip if landmarks not available
    if (face.landmarks) {
        const landmarkScore = assessEnhancedLandmarkQuality(face.landmarks);
        const adjustedLandmarkThreshold = getAdjustedLandmarkThreshold();
        
        // Adjust landmark threshold based on distance AND confidence gap
        let effectiveLandmarkThreshold = adjustedLandmarkThreshold;
        if (isMobile) {
            if (isExcellentDistance && confidenceGap > 0.10) {
                effectiveLandmarkThreshold = 0.25; // Very low for excellent distance + large gap
            } else if (isExcellentDistance) {
                effectiveLandmarkThreshold = 0.30; // Low for excellent distance
            } else if (isVeryGoodDistance && confidenceGap > 0.08) {
                effectiveLandmarkThreshold = 0.35; // Low for very good distance + medium gap
            } else if (isVeryGoodDistance) {
                effectiveLandmarkThreshold = 0.40; // Moderate for very good distance
            } else if (result.distance < 0.50 && confidenceGap > 0.08) {
                effectiveLandmarkThreshold = 0.40; // Moderate for good distance + medium gap
            }
        } else {
            // Desktop: stricter but still consider confidence gap
            if (isExcellentDistance && confidenceGap > 0.10) {
                effectiveLandmarkThreshold = 0.35;
            } else if (isExcellentDistance) {
                effectiveLandmarkThreshold = 0.40;
            } else if (isVeryGoodDistance && confidenceGap > 0.08) {
                effectiveLandmarkThreshold = 0.45;
            }
        }
        
        if (landmarkScore < effectiveLandmarkThreshold) {
            // For excellent distance with large gap, allow much lower landmark score
            if (isExcellentDistance && confidenceGap > 0.10 && landmarkScore > 0.20) {
                console.log(`⚠️ Landmark score ${safeToFixed(landmarkScore)} below standard threshold ${safeToFixed(adjustedLandmarkThreshold)}, but allowing due to excellent distance < 0.35 and large gap ${safeToFixed(confidenceGap)} (effective threshold: ${safeToFixed(effectiveLandmarkThreshold)})`);
            } else if (isExcellentDistance && landmarkScore > 0.25) {
                console.log(`⚠️ Landmark score ${safeToFixed(landmarkScore)} below standard threshold ${safeToFixed(adjustedLandmarkThreshold)}, but allowing due to excellent distance < 0.35 (effective threshold: ${safeToFixed(effectiveLandmarkThreshold)})`);
            } else if (isVeryGoodDistance && confidenceGap > 0.08 && landmarkScore > 0.30) {
                console.log(`⚠️ Landmark score ${safeToFixed(landmarkScore)} below standard threshold ${safeToFixed(adjustedLandmarkThreshold)}, but allowing due to very good distance < 0.45 and medium gap ${safeToFixed(confidenceGap)} (effective threshold: ${safeToFixed(effectiveLandmarkThreshold)})`);
            } else if (isMobile && result.distance < 0.50 && confidenceGap > 0.08 && quality > 0.25 && landmarkScore > 0.35) {
                console.log(`⚠️ Landmark score ${safeToFixed(landmarkScore)} below standard threshold ${safeToFixed(adjustedLandmarkThreshold)}, but allowing due to good distance/quality/gap (mobile)`);
            } else {
                console.log(`🚫 Landmark score ${safeToFixed(landmarkScore)} below threshold ${safeToFixed(effectiveLandmarkThreshold)} (device: ${isMobile ? 'mobile' : 'desktop'}, distance: ${safeToFixed(result.distance)}, gap: ${safeToFixed(confidenceGap)})`);
                return false;
            }
        }
    }
    
    // NEW: Gender validation to prevent cross-gender misdetection (very lenient for excellent distance)
    // CRITICAL: Keep gender validation strict for accuracy, but allow excellent distance
    if (detectionConfig.genderValidation) {
        const genderMatch = validateGenderConsistency(result.label, face);
        if (!genderMatch) {
            // For excellent distance, be very lenient with gender validation
            if (isExcellentDistance) {
                console.log(`⚠️ Gender validation failed for ${result.label}, but allowing due to excellent distance < 0.35 (mobile)`);
            } else if (isVeryGoodDistance && quality > 0.20) {
                console.log(`⚠️ Gender validation failed for ${result.label}, but allowing due to very good distance < 0.45 (mobile)`);
            } else if (isMobile && result.distance < 0.50 && quality > 0.30) {
                console.log(`⚠️ Gender validation failed for ${result.label}, but allowing due to good distance/quality (mobile)`);
            } else {
                console.log(`🚫 Gender validation failed for ${result.label} (distance: ${safeToFixed(result.distance)}, quality: ${safeToFixed(quality)})`);
                return false;
            }
        }
    }
    
    // NEW: Multi-attempt validation for critical decisions (very lenient for excellent distance)
    // For excellent distance, skip strict validation entirely - distance is already strong indicator
    if (isExcellentDistance) {
        console.log(`✅ Excellent distance < 0.35 detected, using lenient multi-attempt validation for mobile`);
        // Still do basic validation but much more lenient
        const validationScore = performMultiAttemptValidation(result, face, isMobile);
        // Validate validationScore is a number
        if (typeof validationScore === 'number' && isFinite(validationScore)) {
            // For excellent distance, only reject if validation score is extremely low
            if (validationScore < 0.20) {
                console.log(`🚫 Multi-attempt validation score ${safeToFixed(validationScore)} extremely low (< 0.20), rejecting despite excellent distance`);
                return false;
            }
        }
    } else if (detectionConfig.multiAttemptValidation && detectionConfig.strictMode) {
        // For mobile, skip strict mode if distance and quality are good enough
        const shouldSkipStrictMode = isMobile && result.distance < 0.50 && quality > 0.20;
        
        if (shouldSkipStrictMode || detectionConfig.strictMode) {
            const validationScore = performMultiAttemptValidation(result, face, isMobile);
            // Validate validationScore is a number
            if (typeof validationScore === 'number' && isFinite(validationScore)) {
                // Much more lenient minimum score for mobile devices
                const minValidationScore = isMobile ? 0.30 : 0.5; // Lowered from 0.35 to 0.30 for mobile
                if (validationScore < minValidationScore) {
                    // For mobile, allow if distance is very good even if validation score is slightly lower
                    if (isMobile && result.distance < 0.40 && quality > 0.25) {
                        console.log(`⚠️ Multi-attempt validation score ${safeToFixed(validationScore)} below threshold ${minValidationScore}, but allowing due to excellent distance/quality (mobile)`);
                    } else if (isMobile && result.distance < 0.45 && quality > 0.20 && validationScore >= 0.25) {
                        // Additional fallback for mobile - allow if score is close to threshold
                        console.log(`⚠️ Multi-attempt validation score ${safeToFixed(validationScore)} below threshold ${minValidationScore}, but allowing due to good distance/quality (mobile, lenient mode)`);
                    } else {
                        console.log(`🚫 Multi-attempt validation failed for ${result.label} (score: ${safeToFixed(validationScore)}, min: ${minValidationScore}, device: ${isMobile ? 'mobile' : 'desktop'})`);
                        return false;
                    }
                }
            }
        }
    }
    
    // Check if this person is already being processed
    if (isProcessingRecognition) return false;
    
    // ENHANCED: Additional confidence gap validation for edge cases
    // Even if gap > 0.05, if gap is small and distance is borderline, be cautious
    if (result.secondBest && confidenceGap < 0.10 && result.distance > (adaptiveThreshold * 0.85)) {
        // Gap is small-medium and distance is close to threshold
        // Require higher quality or better distance for acceptance
        if (quality < 0.5 && result.distance > (adaptiveThreshold * 0.90)) {
            console.log(`🚫 Borderline detection rejected: distance ${safeToFixed(result.distance)} close to threshold ${safeToFixed(adaptiveThreshold)} with small gap ${safeToFixed(confidenceGap)} and low quality ${safeToFixed(quality)}`);
            return false;
        }
    }
    
    // ENHANCED: Log successful detection with confidence gap info
    const secondBestInfo = result.secondBest 
        ? `${result.secondBest.label || 'unknown'} ${safeToFixed(result.secondBest.distance)}`
        : 'N/A';
    const gapInfo = result.secondBest ? `gap: ${safeToFixed(confidenceGap)} (2nd: ${secondBestInfo})` : 'gap: N/A';
    console.log(`✅ Valid detection: ${result.label} (distance: ${safeToFixed(result.distance)}, ${gapInfo}, quality: ${safeToFixed(quality)}, adaptive threshold: ${safeToFixed(adaptiveThreshold)}, base: ${safeToFixed(baseThreshold)}, device: ${isMobile ? 'mobile' : 'desktop'}, excellent: ${isExcellentDistance ? 'YES' : 'NO'})`);
    console.log(`🎯 Processing attendance for: ${result.label}`);
    
    // INSTANT RECOGNITION: Process immediately on first valid detection
    addToRecognitionQueue(result.label, face);
    return true;
}

function addToRecognitionQueue(label, face) {
    // INSTANT PROCESSING: Always process immediately for maximum speed
    // console.log(`🚀 INSTANT PROCESSING for ${label}`);
    handleRecognition(label, 'Biasa'); // Use default expression for speed
}

// NEW: Gender validation function to prevent cross-gender misdetection
function validateGenderConsistency(label, face) {
    try {
        // Check if members array is available
        if (!members || !Array.isArray(members) || members.length === 0) {
            console.log('⚠️ Members array not available for gender validation, allowing detection');
            return true; // Allow detection if no member data
        }
        
        // Get employee data to check gender consistency
        const employee = members.find(m => m.nim === label);
        if (!employee) {
            console.log(`⚠️ Employee data not found for ${label}, allowing detection`);
            return true; // If no employee data, allow detection
        }
        
        // Simple gender detection based on facial features
        if (face.landmarks && face.landmarks.positions) {
            const landmarks = face.landmarks.positions;
            
            // Check if we have enough landmarks
            if (landmarks.length < 68) {
                console.log(`⚠️ Insufficient landmarks for gender validation (${landmarks.length}/68), allowing detection`);
                return true;
            }
            
            // Analyze jawline width (typically wider in males)
            const jawWidth = Math.abs(landmarks[16].x - landmarks[0].x);
            const faceHeight = Math.abs(landmarks[8].y - landmarks[19].y);
            const jawRatio = jawWidth / faceHeight;
            
            // Analyze eyebrow thickness and position
            const leftEyebrowThickness = Math.abs(landmarks[19].y - landmarks[20].y);
            const rightEyebrowThickness = Math.abs(landmarks[24].y - landmarks[25].y);
            const avgEyebrowThickness = (leftEyebrowThickness + rightEyebrowThickness) / 2;
            
            // More lenient heuristic: wider jaw and thicker eyebrows suggest male
            const isLikelyMale = jawRatio > 0.75 && avgEyebrowThickness > 4; // More strict criteria
            const isLikelyFemale = jawRatio < 0.6 && avgEyebrowThickness < 2; // More strict criteria
            
            // Check if employee name suggests gender (simple heuristic)
            const name = employee.nama.toLowerCase();
            const maleNames = ['budi', 'andi', 'joko', 'agus', 'doni', 'riko', 'tono', 'surya', 'rama', 'ahmad', 'muhammad', 'ali', 'umar', 'yusuf'];
            const femaleNames = ['sari', 'dewi', 'maya', 'lina', 'rina', 'siti', 'nina', 'dina', 'lisa', 'ana', 'sarah', 'fatimah', 'aisha', 'zainab'];
            
            const nameSuggestsMale = maleNames.some(maleName => name.includes(maleName));
            const nameSuggestsFemale = femaleNames.some(femaleName => name.includes(femaleName));
            
            // Only reject if we have VERY strong conflicting indicators
            if (isLikelyMale && nameSuggestsFemale && jawRatio > 0.8 && avgEyebrowThickness > 5) {
                console.log(`🚫 Strong gender mismatch: Face strongly suggests male but name suggests female for ${label}`);
                return false;
            }
            if (isLikelyFemale && nameSuggestsMale && jawRatio < 0.55 && avgEyebrowThickness < 1.5) {
                console.log(`🚫 Strong gender mismatch: Face strongly suggests female but name suggests male for ${label}`);
                return false;
            }
            
            console.log(`✅ Gender validation passed for ${label} (jawRatio: ${jawRatio.toFixed(3)}, eyebrowThickness: ${avgEyebrowThickness.toFixed(3)})`);
        }
        
        return true; // Allow detection if no clear gender mismatch
    } catch (error) {
        console.warn('Gender validation error:', error);
        return true; // Allow detection on error
    }
}

// BALANCED: Multi-attempt validation - balanced scoring for reliable detection
function performMultiAttemptValidation(result, face, isMobile = false) {
    try {
        let validationScore = 0;
        let maxPossibleScore = 0;
        
        // Score 1: Distance-based validation (40% weight)
        // Much more lenient scoring for mobile devices
        const distanceWeight = 0.4;
        maxPossibleScore += distanceWeight;
        const mobileDistanceThreshold = isMobile ? 0.55 : 0.38; // Increased from 0.50 to 0.55 for mobile
        const excellentThreshold = isMobile ? 0.40 : 0.30; // More lenient excellent threshold for mobile
        
        if (result.distance < excellentThreshold) {
            validationScore += distanceWeight * 1.0; // Excellent match
        } else if (result.distance < mobileDistanceThreshold) {
            validationScore += distanceWeight * 0.95; // Very good match (within threshold) - increased from 0.9
        } else if (result.distance < (isMobile ? 0.60 : 0.45)) {
            validationScore += distanceWeight * 0.85; // Good match - increased from 0.8 for mobile
        } else if (result.distance < (isMobile ? 0.70 : 0.55)) {
            validationScore += distanceWeight * 0.7; // Acceptable match - increased from 0.6 for mobile
        } else {
            validationScore += distanceWeight * 0.4; // Poor match - increased from 0.3 for mobile
        }
        
        // Score 2: Quality-based validation (35% weight)
        const qualityWeight = 0.35;
        const quality = assessFaceQuality(face);
        maxPossibleScore += qualityWeight;
        const adjustedQualityThreshold = getAdjustedQualityThreshold();
        if (quality > 0.75) {
            validationScore += qualityWeight * 1.0; // Excellent quality
        } else if (quality > adjustedQualityThreshold + 0.1) {
            validationScore += qualityWeight * 0.9; // Very good quality (above threshold)
        } else if (quality > adjustedQualityThreshold) {
            validationScore += qualityWeight * 0.85; // Good quality (within threshold)
        } else if (quality > adjustedQualityThreshold - 0.05) {
            validationScore += qualityWeight * 0.75; // Acceptable quality - increased for mobile
        } else if (quality > adjustedQualityThreshold - 0.1) {
            validationScore += qualityWeight * 0.6; // Marginally acceptable quality - increased for mobile
        } else if (quality > adjustedQualityThreshold - 0.15 && isMobile) {
            validationScore += qualityWeight * 0.5; // Still acceptable for mobile
        } else {
            validationScore += qualityWeight * 0.3; // Poor quality
        }
        
        // Score 3: Landmark-based validation (25% weight, optional)
        const landmarkWeight = 0.25;
        if (face.landmarks) {
            maxPossibleScore += landmarkWeight;
            const landmarkScore = assessEnhancedLandmarkQuality(face.landmarks);
            const adjustedLandmarkThreshold = getAdjustedLandmarkThreshold();
            if (landmarkScore > 0.7) {
                validationScore += landmarkWeight * 1.0; // Excellent landmarks
            } else if (landmarkScore > adjustedLandmarkThreshold + 0.1) {
                validationScore += landmarkWeight * 0.9; // Very good landmarks (above threshold)
            } else if (landmarkScore > adjustedLandmarkThreshold) {
                validationScore += landmarkWeight * 0.85; // Good landmarks (within threshold)
            } else if (landmarkScore > adjustedLandmarkThreshold - 0.05) {
                validationScore += landmarkWeight * 0.75; // Acceptable landmarks - increased for mobile
            } else if (landmarkScore > adjustedLandmarkThreshold - 0.1) {
                validationScore += landmarkWeight * 0.6; // Marginally acceptable landmarks - increased for mobile
            } else if (landmarkScore > adjustedLandmarkThreshold - 0.15 && isMobile) {
                validationScore += landmarkWeight * 0.5; // Still acceptable for mobile
            } else {
                validationScore += landmarkWeight * 0.3; // Poor landmarks
            }
        } else if (isMobile) {
            // For mobile, don't penalize too much if landmarks are missing
            maxPossibleScore += landmarkWeight;
            validationScore += landmarkWeight * 0.6; // Give partial credit for mobile
        }
        
        // Calculate normalized score (0-1 scale)
        const finalScore = maxPossibleScore > 0 ? validationScore / maxPossibleScore : 0.5;
        console.log(`Multi-attempt validation score: ${finalScore.toFixed(3)} (distance: ${result.distance.toFixed(3)}, quality: ${quality.toFixed(3)}, landmark: ${face.landmarks ? assessEnhancedLandmarkQuality(face.landmarks).toFixed(3) : 'N/A'}, device: ${isMobile ? 'mobile' : 'desktop'})`);
        return finalScore;
    } catch (error) {
        console.warn('Multi-attempt validation error:', error);
        return 0.6; // Balanced neutral score on error
    }
}

// Queue system removed for instant processing

let isProcessingRecognition = false;
// Track processed labels to prevent duplicate submissions while tetap melanjutkan deteksi
let processedLabels = new Map(); // nim -> timestamp ms
const processedCooldownMs = 30000; // 30 detik

async function handleRecognition(nim, topExpression){
    if(!scanMode || isProcessingRecognition) return;
    isProcessingRecognition = true;
    
        // Ultra-fast processing - minimal logging
        // console.log('Recognition triggered:', { nim, topExpression, scanMode });
    
    // Parallel processing with improved performance
    const [screenshot, position, membersList] = await Promise.all([
        // Screenshot attempt
        new Promise((resolve) => {
            // ... (keep existing screenshot logic)
            try {
                // Wait for video to be ready - check multiple times if needed
                const checkVideoReady = (attempts = 0) => {
                    if (attempts > 10) {
                        console.warn('Video not ready after multiple attempts');
                        resolve(null);
                        return;
                    }
                    
                    if (video && canvas && video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
                        try {
                            // Ensure video is playing and has valid frame
                            if (video.paused) {
                                video.play().catch(() => {});
                            }
                            
                            // Small delay to ensure frame is rendered
                            setTimeout(() => {
                                try {
                                    const ctx = canvas.getContext('2d');
                                    canvas.width = video.videoWidth;
                                    canvas.height = video.videoHeight;
                                    
                                    // Draw video frame to canvas - ensure video is visible
                                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                    
                                    // Check if canvas has valid image data (not black)
                                    const imageData = ctx.getImageData(0, 0, Math.min(100, canvas.width), Math.min(100, canvas.height));
                                    const pixels = imageData.data;
                                    let hasNonBlackPixels = false;
                                    for (let i = 0; i < pixels.length; i += 4) {
                                        const r = pixels[i];
                                        const g = pixels[i + 1];
                                        const b = pixels[i + 2];
                                        // Check if pixel is not black (allow some tolerance)
                                        if (r > 10 || g > 10 || b > 10) {
                                            hasNonBlackPixels = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!hasNonBlackPixels && attempts < 5) {
                                        // Canvas is black, wait a bit and retry
                                        setTimeout(() => checkVideoReady(attempts + 1), 100);
                                        return;
                                    }
                                    
                                    // Resize to speed up upload while keeping enough detail for verification
                                    const targetW = 240; const scale = targetW / canvas.width; const targetH = Math.round(canvas.height * scale);
                                    const tmp = document.createElement('canvas'); const tctx = tmp.getContext('2d');
                                    tmp.width = targetW; tmp.height = targetH;
                                    // Center-crop from the middle to avoid only-forehead issue on tall mobile cameras
                                    const srcW = video.videoWidth;
                                    const srcH = video.videoHeight;
                                    const aspect = targetW / targetH;
                                    let cropW = srcW;
                                    let cropH = Math.round(cropW / aspect);
                                    if (cropH > srcH) { cropH = srcH; cropW = Math.round(cropH * aspect); }
                                    const sx = Math.max(0, Math.floor((srcW - cropW) / 2));
                                    const sy = Math.max(0, Math.floor((srcH - cropH) / 2));
                                    tctx.drawImage(video, sx, sy, cropW, cropH, 0, 0, targetW, targetH);
                                    const screenshot = tmp.toDataURL('image/jpeg', 0.7); // Higher quality to avoid black screenshots
                                    resolve(screenshot);
                                } catch (drawError) {
                                    console.warn('Failed to draw video to canvas:', drawError);
                                    if (attempts < 5) {
                                        setTimeout(() => checkVideoReady(attempts + 1), 100);
                                    } else {
                                        resolve(null);
                                    }
                                }
                            }, 50); // Small delay to ensure frame is rendered
                        } catch (error) {
                            console.warn('Screenshot error:', error);
                            if (attempts < 5) {
                                setTimeout(() => checkVideoReady(attempts + 1), 100);
                            } else {
                                resolve(null);
                            }
                        }
                    } else {
                        // Video not ready, wait and retry
                        if (attempts < 10) {
                            setTimeout(() => checkVideoReady(attempts + 1), 100);
                        } else {
                            console.warn('Video not ready for screenshot after retries');
                            resolve(null);
                        }
                    }
                };
                
                checkVideoReady(0);
            } catch (screenshotError) {
                console.warn('Failed to take screenshot:', screenshotError);
                resolve(null);
            }
        }),
        
        // Geolocation - Accept GPS even with lower accuracy, but require permission
        new Promise((resolve) => {
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                pos => {
                    // Accept GPS position regardless of accuracy
                    resolve(pos);
                }, 
                err => {
                    console.warn('Geolocation error:', err);
                    // Check if permission was denied
                    if (navigator.permissions) {
                        navigator.permissions.query({ name: 'geolocation' }).then(result => {
                            if (result.state === 'denied') {
                                console.error('Location permission denied');
                            }
                        }).catch(() => {});
                    }
                    resolve(null);
                }, 
                { 
                    enableHighAccuracy: false, // Set to false for faster response on old devices
                    timeout: 4000, // Reduced to 4 seconds for faster response
                    maximumAge: 30000 // Allow 30 second cache for speed (reduced from 60s)
                }
            );
        })
    ]);
    
    // Validate screenshot before proceeding
    if (!screenshot || screenshot.length < 1000) {
        statusMessage('Gagal mengambil screenshot. Silakan coba lagi dengan posisi yang lebih baik.', 'bg-red-100 text-red-700');
        isProcessingRecognition = false;
        return;
    }
    
    // Use position from parallel processing with strict validation
    let lat=null, lng=null;
    if (position) {
        lat = position.coords.latitude;
        lng = position.coords.longitude;
        // Validate coordinates are valid numbers
        if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
            lat = null;
            lng = null;
            statusMessage('Koordinat GPS tidak valid. Pastikan GPS aktif dan akurat.', 'bg-red-100 text-red-700');
        }
        // GPS accuracy is accepted regardless of value (no warning shown)
    } else {
        // Check if permissions are already granted before showing error
        // Only show error if permission was denied, not if there's a timeout or other issue
        if (typeof navigator !== 'undefined' && navigator.permissions) {
            navigator.permissions.query({ name: 'geolocation' }).then(result => {
                if (result.state === 'denied') {
                    statusMessage('Izin lokasi ditolak. Silakan aktifkan izin lokasi di pengaturan browser.', 'bg-red-100 text-red-700');
                } else if (result.state === 'prompt') {
                    statusMessage('Silakan izinkan akses lokasi untuk melanjutkan presensi.', 'bg-yellow-100 text-yellow-700');
                } else {
                    // Permission granted but GPS still failed - might be timeout or GPS not available
                    statusMessage('Mendapatkan lokasi memakan waktu lama. Pastikan GPS aktif dan berada di area terbuka.', 'bg-yellow-100 text-yellow-700');
                }
                
                // Safe event listener binding
                if (result && typeof result.addEventListener === 'function') {
                    result.addEventListener('change', function() {
                        console.log('Permission state changed:', result.state);
                    });
                } else if (result) {
                    result.onchange = function() {
                        console.log('Permission state changed:', result.state);
                    };
                }
            }).catch(() => {
                // Fallback if permissions API not available
                statusMessage('Mendapatkan lokasi memakan waktu lama. Pastikan GPS aktif dan berada di area terbuka.', 'bg-yellow-100 text-yellow-700');
            });
        } else {
            // Fallback if permissions API not available
            statusMessage('Mendapatkan lokasi memakan waktu lama. Pastikan GPS aktif dan berada di area terbuka.', 'bg-yellow-100 text-yellow-700');
        }
        isProcessingRecognition = false;
        return;
    }
    
    // Validate location is required for attendance
    if (!lat || !lng) {
        statusMessage('Lokasi GPS wajib untuk presensi. Pastikan GPS aktif dan izin lokasi diberikan.', 'bg-red-100 text-red-700');
        isProcessingRecognition = false;
        return;
    }
    
    // FAST: Get location string immediately (don't wait, submit with coordinates if needed)
    // Start getting location string in parallel while processing other things
    let lokasi = '';
    const locationPromise = getStreetNameFromCoordinates(lat, lng).then(loc => {
        if (loc) return loc;
        return `Lokasi: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }).catch(() => {
        return `Lokasi: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    });
    
    // Get WiFi SSID if available (for WFO validation)
    // Note: Browser security prevents direct WiFi SSID access, but we can try multiple methods
    let wifiSSID = '';
    try {
        // Method 1: Check if we're on WiFi connection
        if (navigator.connection) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection && connection.type === 'wifi') {
                // We're on WiFi, try to get more info if available
                // For Chrome on Android, we might be able to get SSID in some cases
                if (connection.wifiSSID) {
                    wifiSSID = connection.wifiSSID;
                }
            }
        }
        
        // Method 2: Try Chrome-specific API (limited support)
        if (!wifiSSID && navigator.connection && 'getNetworkInformation' in navigator.connection) {
            try {
                const networkInfo = await navigator.connection.getNetworkInformation();
                if (networkInfo && networkInfo.wifiSSID) {
                    wifiSSID = networkInfo.wifiSSID;
                }
            } catch (e) {
                // Not available
            }
        }
        
        // If still empty and we're inside WFO area (by GPS), assume connected to Telkom WiFi
        // Backend will validate based on IP and location
        if (!wifiSSID && lat && lng) {
            // We'll let backend determine if WiFi is required based on location
            // This allows presensi if GPS indicates inside WFO area
        }
    } catch (e) {
        // WiFi detection not available on this platform - backend will handle validation
    }

    async function submitAttendance(extra={}){
        return api('?ajax=save_attendance', { 
            nim,
            mode: scanMode,
            ekspresi: topExpression,
            screenshot: screenshot,
            lat: lat ?? '',
            lng: lng ?? '',
            lokasi: lokasi ?? '',
            wifi_ssid: wifiSSID,
            gps_accuracy: position?.coords?.accuracy || '',
            ...extra
        }, { suppressModal: true });
    }

    try{
        // OPTIMIZED: Fetch public IP with aggressive timeout for better performance
        // Use cached IP if available (valid for 5 minutes)
        const ipCacheKey = 'cached_public_ip';
        const ipCacheTimeKey = 'cached_public_ip_time';
        const cachedIp = sessionStorage.getItem(ipCacheKey);
        const cachedIpTime = parseInt(sessionStorage.getItem(ipCacheTimeKey) || '0');
        const now = Date.now();
        const cacheValid = cachedIp && (now - cachedIpTime < 300000); // 5 minutes cache
        
        let publicIp = '';
        if (cacheValid) {
            // Use cached IP
            publicIp = cachedIp;
            window.__publicIp = publicIp;
        } else {
            // Fetch new IP with very short timeout for better performance
            const ipPromise = (async () => {
                try {
                    const ipFetch = fetch('https://api.ipify.org?format=json', { 
                        cache: 'no-store',
                        signal: AbortSignal.timeout(200) // Very short timeout: 200ms
                    });
                    const ipResp = await ipFetch;
                    if (ipResp && ipResp.ok) {
                        const ipJson = await ipResp.json();
                        const ip = ipJson?.ip || '';
                        // Cache the IP
                        if (ip) {
                            sessionStorage.setItem(ipCacheKey, ip);
                            sessionStorage.setItem(ipCacheTimeKey, now.toString());
                        }
                        return ip;
                    }
                } catch {}
                return '';
            })();
            
            // Don't wait for IP - get it asynchronously
            ipPromise.then(ip => {
                window.__publicIp = ip;
            });
            // OPTIMIZED: Get IP quickly or use empty string (backend can detect from server IP)
            // Very short wait time for better performance
            publicIp = await Promise.race([
                ipPromise,
                new Promise(resolve => setTimeout(() => resolve(''), 150)) // Reduced to 150ms for faster response
            ]);
            window.__publicIp = publicIp;
        }
        
        // Get location string with reasonable timeout to ensure we get full address
        // User needs to see full address, not just coordinates
        try {
            lokasi = await Promise.race([
                locationPromise,
                new Promise(resolve => setTimeout(() => {
                    // Fallback to coordinates only if timeout (increased timeout for better address retrieval)
                    resolve(`Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                }, 8000)) // Increased to 8 seconds to allow reverse geocoding to complete
            ]);
            
            // If we got coordinates as fallback, try one more time with longer timeout
            if (lokasi && lokasi.startsWith('Koordinat:')) {
                console.log('First attempt returned coordinates, retrying with longer timeout...');
                try {
                    const retryLokasi = await Promise.race([
                        getStreetNameFromCoordinates(lat, lng),
                        new Promise(resolve => setTimeout(() => {
                            resolve(`Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                        }, 6000)) // 6 seconds for retry
                    ]);
                    if (retryLokasi && !retryLokasi.startsWith('Koordinat:')) {
                        lokasi = retryLokasi; // Use the address if we got it
                    }
                } catch (retryError) {
                    console.warn('Retry reverse geocoding failed:', retryError);
                }
            }
        } catch (e) {
            // Fallback to coordinates on error
            console.warn('Error getting location string:', e);
            lokasi = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        
        // Ensure lokasi is never empty
        if (!lokasi || lokasi.trim() === '') {
            lokasi = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        
        // Store attendance data for potential WFA retry
        const attendanceData = { 
            nim,
            mode: scanMode,
            ekspresi: topExpression,
            screenshot: screenshot,
            lat: lat ?? '',
            lng: lng ?? '',
            lokasi: lokasi,
            public_ip: publicIp || '' // Use the IP we got (or empty if timeout)
        };
        window.pendingAttendanceData = attendanceData;
        
        // Recheck location function - called when user clicks "Tidak" on location confirmation
        const recheckLocation = async () => {
            return new Promise((resolve) => {
                // Re-fetch GPS location
                if (!navigator.geolocation) {
                    resolve(null);
                    return;
                }
                
                navigator.geolocation.getCurrentPosition(
                    async (pos) => {
                        const newLat = pos.coords.latitude;
                        const newLng = pos.coords.longitude;
                        
                        // Validate coordinates
                        if (isNaN(newLat) || isNaN(newLng) || newLat === 0 || newLng === 0) {
                            resolve(null);
                            return;
                        }
                        
                        // Get new location string with enhanced reverse geocoding
                        // Since user clicked "Periksa Ulang", they're willing to wait for accurate address
                        let newLokasi = '';
                        let retryCount = 0;
                        const maxRetries = 3;
                        
                        // Try to get address with retries and longer timeout
                        while (retryCount < maxRetries && (!newLokasi || newLokasi.startsWith('Koordinat:'))) {
                            try {
                                // Use longer timeout for recheck (user is willing to wait)
                                const controller = new AbortController();
                                const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout for recheck
                                
                                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${newLat}&lon=${newLng}&addressdetails=1&accept-language=id&zoom=18`, {
                                    signal: controller.signal
                                });
                                clearTimeout(timeoutId);
                                
                                if (response && response.ok) {
                                    const data = await response.json();
                                    
                                    if (data && data.address) {
                                        const address = data.address;
                                        const parts = [];
                                        
                                        // 1. Building name or house name (most specific)
                                        if (address.building) parts.push(address.building);
                                        else if (address.house_name) parts.push(address.house_name);
                                        
                                        // 2. Road/Street with house number if available
                                        const roadParts = [];
                                        if (address.house_number) roadParts.push(address.house_number);
                                        if (address.road) roadParts.push(address.road);
                                        else if (address.pedestrian) roadParts.push(address.pedestrian);
                                        else if (address.footway) roadParts.push(address.footway);
                                        if (roadParts.length > 0) {
                                            parts.push('Jl. ' + roadParts.join(' '));
                                        }
                                        
                                        // 3. Suburb/Neighbourhood
                                        if (address.suburb) parts.push(address.suburb);
                                        else if (address.neighbourhood) parts.push(address.neighbourhood);
                                        
                                        // 4. City/Town/Village
                                        if (address.city) parts.push(address.city);
                                        else if (address.town) parts.push(address.town);
                                        else if (address.village) parts.push(address.village);
                                        
                                        // 5. State/Province
                                        if (address.state) parts.push(address.state);
                                        
                                        // 6. Postal code
                                        if (address.postcode) parts.push(address.postcode);
                                        
                                        if (parts.length > 0) {
                                            newLokasi = parts.join(', ');
                                            break; // Success, exit retry loop
                                        }
                                        
                                        // Fallback to display_name
                                        if (data.display_name) {
                                            let cleanName = data.display_name.replace(/, Indonesia$/, '');
                                            if (address.postcode) {
                                                cleanName += ', ' + address.postcode;
                                            }
                                            newLokasi = cleanName;
                                            break; // Success, exit retry loop
                                        }
                                    }
                                    
                                    // If address parsing failed but display_name exists, use it
                                    if (data && data.display_name && !newLokasi) {
                                        newLokasi = data.display_name.replace(/, Indonesia$/, '');
                                        break; // Success, exit retry loop
                                    }
                                }
                            } catch (e) {
                                console.warn(`Reverse geocoding attempt ${retryCount + 1} failed:`, e);
                                retryCount++;
                                if (retryCount < maxRetries) {
                                    // Wait a bit before retry
                                    await new Promise(resolve => setTimeout(resolve, 1000));
                                }
                            }
                        }
                        
                        // If still no address after retries, use coordinates as last resort
                        if (!newLokasi || newLokasi.startsWith('Koordinat:')) {
                            newLokasi = `Koordinat: ${newLat.toFixed(6)}, ${newLng.toFixed(6)}`;
                        }
                        
                        // Update attendance data with new location
                        attendanceData.lat = newLat;
                        attendanceData.lng = newLng;
                        attendanceData.lokasi = newLokasi;
                        window.pendingAttendanceData = attendanceData;
                        
                        resolve({ lokasi: newLokasi, lat: newLat, lng: newLng });
                    },
                    (err) => {
                        console.warn('Geolocation recheck error:', err);
                        resolve(null);
                    },
                    {
                        enableHighAccuracy: true, // Use high accuracy for recheck
                        timeout: 6000, // Longer timeout for recheck
                        maximumAge: 0 // Force fresh location
                    }
                );
            });
        };
        
        // Show location confirmation modal before submitting - with recheck capability
        const locationResult = await showLocationConfirmation(lokasi, lat, lng, recheckLocation);
        if (!locationResult || !locationResult.confirmed) {
            // User cancelled
            isProcessingRecognition = false;
            return;
        }
        
        // Update with confirmed location (may have been rechecked)
        if (locationResult.lokasi && locationResult.lat && locationResult.lng) {
            lat = locationResult.lat;
            lng = locationResult.lng;
            lokasi = locationResult.lokasi;
            attendanceData.lat = lat;
            attendanceData.lng = lng;
            attendanceData.lokasi = lokasi;
            window.pendingAttendanceData = attendanceData;
        }
        
        // FAST: Submit after confirmation - location is guaranteed to be set
        let r = await submitAttendance();
        if(!r.ok && r.need_overtime_reason){
            // Show Overtime modal
            showOvertimeModal(r.message || 'Presensi di hari libur/weekend dianggap overtime. Harap isi alasan dan lokasi overtime.');
            isProcessingRecognition = false;
            return; // Exit early, Overtime modal will handle retry
        }
        if(!r.ok && r.need_reason){
            // Show WFA modal using new system
            showWFAModal(r.message || 'Di luar wilayah kantor. Harap isi alasan kerja di luar (WFA).');
            isProcessingRecognition = false;
            return; // Exit early, WFA modal will handle retry
        }
        if(!r.ok && r.need_early_leave_reason){
            // Show Early Leave modal
            showEarlyLeaveModal(r.message || 'Anda pulang sebelum jam yang ditentukan. Harap isi alasan pulang awal.');
            isProcessingRecognition = false;
            return; // Exit early, Early Leave modal will handle retry
        }
        // ULTRA-FAST: Skip logging for maximum speed
        
        // Auto stop detection after attendance submission (success or failed)
        isPresensiSuccess = true;
        isDetectionStopped = true;
        stopDetection();
        
        // Ubah tombol stop menjadi start
        const btnStop = qs('#btn-stop-detection');
        const btnStart = qs('#btn-start-detection');
        
        if (btnStop) btnStop.classList.add('hidden');
        if (btnStart) {
            btnStart.classList.remove('hidden');
            // Remove existing listeners and add new one
            const newBtnStart = btnStart.cloneNode(true);
            btnStart.parentNode.replaceChild(newBtnStart, btnStart);
            newBtnStart.addEventListener('click', () => {
                isPresensiSuccess = false;
                isDetectionStopped = false; // Reset stop flag
                processedLabels.delete(nim);
                startVideo();
                startVideoInterval();
                newBtnStart.classList.add('hidden');
                if (btnStop) btnStop.classList.remove('hidden');
            });
        }
        
        if(r.ok){
            statusMessage(r.message, r.statusClass || 'bg-green-100 text-green-700');
            // Update log after successful attendance
            updateLogAfterAttendance(nim, scanMode);
            // Tandai label sudah diproses agar tidak dobel
            processedLabels.set(nim, Date.now());
        } else {
            // Check if error is about WiFi requirement - show WFA modal
            const msg = (r.message || '').toLowerCase();
            if (msg.includes('wifi telkom university') || (msg.includes('wifi') && msg.includes('harus'))) {
                // Show WFA modal for WiFi-related errors
                showWFAModal(r.message || 'Untuk presensi WFO, Anda harus terhubung ke WiFi Telkom University. Silakan hubungkan ke WiFi Telkom University atau gunakan presensi WFA dengan alasan.');
                isProcessingRecognition = false;
                return; // Exit early, WFA modal will handle retry
            }
            
            statusMessage(r.message || 'Gagal menyimpan presensi', r.statusClass || 'bg-yellow-100 text-yellow-700');
            // Jika sudah presensi sebelumnya, hentikan deteksi dan berikan notifikasi jelas
            if (msg.includes('sudah presensi')) {
                processedLabels.set(nim, Date.now());
            }
        }
    }catch(err){
        console.error('Error in handleRecognition:', err);
        let errorMessage = 'Terjadi kesalahan server';
        if (err.message.includes('invalid JSON')) {
            errorMessage = 'Server mengalami masalah teknis. Silakan coba lagi.';
        } else if (err.message.includes('HTTP error')) {
            errorMessage = 'Koneksi ke server bermasalah. Silakan coba lagi.';
        } else if (err.message.includes('Data yang dikirim tidak valid')) {
            errorMessage = 'Data yang dikirim tidak valid. Silakan coba lagi.';
        } else if (err.message.includes('Server error')) {
            errorMessage = 'Server error. Silakan coba lagi.';
        } else if (err.message.includes('Presensi masuk hanya tersedia') || err.message.includes('Presensi masuk tersedia')) {
            errorMessage = 'Waktu presensi tidak sesuai. Silakan coba pada jam yang tepat.';
        } else if (err.message.includes('Waktu presensi tidak sesuai')) {
            errorMessage = 'Waktu presensi tidak sesuai. Silakan coba pada jam yang tepat.';
        } else if (err.message.includes('NIM tidak ditemukan')) {
            errorMessage = 'NIM tidak ditemukan. Silakan hubungi administrator.';
        } else if (err.message.includes('Database error')) {
            errorMessage = 'Database error. Silakan hubungi administrator.';
        } else if (err.message.includes('Screenshot tidak berhasil diambil')) {
            errorMessage = 'Screenshot tidak berhasil diambil. Silakan coba lagi dengan posisi yang lebih baik.';
        } else if (err.message.includes('Ukuran screenshot terlalu besar')) {
            errorMessage = 'Ukuran screenshot terlalu besar. Silakan coba lagi.';
        } else if (err.message.includes('Database structure error')) {
            errorMessage = 'Database structure error. Silakan hubungi administrator.';
        } else if (err.message.includes('Bad request')) {
            errorMessage = 'Bad request. Silakan coba lagi.';
        } else if (err.message.includes('Unauthorized')) {
            errorMessage = 'Unauthorized. Silakan login kembali.';
        } else if (err.message.includes('Forbidden')) {
            errorMessage = 'Forbidden. Silakan hubungi administrator.';
        } else if (err.message.includes('Tidak dapat terhubung ke server')) {
            errorMessage = 'Tidak dapat terhubung ke server. Pastikan XAMPP sudah berjalan.';
        } else if (err.message.includes('Server tidak merespons')) {
            errorMessage = 'Server tidak merespons. Silakan coba lagi.';
        } else if (err.message.includes('Network error')) {
            errorMessage = 'Network error. Silakan coba lagi.';
        } else if (err.message.includes('Connection refused')) {
            errorMessage = 'Connection refused. Silakan coba lagi.';
        }
        statusMessage(errorMessage, 'bg-red-100 text-red-700');
    } finally {
        // INSTANT: Immediate reset for maximum speed
        isProcessingRecognition = false;
    }
}

function stopVideoAfterRecognition(){
    if(videoInterval) {
        clearInterval(videoInterval);
        videoInterval = null;
    }
    // INSTANT: Much faster reset for better user experience
    let delayDuration = 3000; // Reduced from 10000 to 3000
    if (presensiStatus && presensiStatus.textContent) {
        const currentText = presensiStatus.textContent;
        const wordCount = currentText.split(' ').length;
        delayDuration = Math.max(2000, wordCount * 200 + 1000); // Much faster calculation
    }
    setTimeout(()=>{
        if(isCameraActive) resetPresensiPage();
    }, delayDuration);
}

// Function to reset recognition system for multi-person support
function resetRecognitionSystem() {
    // Clear detection history
    detectionHistory = [];
    
    // Clear recognition queue
    recognitionQueue = [];
    
    // Reset processing flags
    isProcessingRecognition = false;
    isProcessingQueue = false;
    recognitionCompleted = false;
    
    // Reset last successful detection
    lastSuccessfulDetection = null;
    
    console.log('Recognition system reset for multi-person support');
}

// Function to manually stop detection (for admin use)
function stopDetection() {
    isDetectionStopped = true; // Set flag to stop detection
    if(videoInterval) {
        clearInterval(videoInterval);
        videoInterval = null;
    }
    resetRecognitionSystem();
    console.log('Face detection stopped manually');
}

// Load daily report statistics for landing page
async function loadLandingDailyReportStats() {
    try {
        console.log('Fetching dashboard data for landing page...');
        const landingStatsDiv = document.getElementById('landing-daily-report-stats');
        
        // Check if section exists first
        if (!landingStatsDiv) {
            console.warn('Landing stats section not found in DOM - section may not be rendered');
            return;
        }
        
        // Show section immediately
        landingStatsDiv.style.display = 'block';
        
        const result = await api('?ajax=get_public_daily_report_stats', {}, { suppressModal: true, cache: false });
        
        console.log('Public daily report stats response:', result);
        
        if (result.ok && result.data) {
            const stats = result.data;
            console.log('Daily report stats:', stats);
            
            // Show section if it exists
            if (landingStatsDiv) {
                landingStatsDiv.style.display = 'block';
                console.log('Landing stats section found and shown');
            } else {
                console.error('Landing stats section not found in DOM');
            }
            
            const employeeListContainer = document.getElementById('landing-employees-list-container');
            if (!employeeListContainer) {
                console.error('Employee list container not found');
                return;
            }
            if (stats.employee_details) {
                const employees = stats.employee_details;
                if (employees.length > 0) {
                    employeeListContainer.innerHTML = employees.map((emp, index) => {
                        const badgeClass = emp.missing_count > 0 
                            ? 'bg-gradient-to-r from-orange-500 to-amber-500' 
                            : 'bg-gradient-to-r from-green-500 to-green-600';
                        const badgeText = emp.missing_count > 0 
                            ? `${emp.missing_count} laporan` 
                            : 'Lengkap';
                        return `
                        <div class="flex items-center justify-between p-2 bg-gradient-to-r from-orange-50 to-amber-50 hover:from-orange-100 hover:to-amber-100 rounded-lg transition-all duration-200 border border-orange-200 hover:border-orange-300 hover:shadow-sm">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <div class="relative flex-shrink-0">
                                    <div id="landing-emp-photo-container-${emp.id}" data-id="${emp.id}" class="lazy-member-photo w-10 h-10 rounded-full border-2 border-orange-400 shadow-sm overflow-hidden bg-gray-200 flex items-center justify-center cursor-pointer">
                                        ${emp.has_foto ? 
                                            `<i class="fi fi-sr-spinner animate-spin text-gray-400 text-[10px]"></i>` : 
                                            `<i class="fi fi-sr-user text-gray-400 text-xs"></i>`
                                        }
                                    </div>
                                    <div class="absolute -top-1 -right-1 bg-gradient-to-br from-orange-500 to-orange-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold shadow-md" style="font-size: 0.65rem;">
                                        ${index + 1}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-900 truncate">${emp.nama}</p>
                                </div>
                            </div>
                            <div class="ml-2 flex-shrink-0">
                                <span class="${badgeClass} text-white text-xs font-bold px-2 py-1 rounded-full shadow-sm">
                                    ${badgeText}
                                </span>
                            </div>
                        </div>
                    `;
                    }).join('');
                    
                    // Trigger lazy load observer for the new photos
                    setTimeout(() => {
                        employees.forEach(emp => {
                            if (emp.has_foto) {
                                const el = document.getElementById(`landing-emp-photo-container-${emp.id}`);
                                if (el && window.memberPhotoObserver) window.memberPhotoObserver.observe(el);
                                else if (el && !window.memberPhotoObserver && window.lazyLoadMemberPhoto) window.lazyLoadMemberPhoto(emp.id, `landing-emp-photo-container-${emp.id}`);
                            }
                        });
                    }, 100);
                } else {
                    employeeListContainer.innerHTML = `
                        <div class="text-center py-8 text-gray-400">
                            <p class="text-sm">Tidak ada data pegawai</p>
                        </div>
                    `;
                }
            }
        } else {
            console.warn('Daily report stats data not available');
            const employeeListContainer = document.getElementById('landing-employees-list-container');
            if (employeeListContainer) {
                employeeListContainer.innerHTML = `
                    <div class="text-center py-8 text-gray-400">
                        <p class="text-sm">Tidak ada data yang tersedia</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading landing daily report stats:', error);
        const employeeListContainer = document.getElementById('landing-employees-list-container');
        if (employeeListContainer) {
            employeeListContainer.innerHTML = `
                <div class="text-center py-8 text-gray-400">
                    <p class="text-sm">Gagal memuat data. Silakan refresh halaman.</p>
                </div>
            `;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Initializing face recognition system...');
    
    // Load daily report statistics for landing page if admin
    // Check if we're on landing page
    const pagePresensi = document.getElementById('page-presensi');
    const isLandingPage = window.location.href.includes('page=landing') || (pagePresensi && pagePresensi.offsetParent !== null);
    const landingStatsDiv = document.getElementById('landing-daily-report-stats');
    
    console.log('DOMContentLoaded - Landing page check:', {
        isLandingPage: isLandingPage,
        hasPagePresensi: !!pagePresensi,
        hasStatsDiv: !!landingStatsDiv,
        url: window.location.href,
        statsDivDisplay: landingStatsDiv ? window.getComputedStyle(landingStatsDiv).display : 'N/A'
    });
    
    // Always try to load if section exists and we're on landing page
    if (isLandingPage) {
        console.log('Landing page detected');
        if (landingStatsDiv) {
            console.log('Stats section found, loading data...');
            // Show section immediately (in case it was hidden) and keep it visible
            landingStatsDiv.style.display = 'block';
            landingStatsDiv.style.visibility = 'visible';
            landingStatsDiv.style.opacity = '1';
            
            // Use MutationObserver to ensure section stays visible
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        const currentDisplay = window.getComputedStyle(landingStatsDiv).display;
                        if (currentDisplay === 'none') {
                            console.log('Section was hidden, restoring visibility...');
                            landingStatsDiv.style.display = 'block';
                            landingStatsDiv.style.visibility = 'visible';
                            landingStatsDiv.style.opacity = '1';
                        }
                    }
                });
            });
            observer.observe(landingStatsDiv, { attributes: true, attributeFilter: ['style'] });
            
            // Load immediately
            loadLandingDailyReportStats();
            // Auto-refresh every 30 seconds (only if admin, will stop if 401)
            const refreshInterval = setInterval(() => {
                loadLandingDailyReportStats().catch(() => {
                    // Stop refreshing if consistently failing
                    clearInterval(refreshInterval);
                });
            }, 30000);
        } else {
            console.warn('Stats section not found in DOM - checking if it exists...');
            // Try to find it again after a short delay (in case DOM not fully loaded)
            setTimeout(() => {
                const retryDiv = document.getElementById('landing-daily-report-stats');
                if (retryDiv) {
                    console.log('Stats section found on retry, loading data...');
                    retryDiv.style.display = 'block';
                    loadLandingDailyReportStats();
                    setInterval(loadLandingDailyReportStats, 30000);
                } else {
                    console.error('Stats section still not found after retry');
                }
            }, 500);
        }
    } else {
        console.log('Not on landing page');
    }
    
    initializeSpeechSynthesis();
    initializeFaceRecognition();
    // OPTIMIZED: Lazy load models - only load when user clicks scan button (not on page load)
    // This significantly improves initial page load time, especially on low-end devices
    // Models will be loaded when btnScanMasuk or btnScanPulang is clicked
    
    // INSTANT: Immediate debug info display
    console.log('🔧 Face Recognition Debug Info:');
    console.log(`  - Face Matcher Threshold: ${detectionConfig.faceMatcherThreshold}`);
    console.log(`  - Recognition Threshold: ${detectionConfig.recognitionThreshold}`);
    console.log(`  - Quality Threshold: ${detectionConfig.qualityThreshold}`);
    console.log(`  - Score Threshold: ${detectionConfig.scoreThreshold}`);
    console.log(`  - Input Size: ${detectionConfig.inputSize}`);
    console.log(`  - Min Face Size: ${detectionConfig.minFaceSize}`);
    // Reset log data daily
    checkAndResetLogDaily();
});

// Load log presensi masuk
async function loadLogMasuk() {
    try {
        const result = await api('?ajax=get_today_attendance', { type: 'masuk' }, { suppressModal: true });
        console.log('Log masuk response:', result);
        
        if (result.ok) {
            logMasukData = result.data || [];
            console.log('Log masuk data:', logMasukData);
            renderLogMasuk();
        } else {
            console.error('API Error:', result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading log masuk:', error);
    }
}

// Load log presensi pulang
async function loadLogPulang() {
    try {
        const result = await api('?ajax=get_today_attendance', { type: 'pulang' }, { suppressModal: true });
        console.log('Log pulang response:', result);
        
        if (result.ok) {
            logPulangData = result.data || [];
            console.log('Log pulang data:', logPulangData);
            renderLogPulang();
        } else {
            console.error('API Error:', result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading log pulang:', error);
    }
}

// ==========================================
function resetTablePage(tbodyId) {
    if (window.tablePaginationState && window.tablePaginationState[tbodyId]) {
        window.tablePaginationState[tbodyId].currentPage = 1;
    }
}

function renderPaginatedTable(tbodyId, items, renderFn, options = {}) {
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
        renderPaginationUI(tbodyId, 0, 1, pageSize, 0, 0, () => {}, () => {});
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

    renderPaginationUI(
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
}

function renderPaginationUI(tbodyId, totalItems, currentPage, pageSize, startIndex, endIndex, onPageChange, onSizeChange) {
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
}

// Render log presensi masuk
function renderLogMasuk() {
    renderPaginatedTable('log-masuk-body', logMasukData, (item, index) => {
        const screenshot = item.has_sm ? 
            `<div class="text-center"><button type="button" class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider hover:bg-blue-200 transition-colors" onclick="loadAndShowEvidence('${item.id}', 'masuk', 'Bukti Masuk')">Lihat Foto</button></div>` :
            '<span class="text-gray-400">-</span>';
        
        const jamMasuk = item.jam_masuk ? item.jam_masuk.substring(0, 5) : '-';
        const tanggal = item.jam_masuk_iso ? new Date(item.jam_masuk_iso).toLocaleDateString('id-ID') : '-';
        const lokasi = item.lokasi_masuk || '-';
        
        return `
            <tr class="border-b hover:bg-gray-50">
                <td class="py-2 px-4 text-center">${index + 1}</td>
                <td class="py-2 px-4 text-center">${tanggal}</td>
                <td class="py-2 px-4">${item.nama || '-'}</td>
                <td class="py-2 px-4 text-center">${item.startup || '-'}</td>
                <td class="py-2 px-4 text-center">${jamMasuk}</td>
                <td class="py-2 px-4">${lokasi}</td>
                <td class="py-2 px-4 text-center">${screenshot}</td>
            </tr>
        `;
    }, {
        colSpan: 7,
        emptyMessage: 'Belum ada presensi masuk hari ini',
        onPageChange: renderLogMasuk
    });
}

// Render log presensi pulang
function renderLogPulang() {
    renderPaginatedTable('log-pulang-body', logPulangData, (item, index) => {
        const screenshot = item.has_sp ? 
            `<div class="text-center"><button type="button" class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider hover:bg-blue-200 transition-colors" onclick="loadAndShowEvidence('${item.id}', 'pulang', 'Bukti Pulang')">Lihat Foto</button></div>` :
            '<span class="text-gray-400">-</span>';
        
        const jamPulang = item.jam_pulang ? item.jam_pulang.substring(0, 5) : '-';
        const tanggal = item.jam_pulang_iso ? new Date(item.jam_pulang_iso).toLocaleDateString('id-ID') : '-';
        const lokasi = item.lokasi_pulang || '-';
        
        return `
            <tr class="border-b hover:bg-gray-50">
                <td class="py-2 px-4 text-center">${index + 1}</td>
                <td class="py-2 px-4 text-center">${tanggal}</td>
                <td class="py-2 px-4">${item.nama || '-'}</td>
                <td class="py-2 px-4 text-center">${item.startup || '-'}</td>
                <td class="py-2 px-4 text-center">${jamPulang}</td>
                <td class="py-2 px-4">${lokasi}</td>
                <td class="py-2 px-4 text-center">${screenshot}</td>
            </tr>
        `;
    }, {
        colSpan: 7,
        emptyMessage: 'Belum ada presensi pulang hari ini',
        onPageChange: renderLogPulang
    });
}

// Update log after successful attendance
function updateLogAfterAttendance(nim, mode) {
    // INSTANT: Immediate update for maximum speed
    if (mode === 'masuk') {
        loadLogMasuk();
    } else {
        loadLogPulang();
    }
}

// Check and reset log daily
function checkAndResetLogDaily() {
    const today = new Date().toDateString();
    const lastReset = localStorage.getItem('lastLogReset');
    
    if (lastReset !== today) {
        logMasukData = [];
        logPulangData = [];
        localStorage.setItem('lastLogReset', today);
    }
}



