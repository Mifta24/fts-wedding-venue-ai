/**
 * Small UI sounds, synthesised with the Web Audio API (no audio files), in a wedding mood:
 * a harp glissando ending on a wedding bell when moving between chapters, a soft harp pluck with
 * a bright bell for incoming messages and a single petal-soft pluck when sending or toggling.
 * Sound only ever follows a couple's action, never plays on its own, and can be muted with the
 * header toggle (remembered per browser).
 */
const STORAGE_KEY = 'venue_sound';

let context = null;
let master = null;

function isEnabled() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

function ensureContext() {
    if (!context) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return null;

        context = new AudioContextClass();
        master = context.createGain();
        master.gain.value = 0.6;
        master.connect(context.destination);
    }

    if (context.state === 'suspended') context.resume().catch(() => {});

    return context;
}

function tone(frequency, start, duration, gain, type = 'sine') {
    const oscillator = context.createOscillator();
    const amp = context.createGain();

    oscillator.type = type;
    oscillator.frequency.value = frequency;
    amp.gain.setValueAtTime(0.0001, start);
    amp.gain.exponentialRampToValueAtTime(gain, start + 0.012);
    amp.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(amp).connect(master);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.05);
}

/** A struck-bell voice: the note plus a quieter, shorter inharmonic partial. */
function bell(frequency, start, gain, duration) {
    tone(frequency, start, duration, gain);
    tone(frequency * 2.76, start, duration * 0.45, gain * 0.28);
}

/** A plucked harp string: a soft triangle with a quieter octave that fades sooner. */
function pluck(frequency, start, gain, duration) {
    tone(frequency, start, duration, gain, 'triangle');
    tone(frequency * 2, start, duration * 0.5, gain * 0.25);
}

/** A wedding bell: a low strike tone ringing under the bell, so it sounds fuller and warmer. */
function weddingBell(frequency, start, gain, duration) {
    bell(frequency, start, gain, duration);
    tone(frequency / 2, start, duration * 0.6, gain * 0.35);
}

const sounds = {
    /** Chapter change: a quick harp glissando up a C major chord, landing on a wedding bell. */
    enter(now) {
        [523.25, 659.25, 783.99, 1046.5, 1318.51].forEach((note, index) => pluck(note, now + index * 0.05, 0.07, 0.5));
        weddingBell(1046.5, now + 0.22, 0.12, 1.4);
        weddingBell(783.99, now + 0.46, 0.09, 1.4);
    },
    /** A message from the concierge: a harp pluck answered by a bright bell. */
    incoming(now) {
        pluck(1046.5, now, 0.09, 0.45);
        bell(1567.98, now + 0.09, 0.07, 0.6);
    },
    /** Sending or toggling: one short, petal-soft pluck. */
    sent(now) {
        pluck(880, now, 0.12, 0.22);
    },
};

function play(name) {
    if (!isEnabled() || !sounds[name]) return;

    try {
        const audio = ensureContext();
        if (audio) sounds[name](audio.currentTime + 0.02);
    } catch {
        /* audio unavailable: the interface works the same without it */
    }
}

function syncToggles() {
    const enabled = isEnabled();

    document.querySelectorAll('[data-sound-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(enabled));
        button.setAttribute('aria-label', enabled ? button.dataset.labelOn : button.dataset.labelOff);
        button.classList.toggle('is-muted', !enabled);
    });
}

function initSoundToggle() {
    syncToggles();

    document.querySelectorAll('[data-sound-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            try {
                localStorage.setItem(STORAGE_KEY, isEnabled() ? 'off' : 'on');
            } catch { /* preference just won't persist */ }

            syncToggles();
            play('sent');
        });
    });
}

window.venueSound = { play, isEnabled };

document.addEventListener('DOMContentLoaded', initSoundToggle);
