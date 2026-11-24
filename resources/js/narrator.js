import { canSpeakLocale, speak as say, stop as stopVoice, unlock } from './voice';

/**
 * The AI concierge introducing a scene: types the intro like speech, animates
 * the avatar and sound bars while "speaking", and can read it aloud with the
 * browser's speech synthesis (or the site's cloud voice, see voice.js) when the client asks (never automatically).
 * The full text is always in the DOM for screen readers and no-JS visitors.
 */
function initNarrators() {
    const narrators = [...document.querySelectorAll('[data-narrator]')];
    if (!narrators.length) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const played = new Set();
    const states = new Map();

    const fullText = (element) => element.querySelector('[data-narrator-text]').dataset.text;
    const isVisible = (element) => element.getClientRects().length > 0;

    /**
     * The line is split into the part already "said" and the part still to come.
     * The unsaid part stays in the layout (just transparent), so the bubble
     * keeps its final size instead of growing and pushing things around.
     */
    function textParts(element) {
        const target = element.querySelector('[data-narrator-text]');
        let typed = target.querySelector('.narrator-typed');
        let rest = target.querySelector('.narrator-rest');

        if (!typed || !rest) {
            target.textContent = '';
            typed = document.createElement('span');
            typed.className = 'narrator-typed';
            rest = document.createElement('span');
            rest.className = 'narrator-rest';
            target.append(typed, rest);
        }

        return { typed, rest };
    }

    function setProgress(element, characters, count) {
        const { typed, rest } = textParts(element);
        typed.textContent = characters.slice(0, count).join('');
        rest.textContent = characters.slice(count).join('');
    }

    function reveal(element, { finished = true } = {}) {
        const state = states.get(element);
        if (state?.timer) window.clearTimeout(state.timer);
        if (state) state.timer = null;

        setProgress(element, [...fullText(element)], Infinity);
        if (finished && !state?.speaking) element.classList.remove('is-speaking');
    }

    function stopSpeech(element) {
        const state = states.get(element);
        if (!state?.speaking) return;

        state.speaking = false;
        stopVoice();
        const button = element.querySelector('[data-narrator-listen]');
        button.textContent = element.dataset.listenLabel;
        if (!state.timer) element.classList.remove('is-speaking');
    }

    function halt(element) {
        stopSpeech(element);
        reveal(element);
    }

    function type(element) {
        const characters = [...fullText(element)];
        let index = 0;

        element.classList.add('is-speaking');
        setProgress(element, characters, 0);

        const step = () => {
            index += 1;
            setProgress(element, characters, index);

            const state = states.get(element);
            if (index >= characters.length) {
                state.timer = null;
                if (!state.speaking) element.classList.remove('is-speaking');
                return;
            }

            const pause = /[.!?。！？]/.test(characters[index - 1]) ? 130 : /[,、]/.test(characters[index - 1]) ? 65 : 15;
            state.timer = window.setTimeout(step, pause);
        };

        states.get(element).timer = window.setTimeout(step, 180);
    }

    function speak(element) {
        const state = states.get(element);
        const button = element.querySelector('[data-narrator-listen]');

        if (state.speaking) {
            stopSpeech(element);
            return;
        }

        unlock();
        // Anything else being read (another narrator, a chat reply) stops first.
        states.forEach((other, otherElement) => {
            if (otherElement !== element && other.speaking) stopSpeech(otherElement);
        });
        state.speaking = true;
        button.textContent = element.dataset.stopLabel;
        element.classList.add('is-speaking');

        say(fullText(element), {
            lang: element.dataset.lang,
            preference: element.dataset.voicePreference,
            onend: () => {
                if (state.speaking) stopSpeech(element);
            },
        });
    }

    function refresh() {
        narrators.forEach((element) => {
            const visible = isVisible(element);
            const state = states.get(element);

            if (!visible) {
                if (state.active) {
                    halt(element);
                    state.active = false;
                }
                return;
            }

            if (state.active) return;
            state.active = true;

            const key = element.dataset.key;
            if (reducedMotion || played.has(key)) {
                reveal(element);
            } else {
                played.add(key);
                type(element);
            }
        });
    }

    narrators.forEach((element) => {
        states.set(element, { timer: null, speaking: false, active: false });

        const listen = element.querySelector('[data-narrator-listen]');
        listen.hidden = true;
        listen.addEventListener('click', () => speak(element));
        element.querySelector('[data-narrator-skip]').addEventListener('click', () => halt(element));
    });

    // A browser can support speech synthesis in general but still ship with
    // no voice for this client's language — reading the text with whatever
    // default voice it falls back to just produces the wrong language, so
    // hide the control rather than mislead the client (unless the cloud voice covers it).
    narrators.forEach((element) => {
        canSpeakLocale(element.dataset.lang).then((possible) => {
            element.querySelector('[data-narrator-listen]').hidden = !possible;
        });
    });

    window.addEventListener('hashchange', () => window.requestAnimationFrame(refresh));
    window.requestAnimationFrame(refresh);
}

document.addEventListener('DOMContentLoaded', initNarrators);
