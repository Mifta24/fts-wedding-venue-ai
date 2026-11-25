import { isSpeaking, stop as stopVoice } from './voice';
import { interpret, perform, stepPage, closeTop, toast } from './voice-nav';

/**
 * Header microphone: voice navigation for every menu. Listens continuously (until tapped again, hidden tab,
 * or two minutes of silence), runs spoken menu/language/scroll/step commands at once, and hands anything
 * else to the AI concierge. Remembered per visit, so it carries on after a page change where the browser
 * already allows the microphone.
 */
const KEY = 'venue_navmic';
const IDLE_MS = 120000;
const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const LOCALES = { id: 'id-ID', en: 'en-US', ja: 'ja-JP', th: 'th-TH', zh: 'zh-TW' };

function initNavMic() {
    const button = document.querySelector('[data-nav-mic]');
    if (!button || !Recognition) return;

    const locale = document.documentElement.lang?.slice(0, 2) || 'en';
    const labels = { opening: button.dataset.opening, here: button.dataset.here };
    let session = null;
    let on = false;
    let lastActivity = Date.now();
    let fastFails = 0;

    button.hidden = false;

    const render = () => {
        button.classList.toggle('is-listening', on);
        button.setAttribute('aria-pressed', String(on));
        const label = on ? button.dataset.labelOn : button.dataset.label;
        button.setAttribute('aria-label', label);
        button.title = label;
    };

    const remember = (value) => {
        try {
            if (value) sessionStorage.setItem(KEY, 'on');
            else sessionStorage.removeItem(KEY);
        } catch { /* only a convenience */ }
    };

    function stop({ forget = true } = {}) {
        on = false;
        if (forget) remember(false);
        session?.abort();
        session = null;
        render();
    }

    function handle(text, { whileSpeaking = false } = {}) {
        lastActivity = Date.now();
        const action = interpret(text);

        // While the concierge is talking, only an outright switch (menu, language, stop) counts: anything else
        // is most likely its own voice coming back through the microphone.
        if (whileSpeaking && !['menu', 'lang', 'hangup'].includes(action?.type)) return;
        if (whileSpeaking && action?.type !== 'hangup') stopVoice();

        if (action?.type === 'hangup') {
            stop();
            return;
        }

        // Steps and closing work on whatever is open: photos, booking steps, the next hall.
        const spaced = text.toLowerCase().replace(/[.,!?。、，！？]/g, ' ').replace(/\s+/g, ' ').trim();
        if (/^(?:next|forward|selanjutnya|berikutnya|lanjut|次|つぎ|次へ|ถัดไป|ต่อไป|下一[張张個个頁页]?)$/.test(spaced) && stepPage(1)) return;
        if (/^(?:previous|sebelumnya|前|まえ|前へ|ก่อนหน้า|上一[張张個个頁页]?)$/.test(spaced) && stepPage(-1)) return;
        if (/^(?:close|tutup|閉じて|閉じる|ปิด|關閉|关闭)$/.test(spaced) && closeTop()) return;

        if (action && perform(action, labels)) return;

        // Not a navigation command: the concierge answers it.
        if (spaced.length > 1) window.dispatchEvent(new CustomEvent('venue:voice-ask', { detail: { message: text.trim() } }));
        else toast(button.dataset.hint);
    }

    function listen() {
        if (!on || session) return;

        // The chat's own microphone has the floor while it listens. The concierge's voice does not block us:
        // a guest can switch menu while it is still talking (see handle()).
        if (document.querySelector('.chat-mic.is-listening, [data-chat-call].is-calling')) {
            window.setTimeout(listen, 600);
            return;
        }

        const current = new Recognition();
        const startedAt = Date.now();
        let transcript = '';
        let failure = null;
        const startedWhileSpeaking = isSpeaking();

        current.lang = LOCALES[locale] || 'en-US';
        current.interimResults = false;
        current.continuous = false;
        current.maxAlternatives = 1;
        current.onresult = (event) => {
            transcript = Array.from(event.results).map((result) => result[0].transcript).join('').trim();
        };
        current.onerror = (event) => { failure = event.error; };
        current.onend = () => {
            if (session !== current) return;
            session = null;

            if (failure === 'not-allowed' || failure === 'service-not-allowed') {
                stop();
                toast(document.getElementById('concierge-app')?.dataset.labelMicDenied);
                return;
            }

            if (transcript) {
                fastFails = 0;
                handle(transcript, { whileSpeaking: startedWhileSpeaking || isSpeaking() });
            } else {
                fastFails = Date.now() - startedAt < 1500 ? fastFails + 1 : 0;
            }

            if (!on) return;
            if (fastFails >= 3 || Date.now() - lastActivity >= IDLE_MS) {
                stop();
                return;
            }
            window.setTimeout(listen, 300);
        };

        session = current;
        try {
            current.start();
        } catch {
            session = null;
            stop();
        }
    }

    function start() {
        if (on) return;
        on = true;
        lastActivity = Date.now();
        fastFails = 0;
        remember(true);
        render();
        window.dispatchEvent(new CustomEvent('venue:navmic-start'));
        listen();
    }

    button.addEventListener('click', () => (on ? stop() : start()));

    // The chat's own microphone and the nav mic never listen at once.
    window.addEventListener('venue:chat-mic-start', () => { if (on) stop({ forget: false }); });

    document.addEventListener('visibilitychange', () => {
        if (!on) return;
        if (document.hidden) {
            session?.abort();
            session = null;
        } else {
            lastActivity = Date.now();
            listen();
        }
    });
    window.addEventListener('pagehide', () => { session?.abort(); });

    // Carry on after a page change where the microphone is already allowed (Safari has no permission API: tap again).
    (async () => {
        let wanted = false;
        try { wanted = sessionStorage.getItem(KEY) === 'on'; } catch { wanted = false; }
        if (!wanted || !navigator.permissions?.query) return;

        try {
            const status = await navigator.permissions.query({ name: 'microphone' });
            if (status.state === 'granted') start();
        } catch { /* tap again */ }
    })();

    render();
}

document.addEventListener('DOMContentLoaded', initNavMic);
