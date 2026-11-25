import { formatMoney } from './money';
import { canSpeakLocale, speak as sayAloud, stop as stopVoice, unlock as unlockVoice } from './voice';
import { interpret as interpretNav, decisiveAction, earlyDelay, perform as performNav } from './voice-nav';
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
            mic: root.dataset.labelMic,
            micListening: root.dataset.labelMicListening,
            micDenied: root.dataset.labelMicDenied,
            micNoSpeech: root.dataset.labelMicNospeech,
            voice: root.dataset.labelVoice,
            voiceOn: root.dataset.labelVoiceOn,
            call: root.dataset.labelCall,
            callOn: root.dataset.labelCallOn,
            callEnded: root.dataset.labelCallEnded,
            talkHint: root.dataset.labelTalkHint,
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
            options: root.dataset.labelOptions,
            hallDetailsQuestion: root.dataset.labelHallDetailsQuestion,
            bookNow: root.dataset.labelBookNow,
            menuHeading: root.dataset.labelMenuHeading,
            staff: root.dataset.labelStaff,
            error: root.dataset.labelError,
            slow: root.dataset.labelSlow,
            retry: root.dataset.labelRetry,
        },
    };

    const speechLocales = { id: 'id-ID', en: 'en-US', ja: 'ja-JP', th: 'th-TH', zh: 'zh-TW' };
    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const micEl = root.querySelector('[data-chat-mic]');
    const voiceEl = root.querySelector('[data-chat-voice]');
    const callEl = root.querySelector('[data-chat-call]');
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
        if (tone === 'error' && text) showcaseCaption(text);
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
        endCall();
        stopListening({ discard: true });
        stopReading();
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

    // Content inside the menu (a hall, a service) was clicked while the chat was open: keep it open.
    if (window.takeKeptChat?.()) openPanel();

    // Amounts from the server are already in the page language's currency.
    function money(value) {
        return formatMoney(value, config.currency);
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
        const tag = { ja: 'ja-JP', en: 'en-US', th: 'th-TH', zh: 'zh-TW' }[config.locale] || 'id-ID';
        return new Intl.DateTimeFormat(tag, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

    function hallResultCard(hall) {
        const el = document.createElement('div');
        el.className = 'chat-card';
        const facts = [config.settings[hall.setting] || '', hall.size_sqm ? `${hall.size_sqm} m²` : '', (hall.min_guests != null ? `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}` : '')].filter(Boolean).join(' · ');
        el.innerHTML = `
            ${hall.thumbnail_url ? `<img src="${esc(hall.thumbnail_url)}" alt="${esc(hall.name)}" class="h-28 w-full object-cover">` : ''}
            <div class="p-3">
                <p class="chat-card-tag">${esc(facts)}</p>
                <p class="mt-1 font-semibold">${esc(hall.name)}</p>
                ${hall.event_date ? `<p class="chat-card-tag mt-1">${esc(longDate(hall.event_date))}</p>` : ''}
                ${hall.total_price != null ? `<p class="mt-1 text-sm font-semibold">${moneyCount(hall.total_price)} <span class="font-normal opacity-60">${esc(config.labels.perEvent)}</span></p>` : ''}
                ${hall.discount_percent > 0 ? `<p class="chat-card-tag mt-1">−${hall.discount_percent}% ${esc(config.labels.discount)}</p>` : ''}
                ${hall.deposit_total > 0 ? `<p class="mt-1 text-xs opacity-70">${esc(config.labels.deposit)}: ${money(hall.deposit_total)}</p>` : ''}
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

    function serviceCard(service) {
        const el = document.createElement('a');
        el.className = 'chat-card';
        el.href = service.url;
        el.innerHTML = `
            ${service.image_url ? `<img src="${esc(service.image_url)}" alt="${esc(service.title)}" class="h-28 w-full object-cover">` : ''}
            <div class="p-3">
                <p class="font-semibold">${esc(service.title)}</p>
                <p class="mt-1 text-xs opacity-70">${esc(service.body)}</p>
            </div>
        `;
        return el;
    }

    function showcaseServices(services) {
        const el = document.createElement('div');
        el.className = 'showcase-results';
        el.innerHTML = `<div class="showcase-grid">${services.map((service, index) => `
            <a class="showcase-card" href="${esc(service.url)}" style="--i:${index}">
                <div class="showcase-card-photo">${service.image_url ? `<img src="${esc(service.image_url)}" alt="${esc(service.title)}">` : ''}</div>
                <div class="showcase-card-body">
                    <h3 class="showcase-card-title">${esc(service.title)}</h3>
                    <p class="text-sm opacity-70">${esc(service.body)}</p>
                </div>
            </a>`).join('')}</div>`;

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
                <p class="chat-card-tag">${esc([config.settings[hall.setting] || '', (hall.min_guests != null ? `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}` : '')].filter(Boolean).join(' · '))}</p>
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
            ${q.grand_total == null ? '' : `${q.discount_percent > 0 ? `<p class="mt-1 text-xs opacity-70"><s>${money(q.subtotal)}</s> · −${q.discount_percent}% ${esc(config.labels.discount)}</p>` : ''}
            <p class="mt-1 text-base font-semibold">${moneyCount(q.grand_total)}</p>
            <p class="mt-1 text-xs opacity-70">${esc(config.labels.deposit)} (${q.deposit_percent}%): ${money(q.deposit_total)}</p>`}
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
            ${b.total_price == null ? '' : `<p class="mt-1 text-sm font-semibold">${moneyCount(b.total_price)}</p>
            <p class="mt-1 text-xs opacity-70">${esc(config.labels.deposit)}: ${money(b.deposit_amount)}</p>`}
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

        // Content inside the menu the client is already in: no curtain, the chat stays open.
        if (link.pathname !== window.location.pathname && window.venueStage?.sameMenu(link)) {
            event.preventDefault();
            window.venueStage.go(link);
            return;
        }

        closePanel({ confirmDraft: false, restoreFocus: false });

        if (link.pathname !== window.location.pathname && window.venueStage) {
            event.preventDefault();
            window.venueStage.leave(link.href);
        }
    }

    const plainText = (text) => String(text).replace(/!\[[^\]]*\]\([^)]*\)/g, '').replace(/\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/[*_`#>]+/g, '').trim();

    let captionText = '';

    function showcaseCaption(text) {
        captionText = text || '';
        const caption = showcaseEl?.querySelector('[data-showcase-caption]');
        if (!caption) return;

        caption.textContent = text || '';
        caption.hidden = !text;
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
        // Arrow keys and spoken "next/previous" step through the photos of whatever the window holds.
        wrap._step = (direction) => body._step?.(direction);
        wrap._canStep = () => typeof body._step === 'function';

        // The photo window covers the chat bar, so the guest can speak from right here.
        if (SpeechRecognitionClass) {
            wrap.classList.add('has-mic');
            const dock = document.createElement('div');
            dock.className = 'showcase-voice';
            dock.innerHTML = `
                <p class="showcase-caption" data-showcase-caption aria-live="polite" hidden></p>
                <button type="button" class="showcase-mic" data-showcase-mic aria-pressed="false" aria-label="${esc(config.labels.call)}" title="${esc(config.labels.call)}">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                </button>`;
            dock.querySelector('[data-showcase-mic]').addEventListener('click', toggleCall);
            wrap.appendChild(dock);
        }

        wrap.addEventListener('click', (event) => {
            if (event.target.closest('[data-showcase-close]')) closeShowcase();
        });

        showcaseReturnFocus = document.activeElement;
        (root.closest('.stage') || document.body).appendChild(wrap);
        showcaseEl = wrap;
        wrap.querySelector('.showcase-close').focus({ preventScroll: true });
        animateCounts(wrap);
        syncShowcaseMic();
        if (!inCall) autoListenWhereAllowed();
        // A window opened by the answer keeps showing that answer; with nothing to show, say how to talk.
        showcaseCaption(captionText || (inCall ? config.labels.micListening : config.labels.talkHint));
    }

    /** Every photo of a hall, big in the middle of the screen, drifting slowly like a film still. */
    function showcaseGallery(hall) {
        const images = hall.images || [];
        const facts = [config.settings[hall.setting] || '', hall.size_sqm ? `${hall.size_sqm} m²` : '', (hall.min_guests != null ? `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}` : '')].filter(Boolean).join(' · ');
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
                ${hall.starting_price != null ? `<p class="showcase-price" style="--i:3">${moneyCount(hall.starting_price)} <span>${esc(config.labels.perEvent)}</span></p>` : (hall.details || []).length ? `<dl class="showcase-facts" style="--i:3">${hall.details.map((fact) => `<div><dt>${esc(fact.label)}</dt><dd>${esc(fact.value)}</dd></div>`).join('')}</dl>` : ''}
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
            const facts = [config.settings[hall.setting] || '', hall.size_sqm ? `${hall.size_sqm} m²` : '', (hall.min_guests != null ? `${hall.min_guests}–${hall.max_guests} ${config.labels.maxGuests}` : '')].filter(Boolean).join(' · ');

            return `
                <article class="showcase-card" style="--i:${index}">
                    <div class="showcase-card-photo">${hall.thumbnail_url ? `<img src="${esc(hall.thumbnail_url)}" alt="${esc(hall.name)}">` : ''}</div>
                    <div class="showcase-card-body">
                        <p class="chat-card-tag">${esc(facts)}</p>
                        <h3 class="showcase-card-title">${esc(hall.name)}</h3>
                        ${hall.event_date ? `<p class="chat-card-tag">${esc(longDate(hall.event_date))}</p>` : ''}
                        ${hall.total_price != null ? `<p class="showcase-card-price">${moneyCount(hall.total_price)} <span>${esc(config.labels.perEvent)}</span></p>` : ''}
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
            } else if (payload.type === 'service_results') {
                wrapper = card(payload.services.map(serviceCard));
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

        const stageful = uiPayload.find((payload) => (payload.type === 'hall_detail' && payload.hall.images?.length) || (payload.type === 'hall_results' && payload.halls?.length) || (payload.type === 'service_results' && payload.services?.length));
        if (stageful) {
            window.setTimeout(() => {
                if (stageful.type === 'hall_detail') {
                    openShowcase(stageful.hall.name, showcaseGallery(stageful.hall));
                } else if (stageful.type === 'service_results') {
                    openShowcase(config.labels.options || '', showcaseServices(stageful.services));
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
        if (micEl) micEl.disabled = disabled;
        if (voiceEl) voiceEl.disabled = !ready;
        if (callEl) callEl.disabled = !ready || handedOver;
        if (disabled) stopListening({ discard: true });
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
        endCall({ quiet: true });
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
                if (link.pathname !== window.location.pathname && window.venueStage?.sameMenu(link)) {
                    event.preventDefault();
                    window.venueStage.go(link);
                    return;
                }

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

    async function sendMessage(text, { retry = false, spoken = false } = {}) {
        if (!text.trim() || handedOver || busy || !ready) return;
        stopReading();

        const clientToken = localStorage.getItem(config.storageKey);
        if (!clientToken) return;

        openPanel();
        if (!retry) messagesEl.appendChild(bubble('client', text));
        window.venueSound?.play('sent');
        scrollToBottom();
        setBusy(true);
        showcaseCaption(config.labels.thinking);

        try {
            const data = await api(config.messageUrl, { client_token: clientToken, message: text, ...uiContext() });
            renderMessage(data.message, true);
            showcaseCaption(data.message.content ? plainText(data.message.content) : '');
            if (inCall) {
                // In a call the concierge always answers aloud, then the mic opens again by itself.
                lastActivity = Date.now();
                if (data.status === 'handed_over') endCall({ quiet: true });
                else if (data.message.content) readAloud(data.message.content, continueCall);
                else continueCall();
            } else if (voiceReplies || spoken) {
                readAloud(data.message.content);
            }
            setChatStatus(config.labels.statusSent, 'sent');
            window.venueSound?.play('incoming');
            knownMessageCount += 2; // the client message just sent + the reply just rendered
            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch (err) {
            messagesEl.appendChild(errorCard(text, err));
            endCall({ quiet: true });
            showcaseCaption('');
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
        endCall();
        stopReading();

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

    // Voice input: the browser's speech recognition turns what the guest says
    // into text, which then goes through the same path as a typed message.
    // The button stays hidden where the browser has no speech recognition.
    const SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let micFailure = null;
    let micDiscard = false;

    function setListening(value) {
        micEl.classList.toggle('is-listening', value);
        micEl.setAttribute('aria-pressed', String(value));
        const label = value ? config.labels.micListening : config.labels.mic;
        micEl.setAttribute('aria-label', label);
        micEl.title = label;
        inputEl.placeholder = value ? config.labels.micListening : config.labels.placeholder;

        syncShowcaseMic();
        if (value) showcaseCaption(config.labels.micListening);
    }

    /** The mic in the photo window mirrors the call: green while hands-free, red while the mic is open. */
    function syncShowcaseMic() {
        const label = inCall ? config.labels.callOn : (recognition ? config.labels.micListening : config.labels.call);
        document.querySelectorAll('[data-showcase-mic]').forEach((button) => {
            button.classList.toggle('is-listening', !!recognition);
            button.classList.toggle('is-calling', inCall);
            button.setAttribute('aria-pressed', String(inCall));
            button.setAttribute('aria-label', label);
            button.title = label;
        });
    }

    function stopListening({ discard = false } = {}) {
        micDiscard = discard;
        if (discard) recognition?.abort();
        else recognition?.stop();
    }

    function startListening() {
        if (!SpeechRecognitionClass || recognition || !ready || busy || handedOver) return;

        stopReading();
        window.dispatchEvent(new CustomEvent('venue:chat-mic-start'));

        const session = new SpeechRecognitionClass();
        const startedAt = Date.now();
        let transcript = '';
        micFailure = null;
        micDiscard = false;
        session.lang = speechLocales[config.locale] || 'en-US';
        session.interimResults = true;
        session.continuous = false;
        session.maxAlternatives = 1;

        let earlyTimer = null;
        session.onresult = (event) => {
            transcript = Array.from(event.results).map((result) => result[0].transcript).join('').trim();
            inputEl.value = transcript;

            // A clear menu/language command is acted on as soon as it is recognised, without the silence timeout.
            window.clearTimeout(earlyTimer);
            const finished = Array.from(event.results).every((result) => result.isFinal !== false);
            if (finished || !decisiveAction(transcript)) return;

            earlyTimer = window.setTimeout(() => {
                if (recognition !== session || busy || handedOver) return;
                const hint = document.querySelector('[data-nav-mic]')?.dataset ?? {};
                if (!performNav(decisiveAction(transcript), { opening: hint.opening, here: hint.here })) return;

                micDiscard = true;
                inputEl.value = '';
                stopReading();
                session.abort();
                if (inCall) continueCall();
            }, earlyDelay(transcript));
        };
        session.onerror = (event) => {
            micFailure = event.error;
        };
        session.onend = () => {
            window.clearTimeout(earlyTimer);
            recognition = null;
            setListening(false);

            if (micDiscard) return;

            if (micFailure === 'not-allowed' || micFailure === 'service-not-allowed') {
                endCall({ quiet: true });
                setChatStatus(config.labels.micDenied, 'error');
            } else if (!transcript && micFailure !== 'aborted') {
                if (inCall) {
                    // Nobody spoke. The mic stays on for a natural conversation, but never open for nothing:
                    // hang up after two minutes of silence, or when the browser keeps refusing to listen.
                    fastFails = Date.now() - startedAt < 1500 ? fastFails + 1 : 0;
                    if (fastFails >= 3 || Date.now() - lastActivity >= CALL_IDLE_MS) endCall();
                    else continueCall();
                } else {
                    setChatStatus(config.labels.micNoSpeech, 'error');
                }
            } else if (transcript && !busy && !handedOver) {
                fastFails = 0;
                lastActivity = Date.now();
                inputEl.value = '';

                if (handleVoiceCommand(transcript)) {
                    // "next photo", "close": done right here, no round trip to the AI.
                    if (inCall) continueCall();
                } else {
                    sendMessage(transcript, { spoken: true });
                }
            }
        };

        recognition = session;
        setListening(true);
        try {
            session.start();
        } catch {
            recognition = null;
            setListening(false);
        }
    }

    function toggleMic() {
        unlockVoice(); // a tap now lets the spoken answer start later on iPhone
        if (recognition) stopListening();
        else startListening();
    }

    if (micEl && SpeechRecognitionClass) {
        micEl.hidden = false;
        micEl.addEventListener('click', toggleMic);
    }

    // Voice replies: the concierge reads its answers aloud (device voice, or the site's cloud voice when switched on).
    // Off by default and remembered per browser; when the guest speaks with the mic the answer is read once regardless.
    const voiceStorageKey = 'venue_voice_reply';
    const voiceLocale = speechLocales[config.locale] || 'en-US';
    let voiceReplies = false;
    try {
        voiceReplies = localStorage.getItem(voiceStorageKey) === 'on';
    } catch {
        voiceReplies = false;
    }

    function stopReading() {
        stopVoice();
        voiceEl?.classList.remove('is-speaking');
    }

    function readAloud(text, then) {
        if (!text || !isOpen) return;

        sayAloud(text, {
            lang: voiceLocale,
            preference: 'female',
            onstart: () => voiceEl?.classList.add('is-speaking'),
            onend: () => {
                voiceEl?.classList.remove('is-speaking');
                lastActivity = Date.now();
                then?.();
            },
        });
    }

    // Voice call: one tap, then talk. The concierge greets, listens, answers aloud and listens again,
    // until the guest hangs up (tap again), closes the chat, or two rounds pass in silence.
    let inCall = false;
    let lastActivity = Date.now();
    let fastFails = 0;
    const CALL_IDLE_MS = 120000;
    const HANDSFREE_KEY = 'venue_handsfree';

    // Short spoken commands for the photo window, matched whole (never inside a longer question) so they run at once.
    const VOICE_COMMANDS = {
        next: [
            /^(?:go )?(?:to )?(?:the )?(?:next|forward|another)(?: (?:one|photo|picture|image|slide))?(?: please)?$/,
            /^(?:foto )?(?:berikutnya|selanjutnya|lanjut|next|foto lagi)(?: (?:ya|dong|tolong))?$/,
            /^(?:もう一[つ枚]|次|つぎ)(?:の)?(?:写真|画像|ページ)?(?:を|へ)?(?:見せて|お願い(?:します)?|ください)?$/,
            /^(?:รูป)?(?:ถัดไป|ต่อไป)(?:ครับ|ค่ะ)?$/,
            /^下一(?:[張张個个頁页]|[張张]?照片)?$/,
        ],
        previous: [
            /^(?:go )?(?:back|(?:to )?(?:the )?previous|before)(?: (?:one|photo|picture|image))?(?: please)?$/,
            /^(?:foto )?(?:sebelumnya|kembali|balik|previous)(?: (?:ya|dong|tolong))?$/,
            /^(?:前|まえ|一つ前|ひとつ前|戻(?:って|る|して)?)(?:の)?(?:写真|画像|ページ)?(?:を|へ)?(?:見せて|お願い(?:します)?|ください)?$/,
            /^(?:รูป)?(?:ก่อนหน้า|ก่อนหน้านี้|ย้อนกลับ)(?:ครับ|ค่ะ)?$/,
            /^上一(?:[張张個个頁页]|[張张]?照片)?$/,
        ],
        close: [
            /^(?:please )?(?:close|exit|dismiss)(?: (?:it|this|the (?:photo|window|picture)s?))?(?: please)?$/,
            /^(?:tutup|selesai)(?: (?:ini|foto|jendela))?(?: (?:ya|dong|tolong))?$/,
            /^(?:閉じて|閉じる|とじて|クローズ|終了|閉じてください)$/,
            /^(?:ปิด)(?:หน้าต่าง|รูป)?(?:ครับ|ค่ะ)?$/,
            /^(?:關閉|关闭|關掉|关掉)(?:視窗|窗口)?$/,
        ],
    };

    function matchVoiceCommand(text) {
        const spaced = String(text).toLowerCase().replace(/[.,!?。、，！？"“”「」]/g, ' ').replace(/\s+/g, ' ').trim();
        const compact = spaced.replace(/\s/g, '');

        return Object.keys(VOICE_COMMANDS).find((name) => VOICE_COMMANDS[name].some((pattern) => pattern.test(spaced) || pattern.test(compact))) || null;
    }

    function handleVoiceCommand(text) {
        // Switching menu or language by voice works from the chat and the call too, even mid-conversation.
        const navigation = interpretNav(text);
        if (navigation && (navigation.type === 'menu' || navigation.type === 'lang')) {
            const hint = document.querySelector('[data-nav-mic]')?.dataset ?? {};
            if (performNav(navigation, { opening: hint.opening, here: hint.here })) {
                stopReading();
                return true;
            }
        }

        const command = matchVoiceCommand(text);
        if (!command || !showcaseEl) return false;

        if (command === 'close') {
            closeShowcase();
            return true;
        }

        if (!showcaseEl._canStep?.()) return false;
        showcaseEl._step(command === 'next' ? 1 : -1);
        showcaseCaption(`“${String(text).trim()}”`);

        return true;
    }

    function renderCallButton() {
        syncShowcaseMic();
        if (!callEl) return;

        const label = inCall ? config.labels.callOn : config.labels.call;
        callEl.classList.toggle('is-calling', inCall);
        callEl.setAttribute('aria-pressed', String(inCall));
        callEl.setAttribute('aria-label', label);
        callEl.title = label;
    }

    function continueCall() {
        if (!inCall) return;
        window.setTimeout(() => {
            if (inCall && !busy && !recognition) startListening();
        }, 350);
    }

    function endCall({ quiet = false } = {}) {
        if (!inCall) return;

        inCall = false;
        try {
            sessionStorage.removeItem(HANDSFREE_KEY);
        } catch {
            // the flag is only a convenience
        }
        stopListening({ discard: true });
        stopReading();
        renderCallButton();
        if (!quiet) setChatStatus(config.labels.callEnded, 'sent');
    }

    function startCall({ resume = false } = {}) {
        if (inCall || !ready || busy || handedOver || !SpeechRecognitionClass) return;

        unlockVoice();
        openPanel();
        inCall = true;
        lastActivity = Date.now();
        fastFails = 0;
        setChatStatus('', '');
        renderCallButton();
        try {
            sessionStorage.setItem(HANDSFREE_KEY, 'on');
        } catch {
            // the flag is only a convenience
        }

        // The greeting is the chat's own intro line; when resuming on another page, or with nothing to say, the mic simply opens.
        if (!resume && knownMessageCount === 0 && config.labels.intro) readAloud(config.labels.intro, continueCall);
        else startListening();
    }

    function toggleCall() {
        if (inCall) endCall();
        else startCall();
    }

    // The mic should never keep listening in a hidden tab: pause there, carry on when the guest comes back.
    document.addEventListener('visibilitychange', () => {
        if (!inCall) return;
        if (document.hidden) {
            stopListening({ discard: true });
            stopReading();
        } else {
            lastActivity = Date.now();
            continueCall();
        }
    });

    // Where the browser already allows the microphone, a photo window starts listening by itself (first time: one tap).
    async function autoListenWhereAllowed() {
        if (!SpeechRecognitionClass || !navigator.permissions?.query) return;

        try {
            const status = await navigator.permissions.query({ name: 'microphone' });
            if (status.state === 'granted' && showcaseEl && !inCall && !busy) startCall({ resume: true });
        } catch {
            // no permission API (Safari): the guest taps the mic
        }
    }

    // Moving to another page in the same visit: pick the conversation up again, but only where the browser
    // already allows the microphone (a first visit always needs one tap).
    async function resumeCall() {
        let wanted = false;
        try {
            wanted = sessionStorage.getItem(HANDSFREE_KEY) === 'on';
        } catch {
            wanted = false;
        }
        if (!wanted || !SpeechRecognitionClass || !navigator.permissions?.query) return;

        try {
            const status = await navigator.permissions.query({ name: 'microphone' });
            if (status.state === 'granted') startCall({ resume: true });
        } catch {
            // no permission API (Safari): the guest taps again
        }
    }

    if (callEl && SpeechRecognitionClass) {
        // A call without a voice to answer would be silent: show the button only where the language can be spoken.
        canSpeakLocale(speechLocales[config.locale] || 'en-US').then((possible) => {
            callEl.hidden = !possible;
        });
        renderCallButton();
        callEl.addEventListener('click', toggleCall);
    }

    function renderVoiceButton() {
        if (!voiceEl) return;

        const label = voiceReplies ? config.labels.voiceOn : config.labels.voice;
        voiceEl.classList.toggle('is-on', voiceReplies);
        voiceEl.setAttribute('aria-pressed', String(voiceReplies));
        voiceEl.setAttribute('aria-label', label);
        voiceEl.title = label;
    }

    if (voiceEl) {
        canSpeakLocale(voiceLocale).then((possible) => {
            voiceEl.hidden = !possible;
            renderVoiceButton();
        });
        voiceEl.addEventListener('click', () => {
            voiceReplies = !voiceReplies;
            try {
                localStorage.setItem(voiceStorageKey, voiceReplies ? 'on' : 'off');
            } catch {
                // remembering the choice is best effort
            }
            renderVoiceButton();
            if (voiceReplies) unlockVoice();
            else stopReading();
        });
    }

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        unlockVoice();
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

    // Voice navigation (header mic): anything that is not a menu command goes to the concierge, answered aloud.
    window.addEventListener('venue:voice-ask', (event) => {
        unlockVoice();
        if (!ready || busy || handedOver) return;
        openPanel();
        sendMessage(event.detail.message, { spoken: true });
    });

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
        if (ready) resumeCall();
    });
}

document.addEventListener('DOMContentLoaded', initConcierge);

function initHallGallery() {
    document.querySelectorAll('[data-hall-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-hall-gallery-main]');
        const thumbs = [...gallery.querySelectorAll('[data-hall-thumb]')];
        if (!main) return;

        const select = (thumb) => {
            main.src = thumb.dataset.src;
            main.alt = thumb.dataset.alt || '';
            thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
        };

        thumbs.forEach((thumb) => thumb.addEventListener('click', () => select(thumb)));

        initHallLightbox(gallery, main, thumbs, select);
    });
}

function parseTags(raw) {
    try {
        const tags = JSON.parse(raw || '[]');
        return Array.isArray(tags) ? tags : [];
    } catch {
        return [];
    }
}

function initHallLightbox(gallery, main, thumbs, select) {
    const zoom = gallery.querySelector('[data-hall-zoom]');
    const box = gallery.querySelector('[data-hall-lightbox]');
    if (!zoom || !box || typeof box.showModal !== 'function') return;

    const image = box.querySelector('[data-lightbox-image]');
    const caption = box.querySelector('[data-lightbox-caption]');
    const sources = thumbs.length
        ? thumbs.map((thumb) => ({ src: thumb.dataset.src, alt: thumb.dataset.alt || '', tags: parseTags(thumb.dataset.tags) }))
        : [{ src: main.src, alt: main.alt, tags: parseTags(main.dataset.tags) }];
    const detail = box.querySelector('[data-lightbox-detail]');
    const info = box.querySelector('[data-lightbox-info]');
    const infoTitle = box.querySelector('[data-info-title]');
    const infoCount = box.querySelector('[data-info-count]');
    const infoTags = box.querySelector('[data-info-tags]');
    const strip = box.querySelector('[data-lightbox-thumbs]');
    let index = 0;

    // A strip of small photos to pick one directly (only when there is more than one).
    const stripButtons = strip && sources.length > 1
        ? sources.map((item, position) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'hall-lightbox-thumb';
            button.setAttribute('aria-label', `${position + 1} / ${sources.length}`);
            const preview = document.createElement('img');
            preview.src = item.src;
            preview.alt = '';
            preview.loading = 'lazy';
            button.append(preview);
            button.addEventListener('click', () => show(position));
            return button;
        })
        : [];
    if (strip && stripButtons.length) {
        strip.append(...stripButtons);
        strip.hidden = false;
    }

    const show = (next) => {
        index = (next + sources.length) % sources.length;
        const item = sources[index];
        image.src = item.src.replace(/([?&])w=\d+/, '$1w=2000');
        image.alt = item.alt;
        caption.textContent = sources.length > 1 ? `${item.alt} (${index + 1}/${sources.length})` : item.alt;
        if (thumbs[index]) select(thumbs[index]);
        stripButtons.forEach((button, position) => {
            button.toggleAttribute('aria-current', position === index);
            if (position === index) button.scrollIntoView({ block: 'nearest', inline: 'center' });
        });

        if (!info) return;
        infoTitle.textContent = item.alt;
        infoCount.textContent = `${index + 1} / ${sources.length}`;
        infoTags.replaceChildren(...item.tags.map((tag) => Object.assign(document.createElement('li'), { textContent: tag })));
        infoTags.hidden = item.tags.length === 0;
    };

    // The photo information shows only while "Detail" is hovered or focused (tap toggles on touch screens).
    // A photo without detail panel (a service) has neither button nor panel.
    if (detail && info) {
        const setInfo = (open) => {
            info.hidden = !open;
            detail.setAttribute('aria-expanded', String(open));
        };
        detail.addEventListener('pointerenter', (event) => { if (event.pointerType !== 'touch') setInfo(true); });
        detail.addEventListener('pointerleave', (event) => { if (event.pointerType !== 'touch') setInfo(false); });
        detail.addEventListener('focus', () => { if (detail.matches(':focus-visible')) setInfo(true); });
        detail.addEventListener('blur', () => setInfo(false));
        detail.addEventListener('click', () => setInfo(info.hidden));
        box.addEventListener('close', () => setInfo(false));
    }

    const current = () => {
        const found = thumbs.findIndex((thumb) => thumb.hasAttribute('aria-current'));
        return found >= 0 ? found : 0;
    };

    zoom.addEventListener('click', () => {
        show(current());
        box.showModal();
    });
    box.querySelector('[data-lightbox-close]')?.addEventListener('click', () => box.close());
    box.querySelector('[data-lightbox-prev]')?.addEventListener('click', () => show(index - 1));
    box.querySelector('[data-lightbox-next]')?.addEventListener('click', () => show(index + 1));
    // Click on the dark backdrop (the dialog itself, not its children) closes it.
    box.addEventListener('click', (event) => {
        if (event.target === box) box.close();
    });
    box.addEventListener('keydown', (event) => {
        if (sources.length < 2) return;
        if (event.key === 'ArrowLeft') show(index - 1);
        if (event.key === 'ArrowRight') show(index + 1);
    });
}

document.addEventListener('DOMContentLoaded', initHallGallery);
