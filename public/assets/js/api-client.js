// Shared legacy transport. Keep existing modal flags and POST defaults for old callers.
(() => {
    const notify = (json, options) => {
        if (!options.suppressModal && json?.message && typeof window.showModalNotif === 'function') {
            window.showModalNotif(json.message, json.ok === true, json.ok === true ? 'Berhasil' : 'Gagal');
        }
    };
    window.api = async function api(url, data = {}, options = {}) {
        if (url.startsWith('?')) url = location.pathname + url;
        else if (!url.startsWith('/') && !url.startsWith('http')) url = '/api/' + url;
        const target = new URL(url, location.href);
        if (target.host === 'localhost:3000') target.host = location.host;
        const method = (options.method || 'POST').toUpperCase();
        const isAttendance = target.searchParams.get('ajax') === 'save_attendance';
        if (isAttendance && data && !(data instanceof FormData)) {
            // A retry of the same payload retains its key. Modal amendments happen before success.
            data.request_id ||= window.crypto?.randomUUID?.() || `attendance-${Date.now()}-${Math.random()}`;
            if (window.attendanceSecurity?.requireProof && !data.face_proof) {
                const verified = await api('/api/face/verify', {
                    user_id: window.attendanceSecurity.userId,
                    image: data.screenshot || data.foto_base64 || '',
                    mode: data.mode,
                }, { suppressModal: true });
                if (!verified.ok) { notify(verified, options); return verified; }
                data.face_proof = verified.verification_token;
            }
        }
        const headers = new Headers({ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' });
        if (target.origin === location.origin) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) headers.set('X-CSRF-TOKEN', token);
        }
        const init = { method, headers, credentials:'same-origin', cache:'no-store' };
        if (['GET','HEAD'].includes(method)) {
            for (const [key, value] of new URLSearchParams(data)) target.searchParams.set(key, value);
        } else {
            init.body = data instanceof FormData ? data : new URLSearchParams(data);
        }
        try {
            const response = await fetch(target.href, init);
            if (!(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Server tidak mengembalikan data JSON. Silakan muat ulang halaman.');
            }
            const json = await response.json();
            notify(json, options);
            return json;
        } catch (error) {
            if (!options.suppressModal && typeof window.showNotif === 'function') {
                window.showNotif('Koneksi atau respons server bermasalah. Silakan coba lagi.', false);
            }
            throw error;
        }
    };
})();
