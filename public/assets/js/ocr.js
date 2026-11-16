// CloakPaste - client-side OCR + PII detection. Nothing leaves the browser.
// Requires Tesseract.js v5 (global `Tesseract`).

(() => {
    // ---- Validators -------------------------------------------------------
    const digitsOnly = (s) => s.replace(/\D/g, '');

    function luhn(num) {
        let sum = 0, alt = false;
        for (let i = num.length - 1; i >= 0; i--) {
            let n = num.charCodeAt(i) - 48;
            if (alt) { n *= 2; if (n > 9) n -= 9; }
            sum += n; alt = !alt;
        }
        return sum % 10 === 0;
    }

    function ibanValid(raw) {
        const s = raw.replace(/\s/g, '').toUpperCase();
        if (s.length < 15 || s.length > 34) return false;
        const r = s.slice(4) + s.slice(0, 4);
        let rem = 0;
        for (const ch of r) {
            const v = /[A-Z]/.test(ch) ? String(ch.charCodeAt(0) - 55) : ch;
            for (const d of v) rem = (rem * 10 + (d.charCodeAt(0) - 48)) % 97;
        }
        return rem === 1;
    }

    // ---- Rules (order = priority when matches overlap) ---------------------
    // Each regex is created fresh per scan with the g flag, used via matchAll,
    // so there is no shared lastIndex state.
    const RULES = [
        { type: 'email', re: /[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/g },
        { type: 'iban', re: /\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]{4}){2,7}(?:[ ]?[A-Z0-9]{1,4})?\b/g, validate: ibanValid },
        { type: 'cnic', re: /\b\d{5}-\d{7}-\d\b/g },
        { type: 'ssn', re: /\b\d{3}-\d{2}-\d{4}\b/g },
        { type: 'card', re: /\b(?:\d[ -]?){12,18}\d\b/g, validate: (m) => { const d = digitsOnly(m); return d.length >= 13 && d.length <= 19 && luhn(d); } },
        {
            type: 'phone',
            re: /(?:\+|\b00)\d{1,3}[\s().-]*\d(?:[\s().-]*\d){6,12}|\b0\d{2,4}[\s-]?\d{6,8}\b/g,
            validate: (m) => { const n = digitsOnly(m).length; return n >= 9 && n <= 15; }
        }
    ];

    // Find PII spans in one line of text. Returns [{type,start,end,text}]
    function findSpans(text) {
        const spans = [];
        for (const rule of RULES) {
            for (const m of text.matchAll(new RegExp(rule.re.source, rule.re.flags))) {
                if (rule.validate && !rule.validate(m[0])) continue;
                const start = m.index, end = m.index + m[0].length;
                if (spans.some((s) => start < s.end && end > s.start)) continue; // higher-priority rule already took it
                spans.push({ type: rule.type, start, end, text: m[0] });
            }
        }
        return spans;
    }

    // ---- OCR worker (reused between scans) --------------------------------
    let workerPromise = null;
    let progressCb = null;

    function getWorker() {
        if (!workerPromise) {
            workerPromise = Tesseract.createWorker('eng', 1, {
                logger: (m) => {
                    if (!progressCb) return;
                    if (m.status === 'recognizing text') progressCb(m.progress, 'Recognizing text ' + Math.round(m.progress * 100) + '%');
                    else progressCb(0, m.status ? m.status.charAt(0).toUpperCase() + m.status.slice(1) : 'Working');
                }
            }).catch((e) => { workerPromise = null; throw e; });
        }
        return workerPromise;
    }

    // ---- Public API -------------------------------------------------------
    // extraPatterns: optional [{name, pattern}] (Pro saved rules; regex source strings)
    async function scanImageForPII(imageElement, onProgress, extraPatterns = []) {
        if (typeof Tesseract === 'undefined') throw new Error('OCR engine failed to load. Check your connection.');
        progressCb = onProgress || null;

        const custom = [];
        for (const r of extraPatterns) {
            try { custom.push({ type: r.name || 'custom', re: new RegExp(r.pattern, 'gi') }); } catch { /* ignore invalid user regex */ }
        }
        const rules = [...RULES, ...custom];

        const worker = await getWorker();
        const { data } = await worker.recognize(imageElement);
        progressCb = null;

        const boxes = [];
        for (const line of data.lines || []) {
            const words = (line.words || []).filter((w) => w.text && w.text.trim());
            if (!words.length) continue;

            // Build line text and remember each word's character range.
            let text = '';
            const ranges = words.map((w) => {
                const start = text.length;
                text += w.text.trim();
                const r = { start, end: text.length, w };
                text += ' ';
                return r;
            });
            text = text.trimEnd();

            const spans = findSpansWith(text, rules);
            for (const s of spans) {
                const hit = ranges.filter((r) => r.start < s.end && r.end > s.start).map((r) => r.w.bbox);
                if (!hit.length) continue;
                const x0 = Math.min(...hit.map((b) => b.x0)), y0 = Math.min(...hit.map((b) => b.y0));
                const x1 = Math.max(...hit.map((b) => b.x1)), y1 = Math.max(...hit.map((b) => b.y1));
                boxes.push({ x: x0, y: y0, width: x1 - x0, height: y1 - y0, text: s.text, type: s.type, redacted: true });
            }
        }
        return boxes;
    }

    function findSpansWith(text, rules) {
        const spans = [];
        for (const rule of rules) {
            for (const m of text.matchAll(new RegExp(rule.re.source, rule.re.flags))) {
                if (!m[0] || (rule.validate && !rule.validate(m[0]))) continue;
                const start = m.index, end = m.index + m[0].length;
                if (spans.some((s) => start < s.end && end > s.start)) continue;
                spans.push({ type: rule.type, start, end, text: m[0] });
            }
        }
        return spans;
    }

    window.scanImageForPII = scanImageForPII;
    window.CloakPII = { findSpans, luhn, ibanValid }; // exposed for testing
})();
