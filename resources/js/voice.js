/**
 * Shared "the concierge speaks" engine for the narrator and the chat.
 *
 * Two voices behind one call:
 *  - browser: the device's own speech synthesis (free, quality depends on the device);
 *  - cloud:   a TTS endpoint on our server (same quality everywhere, costs money), used
 *             only when the site has it switched on (data-tts-enabled on #concierge-app)
 *             and falling back to the browser voice if the request fails.
 * Speech only ever follows a guest's action, never starts on its own.
 */
const app = () => document.getElementById('concierge-app');

export const canBrowserSpeak = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;

export const cloudEnabled = () => app()?.dataset.ttsEnabled === '1' && !!app()?.dataset.ttsUrl;

export const voicesReady = canBrowserSpeak ? new Promise((resolve) => {
    const finish = (allowEmpty = false) => {
        if (window.speechSynthesis.getVoices().length || allowEmpty) {
            window.speechSynthesis.removeEventListener('voiceschanged', finish);
            resolve();
        }
    };

    window.speechSynthesis.addEventListener('voiceschanged', finish);
    finish();
    window.setTimeout(() => finish(true), 800);
}) : Promise.resolve();

export function voicesForLocale(lang) {
    if (!canBrowserSpeak) return [];

    const requested = lang.toLowerCase();
    const language = requested.split('-')[0];

    return window.speechSynthesis.getVoices()
        .filter((voice) => voice.lang?.toLowerCase().startsWith(language))
        .sort((first, second) => {
            const firstExact = first.lang.toLowerCase() === requested ? 1 : 0;
            const secondExact = second.lang.toLowerCase() === requested ? 1 : 0;

            return secondExact - firstExact;
        });
}

const FEMALE_NAMES = /\b(kyoko|ayumi|haruka|nanami|mizuki|yuna|sachiko|eiko|akari|hina|mai|female|woman|girl)\b/i;
const MALE_NAMES = /\b(otoya|ichiro|takumi|male|man)\b/i;
const voiceLabel = (voice) => `${voice.name} ${voice.voiceURI || ''}`;

export function pickVoice(lang, preference = 'female') {
    const voices = voicesForLocale(lang);
    if (!voices.length) return null;

    const preferred = voices.find((voice) => FEMALE_NAMES.test(voiceLabel(voice)));
    if (preference === 'female' && preferred) return preferred;

    return voices
        .map((voice, index) => {
            let score = (voice.lang.toLowerCase() === lang.toLowerCase() ? 30 : 0) + (voice.localService ? 4 : 0) - index;

            if (preference === 'female') {
                if (FEMALE_NAMES.test(voiceLabel(voice))) score += 100;
                if (MALE_NAMES.test(voiceLabel(voice))) score -= 100;
            }

            return { voice, score };
        })
        .sort((first, second) => second.score - first.score)[0].voice;
}

