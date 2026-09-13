// Send CSRF only to this origin, covering legacy helpers and direct fetch calls.
(() => {
    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, options = {}) => {
        const url = new URL(input instanceof Request ? input.url : input, location.href);
        const method = (options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
        if (url.origin === location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            const headers = new Headers(options.headers || (input instanceof Request ? input.headers : undefined));
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) headers.set('X-CSRF-TOKEN', token);
            options = { ...options, headers };
        }
        return originalFetch(input, options);
    };
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() === 'GET' || new URL(form.action).origin !== location.origin) return;
        let field = form.querySelector('input[name="_token"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden'; field.name = '_token'; form.appendChild(field);
        }
        field.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }, true);
})();
