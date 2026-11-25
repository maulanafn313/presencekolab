const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const vm = require('vm');

function loadAttendance(labeled) {
    const context = {
        window: {}, navigator: { userAgent: '' }, console,
        document: { addEventListener() {}, getElementById: () => null, querySelector: () => null },
        faceapi: {
            euclideanDistance: (a, b) => Math.hypot(...a.map((v, i) => v - b[i])),
            FaceMatcher: function (lds, t) { this.labeledDescriptors = lds; this.distanceThreshold = t; },
        },
    };
    context.window = context;
    vm.runInNewContext(fs.readFileSync('public/assets/js/attendance.js', 'utf8')
        + '\nthis.__t = { buildFaceMatcher, secondBestDistance, MIN_MATCH_MARGIN, setLabeled: v => { labeledFaceDescriptors = v; faceMatcher = buildFaceMatcher(); } };', context);
    context.__t.setLabeled(labeled);
    return context.__t;
}

test('matcher uses configured threshold, not hardcoded 0.5', () => {
    const t = loadAttendance([]);
    assert.strictEqual(t.buildFaceMatcher().distanceThreshold, 0.4);
});

test('face close to two employees is flagged ambiguous; single-person gallery is not', () => {
    const t = loadAttendance([{ label: 'A', descriptors: [[0, 0]] }, { label: 'B', descriptors: [[0.3, 0]] }]);
    const face = [0.14, 0]; // A=0.14, B=0.16 -> gap 0.02
    assert.ok(t.secondBestDistance(face, 'A') - 0.14 < t.MIN_MATCH_MARGIN);
    const solo = loadAttendance([{ label: 'A', descriptors: [[0, 0]] }]);
    assert.strictEqual(solo.secondBestDistance(face, 'A'), Infinity);
});

test('descriptor cache key changes when a face embedding is re-registered', async () => {
    const context = { window: {}, console, document: { addEventListener() {}, getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] }, navigator: {}, localStorage: { getItem() {} } };
    context.window = context;
    const src = fs.readFileSync('public/assets/js/modules/utilities.js', 'utf8');
    const fn = src.slice(src.indexOf('function simpleHash'), src.indexOf('function idbOpen'));
    vm.runInNewContext(fn + '\nthis.k = computeMembersVersionKey;', context);
    const a = await context.k([{ id: 1, nim: '1', nama: 'X', face_embedding: '[1]' }]);
    const b = await context.k([{ id: 1, nim: '1', nama: 'X', face_embedding: '[2]' }]);
    assert.notStrictEqual(a, b);
});
