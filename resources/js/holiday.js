// Святкові частинки (App\Support\HolidayTheme): легкий canvas-«снігопад»,
// що грає ~10 с один раз за сесію для кожної теми й плавно зникає.
// Вимкнено при prefers-reduced-motion. Тип і кольори — з data-атрибутів <body>.

const GLYPHS = ['0', '1', '{ }', '</>', ';', '=>'];

const rand = (a, b) => a + Math.random() * (b - a);

function makeParticle(type, colors, w, h, initial) {
    const p = {
        x: rand(0, w),
        y: initial ? rand(-h * 0.3, h * 0.75) : rand(-60, -10),
        age: 0,
        size: rand(5, 11),
        color: colors[Math.floor(Math.random() * colors.length)],
        vy: rand(0.6, 1.4),
        vx: rand(-0.3, 0.3),
        rot: rand(0, Math.PI * 2),
        vr: rand(-0.03, 0.03),
        phase: rand(0, Math.PI * 2),
        alpha: rand(0.4, 0.7),
    };
    if (type === 'snow') { p.size = rand(5, 12); p.vy = rand(0.5, 1.2); }
    if (type === 'leaves') { p.size = rand(9, 16); p.vy = rand(0.8, 1.5); p.vr = rand(-0.04, 0.04); }
    if (type === 'petals') { p.size = rand(6, 10); p.vy = rand(0.7, 1.3); }
    if (type === 'confetti') { p.size = rand(5, 9); p.vy = rand(1.2, 2.2); p.vr = rand(-0.12, 0.12); }
    if (type === 'code') { p.size = rand(11, 16); p.vy = rand(1, 1.8); p.glyph = GLYPHS[Math.floor(Math.random() * GLYPHS.length)]; p.alpha = rand(0.35, 0.6); }
    if (type === 'sparks') { p.size = rand(3, 7); p.vy = rand(0.5, 1); }
    return p;
}

function draw(ctx, type, p, t) {
    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.globalAlpha = p.alpha * Math.min(1, p.age / 45); // плавна поява
    ctx.fillStyle = p.color;
    ctx.strokeStyle = p.color;

    if (type === 'snow') {
        // Шестипроменева сніжинка з «гілочками»
        ctx.rotate(p.rot);
        ctx.lineWidth = 1.6;
        ctx.lineCap = 'round';
        for (let i = 0; i < 6; i++) {
            ctx.rotate(Math.PI / 3);
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -p.size);
            ctx.moveTo(0, -p.size * 0.55);
            ctx.lineTo(-p.size * 0.25, -p.size * 0.8);
            ctx.moveTo(0, -p.size * 0.55);
            ctx.lineTo(p.size * 0.25, -p.size * 0.8);
            ctx.stroke();
        }
    } else if (type === 'leaves') {
        // Листок із прожилкою, що перекидається в падінні
        ctx.rotate(p.rot);
        ctx.scale(1, Math.cos(t * 0.002 + p.phase) * 0.6 + 0.4);
        ctx.beginPath();
        ctx.moveTo(0, -p.size);
        ctx.quadraticCurveTo(p.size * 0.7, 0, 0, p.size);
        ctx.quadraticCurveTo(-p.size * 0.7, 0, 0, -p.size);
        ctx.fill();
        ctx.globalAlpha *= 0.5;
        ctx.strokeStyle = 'rgba(0,0,0,.35)';
        ctx.lineWidth = 0.8;
        ctx.beginPath();
        ctx.moveTo(0, -p.size * 0.8);
        ctx.lineTo(0, p.size);
        ctx.stroke();
    } else if (type === 'petals') {
        ctx.rotate(p.rot);
        ctx.scale(Math.cos(t * 0.003 + p.phase), 1);
        ctx.beginPath();
        ctx.ellipse(0, 0, p.size * 0.5, p.size, 0, 0, Math.PI * 2);
        ctx.fill();
    } else if (type === 'confetti') {
        ctx.rotate(p.rot);
        ctx.scale(1, Math.cos(t * 0.006 + p.phase));
        ctx.fillRect(-p.size / 2, -p.size * 0.35, p.size, p.size * 0.7);
    } else if (type === 'code') {
        ctx.font = `600 ${p.size}px ui-monospace, SFMono-Regular, Menlo, monospace`;
        ctx.textAlign = 'center';
        ctx.fillText(p.glyph, 0, 0);
    } else if (type === 'sparks') {
        // Чотирипроменева іскра, що мерехтить
        ctx.globalAlpha = p.alpha * Math.min(1, p.age / 45) * (0.55 + 0.45 * Math.sin(t * 0.008 + p.phase));
        const s = p.size;
        ctx.beginPath();
        ctx.moveTo(0, -s * 2);
        ctx.quadraticCurveTo(0, 0, s * 2, 0);
        ctx.quadraticCurveTo(0, 0, 0, s * 2);
        ctx.quadraticCurveTo(0, 0, -s * 2, 0);
        ctx.quadraticCurveTo(0, 0, 0, -s * 2);
        ctx.fill();
    }
    ctx.restore();
}

