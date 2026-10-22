/**
 * AI Concierge chat panel. Vanilla JS, no framework: sends client messages to
 * the Laravel backend and renders both plain text and the structured
 * ui_payload the AI's tools attach (hall cards, price quotes, booking
 * confirmations, handover notices) as real DOM, not markdown-in-a-bubble.
 */
function initConcierge() {
    const root = document.getElementById('concierge-app');
    const widget = document.querySelector('[data-chat-widget]');
    if (!root || !widget) return;

    const config = {
        startUrl: root.dataset.startUrl,
        messageUrl: root.dataset.messageUrl,
        historyUrl: root.dataset.historyUrl,
        storageKey: root.dataset.storageKey,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        lobbyUrl: root.dataset.lobbyUrl,
        hallUrlTemplate: root.dataset.hallUrl,
        staffUrl: root.dataset.staffUrl,
        reservationUrlTemplate: root.dataset.reservationUrl,
        settings: JSON.parse(root.dataset.settingLabels || '{}'),
        eventTypes: JSON.parse(root.dataset.eventLabels || '{}'),
        labels: {
            placeholder: root.dataset.labelPlaceholder,
            send: root.dataset.labelSend,
            open: root.dataset.labelOpen,
            close: root.dataset.labelClose,
            intro: root.dataset.labelIntro,
            cateringIncluded: root.dataset.labelCateringIncluded,
            maxGuests: root.dataset.labelMaxGuests,
            cateringExcluded: root.dataset.labelCateringExcluded,
            deposit: root.dataset.labelDeposit,
            perEvent: root.dataset.labelPerEvent,
            discount: root.dataset.labelDiscount,
            noAvailability: root.dataset.labelNoAvailability,
            bookingReceived: root.dataset.labelBookingReceived,
            reference: root.dataset.labelReference,
            viewSuffix: root.dataset.labelViewSuffix,
            thinking: root.dataset.labelThinking,
            handedOver: root.dataset.labelHandedOver,
            statusSent: root.dataset.labelStatusSent,
            statusWaiting: root.dataset.labelStatusWaiting,
            statusReplied: root.dataset.labelStatusReplied,
            draftTitle: root.dataset.labelDraftTitle,
            draftBody: root.dataset.labelDraftBody,
            draftKeep: root.dataset.labelDraftKeep,
            draftDiscard: root.dataset.labelDraftDiscard,
            viewDetails: root.dataset.labelViewDetails,
            hallDetailsQuestion: root.dataset.labelHallDetailsQuestion,
            bookNow: root.dataset.labelBookNow,
            menuHeading: root.dataset.labelMenuHeading,
            staff: root.dataset.labelStaff,
            error: root.dataset.labelError,
            slow: root.dataset.labelSlow,
            retry: root.dataset.labelRetry,
        },
    };

    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const submitEl = root.querySelector('[data-chat-submit]');
    const statusBanner = root.querySelector('[data-status-banner]');
    const chatStatus = root.querySelector('[data-chat-status]');
    const thinkingIndicator = root.querySelector('[data-thinking-indicator]');
    const draftDialog = root.querySelector('[data-draft-dialog]');
    const draftKeep = root.querySelector('[data-draft-keep]');
    const draftDiscard = root.querySelector('[data-draft-discard]');
    const chatLog = root.querySelector('#concierge-chat-log');
    const closeButton = widget.querySelector('[data-chat-close]');
    const newChatButton = widget.querySelector('[data-chat-new]');
    const launcher = widget.querySelector('[data-chat-launcher]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let ready = false;
    let busy = false;
    let handedOver = false;
    let knownMessageCount = 0;
    let pollTimer = null;
    let isOpen = false;
    let draftDialogOpen = false;
    let showcaseEl = null;
    let showcaseReturnFocus = null;
    let showcaseTimer = null;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function setChatStatus(text, tone = '') {
        if (!chatStatus) return;

        chatStatus.textContent = text || '';
        chatStatus.className = `chat-status${text ? ` is-${tone}` : ''}`;
        chatStatus.hidden = !text;
    }

    function showDraftDialog() {
        if (!draftDialog) return;

        draftDialogOpen = true;
        draftDialog.hidden = false;
        draftDialog.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => draftKeep?.focus());
    }

    function hideDraftDialog({ focusInput = false } = {}) {
        if (!draftDialog) return;

        draftDialogOpen = false;
        draftDialog.hidden = true;
        draftDialog.setAttribute('aria-hidden', 'true');
        if (focusInput) inputEl.focus();
    }

    function syncPanelState() {
        root.classList.toggle('is-open', isOpen);
        root.dataset.chatState = isOpen ? 'open' : 'closed';
        chatLog?.setAttribute('aria-hidden', String(!isOpen));
        formEl?.setAttribute('aria-hidden', String(!isOpen));
        launcher?.setAttribute('aria-expanded', String(isOpen));
    }

    function openPanel({ focus = false } = {}) {
        isOpen = true;
        syncPanelState();
        scrollToBottom();

        if (focus) {
            requestAnimationFrame(() => inputEl.focus());
        }
    }

    function finishClosePanel({ restoreFocus = true } = {}) {
        isOpen = false;
        syncPanelState();
        hideDraftDialog();
        if (restoreFocus) launcher?.focus();
    }

    function closePanel({ confirmDraft = true, restoreFocus = true } = {}) {
        if (confirmDraft && inputEl?.value.trim()) {
            showDraftDialog();
            return;
        }

        finishClosePanel({ restoreFocus });
    }

    closeButton?.addEventListener('click', () => closePanel());
    launcher?.addEventListener('click', () => openPanel({ focus: true }));
    draftKeep?.addEventListener('click', () => hideDraftDialog({ focusInput: true }));
    draftDiscard?.addEventListener('click', () => {
        inputEl.value = '';
        finishClosePanel();
    });
    document.addEventListener('keydown', (event) => {
        if (showcaseEl) {
            if (event.key === 'Escape') {
                closeShowcase();
                event.stopImmediatePropagation();
            } else if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                showcaseEl._step?.(event.key === 'ArrowRight' ? 1 : -1);
            }
            return;
        }

        if (event.key !== 'Escape') return;

        if (draftDialogOpen) {
            hideDraftDialog({ focusInput: true });
            return;
        }

        if (isOpen) closePanel();
    });
    syncPanelState();

    function money(value) {
        const n = Number(value) || 0;
        const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
        return `${config.currency} ${new Intl.NumberFormat(numberLocale, { maximumFractionDigits: 0 }).format(n)}`;
    }

    const moneyCount = (value) => `<span data-count="${Number(value) || 0}">${money(value)}</span>`;

    /** Numbers climb from zero to their value; skipped for people who asked for less motion. */
    function animateCounts(scope) {
        if (reduceMotion.matches) return;

        scope.querySelectorAll('[data-count]').forEach((el) => {
            const target = Number(el.dataset.count) || 0;
            const started = performance.now();
            el.textContent = money(0);

            const tick = (now) => {
                const progress = Math.min(1, (now - started) / 1000);
                el.textContent = money(Math.round(target * (1 - Math.pow(1 - progress, 3))));
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    delete el.dataset.count;
                }
            };

            requestAnimationFrame(tick);
        });
    }

    // Cloudflare drops a request that is still unanswered after 100s, so give
    // up just before that and let the client retry instead of waiting forever.
    const API_TIMEOUT_MS = 95000;

    async function api(url, body) {
        let response;

        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(body || {}),
                signal: AbortSignal.timeout(API_TIMEOUT_MS),
            });
        } catch (err) {
            const error = new Error(err.name === 'TimeoutError' ? 'Request timed out' : 'Network error');
            error.status = err.name === 'TimeoutError' ? 408 : 0;
            throw error;
        }

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            const error = new Error(payload.message || `Request failed (${response.status})`);
            error.status = response.status;
            throw error;
        }

        return response.json();
    }

    function avatarMark() {
        const el = document.createElement('span');
        el.className = 'chat-host-avatar';
        el.setAttribute('aria-hidden', 'true');
        return el;
    }

    function bubble(role, text) {
        const wrap = document.createElement('div');
        wrap.className =
            role === 'client' ? 'flex justify-end' : 'flex items-end justify-start gap-2';

        if (role !== 'client') {
            wrap.appendChild(avatarMark());
        }

        const inner = document.createElement('div');
        inner.className = role === 'client' ? 'chat-bubble is-client' : 'chat-bubble is-host';
        if (role === 'client') {
            inner.textContent = text;
        } else {
            // The local model writes **bold** and [links](https://…); escape everything first, then turn only those into markup.
            inner.innerHTML = esc(text)
                .replace(/!\[[^\]]*\]\(https?:\/\/[^\s)]+\)\s*/g, '')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="underline">$1</a>');
        }

        wrap.appendChild(inner);
        return wrap;
    }

    function card(children) {
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-start';
        const inner = document.createElement('div');
        inner.className = 'w-full max-w-[92%] space-y-2';
        children.forEach((c) => inner.appendChild(c));
        wrap.appendChild(inner);
        return wrap;
    }

    const longDate = (iso) => {
        const [year, month, day] = String(iso).split('-').map(Number);
        const tag = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
        return new Intl.DateTimeFormat(tag, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

    function hallResultCard(hall) {
        const el = document.createElement('div');
        el.className = 'chat-card';
        const facts = [config.settings[hall.setting] || '', hall.size_sqm ? `${hall.size_sqm} m²` : '', `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}`].filter(Boolean).join(' · ');
        el.innerHTML = `
            ${hall.thumbnail_url ? `<img src="${esc(hall.thumbnail_url)}" alt="${esc(hall.name)}" class="h-28 w-full object-cover">` : ''}
            <div class="p-3">
                <p class="chat-card-tag">${esc(facts)}</p>
                <p class="mt-1 font-semibold">${esc(hall.name)}</p>
                <p class="mt-1 text-sm font-semibold">${moneyCount(hall.total_price)} <span class="font-normal opacity-60">${esc(config.labels.perEvent)}</span></p>
                ${hall.discount_percent > 0 ? `<p class="chat-card-tag mt-1">−${hall.discount_percent}% ${esc(config.labels.discount)}</p>` : ''}
                <p class="mt-1 text-xs opacity-70">${esc(config.labels.deposit)}: ${money(hall.deposit_total)}</p>
                <button type="button" class="chat-card-button" data-detail-slug="${esc(hall.hall_slug)}">
                    ${esc(config.labels.viewDetails)}
                </button>
            </div>
        `;
        el.querySelector('[data-detail-slug]').addEventListener('click', () => {
            sendMessage(config.labels.hallDetailsQuestion.replace(':hall', hall.name));
        });
        return el;
    }

    function hallDetailCard(hall) {
        const el = document.createElement('div');
        el.className = 'chat-card';

        const images = (hall.images || [])
            .slice(0, 4)
            .map((img) => `<img src="${esc(img.url)}" alt="${esc(img.alt || hall.name)}" class="h-16 w-full rounded object-cover">`)
            .join('');

        el.innerHTML = `
            <div class="p-3">
                <p class="chat-card-tag">${esc([config.settings[hall.setting] || '', `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}`].filter(Boolean).join(' · '))}</p>
                <p class="mt-1 font-semibold">${esc(hall.name)}</p>
                <p class="mt-1 text-xs opacity-70">${esc(hall.description || '')}</p>
                <div class="mt-2 grid grid-cols-4 gap-1">${images}</div>
                <dl class="mt-2 grid grid-cols-2 gap-1 text-xs opacity-70">
                    <div>${hall.size_sqm ?? '-'} m²</div>
                    <div>${hall.catering_included ? esc(config.labels.cateringIncluded) : esc(config.labels.cateringExcluded)}</div>
                    <div>${hall.view_type ? esc(hall.view_type + ' ' + config.labels.viewSuffix) : ''}</div>
                </dl>
            </div>
        `;
        return el;
    }

    function availabilityCard(payload) {
        const el = document.createElement('div');
        el.className = 'chat-card p-3';

        if (!payload.available) {
            el.innerHTML = `<p class="text-sm opacity-70">${esc(config.labels.noAvailability)}</p>`;
            return el;
        }

        const q = payload.quote;
        el.innerHTML = `
            <div class="avail-date">
                <span class="avail-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7"/></svg></span>
                <div class="min-w-0">
                    <p class="font-semibold">${esc(q.name)}</p>
                    <p class="chat-card-tag mt-0.5">${esc(longDate(q.event_date))}</p>
                </div>
            </div>
            ${q.discount_percent > 0 ? `<p class="mt-1 text-xs opacity-70"><s>${money(q.subtotal)}</s> · −${q.discount_percent}% ${esc(config.labels.discount)}</p>` : ''}
            <p class="mt-1 text-base font-semibold">${moneyCount(q.grand_total)}</p>
            <p class="mt-1 text-xs opacity-70">${esc(config.labels.deposit)} (${q.deposit_percent}%): ${money(q.deposit_total)}</p>
        `;
        return el;
    }

    function bookingConfirmationCard(payload) {
        const b = payload.booking;
        const el = document.createElement('div');
        el.className = 'chat-card p-3';
        el.style.borderLeft = '3px solid var(--gold-deep)';
        el.classList.add('is-celebrating');
        el.innerHTML = `
            <p class="text-sm font-semibold">${esc(config.labels.bookingReceived)}</p>
            <p class="chat-card-tag mt-1">${esc(config.labels.reference)}: ${esc(b.booking_reference)}</p>
            <p class="mt-1 text-xs opacity-70">${esc(b.name)} · ${esc(longDate(b.event_date))}${config.eventTypes[b.event_type] ? ' · ' + esc(config.eventTypes[b.event_type]) : ''}</p>
            <p class="mt-1 text-sm font-semibold">${moneyCount(b.total_price)}</p>
            <p class="mt-1 text-xs opacity-70">${esc(config.labels.deposit)}: ${money(b.deposit_amount)}</p>
        `;
        return el;
    }

    function handoverCard(payload) {
        const el = document.createElement('div');
        el.className = 'chat-card p-3 text-xs';
        el.style.borderLeft = '3px solid #d9a441';
        el.textContent = config.labels.handedOver;
        return el;
    }

    const hallUrl = (slug) => config.hallUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
    const reservationUrl = (slug) => config.reservationUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));

    /** Another page: walk there the way the menu does; a hash change is left to the browser. */
    function followLink(link, event) {
        closeShowcase();
        closePanel({ confirmDraft: false, restoreFocus: false });

        if (link.pathname !== window.location.pathname && window.venueStage) {
            event.preventDefault();
            window.venueStage.leave(link.href);
        }
    }

    function closeShowcase() {
        if (!showcaseEl) return;

        const el = showcaseEl;
        showcaseEl = null;
        clearInterval(showcaseTimer);
        el.classList.add('is-closing');
        window.setTimeout(() => el.remove(), reduceMotion.matches ? 0 : 340);
        showcaseReturnFocus?.focus?.({ preventScroll: true });
    }

    function openShowcase(label, body) {
        closeShowcase();

        const wrap = document.createElement('div');
        wrap.className = 'showcase';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-modal', 'true');
        wrap.setAttribute('aria-label', label);
        wrap.innerHTML = `
            <div class="showcase-backdrop" data-showcase-close></div>
            <div class="showcase-panel">
                <button type="button" class="showcase-close" data-showcase-close aria-label="${esc(config.labels.close)}">×</button>
            </div>
        `;
        wrap.querySelector('.showcase-panel').appendChild(body);
        wrap.addEventListener('click', (event) => {
            if (event.target.closest('[data-showcase-close]')) closeShowcase();
        });

        showcaseReturnFocus = document.activeElement;
        (root.closest('.stage') || document.body).appendChild(wrap);
        showcaseEl = wrap;
        wrap.querySelector('.showcase-close').focus({ preventScroll: true });
        animateCounts(wrap);
    }

    /** Every photo of a hall, big in the middle of the screen, drifting slowly like a film still. */
    function showcaseGallery(hall) {
        const images = hall.images || [];
        const facts = [config.settings[hall.setting] || '', hall.size_sqm ? `${hall.size_sqm} m²` : '', `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}`].filter(Boolean).join(' · ');
        const el = document.createElement('div');
        el.className = 'showcase-gallery';
        el.innerHTML = `
            <div class="showcase-media">
                <div class="showcase-stage">
                    <img class="showcase-photo" src="${esc(images[0].url)}" alt="${esc(images[0].alt || hall.name)}">
                    <span class="showcase-badge">${esc(hall.name)}</span>
                </div>
                ${images.length > 1 ? `<div class="showcase-thumbs">${images.map((img, index) => `
                    <button type="button" class="showcase-thumb" data-index="${index}" ${index === 0 ? 'aria-current="true"' : ''} aria-label="${esc(img.alt || `${hall.name} ${index + 1}`)}">
                        <img src="${esc(img.url)}" alt="" loading="lazy">
                    </button>`).join('')}</div>` : ''}
            </div>
            <div class="showcase-info">
                <p class="chat-card-tag" style="--i:0">${esc(facts)}</p>
                <h2 class="showcase-title" style="--i:1">${esc(hall.name)}</h2>
                <p class="showcase-text" style="--i:2">${esc(hall.description || '')}</p>
                <p class="showcase-price" style="--i:3">${moneyCount(hall.starting_price)} <span>${esc(config.labels.perEvent)}</span></p>
                <div class="showcase-actions" style="--i:4">
                    <a class="showcase-action is-primary" href="${esc(reservationUrl(hall.hall_slug))}">${esc(config.labels.bookNow)}</a>
                    <a class="showcase-action" href="${esc(hallUrl(hall.hall_slug))}">${esc(config.labels.viewDetails)}</a>
                </div>
            </div>
        `;

        const photo = el.querySelector('.showcase-photo');
        const thumbs = [...el.querySelectorAll('.showcase-thumb')];
        let current = 0;

        const show = (index) => {
            if (!thumbs.length) return;
            current = (index + images.length) % images.length;
            photo.style.animation = 'none';
            void photo.offsetWidth;
            photo.style.animation = '';
            photo.src = images[current].url;
            photo.alt = images[current].alt || hall.name;
            thumbs.forEach((thumb, i) => thumb.toggleAttribute('aria-current', i === current));
        };

        thumbs.forEach((thumb) => thumb.addEventListener('click', () => {
            clearInterval(showcaseTimer);
            show(Number(thumb.dataset.index));
        }));
        el.querySelectorAll('a.showcase-action').forEach((link) => link.addEventListener('click', (event) => followLink(link, event)));

        el._step = (direction) => {
            clearInterval(showcaseTimer);
            show(current + direction);
        };
        if (thumbs.length && !reduceMotion.matches) {
            window.setTimeout(() => {
                if (showcaseEl) showcaseTimer = setInterval(() => show(current + 1), 4800);
            }, 0);
        }

        return el;
    }

    /** The halls the concierge picked, dealt out like cards on the table. */
    function showcaseResults(halls) {
        const el = document.createElement('div');
        el.className = 'showcase-results';
        el.innerHTML = `<div class="showcase-grid">${halls.map((hall, index) => {
            const facts = [config.settings[hall.setting] || '', hall.size_sqm ? `${hall.size_sqm} m²` : '', `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}`].filter(Boolean).join(' · ');

            return `
                <article class="showcase-card" style="--i:${index}">
                    <div class="showcase-card-photo">${hall.thumbnail_url ? `<img src="${esc(hall.thumbnail_url)}" alt="${esc(hall.name)}">` : ''}</div>
                    <div class="showcase-card-body">
                        <p class="chat-card-tag">${esc(facts)}</p>
                        <h3 class="showcase-card-title">${esc(hall.name)}</h3>
                        <p class="showcase-card-price">${moneyCount(hall.total_price)} <span>${esc(config.labels.perEvent)}</span></p>
                        ${hall.discount_percent > 0 ? `<p class="chat-card-tag">−${hall.discount_percent}% ${esc(config.labels.discount)}</p>` : ''}
                        <button type="button" class="chat-card-button" data-showcase-slug="${esc(hall.hall_slug)}">${esc(config.labels.viewDetails)}</button>
                    </div>
                </article>`;
        }).join('')}</div>`;

        el.querySelectorAll('[data-showcase-slug]').forEach((button, index) => button.addEventListener('click', () => {
            closeShowcase();
            sendMessage(config.labels.hallDetailsQuestion.replace(':hall', halls[index].name));
        }));

        return el;
    }

    /** A short shower of petals when a date is held. */
    function petalShower() {
        if (reduceMotion.matches) return;

        const layer = document.createElement('div');
        layer.className = 'petals';
        layer.setAttribute('aria-hidden', 'true');

        const count = window.innerWidth < 700 ? 16 : 30;
        for (let i = 0; i < count; i += 1) {
            const petal = document.createElement('i');
            petal.className = `petal${i % 4 === 0 ? ' is-gold' : ''}`;
            petal.style.cssText = `--x:${Math.random() * 100}%;--s:${9 + Math.random() * 10}px;--d:${3.2 + Math.random() * 2.2}s;--delay:${Math.random() * 1.1}s;--drift:${(Math.random() - 0.5) * 180}px;--spin:${240 + Math.random() * 420}deg`;
            layer.appendChild(petal);
        }

        (root.closest('.stage') || document.body).appendChild(layer);
        window.setTimeout(() => layer.remove(), 7000);
    }

    function renderUiPayload(uiPayload, live = false) {
        if (!uiPayload || !uiPayload.length) return;

        uiPayload.forEach((payload) => {
            let wrapper = null;

            if (payload.type === 'hall_results') {
                wrapper = card(payload.halls.map(hallResultCard));
            } else if (payload.type === 'hall_detail') {
                wrapper = card([hallDetailCard(payload.hall)]);
            } else if (payload.type === 'availability') {
                wrapper = card([availabilityCard(payload)]);
            } else if (payload.type === 'booking_confirmation') {
                wrapper = card([bookingConfirmationCard(payload)]);
            } else if (payload.type === 'handover') {
                wrapper = card([handoverCard(payload)]);
            }

            if (!wrapper) return;

            messagesEl.appendChild(wrapper);
            if (live) animateCounts(wrapper);
        });

        if (!live) return;

        const stageful = uiPayload.find((payload) => (payload.type === 'hall_detail' && payload.hall.images?.length) || (payload.type === 'hall_results' && payload.halls?.length));
        if (stageful) {
            window.setTimeout(() => {
                if (stageful.type === 'hall_detail') {
                    openShowcase(stageful.hall.name, showcaseGallery(stageful.hall));
                } else {
                    openShowcase(config.labels.viewDetails, showcaseResults(stageful.halls));
                }
            }, 320);
        }

        if (uiPayload.some((payload) => payload.type === 'booking_confirmation')) {
            window.setTimeout(petalShower, 250);
        }
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setBusy(value) {
        busy = value;
        const disabled = busy || !ready || handedOver;
        inputEl.disabled = disabled;
        submitEl.disabled = disabled;
        if (launcher) launcher.disabled = busy || !ready;
        if (newChatButton) newChatButton.disabled = busy || !ready;
        root.setAttribute('aria-busy', String(busy));
        document.body.classList.toggle('is-thinking', busy);
        if (thinkingIndicator) {
            thinkingIndicator.hidden = !busy;
        }
        document.querySelectorAll('[data-hero-quick-message], [data-ask-ai-button], [data-quick-message]')
            .forEach((button) => { button.disabled = disabled; });
        submitEl.textContent = busy ? config.labels.thinking : config.labels.send;
        if (busy) scrollToBottom();
    }

    function showHandedOverBanner() {
        handedOver = true;
        statusBanner.textContent = config.labels.handedOver;
        statusBanner.classList.remove('hidden');
        setChatStatus(config.labels.statusWaiting, 'waiting');
        setBusy(busy);
        startPolling();
    }

    function clearHandedOverBanner() {
        handedOver = false;
        statusBanner.classList.add('hidden');
        setBusy(busy);
        stopPolling();
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollForStaffReplies, 4000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollForStaffReplies() {
        const clientToken = localStorage.getItem(config.storageKey);
        if (!clientToken) return;

        try {
            const response = await fetch(`${config.historyUrl}?client_token=${encodeURIComponent(clientToken)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();

            if (data.messages.length > knownMessageCount) {
                const newMessages = data.messages.slice(knownMessageCount);
                newMessages.forEach((message) => renderMessage(message));
                if (newMessages.some((message) => message.role === 'staff')) {
                    setChatStatus(config.labels.statusReplied, 'replied');
                }
                window.venueSound?.play('incoming');
                knownMessageCount = data.messages.length;
                scrollToBottom();
            }

            if (data.status !== 'handed_over') {
                clearHandedOverBanner();
            }
        } catch {
            // transient network hiccup — try again on the next tick
        }
    }

    function renderMessage(message, live = false) {
        if (message.role === 'client') {
            messagesEl.appendChild(bubble('client', message.content));
        } else if (message.role === 'assistant') {
            if (message.content) {
                messagesEl.appendChild(bubble('assistant', message.content));
            }
            renderUiPayload(message.ui_payload, live);
            const chips = actionChips(message.suggested_actions);
            if (chips) messagesEl.appendChild(chips);
        } else if (message.role === 'staff') {
            messagesEl.appendChild(card([staffBubble(message.content)]));
            setChatStatus(config.labels.statusReplied, 'replied');
        } else if (message.role === 'system') {
            messagesEl.appendChild(bubble('assistant', message.content));
        }
    }

    function quickMenuCard() {
        const template = document.querySelector('[data-quick-menu-items]');
        if (!template) return null;

        const el = document.createElement('div');
        el.className = 'chat-card';

        const heading = document.createElement('p');
        heading.className = 'chat-card-tag border-b border-[var(--line)] px-3 py-2';
        heading.textContent = config.labels.menuHeading;
        el.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'divide-y divide-[var(--line)]';

        template.content.querySelectorAll('[data-quick-message]').forEach((source) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-[#e3c27c33]';
            item.innerHTML = `<span>${source.textContent}</span><span class="opacity-40">→</span>`;
            item.addEventListener('click', () => sendMessage(source.dataset.quickMessage));
            list.appendChild(item);
        });

        el.appendChild(list);
        return el;
    }

    function staffBubble(text) {
        const el = document.createElement('div');
        el.className = 'chat-bubble is-host';
        el.style.borderLeft = '3px solid #d98a93';
        el.innerHTML = `<p class="chat-card-tag mb-0.5" style="color:#a85a64">${config.labels.staff}</p>`;
        el.append(document.createTextNode(text));
        return el;
    }

    const sceneNames = { home: 'reception', lobby: 'reception', info: 'reception', halls: 'halls', hall: 'hall_detail', services: 'services', service: 'service_detail', reservation: 'reservation', staff: 'handover' };

    /**
     * Where the client is in the lobby, so the concierge can answer for "this
     * hall" or the reservation on screen. Never includes name or contact data.
     */
    function uiContext() {
        const pageScene = document.querySelector('[data-lobby]')?.dataset.scene || 'lobby';
        const context = { scene: sceneNames[pageScene] || 'reception' };

        const lastSegment = decodeURIComponent(window.location.pathname.split('/').pop());
        if (pageScene === 'hall') context.selected_hall = lastSegment;
        if (pageScene === 'service') context.selected_service = Number(lastSegment);

        if (pageScene === 'reservation') {
            try {
                const draft = JSON.parse(sessionStorage.getItem(`reservation_draft_${root.dataset.venueSlug}`) || 'null');
                const { event_date, event_type, guests, extra_hours, hall_slug } = draft?.values || {};
                context.reservation = { event_date, event_type, guests, extra_hours, hall_slug };
                if (hall_slug) context.selected_hall = hall_slug;
            } catch { /* no draft to share */ }
        }

        return context;
    }

    function actionChips(actions) {
        if (!actions || !actions.length) return null;

        const targets = {
            view_hall: [config.labels.viewDetails, (action) => hallUrl(action.hall)],
            reserve: [config.labels.bookNow, (action) => reservationUrl(action.hall)],
            staff: [config.labels.staff, () => config.staffUrl],
        };

        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2 pl-10';

        actions.forEach((action) => {
            const target = targets[action.action];
            if (!target) return;
            const link = document.createElement('a');
            link.href = target[1](action);
            link.className = 'chat-chip';
            link.textContent = target[0];
            link.addEventListener('click', (event) => {
                closePanel({ confirmDraft: false, restoreFocus: false });

                // Another page: walk there the way the menu does. A panel on
                // this page is just a hash change, so leave it to the browser.
                if (link.pathname !== window.location.pathname && window.venueStage) {
                    event.preventDefault();
                    window.venueStage.leave(link.href);
                }
            });
            wrap.appendChild(link);
        });

        return wrap.childElementCount ? wrap : null;
    }

    function errorCard(text, err) {
        const el = document.createElement('div');
        el.className = 'chat-card p-3 text-sm';
        el.style.borderLeft = '3px solid var(--danger)';

        const message = document.createElement('p');
        message.textContent = err.status === 429 ? config.labels.slow : config.labels.error;

        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-2';

        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'chat-mini-button is-primary';
        retry.textContent = config.labels.retry;
        retry.addEventListener('click', () => {
            el.remove();
            sendMessage(text, { retry: true });
        });

        const staff = document.createElement('a');
        staff.href = config.staffUrl;
        staff.className = 'chat-mini-button';
        staff.textContent = config.labels.staff;
        staff.addEventListener('click', (event) => {
            closePanel({ confirmDraft: false, restoreFocus: false });

            if (window.venueStage) {
                event.preventDefault();
                window.venueStage.leave(staff.href);
            }
        });

        actions.append(retry, staff);
        el.append(message, actions);
        return el;
    }

    async function sendMessage(text, { retry = false } = {}) {
        if (!text.trim() || handedOver || busy || !ready) return;

        const clientToken = localStorage.getItem(config.storageKey);
        if (!clientToken) return;

        openPanel();
        if (!retry) messagesEl.appendChild(bubble('client', text));
        window.venueSound?.play('sent');
        scrollToBottom();
        setBusy(true);

        try {
            const data = await api(config.messageUrl, { client_token: clientToken, message: text, ...uiContext() });
            renderMessage(data.message, true);
            setChatStatus(config.labels.statusSent, 'sent');
            window.venueSound?.play('incoming');
            knownMessageCount += 2; // the client message just sent + the reply just rendered
            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch (err) {
            messagesEl.appendChild(errorCard(text, err));
        } finally {
            setBusy(false);
            scrollToBottom();
        }
    }

    async function boot() {
        let clientToken = localStorage.getItem(config.storageKey);

        if (!clientToken) {
            const data = await api(config.startUrl, { locale: config.locale });
            clientToken = data.client_token;
            localStorage.setItem(config.storageKey, clientToken);
            messagesEl.appendChild(bubble('assistant', config.labels.intro));
            const menu = quickMenuCard();
            if (menu) messagesEl.appendChild(card([menu]));
            return;
        }

        try {
            const response = await fetch(`${config.historyUrl}?client_token=${encodeURIComponent(clientToken)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('history_unavailable');

            const data = await response.json();

            if (!data.messages.length) {
                messagesEl.appendChild(bubble('assistant', config.labels.intro));
                const menu = quickMenuCard();
                if (menu) messagesEl.appendChild(card([menu]));
            } else {
                data.messages.forEach((message) => renderMessage(message));
            }
            knownMessageCount = data.messages.length;

            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
            if (data.messages.some((message) => message.role === 'staff')) {
                setChatStatus(config.labels.statusReplied, 'replied');
            }
        } catch {
            localStorage.removeItem(config.storageKey);
            return boot();
        }

        scrollToBottom();
    }

    /** Drops the stored conversation and starts a fresh one with the intro and quick menu. */
    async function startNewChat() {
        if (busy || !ready) return;

        ready = false;
        setBusy(true);
        closeShowcase();
        hideDraftDialog();
        stopPolling();

        localStorage.removeItem(config.storageKey);
        messagesEl.replaceChildren();
        inputEl.value = '';
        knownMessageCount = 0;
        handedOver = false;
        statusBanner.classList.add('hidden');
        setChatStatus('');

        try {
            await boot();
        } catch {
            statusBanner.textContent = root.dataset.labelConnectionError;
            statusBanner.classList.remove('hidden');
            messagesEl.appendChild(bubble('assistant', config.labels.intro));
        } finally {
            ready = true;
            setBusy(false);
            scrollToBottom();
            inputEl.focus();
        }
    }

    newChatButton?.addEventListener('click', startNewChat);

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!ready || busy || handedOver || !inputEl.value.trim()) return;
        const text = inputEl.value;
        inputEl.value = '';
        sendMessage(text);
    });

    document.querySelectorAll('[data-ask-ai-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const hallCard = button.closest('[data-hall-card]');
            const hallName = hallCard?.dataset.hallName || '';
            openPanel();
            sendMessage(`${hallName}`);
        });
    });

    // Hero quick-start menu — same topics as the in-chat menu, always
    // visible up front so guests see what the AI can do immediately.
    document.querySelectorAll('[data-hero-quick-message]').forEach((button) => {
        button.addEventListener('click', () => {
            openPanel();
            sendMessage(button.dataset.heroQuickMessage);
        });
    });

    // A menu pick in the same scene: the concierge answers right where the client stands.
    window.addEventListener('concierge:ask', (event) => sendMessage(event.detail.message));

    setBusy(true);
    boot().then(() => {
        ready = true;
        scrollToBottom();
    }).catch(() => {
        statusBanner.textContent = root.dataset.labelConnectionError;
        statusBanner.classList.remove('hidden');
        messagesEl.appendChild(bubble('assistant', config.labels.intro));
    }).finally(() => {
        setBusy(false);

        // The client chose this topic from the menu on the previous scene:
        // let the new scene settle for a beat, then carry on the conversation.
        const topic = window.takePendingConciergeTopic?.();
        if (topic && ready) window.setTimeout(() => sendMessage(topic), 650);
    });
}

document.addEventListener('DOMContentLoaded', initConcierge);

function initHallGallery() {
    document.querySelectorAll('[data-hall-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-hall-gallery-main]');
        const thumbs = [...gallery.querySelectorAll('[data-hall-thumb]')];
        if (!main) return;

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.src = thumb.dataset.src;
                main.alt = thumb.dataset.alt || '';
                thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initHallGallery);
