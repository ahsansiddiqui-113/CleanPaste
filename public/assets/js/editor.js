// CloakPaste - canvas redaction editor. All rendering is local.

class CloakEditor {
    constructor(canvasId, imgElement) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.image = imgElement;
        this.boxes = [];
        this.drawEnabled = true;
        this.onChange = null;   // called when boxes are added/toggled by the user
        this.drag = null;       // {x0,y0,x1,y1} in image px while drawing

        // Replace canvas to drop listeners from any previous editor instance.
        const fresh = this.canvas.cloneNode(false);
        this.canvas.replaceWith(fresh);
        this.canvas = fresh;
        this.ctx = fresh.getContext('2d');

        this._bind();
    }

    init(boxes) {
        this.canvas.width = this.image.naturalWidth;
        this.canvas.height = this.image.naturalHeight;
        this.boxes = boxes;
        this.redraw();
    }

    get pad() { return Math.max(3, Math.round(Math.min(this.canvas.width, this.canvas.height) / 300)); }

    _renderTo(ctx, { preview }) {
        const p = this.pad;
        ctx.drawImage(this.image, 0, 0);
        for (const b of this.boxes) {
            if (b.redacted) {
                ctx.fillStyle = '#000';
                ctx.fillRect(b.x - p, b.y - p, b.width + p * 2, b.height + p * 2);
            } else if (preview) {
                ctx.save();
                ctx.strokeStyle = '#f59e0b';
                ctx.lineWidth = Math.max(2, p / 2);
                ctx.setLineDash([p * 2, p * 2]);
                ctx.strokeRect(b.x - p, b.y - p, b.width + p * 2, b.height + p * 2);
                ctx.restore();
            }
        }
        if (preview && this.drag) {
            const d = this.drag;
            ctx.save();
            ctx.fillStyle = 'rgba(79,70,229,0.25)';
            ctx.strokeStyle = '#4f46e5';
            ctx.lineWidth = Math.max(2, p / 2);
            const x = Math.min(d.x0, d.x1), y = Math.min(d.y0, d.y1);
            ctx.fillRect(x, y, Math.abs(d.x1 - d.x0), Math.abs(d.y1 - d.y0));
            ctx.strokeRect(x, y, Math.abs(d.x1 - d.x0), Math.abs(d.y1 - d.y0));
            ctx.restore();
        }
    }

    redraw() { this._renderTo(this.ctx, { preview: true }); }

    // Renders on an offscreen canvas so the editing view is never altered.
    // Re-encoding via canvas drops EXIF/GPS and any other embedded metadata.
    exportCanvas(isProUser) {
        const out = document.createElement('canvas');
        out.width = this.canvas.width;
        out.height = this.canvas.height;
        const ctx = out.getContext('2d');
        this._renderTo(ctx, { preview: false });

        if (!isProUser) {
            const s = Math.max(1, out.width / 1000);
            const fontPx = Math.round(16 * s), padX = 10 * s, h = 30 * s;
            ctx.font = `bold ${fontPx}px Arial, sans-serif`;
            const label = 'Protected by CloakPaste';
            const w = ctx.measureText(label).width + padX * 2;
            const x = out.width - w - 12 * s, y = out.height - h - 12 * s;
            ctx.fillStyle = 'rgba(255,255,255,0.85)';
            ctx.fillRect(x, y, w, h);
            ctx.fillStyle = '#111827';
            ctx.textBaseline = 'middle';
            ctx.fillText(label, x + padX, y + h / 2);
        }
        return out;
    }

    exportImage(isProUser) {
        return this.exportCanvas(isProUser).toDataURL('image/png');
    }

    // ---- pointer interaction -------------------------------------------
    _pt(e) {
        const r = this.canvas.getBoundingClientRect();
        return {
            x: (e.clientX - r.left) * (this.canvas.width / r.width),
            y: (e.clientY - r.top) * (this.canvas.height / r.height)
        };
    }

    _hit(pt) {
        const p = this.pad;
        for (let i = this.boxes.length - 1; i >= 0; i--) {
            const b = this.boxes[i];
            if (pt.x >= b.x - p && pt.x <= b.x + b.width + p && pt.y >= b.y - p && pt.y <= b.y + b.height + p) return b;
        }
        return null;
    }

    _bind() {
        const c = this.canvas;
        let start = null, moved = false;

        c.addEventListener('pointerdown', (e) => {
            c.setPointerCapture(e.pointerId);
            start = this._pt(e); moved = false;
        });

        c.addEventListener('pointermove', (e) => {
            if (!start) return;
            const pt = this._pt(e);
            const scale = this.canvas.width / c.getBoundingClientRect().width;
            if (!moved && Math.hypot(pt.x - start.x, pt.y - start.y) / scale < 5) return;
            if (!this.drawEnabled) return;
            moved = true;
            this.drag = { x0: start.x, y0: start.y, x1: pt.x, y1: pt.y };
            this.redraw();
        });

        const finish = (e) => {
            if (!start) return;
            const pt = this._pt(e);
            if (moved && this.drag) {
                const d = this.drag;
                const w = Math.abs(d.x1 - d.x0), h = Math.abs(d.y1 - d.y0);
                this.drag = null;
                if (w > 4 && h > 4) {
                    this.boxes.push({ x: Math.min(d.x0, d.x1), y: Math.min(d.y0, d.y1), width: w, height: h, text: 'Manual area', type: 'manual', redacted: true });
                }
            } else {
                const b = this._hit(pt);
                if (b) b.redacted = !b.redacted;
            }
            start = null; moved = false; this.drag = null;
            this.redraw();
            if (this.onChange) this.onChange(this.boxes);
        };
        c.addEventListener('pointerup', finish);
        c.addEventListener('pointercancel', () => { start = null; this.drag = null; this.redraw(); });
    }
}

window.CloakEditor = CloakEditor;
