/**
 * Generates the PWA icon set from the KV brand mark (see
 * resources/views/components/kv-icon.blade.php — teal gradient tile,
 * white K-with-check strokes) into public/icons/.
 *
 * Pure Node (zlib + hand-rolled PNG encoder) — no dependencies.
 * Run: node tools/generate-pwa-icons.js
 */
const zlib = require('zlib');
const fs = require('fs');
const path = require('path');

// ---------- minimal PNG encoder ----------
const crcTable = (() => {
    const t = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
        t[n] = c >>> 0;
    }
    return t;
})();
function crc32(buf) {
    let c = 0xFFFFFFFF;
    for (const b of buf) c = crcTable[(c ^ b) & 0xFF] ^ (c >>> 8);
    return (c ^ 0xFFFFFFFF) >>> 0;
}
function chunk(type, data) {
    const t = Buffer.from(type, 'ascii');
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(Buffer.concat([t, data])));
    return Buffer.concat([len, t, data, crc]);
}
function encodePng(w, h, rgba) {
    const stride = w * 4 + 1;
    const raw = Buffer.alloc(h * stride);
    for (let y = 0; y < h; y++) {
        raw[y * stride] = 0;
        rgba.copy(raw, y * stride + 1, y * w * 4, (y + 1) * w * 4);
    }
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(w, 0);
    ihdr.writeUInt32BE(h, 4);
    ihdr[8] = 8;  // bit depth
    ihdr[9] = 6;  // RGBA
    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        chunk('IHDR', ihdr),
        chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

// ---------- KV mark rasterizer (48×48 viewBox, same geometry as kv-icon) ----------
const C1 = [0x0d, 0x94, 0x88]; // #0d9488
const C2 = [0x0e, 0x74, 0x90]; // #0e7490
const SEGS = [
    [13, 12.5, 13, 35.5],        // K stem
    [24.5, 12.5, 13.8, 24.6],    // K upper arm
    [13.8, 24.6, 20.5, 34.5],    // K lower leg → check
    [20.5, 34.5, 34, 14.5],      // check tip
];
const STROKE_R = 2; // stroke-width 4 / 2
const CORNER_R = 12;

function distSeg(px, py, ax, ay, bx, by) {
    const dx = bx - ax, dy = by - ay;
    const l2 = dx * dx + dy * dy;
    let t = l2 ? ((px - ax) * dx + (py - ay) * dy) / l2 : 0;
    t = Math.max(0, Math.min(1, t));
    return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
}

function render(size, { rounded = true, contentScale = 1 } = {}) {
    const out = Buffer.alloc(size * size * 4);
    const SUB = 3, N = SUB * SUB; // 3×3 supersampling for clean edges
    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            let bgCov = 0, whiteCov = 0, r = 0, g = 0, b = 0;
            for (let sy = 0; sy < SUB; sy++) {
                for (let sx = 0; sx < SUB; sx++) {
                    const vx = (x + (sx + 0.5) / SUB) / size * 48;
                    const vy = (y + (sy + 0.5) / SUB) / size * 48;
                    if (vx < 0 || vx > 48 || vy < 0 || vy > 48) continue;
                    let inside = true;
                    if (rounded) {
                        const cx = Math.min(Math.max(vx, CORNER_R), 48 - CORNER_R);
                        const cy = Math.min(Math.max(vy, CORNER_R), 48 - CORNER_R);
                        inside = Math.hypot(vx - cx, vy - cy) <= CORNER_R;
                    }
                    if (!inside) continue;
                    bgCov++;
                    const t = (vx + vy) / 96; // diagonal gradient (0,0)→(48,48)
                    r += C1[0] + (C2[0] - C1[0]) * t;
                    g += C1[1] + (C2[1] - C1[1]) * t;
                    b += C1[2] + (C2[2] - C1[2]) * t;
                    const ox = 24 + (vx - 24) / contentScale;
                    const oy = 24 + (vy - 24) / contentScale;
                    if (SEGS.some(s => distSeg(ox, oy, s[0], s[1], s[2], s[3]) <= STROKE_R)) whiteCov++;
                }
            }
            if (!bgCov) continue;
            const i = (y * size + x) * 4;
            const w = whiteCov / bgCov;
            out[i] = Math.round((r / bgCov) + (255 - r / bgCov) * w);
            out[i + 1] = Math.round((g / bgCov) + (255 - g / bgCov) * w);
            out[i + 2] = Math.round((b / bgCov) + (255 - b / bgCov) * w);
            out[i + 3] = Math.round(255 * (bgCov / N));
        }
    }
    return out;
}

const dir = path.join(__dirname, '..', 'public', 'icons');
fs.mkdirSync(dir, { recursive: true });

const jobs = [
    ['icon-192.png', 192, { rounded: true }],
    ['icon-512.png', 512, { rounded: true }],
    // Maskable: full-bleed tile, mark shrunk into the 80% safe zone.
    ['icon-maskable-192.png', 192, { rounded: false, contentScale: 0.62 }],
    ['icon-maskable-512.png', 512, { rounded: false, contentScale: 0.62 }],
    // iOS applies its own rounding — full-bleed opaque tile.
    ['apple-touch-icon.png', 180, { rounded: false, contentScale: 0.62 }],
];

for (const [name, size, opts] of jobs) {
    fs.writeFileSync(path.join(dir, name), encodePng(size, size, render(size, opts)));
    console.log(`wrote public/icons/${name} (${size}×${size})`);
}