export function startHolidayParticles(body = document.body) {
    const theme = body.dataset.holiday;
    const type = body.dataset.holidayParticles;
    const colors = (body.dataset.holidayColors || '').split(',').filter(Boolean);
    if (!theme || !type || !colors.length) return;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    if (motion.matches || document.hidden) return;

    const key = 'hd-shown-' + theme;
    try {
        if (sessionStorage.getItem(key)) return;
    } catch (e) { /* приватний режим — просто граємо */ }

    const canvas = document.createElement('canvas');
    canvas.className = 'holiday-particles';
    canvas.setAttribute('aria-hidden', 'true');
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    body.appendChild(canvas);
    try { sessionStorage.setItem(key, '1'); } catch (e) { /* приватний режим */ }
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    let w = 0;
    let h = 0;
    const resize = () => {
        w = window.innerWidth;
        h = window.innerHeight;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener('resize', resize);

    const count = Math.round(Math.min(50, Math.max(12, w / 30)));
    const particles = Array.from({ length: count }, () => makeParticle(type, colors, w, h, true));
    const spawnUntil = performance.now() + 9000;
    let fading = false;
    let previous = performance.now();
    let frameId;
    const stop = () => {
        cancelAnimationFrame(frameId);
        window.removeEventListener('resize', resize);
        motion.removeEventListener('change', stop);
        document.removeEventListener('visibilitychange', onVisibility);
        canvas.remove();
    };
    const onVisibility = () => { if (document.hidden) stop(); };
    motion.addEventListener('change', stop);
    document.addEventListener('visibilitychange', onVisibility);

    const frame = (t) => {
        // Швидкість однакова на екранах 60/120/144 Гц; довгу паузу не наздоганяємо.
        const step = Math.min(3, Math.max(0, (t - previous) / (1000 / 60)));
        previous = t;
        ctx.clearRect(0, 0, w, h);
        let alive = 0;
        for (const p of particles) {
            if (p.dead) continue;
            p.age += step;
            p.y += p.vy * 1.6 * step;
            p.x += (p.vx + Math.sin(t * 0.0012 + p.phase) * 0.4) * step;
            p.rot += p.vr * step;
            if (p.y > h + 30) {
                if (t < spawnUntil) Object.assign(p, makeParticle(type, colors, w, h, false));
                else { p.dead = true; continue; }
            }
            alive++;
            draw(ctx, type, p, t);
        }
        // Після «залпу» — плавне згасання й прибирання полотна
        if (!fading && t > spawnUntil + 2500) {
            fading = true;
            canvas.style.opacity = '0';
        }
        if (alive > 0 && t < spawnUntil + 4200) {
            frameId = requestAnimationFrame(frame);
        } else {
            stop();
        }
    };
    frameId = requestAnimationFrame(frame);
}
