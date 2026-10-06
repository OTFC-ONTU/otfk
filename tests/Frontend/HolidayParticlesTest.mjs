import { test } from 'node:test';
import assert from 'node:assert/strict';
import { startHolidayParticles } from '../../resources/js/holiday.js';

function environment({ reduced = false, context = true } = {}) {
    const originals = new Map();
    const replace = (name, value) => {
        originals.set(name, Object.getOwnPropertyDescriptor(globalThis, name));
        Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });
    };
    const listeners = new Map();
    const motionListeners = new Map();
    const docListeners = new Map();
    const positions = [];
    const ctx = new Proxy({ translate: (x, y) => positions.push([x, y]) }, {
        get: (target, key) => target[key] ?? (() => {}),
    });
    let nextFrame;
    let removed = false;
    let appended = 0;
    const stored = new Map();
    const canvas = { style: {}, setAttribute() {}, getContext: () => context ? ctx : null, remove: () => { removed = true; } };
    const body = { dataset: { holiday: 'new_year', holidayParticles: 'snow', holidayColors: '#fff' }, appendChild: () => appended++ };
    const motion = { matches: reduced, addEventListener: (k, f) => motionListeners.set(k, f), removeEventListener: k => motionListeners.delete(k) };
    replace('document', { body, hidden: false, createElement: () => canvas, addEventListener: (k, f) => docListeners.set(k, f), removeEventListener: k => docListeners.delete(k) });
    replace('window', { innerWidth: 800, innerHeight: 800, devicePixelRatio: 1, addEventListener: (k, f) => listeners.set(k, f), removeEventListener: k => listeners.delete(k) });
    replace('performance', { now: () => 0 });
    replace('matchMedia', () => motion);
    replace('sessionStorage', { getItem: k => stored.get(k), setItem: (k, v) => stored.set(k, v) });
    replace('requestAnimationFrame', f => { nextFrame = f; return 1; });
    replace('cancelAnimationFrame', () => { nextFrame = undefined; });
    const random = Math.random;
    Math.random = () => 0.5;
    return {
        start: () => startHolidayParticles(body),
        frame: t => { positions.length = 0; nextFrame(t); return positions[0]; },
        stopForMotion: () => motionListeners.get('change')(),
        state: () => ({ removed, appended, stored: stored.size, listeners: listeners.size + motionListeners.size + docListeners.size }),
        restore: () => {
            Math.random = random;
            for (const [name, descriptor] of originals) {
                if (descriptor) Object.defineProperty(globalThis, name, descriptor);
                else delete globalThis[name];
            }
        },
    };
}

test('snow travels the same distance at 60 and 120 Hz', () => {
    const finalY = hz => {
        const env = environment();
        try {
            env.start();
            let position;
            for (let i = 1; i <= hz; i++) position = env.frame(i * 1000 / hz);
            return position[1];
        } finally { env.restore(); }
    };
    assert.ok(Math.abs(finalY(60) - finalY(120)) < 0.001);
});

test('reduced motion does not create a canvas or consume the session effect', () => {
    const env = environment({ reduced: true });
    try { env.start(); assert.equal(env.state().appended, 0); assert.equal(env.state().stored, 0); }
    finally { env.restore(); }
});

test('unavailable canvas does not consume the session effect', () => {
    const env = environment({ context: false });
    try { env.start(); assert.equal(env.state().appended, 0); assert.equal(env.state().stored, 0); }
    finally { env.restore(); }
});

test('effect runs once per theme and cleans up after it finishes', () => {
    const env = environment();
    try {
        env.start(); env.start();
        assert.equal(env.state().appended, 1);
        env.frame(14000);
        assert.equal(env.state().removed, true);
        assert.equal(env.state().listeners, 0);
    } finally { env.restore(); }
});

test('changing motion preference stops the effect and releases listeners', () => {
    const env = environment();
    try { env.start(); env.stopForMotion(); assert.equal(env.state().removed, true); assert.equal(env.state().listeners, 0); }
    finally { env.restore(); }
});