/** What a voice should read: no markdown, no links or image tags, one space between words. */
export function plainSpeech(text) {
    return String(text || '')
        .replace(/!\[[^\]]*\]\([^)]*\)/g, ' ')
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/https?:\/\/\S+/g, ' ')
        .replace(/[*_`#>]+/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Short pieces (browsers cut off or stall on very long utterances), split after sentence ends. */
export function speechChunks(text, max = 180) {
    const sentences = plainSpeech(text).match(/[^.!?。！？\n]+[.!?。！？]*\s*/g) || [];
    const chunks = [];
    let current = '';

    sentences.forEach((sentence) => {
        if ((current + sentence).length > max && current) {
            chunks.push(current.trim());
            current = '';
        }
        current += sentence;
        while (current.length > max * 1.5) {
            chunks.push(current.slice(0, max).trim());
            current = current.slice(max);
        }
    });
    if (current.trim()) chunks.push(current.trim());

    return chunks;
}

let token = 0;
let audio = null;
let objectUrl = null;
let unlocked = false;

// Silent 0.1 s WAV: playing it inside a tap lets iOS Safari play audio later, after the reply arrives.
const SILENCE = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';

function audioElement() {
    if (!audio) {
        audio = new Audio();
        audio.preload = 'auto';
    }

    return audio;
}

function releaseAudio() {
    if (audio) {
        audio.pause();
        audio.onended = audio.onerror = null;
    }
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
}

/** Call from a tap/click handler once: lets later (async) speech start on iPhone/Safari. */
export function unlock() {
    if (unlocked) return;
    unlocked = true;

    try {
        if (canBrowserSpeak) {
            const silent = new SpeechSynthesisUtterance(' ');
            silent.volume = 0;
            window.speechSynthesis.speak(silent);
        }
        if (cloudEnabled()) {
            const element = audioElement();
            element.src = SILENCE;
            element.play().catch(() => {});
        }
    } catch {
        // unlocking is best effort
    }
}

export function stop() {
    token += 1;
    releaseAudio();
    if (canBrowserSpeak) window.speechSynthesis.cancel();
}

async function speakBrowser(text, { lang, preference }, mine, done) {
    await voicesReady;
    if (mine !== token) return;

    const chunks = speechChunks(text);
    if (!chunks.length) return done();

    const voice = pickVoice(lang, preference);
    window.speechSynthesis.cancel();

    chunks.forEach((chunk, index) => {
        const utterance = new SpeechSynthesisUtterance(chunk);
        utterance.lang = lang;
        if (voice) utterance.voice = voice;
        if (lang === 'ja-JP' && (!voice || MALE_NAMES.test(voiceLabel(voice)))) utterance.pitch = 1.12;

        if (index === chunks.length - 1) {
            utterance.onend = utterance.onerror = () => {
                if (mine === token) done();
            };
        }

        window.speechSynthesis.speak(utterance);
    });
}

async function speakCloud(text, options, mine, done, fallback) {
    const root = app();
    const clientToken = localStorage.getItem(root.dataset.storageKey) || '';
    if (!clientToken) throw new Error('tts_no_conversation');

    const response = await fetch(root.dataset.ttsUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'audio/mpeg',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ text: plainSpeech(text), client_token: clientToken, locale: root.dataset.locale }),
        signal: AbortSignal.timeout(25000),
    });
    if (!response.ok) throw new Error(`tts_${response.status}`);

    const blob = await response.blob();
    if (mine !== token) return;

    releaseAudio();
    objectUrl = URL.createObjectURL(blob);
    const element = audioElement();
    element.src = objectUrl;
    element.onended = () => {
        if (mine === token) done();
    };
    element.onerror = () => {
        if (mine === token) fallback();
    };
    await element.play();
}

/**
 * Reads `text` aloud. options: { lang: 'ja-JP', preference: 'female', cloud: false to force the device voice, onstart, onend }.
 * Whoever calls speak() again (or stop()) cancels the previous speech; onend fires once, only for the current speech.
 */
export function speak(text, options = {}) {
    stop();
    token += 1;
    const mine = token;
    let finished = false;
    const done = () => {
        if (finished || mine !== token) return;
        finished = true;
        options.onend?.();
    };

    options.onstart?.();

    const browser = () => speakBrowser(text, options, mine, done).catch(done);

    if (cloudEnabled() && options.cloud !== false) {
        // The cloud voice failed (quota, network, blocked playback): the device voice still works.
        const fallback = () => {
            if (mine !== token) return;
            releaseAudio();
            if (canBrowserSpeak) browser();
            else done();
        };

        speakCloud(text, options, mine, done, fallback).catch(fallback);
    } else if (canBrowserSpeak) {
        browser();
    } else {
        done();
    }
}

/** True when something can speak this language: a cloud voice, or a device voice for it. */
export async function canSpeakLocale(lang) {
    if (cloudEnabled()) return true;
    if (!canBrowserSpeak) return false;
    await voicesReady;

    return voicesForLocale(lang).length > 0;
}

window.addEventListener('pagehide', stop);
