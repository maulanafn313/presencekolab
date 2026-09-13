const { test } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

test('first-party browser assets parse as JavaScript', () => {
    const visit = directory => {
        for (const entry of fs.readdirSync(directory, { withFileTypes:true })) {
            const file = path.join(directory, entry.name);
            if (entry.isDirectory()) { if (entry.name !== 'face-api-models') visit(file); }
            else if (entry.name.endsWith('.js') && !entry.name.includes('.min.') && !entry.name.startsWith('tailwind')) {
                new vm.Script(fs.readFileSync(file, 'utf8'), { filename:file });
            }
        }
    };
    visit('public/assets/js');
});

test('rendered login and both dashboard roles emit valid inline JavaScript', () => {
    for (const [url, role] of [['/login','guest'], ['/dashboard','admin'], ['/dashboard','pegawai']]) {
        const html = execFileSync('php', ['tests/Fixtures/legacy_page.php', url, role], { encoding:'utf8', maxBuffer:8*1024*1024 });
        for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
            if (/\bsrc=|application\/ld\+json|application\/json/i.test(match[1])) continue;
            new vm.Script(match[2], { filename:`${role}-inline.js` });
        }
    }
});
