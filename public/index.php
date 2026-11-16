<?php
// CloakPaste - Single Page App shell.
// All media processing happens client-side; PHP only serves the UI.
$appName = 'CloakPaste';
$freeDailyLimit = 3;

// Session state for the UI. The page still works (as free tier) if the DB is down.
$user = null; $csrf = ''; $checkout = ['monthly' => '', 'lifetime' => ''];
try {
    require_once __DIR__ . '/../src/bootstrap.php';
    foreach (['monthly' => 'LS_CHECKOUT_MONTHLY', 'lifetime' => 'LS_CHECKOUT_LIFETIME'] as $plan => $key) {
        $url = (string)\CloakPaste\Config\Env::get($key, '');
        if (str_starts_with($url, 'https://')) $checkout[$plan] = $url; // ignore anything that isn't https
    }
    $user = \CloakPaste\Support\Session::user();
    $csrf = \CloakPaste\Support\Session::csrf();
} catch (Throwable $e) {
    error_log((string)$e);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName) ?> - Paste. Redact. Share safely.</title>
    <meta name="description" content="Paste a screenshot and automatically redact emails, phone numbers, IBANs, cards and IDs in seconds. Everything runs in your browser - nothing is uploaded.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 50:'#eef2ff', 100:'#e0e7ff',500:'#6366f1', 600:'#4f46e5', 700:'#4338ca' } } } } };
    </script>
    <style>
        .dz-active { border-color:#6366f1 !important; background-color:#eef2ff !important; }
        .checker { background-image: linear-gradient(45deg,#e5e7eb 25%,transparent 25%),linear-gradient(-45deg,#e5e7eb 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e5e7eb 75%),linear-gradient(-45deg,transparent 75%,#e5e7eb 75%); background-size:16px 16px; background-position:0 0,0 8px,8px -8px,-8px 0; }
        [data-state] { display:none; }
        body[data-current="idle"] [data-state="idle"],
        body[data-current="processing"] [data-state="processing"],
        body[data-current="editor"] [data-state="editor"],
        body[data-current="error"] [data-state="error"] { display:block; }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased" data-current="idle">

<!-- Header -->
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href="/" class="flex items-center gap-2 font-bold text-slate-900">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3z"/></svg>
            </span>
            <?= htmlspecialchars($appName) ?>
        </a>
        <nav class="flex items-center gap-3 text-sm">
            <span id="usageBadge" class="hidden rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 sm:inline-block"></span>
            <a href="#pricing" class="text-slate-600 hover:text-slate-900">Pricing</a>
            <span id="userEmail" class="hidden max-w-[10rem] truncate text-xs text-slate-500 md:inline-block"></span>
            <button id="rulesBtn" class="hidden rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">My rules</button>
            <button id="loginBtn" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">Log in</button>
            <button id="upgradeBtn" class="rounded-lg bg-brand-600 px-3 py-1.5 font-medium text-white hover:bg-brand-700">Go Pro</button>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-10">

    <!-- ============ STATE: idle ============ -->
    <section data-state="idle">
        <div class="mx-auto max-w-2xl text-center">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Paste a screenshot. We hide the private stuff.</h1>
            <p class="mt-3 text-slate-600">Emails, phone numbers, IBANs, cards and national IDs are detected and blacked out in about 2 seconds.</p>
        </div>

        <label id="dropzone" for="fileInput" tabindex="0"
               class="mx-auto mt-8 flex max-w-3xl cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center transition focus:outline-none focus:ring-2 focus:ring-brand-500">
            <svg class="h-12 w-12 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            <p class="mt-4 text-lg font-semibold text-slate-800">
                Press <kbd class="rounded border border-slate-300 bg-slate-100 px-2 py-0.5 font-mono text-sm">Ctrl</kbd> +
                <kbd class="rounded border border-slate-300 bg-slate-100 px-2 py-0.5 font-mono text-sm">V</kbd> anywhere to paste
            </p>
            <p class="mt-1 text-sm text-slate-500">or drag &amp; drop an image here, or <span class="font-medium text-brand-600 underline">browse files</span></p>
            <p class="mt-4 text-xs text-slate-400">PNG, JPG, WEBP &middot; up to 15&nbsp;MB</p>
            <input id="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">
        </label>

        <div class="mx-auto mt-6 flex max-w-3xl flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-slate-600">
            <span class="flex items-center gap-1.5"><span class="text-emerald-600">&#10003;</span> Never uploaded - runs in your browser</span>
            <span class="flex items-center gap-1.5"><span class="text-emerald-600">&#10003;</span> Metadata (EXIF/GPS) stripped</span>
            <span class="flex items-center gap-1.5"><span class="text-emerald-600">&#10003;</span> Review every box before export</span>
        </div>
    </section>

    <!-- ============ STATE: processing ============ -->
    <section data-state="processing" class="mx-auto max-w-xl py-16 text-center">
        <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-brand-600"></div>
        <h2 class="mt-6 text-xl font-semibold text-slate-900" id="procTitle">Scanning for sensitive data&hellip;</h2>
        <p class="mt-1 text-sm text-slate-500" id="procDetail">Loading OCR engine (first run only)</p>
        <div class="mx-auto mt-6 h-2 w-full max-w-sm overflow-hidden rounded-full bg-slate-200">
            <div id="procBar" class="h-full w-0 rounded-full bg-brand-600 transition-all duration-300"></div>
        </div>
        <button id="cancelBtn" class="mt-6 text-sm text-slate-500 underline hover:text-slate-800">Cancel</button>
    </section>

    <!-- ============ STATE: editor ============ -->
    <section data-state="editor">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Review redactions</h2>
                <p class="text-sm text-slate-500"><span id="boxCount">0</span> item(s) detected. Click a box on the image or toggle it in the list.</p>
            </div>
            <div class="flex gap-2">
                <button id="newBtn" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">New image</button>
                <button id="copyBtn" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Copy to clipboard</button>
                <button id="downloadBtn" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Download PNG</button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
            <div class="checker overflow-auto rounded-xl border border-slate-200 bg-white p-2">
                <canvas id="editorCanvas" class="mx-auto block max-w-full cursor-crosshair"></canvas>
            </div>

            <aside class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Detected items</h3>
                    <ul id="boxList" class="mt-3 max-h-72 space-y-2 overflow-auto text-sm"></ul>
                    <p id="boxEmpty" class="mt-3 hidden text-sm text-slate-500">Nothing detected. Draw a box on the image to redact manually.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    <h3 class="font-semibold text-slate-900">Tools</h3>
                    <label class="mt-3 flex items-center gap-2"><input type="checkbox" id="toolDraw" class="rounded" checked> Drag on image to add a box</label>
                    <button id="redactAllBtn" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Redact all</button>
                    <button id="clearAllBtn" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Clear all</button>
                </div>
                <div id="freeNotice" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-medium">Free export includes a small watermark.</p>
                    <p class="mt-1 text-amber-800">Go Pro for HD exports without a watermark, batch mode and saved rules.</p>
                </div>
            </aside>
        </div>
    </section>

    <!-- ============ STATE: error ============ -->
    <section data-state="error" class="mx-auto max-w-md py-16 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 text-2xl font-bold">!</div>
        <h2 class="mt-4 text-xl font-semibold text-slate-900">Something went wrong</h2>
        <p id="errorMsg" class="mt-1 text-sm text-slate-600"></p>
        <button id="retryBtn" class="mt-6 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Try again</button>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="mx-auto mt-24 max-w-4xl">
        <h2 class="text-center text-2xl font-bold text-slate-900">Simple pricing</h2>
        <div class="mt-8 grid gap-6 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="font-semibold">Free</h3>
                <p class="mt-2 text-3xl font-extrabold">$0</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-600">
                    <li>&#10003; <?= (int)$freeDailyLimit ?> exports / day</li>
                    <li>&#10003; Auto-detect PII</li>
                    <li>&#10003; Small watermark</li>
                </ul>
            </div>
            <div class="rounded-2xl border-2 border-brand-500 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-brand-700">Pro monthly</h3>
                <p class="mt-2 text-3xl font-extrabold">$7<span class="text-base font-medium text-slate-500">/mo</span></p>
                <ul class="mt-4 space-y-2 text-sm text-slate-600">
                    <li>&#10003; Unlimited HD exports</li>
                    <li>&#10003; No watermark</li>
                    <li>&#10003; Batch &amp; multi-page PDF</li>
                    <li>&#10003; Saved custom blocklists</li>
                </ul>
                <button data-buy="monthly" class="mt-5 w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60">Get Pro</button>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="font-semibold">Lifetime</h3>
                <p class="mt-2 text-3xl font-extrabold">$39</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-600">
                    <li>&#10003; Everything in Pro</li>
                    <li>&#10003; Pay once, use forever</li>
                </ul>
                <button data-buy="lifetime" class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60">Get Lifetime</button>
            </div>
        </div>
    </section>
</main>

<footer class="mt-16 border-t border-slate-200 py-6 text-center text-xs text-slate-500">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?> &middot; Your images never leave your device.
</footer>

<!-- Auth modal -->
<dialog id="authDialog" class="w-full max-w-sm rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/50">
    <form id="authForm" class="p-6" novalidate>
        <div class="flex items-start justify-between">
            <h2 id="authTitle" class="text-lg font-bold text-slate-900">Log in</h2>
            <button type="button" data-close class="text-slate-400 hover:text-slate-700" aria-label="Close">&#10005;</button>
        </div>
        <label class="mt-4 block text-sm font-medium text-slate-700">Email
            <input id="authEmail" type="email" autocomplete="email" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        </label>
        <label class="mt-3 block text-sm font-medium text-slate-700">Password
            <input id="authPass" type="password" autocomplete="current-password" required minlength="8" maxlength="72" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        </label>
        <p id="authHint" class="mt-1 hidden text-xs text-slate-500">At least 8 characters. Use the same email you buy Pro with.</p>
        <p id="authError" class="mt-3 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <button id="authSubmit" class="mt-5 w-full rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Log in</button>
        <p class="mt-4 text-center text-sm text-slate-600">
            <span id="authSwitchText">No account?</span>
            <button type="button" id="authSwitch" class="font-medium text-brand-600 underline">Sign up</button>
        </p>
    </form>
</dialog>

<!-- Rules manager modal (Pro) -->
<dialog id="rulesDialog" class="w-full max-w-lg rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/50">
    <div class="p-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Custom redaction rules</h2>
                <p class="mt-1 text-sm text-slate-500">Regular expressions matched against each line of OCR text. Saved to your account; matching runs in your browser.</p>
            </div>
            <button type="button" data-close class="text-slate-400 hover:text-slate-700" aria-label="Close">&#10005;</button>
        </div>
        <ul id="rulesList" class="mt-4 max-h-64 space-y-2 overflow-auto text-sm"></ul>
        <p id="rulesEmpty" class="mt-4 hidden text-sm text-slate-500">No rules yet. Add one below, e.g. an employee ID like <code class="rounded bg-slate-100 px-1">EMP-\d{5}</code>.</p>
        <form id="ruleForm" class="mt-4 space-y-2 border-t border-slate-200 pt-4" novalidate>
            <input id="ruleName" placeholder="Rule name (e.g. Employee ID)" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <input id="rulePattern" placeholder="Pattern (e.g. EMP-\d{5})" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <input id="ruleSample" placeholder="Optional: test text, e.g. badge EMP-12345" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
            <p id="ruleTest" class="hidden text-xs"></p>
            <p id="ruleError" class="hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
            <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Add rule</button>
        </form>
    </div>
</dialog>

<!-- Toast -->
<div id="toast" class="pointer-events-none fixed bottom-6 left-1/2 -translate-x-1/2 translate-y-4 rounded-lg bg-slate-900 px-4 py-2 text-sm text-white opacity-0 shadow-lg transition"></div>

<!-- Page-level config exposed to JS (no secrets) -->
<script>
    window.CLOAK = {
        freeDailyLimit: <?= (int)$freeDailyLimit ?>,
        isPro: <?= !empty($user['is_pro']) ? 'true' : 'false' ?>,
        email: <?= json_encode($user['email'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        csrf: <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        plan: <?= json_encode($user['plan_type'] ?? 'free') ?>,
        checkout: <?= json_encode($checkout, JSON_HEX_TAG | JSON_HEX_AMP) ?>
    };
</script>

<!-- UI controller: input handling + state machine. Engine hooks: ocr.js / editor.js (next steps). -->
<script>
(() => {
    const MAX_BYTES = 15 * 1024 * 1024;
    const ACCEPTED = ['image/png', 'image/jpeg', 'image/webp'];
    const $ = (id) => document.getElementById(id);
    const body = document.body;

    let currentImage = null;   // HTMLImageElement
    let currentObjectUrl = null;
    let cancelled = false;

    // ---------- state machine ----------
    function setState(s) { body.dataset.current = s; }

    function showError(msg) {
        $('errorMsg').textContent = msg;
        setState('error');
    }

    function toast(msg) {
        const t = $('toast');
        t.textContent = msg;
        t.classList.remove('opacity-0', 'translate-y-4');
        clearTimeout(toast._t);
        toast._t = setTimeout(() => t.classList.add('opacity-0', 'translate-y-4'), 2200);
    }

    function setProgress(pct, title, detail) {
        $('procBar').style.width = Math.max(0, Math.min(100, pct)) + '%';
        if (title) $('procTitle').textContent = title;
        if (detail) $('procDetail').textContent = detail;
    }

    // ---------- free-tier usage (UI hint only; enforce server-side later) ----------
    function usageKey() { return 'cloak_usage_' + new Date().toISOString().slice(0, 10); }
    function getUsage() { try { return parseInt(localStorage.getItem(usageKey()) || '0', 10); } catch { return 0; } }
    function bumpUsage() { try { localStorage.setItem(usageKey(), String(getUsage() + 1)); } catch {} renderUsage(); }
    function renderUsage() {
        const b = $('usageBadge');
        if (window.CLOAK.isPro) { b.classList.add('hidden'); $('freeNotice').classList.add('hidden'); return; }
        const left = Math.max(0, window.CLOAK.freeDailyLimit - getUsage());
        b.textContent = left + ' free export' + (left === 1 ? '' : 's') + ' left today';
        b.classList.remove('hidden');
    }

    // ---------- intake ----------
    function handleFile(file) {
        if (!file) return;
        if (!ACCEPTED.includes(file.type)) return showError('Unsupported file type. Please use PNG, JPG or WEBP.');
        if (file.size > MAX_BYTES) return showError('That image is larger than 15 MB.');

        cancelled = false;
        setState('processing');
        setProgress(5, 'Reading image…', 'Decoding locally');

        if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
        currentObjectUrl = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { currentImage = img; runPipeline(img); };
        img.onerror = () => showError('Could not read that image.');
        img.src = currentObjectUrl;
    }

    async function runPipeline(img) {
        try {
            let boxes = [];
            // Hook: ocr.js exposes scanImageForPII(img) once added.
            if (typeof window.scanImageForPII === 'function') {
                setProgress(25, 'Scanning for sensitive data…', 'Running OCR in your browser');
                let extra = [];
                if (window.CLOAK.isPro) {
                    try {
                        const r = await fetch('api/rules.php', { credentials: 'same-origin' });
                        if (r.ok) extra = (await r.json()).rules.filter((x) => x.is_active).map((x) => ({ name: x.rule_name, pattern: x.pattern }));
                    } catch { /* saved rules are optional */ }
                }
                boxes = await window.scanImageForPII(img, (p, msg) => setProgress(25 + p * 70, null, msg), extra);
            } else {
                setProgress(60, 'Preparing editor…', 'OCR engine not loaded yet');
            }
            if (cancelled) return;
            setProgress(100, 'Done', '');
            openEditor(img, boxes);
        } catch (err) {
            console.error(err);
            showError('Scanning failed: ' + (err && err.message ? err.message : 'unknown error'));
        }
    }

    function openEditor(img, boxes) {
        setState('editor');
        // Hook: editor.js exposes CloakEditor. Fallback just paints the image.
        if (typeof window.CloakEditor === 'function') {
            window.cloakEditor = new window.CloakEditor('editorCanvas', img);
            window.cloakEditor.init(boxes);
            window.cloakEditor.drawEnabled = $('toolDraw').checked;
            window.cloakEditor.onChange = (b) => renderBoxList(b);
        } else {
            const c = $('editorCanvas');
            c.width = img.naturalWidth; c.height = img.naturalHeight;
            c.getContext('2d').drawImage(img, 0, 0);
        }
        renderBoxList(boxes);
        $('freeNotice').classList.toggle('hidden', !!window.CLOAK.isPro);
    }

    function renderBoxList(boxes) {
        const list = $('boxList');
        list.innerHTML = '';
        $('boxCount').textContent = boxes.length;
        $('boxEmpty').classList.toggle('hidden', boxes.length > 0);
        boxes.forEach((b) => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2';
            const label = document.createElement('span');
            label.className = 'truncate font-mono text-xs';
            label.textContent = b.text; // textContent: OCR output is untrusted
            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.checked = !!b.redacted;
            cb.addEventListener('change', () => {
                b.redacted = cb.checked;
                if (window.cloakEditor) window.cloakEditor.redraw();
            });
            li.append(label, cb);
            list.appendChild(li);
        });
    }

    function reset() {
        cancelled = true;
        currentImage = null;
        $('fileInput').value = '';
        setState('idle');
    }

    // ---------- export ----------
    function exportDataUrl() {
        if (window.cloakEditor) return window.cloakEditor.exportImage(!!window.CLOAK.isPro);
        return $('editorCanvas').toDataURL('image/png');
    }
    function checkQuota() {
        if (window.CLOAK.isPro) return true;
        if (getUsage() >= window.CLOAK.freeDailyLimit) {
            toast('Daily free limit reached - upgrade for unlimited exports.');
            $('pricing').scrollIntoView({ behavior: 'smooth' });
            return false;
        }
        return true;
    }

    // ---------- events ----------
    // Global Ctrl+V / Cmd+V paste
    document.addEventListener('paste', (e) => {
        const tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' && e.target.type === 'text' || tag === 'TEXTAREA') return;
        const items = (e.clipboardData && e.clipboardData.items) || [];
        for (const it of items) {
            if (it.kind === 'file' && it.type.startsWith('image/')) {
                e.preventDefault();
                handleFile(it.getAsFile());
                return;
            }
        }
        toast('No image found in clipboard.');
    });

    const dz = $('dropzone');
    ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('dz-active'); }));
    ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('dz-active'); }));
    dz.addEventListener('drop', (e) => handleFile(e.dataTransfer.files && e.dataTransfer.files[0]));
    dz.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('fileInput').click(); } });
    // Prevent the browser from navigating to a file dropped outside the dropzone
    ['dragover', 'drop'].forEach((ev) => window.addEventListener(ev, (e) => e.preventDefault()));

    $('fileInput').addEventListener('change', (e) => handleFile(e.target.files[0]));
    $('cancelBtn').addEventListener('click', reset);
    $('retryBtn').addEventListener('click', reset);
    $('newBtn').addEventListener('click', reset);

    $('downloadBtn').addEventListener('click', () => {
        if (!checkQuota()) return;
        const a = document.createElement('a');
        a.href = exportDataUrl();
        a.download = 'cloakpaste-' + Date.now() + '.png';
        a.click();
        bumpUsage();
        // re-render without watermark baked into the editing canvas
        if (window.cloakEditor) window.cloakEditor.redraw();
    });

    $('copyBtn').addEventListener('click', async () => {
        if (!checkQuota()) return;
        try {
            const blob = await new Promise((res) => {
                const url = exportDataUrl();
                fetch(url).then((r) => r.blob()).then(res);
            });
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
            bumpUsage();
            toast('Copied to clipboard');
        } catch { toast('Copy failed - use Download instead.'); }
        if (window.cloakEditor) window.cloakEditor.redraw();
    });

    $('toolDraw').addEventListener('change', (e) => { if (window.cloakEditor) window.cloakEditor.drawEnabled = e.target.checked; });
    $('redactAllBtn').addEventListener('click', () => setAll(true));
    $('clearAllBtn').addEventListener('click', () => setAll(false));
    function setAll(v) {
        if (!window.cloakEditor) return;
        window.cloakEditor.boxes.forEach((b) => (b.redacted = v));
        window.cloakEditor.redraw();
        renderBoxList(window.cloakEditor.boxes);
    }

    // ---------- accounts ----------
    async function api(path, method = 'GET', body) {
        const r = await fetch(path, {
            method, credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CLOAK.csrf },
            body: body ? JSON.stringify(body) : undefined
        });
        let d = {};
        try { d = await r.json(); } catch {}
        if (!r.ok) throw new Error(d.error || 'Request failed (' + r.status + ')');
        return d;
    }
    const show = (el, msg) => { el.textContent = msg; el.classList.toggle('hidden', !msg); };
    document.querySelectorAll('dialog [data-close]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));
    document.querySelectorAll('dialog').forEach((d) => d.addEventListener('click', (e) => { if (e.target === d) d.close(); })); // backdrop click

    let authMode = 'login';
    function setAuthMode(mode) {
        authMode = mode;
        const reg = mode === 'register';
        $('authTitle').textContent = reg ? 'Create account' : 'Log in';
        $('authSubmit').textContent = reg ? 'Sign up' : 'Log in';
        $('authSwitchText').textContent = reg ? 'Already have an account?' : 'No account?';
        $('authSwitch').textContent = reg ? 'Log in' : 'Sign up';
        $('authPass').autocomplete = reg ? 'new-password' : 'current-password';
        $('authHint').classList.toggle('hidden', !reg);
        show($('authError'), '');
    }
    function openAuth(mode) { setAuthMode(mode); $('authDialog').showModal(); $('authEmail').focus(); }

    $('authSwitch').addEventListener('click', () => setAuthMode(authMode === 'login' ? 'register' : 'login'));
    $('authForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('authSubmit');
        btn.disabled = true; show($('authError'), '');
        try {
            await api('api/auth.php?action=' + authMode, 'POST', { email: $('authEmail').value, password: $('authPass').value });
            location.reload(); // refresh Pro state + CSRF token from the new session
        } catch (err) {
            show($('authError'), err.message);
            btn.disabled = false;
        }
    });

    $('loginBtn').addEventListener('click', async () => {
        if (!window.CLOAK.email) return openAuth('login');
        try { await api('api/auth.php?action=logout', 'POST'); } catch {}
        location.reload();
    });

    $('upgradeBtn').addEventListener('click', () => {
        if (!window.CLOAK.email) openAuth('register'); // same email must be used at checkout
        else $('pricing').scrollIntoView({ behavior: 'smooth' });
    });

    (function renderAccountUi() {
        if (!window.CLOAK.email) return;
        $('loginBtn').textContent = 'Log out';
        $('userEmail').textContent = window.CLOAK.email;
        $('userEmail').classList.remove('hidden');
        if (window.CLOAK.isPro) { $('rulesBtn').classList.remove('hidden'); $('upgradeBtn').classList.add('hidden'); }
    })();

    // ---------- buy buttons ----------
    document.querySelectorAll('[data-buy]').forEach((btn) => {
        const plan = btn.dataset.buy;
        const url = window.CLOAK.checkout[plan];
        const label = btn.textContent;
        if (window.CLOAK.plan === plan) { btn.disabled = true; btn.textContent = 'Your current plan'; return; }
        if (window.CLOAK.plan === 'lifetime') { btn.disabled = true; btn.textContent = 'Already Pro'; return; }
        if (!url) { btn.disabled = true; btn.textContent = 'Coming soon'; return; }
        btn.addEventListener('click', () => {
            // Pro is matched by email, so the buyer must have an account first.
            if (!window.CLOAK.email) { toast('Create an account first - Pro is linked to your email.'); return openAuth('register'); }
            const u = new URL(url);
            u.searchParams.set('checkout[email]', window.CLOAK.email); // prefill so checkout email matches the account
            location.href = u.toString();
        });
        btn.setAttribute('aria-label', label + ' - opens checkout');
    });

    // ---------- custom rules manager ----------
    function renderRules(rules) {
        const ul = $('rulesList');
        ul.innerHTML = '';
        $('rulesEmpty').classList.toggle('hidden', rules.length > 0);
        rules.forEach((r) => {
            const li = document.createElement('li');
            li.className = 'flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2';
            const info = document.createElement('div');
            info.className = 'min-w-0 flex-1';
            const n = document.createElement('p'); n.className = 'truncate font-medium'; n.textContent = r.rule_name;
            const p = document.createElement('p'); p.className = 'truncate font-mono text-xs text-slate-500'; p.textContent = r.pattern;
            info.append(n, p);

            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.checked = r.is_active; cb.title = 'Active'; cb.setAttribute('aria-label', 'Active');
            cb.addEventListener('change', async () => {
                try { await api('api/rules.php?id=' + r.id, 'PUT', { is_active: cb.checked }); }
                catch (err) { cb.checked = !cb.checked; show($('ruleError'), err.message); }
            });
            const del = document.createElement('button');
            del.type = 'button'; del.textContent = 'Delete'; del.className = 'text-xs text-red-600 hover:underline';
            del.addEventListener('click', async () => {
                try { await api('api/rules.php?id=' + r.id, 'DELETE'); loadRules(); }
                catch (err) { show($('ruleError'), err.message); }
            });
            li.append(info, cb, del);
            ul.appendChild(li);
        });
    }
    async function loadRules() {
        show($('ruleError'), '');
        try { renderRules((await api('api/rules.php')).rules); }
        catch (err) { show($('ruleError'), err.message); }
    }
    $('rulesBtn').addEventListener('click', () => { $('rulesDialog').showModal(); loadRules(); });

    // live regex test as the user types
    function updateRuleTest() {
        const t = $('ruleTest'), pat = $('rulePattern').value, sample = $('ruleSample').value;
        if (!pat) return t.classList.add('hidden');
        t.classList.remove('hidden');
        try {
            const re = new RegExp(pat, 'gi');
            if (!sample) { t.className = 'text-xs text-slate-500'; t.textContent = 'Valid pattern.'; return; }
            const m = [...sample.matchAll(re)].map((x) => x[0]).filter(Boolean);
            t.className = 'text-xs ' + (m.length ? 'text-emerald-600' : 'text-amber-600');
            t.textContent = m.length ? 'Would redact: ' + m.join(', ') : 'No match in sample text.';
        } catch (err) { t.className = 'text-xs text-red-600'; t.textContent = 'Invalid pattern: ' + err.message; }
    }
    $('rulePattern').addEventListener('input', updateRuleTest);
    $('ruleSample').addEventListener('input', updateRuleTest);

    $('ruleForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        show($('ruleError'), '');
        try {
            new RegExp($('rulePattern').value, 'gi');
            await api('api/rules.php', 'POST', { rule_name: $('ruleName').value, pattern: $('rulePattern').value });
            $('ruleName').value = ''; $('rulePattern').value = ''; $('ruleSample').value = '';
            updateRuleTest(); loadRules();
        } catch (err) { show($('ruleError'), err.message); }
    });

    renderUsage();
})();
</script>

<!-- Engine scripts (added in the next steps) -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js"></script>
<script src="assets/js/ocr.js"></script>
<script src="assets/js/editor.js"></script>
</body>
</html>
