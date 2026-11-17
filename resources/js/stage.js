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
const DIRECT_KEY = 'venue_direct';
const CHAT_KEEP_KEY = 'venue_chat_keep';
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

    /** Move to `link` with no curtain on either side. */
    function go(link) {
        if (leaving) return;
        leaving = true;

        try {
            sessionStorage.removeItem(TOPIC_KEY);
            sessionStorage.setItem(DIRECT_KEY, JSON.stringify({ path: link.pathname, at: Date.now() }));
            // Content inside the menu (a hall, a service): a chat that is open stays open on the next page.
            if (document.getElementById('concierge-app')?.dataset.chatState === 'open') {
                sessionStorage.setItem(CHAT_KEEP_KEY, JSON.stringify({ path: link.pathname, at: Date.now() }));
            } else {
                sessionStorage.removeItem(CHAT_KEEP_KEY);
            }
        } catch { /* private mode: the next page just opens its curtain as usual */ }

        window.location.href = link.href;
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

            // Content inside the menu the client is already in (a hall, a service,
            // prev/next): no curtain. The next page is told to skip its opening
            // curtain too, so the choice simply appears.
            if (link.hasAttribute('data-stage-inner')) {
                go(link);
                return;
            }

            // Another menu: the chat is closed on arrival (no topic is raised, so it does not pop open).
            leave(link.href, link.dataset.tourLine, null);
        });
    });

    /** True when `link` is content inside the menu the client is already in (a hall, a service). */
    const section = (path) => /^(.*?\/(?:halls|services))(?:\/|$)/.exec(path)?.[1] ?? null;
    const sameMenu = (link) => !!section(link.pathname) && section(link.pathname) === section(window.location.pathname);

    window.venueStage = { leave, go, sameMenu };
}

/** True when the chat was open on the previous page and the client only moved to content inside the same menu (read once). */
window.takeKeptChat = () => {
    try {
        const kept = JSON.parse(sessionStorage.getItem(CHAT_KEEP_KEY) || 'null');
        sessionStorage.removeItem(CHAT_KEEP_KEY);
        return !!kept && kept.path === window.location.pathname && Date.now() - kept.at < 8000;
    } catch {
        return false;
    }
};

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


/* Venue film: the YouTube player is created on click and removed on close, so
   nothing is fetched from YouTube until a visitor asks for it and the video
   stops the moment the pop-up closes. */
function initVenueVideo() {
    const open = document.querySelector('[data-video-open]');
    const modal = document.querySelector('[data-video-modal]');
    if (!open || !modal || typeof modal.showModal !== 'function') return;

    const frame = modal.querySelector('[data-video-frame]');
    const id = modal.dataset.videoId;

    const stop = () => frame.replaceChildren();

    open.addEventListener('click', () => {
        const player = document.createElement('iframe');
        player.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0&playsinline=1`;
        player.title = modal.getAttribute('aria-label') || '';
        player.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        player.allowFullscreen = true;
        player.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.replaceChildren(player);
        modal.showModal();
    });

    modal.querySelector('[data-video-close]')?.addEventListener('click', () => modal.close());
    modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
    modal.addEventListener('close', stop);
}

initVenueVideo();

/**
 * Area buttons on the hall board: show only the halls of the chosen area.
 * Scoped to [data-area-board], so nothing else on the site is affected.
 */
function initAreaTabs() {
    document.querySelectorAll('[data-area-board]').forEach((board) => {
        const tabs = [...board.querySelectorAll('[data-area-tab]')];
        const links = [...board.querySelectorAll('[data-area]')];
        const count = board.querySelector('[data-area-count]');
        const list = board.querySelector('.hall-nav');

        const select = (key, focus = false) => {
            let shown = 0;
            tabs.forEach((tab) => {
                const on = tab.dataset.areaTab === key;
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.tabIndex = on ? 0 : -1;
                if (on && focus) tab.focus();
                if (on && list) list.setAttribute('aria-labelledby', tab.id);
            });
            links.forEach((link) => {
                const on = link.dataset.area === key;
                link.hidden = !on;
                if (on) shown += 1;
            });
            if (count) count.textContent = String(shown);
            if (list) list.scrollTop = 0;
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => select(tab.dataset.areaTab));
            tab.addEventListener('keydown', (event) => {
                const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
                if (step === undefined) return;
                event.preventDefault();
                select(tabs[(index + step + tabs.length) % tabs.length].dataset.areaTab, true);
            });
        });
    });
}

initAreaTabs();


/* Short films (files on this site): the player is created on click and removed on close,
   so nothing is downloaded until a visitor asks for it and the film stops when the pop-up closes. */
function initFilms() {
    const modal = document.querySelector('[data-film-modal]');
    const buttons = document.querySelectorAll('[data-film-open]');
    if (!modal || !buttons.length || typeof modal.showModal !== 'function') return;

    const frame = modal.querySelector('[data-film-frame]');
    const stop = () => frame.replaceChildren();

    buttons.forEach((button) => button.addEventListener('click', () => {
        const player = document.createElement('video');
        player.src = button.dataset.filmSrc;
        player.controls = true;
        player.autoplay = true;
        player.playsInline = true;
        player.setAttribute('aria-label', button.dataset.filmTitle || '');
        frame.replaceChildren(player);
        modal.showModal();
        player.play?.().catch(() => { /* autoplay refused: the controls are there */ });
    }));

    modal.querySelector('[data-film-close]')?.addEventListener('click', () => modal.close());
    modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
    modal.addEventListener('close', stop);
}

initFilms();


/* Photo icons on the lobby: a small viewer with previous/next, the plan's name and a link to the plan. */
function initLobbyPhotos() {
    const modal = document.querySelector('[data-photo-modal]');
    const buttons = document.querySelectorAll('[data-photo-open]');
    const data = document.querySelector('[data-photo-data]');
    if (!modal || !buttons.length || !data || typeof modal.showModal !== 'function') return;

    let photos = [];
    try { photos = JSON.parse(data.textContent); } catch { return; }
    const image = modal.querySelector('[data-photo-image]');
    const caption = modal.querySelector('[data-photo-caption]');
    const link = modal.querySelector('[data-photo-link]');
    let index = 0;

    const show = (next) => {
        index = (next + photos.length) % photos.length;
        const photo = photos[index];
        image.src = photo.src;
        image.alt = photo.title;
        caption.textContent = `${photo.title} (${index + 1}/${photos.length})`;
        link.hidden = !photo.url;
        link.href = photo.url || '#';
    };

    buttons.forEach((button) => button.addEventListener('click', () => {
        show(Number(button.dataset.photoOpen) || 0);
        modal.showModal();
    }));
    modal.querySelector('[data-photo-prev]').addEventListener('click', () => show(index - 1));
    modal.querySelector('[data-photo-next]').addEventListener('click', () => show(index + 1));
    modal.querySelector('[data-photo-close]').addEventListener('click', () => modal.close());
    modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
    modal.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') show(index - 1);
        if (event.key === 'ArrowRight') show(index + 1);
    });
    modal.addEventListener('close', () => { image.removeAttribute('src'); });
}

initLobbyPhotos();
