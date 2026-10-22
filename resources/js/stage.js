/**
 * Full-screen stage chrome shared by the opening screen and every chapter:
 * draws the velvet curtains back once the photo is ready, turns the chapter
 * display to the chapter the client chose, and closes the curtains again
 * before following a link — so moving between pages feels like turning the
 * page of a wedding programme rather than loading a page.
 *
 * A link may carry `data-topic`: the question the client picked from the menu.
 * It is handed to the AI concierge, which raises it once the next scene is up
 * (or straight away when the client is already in that scene).
 */
const TOPIC_KEY = 'concierge_pending_topic';
const CHAPTER_KEY = 'venue_last_chapter';
const CHAPTER_ORDER = ['I', 'II', 'III', 'IV', 'V', 'VI'];

/**
 * The chapter display counts through every chapter between the last one the
 * client was on and this one, with the ornament turning the way they travelled.
 */
function runChapterDisplay(stage, reducedMotion) {
    const current = stage.dataset.chapter;
    const code = stage.querySelector('[data-chapter-code]');
    const arrow = stage.querySelector('[data-chapter-arrow]');
    let previous = null;

    try {
        previous = sessionStorage.getItem(CHAPTER_KEY);
        sessionStorage.setItem(CHAPTER_KEY, current);
    } catch { /* private mode: the display just shows the current chapter */ }

    if (!code || !previous || previous === current) return;

    const from = CHAPTER_ORDER.indexOf(previous);
    const to = CHAPTER_ORDER.indexOf(current);
    if (from < 0 || to < 0) return;

    arrow?.classList.toggle('is-down', to < from);
    if (reducedMotion) return;

    const step = to > from ? 1 : -1;
    let index = from;
    code.textContent = CHAPTER_ORDER[index];

    const timer = window.setInterval(() => {
        index += step;
        code.textContent = CHAPTER_ORDER[index];
        if (index === to) window.clearInterval(timer);
    }, 180);
}

function initStage() {
    const stage = document.querySelector('.stage');
    if (!stage) return;

    const loader = stage.querySelector('[data-stage-loader]');
    const image = stage.querySelector('.stage-image');
    const tour = stage.querySelector('[data-stage-tour]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const showScene = () => loader?.classList.add('is-ready');

    runChapterDisplay(stage, reducedMotion);

    // On phones the order-of-the-day panel is a scrolling strip; keep the lit button in view.
    stage.querySelector('.journey-button[aria-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' });

    if (!image || image.complete) {
        showScene();
    } else {
        image.addEventListener('load', showScene, { once: true });
        image.addEventListener('error', showScene, { once: true });
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            leaving = false;
            stage.classList.remove('is-leaving');
            tour?.classList.remove('is-visible');
        }
    });

    let leaving = false;

    /** Ease the stage out, then walk the client over to `href`. */
    function leave(href, line = null, topic = null) {
        if (leaving) return;
        leaving = true;

        try {
            if (topic) sessionStorage.setItem(TOPIC_KEY, topic);
            else sessionStorage.removeItem(TOPIC_KEY);
        } catch { /* private mode: the scene still changes, only the topic is dropped */ }

        // The concierge announces the chapter while the curtains close.
        if (line && tour) {
            tour.textContent = line;
            tour.classList.add('is-visible');
        }

        stage.classList.add('is-leaving');

        const chime = window.venueSound?.isEnabled() ?? false;
        if (chime) window.venueSound.play('enter');

        const hold = reducedMotion ? 0 : line ? 900 : 520;
        window.setTimeout(() => { window.location.href = href; }, hold);
    }

    document.querySelectorAll('[data-stage-exit]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const modified = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (event.defaultPrevented || modified || link.target === '_blank') return;

            event.preventDefault();

            // Already in this scene: no walk needed, the concierge just takes
            // the topic up where the client is standing.
            const samePlace = link.pathname === window.location.pathname;
            if (samePlace && link.dataset.topic) {
                window.dispatchEvent(new CustomEvent('concierge:ask', { detail: { message: link.dataset.topic } }));
                return;
            }
            if (samePlace) return;

            leave(link.href, link.dataset.tourLine, link.dataset.topic);
        });
    });

    window.venueStage = { leave };
}

/** The topic the client picked on the previous scene, if any (read once). */
window.takePendingConciergeTopic = () => {
    try {
        const topic = sessionStorage.getItem(TOPIC_KEY);
        sessionStorage.removeItem(TOPIC_KEY);
        return topic;
    } catch {
        return null;
    }
};

document.addEventListener('DOMContentLoaded', initStage);
