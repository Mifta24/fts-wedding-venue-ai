import { createDatePicker } from './date-picker';

/**
 * Guided date request wizard: five short steps — wedding date, event and
 * guest count, hall, contact, summary — then a reference number and
 * WhatsApp / phone / email hand-over. Progress survives leaving the page and
 * switching language (sessionStorage), and every rule is enforced again on the
 * server, which is the only source of price and availability.
 */
function initReservationWizard() {
    const root = document.querySelector('[data-wizard]');
    if (!root) return;

    const labels = JSON.parse(root.querySelector('[data-wizard-labels]').textContent);
    const form = root.querySelector('[data-wizard-form]');
    const flow = root.querySelector('[data-wizard-flow]');
    const done = root.querySelector('[data-wizard-done]');
    const steps = [...root.querySelectorAll('[data-step]')];
    const progress = [...root.querySelectorAll('[data-progress-step]')];
    const stepLabel = root.querySelector('[data-wizard-step-label]');
    const dateHint = root.querySelector('[data-date-hint]');
    const backButton = root.querySelector('[data-wizard-back]');
    const nextButton = root.querySelector('[data-wizard-next]');
    const submitButton = root.querySelector('[data-wizard-submit]');
    const errorBox = root.querySelector('[data-wizard-error]');
    const errorText = root.querySelector('[data-wizard-error-text]');
    const alternativesBox = root.querySelector('[data-wizard-alternatives]');
    const hallOptions = [...root.querySelectorAll('[data-hall-option]')];
    const extraHoursField = root.querySelector('[data-extra-hours-field]');
    const extraHoursHint = root.querySelector('[data-extra-hours-hint]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const config = {
        quoteUrl: root.dataset.quoteUrl,
        submitUrl: root.dataset.submitUrl,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        today: root.dataset.today,
        weekdayDeal: root.dataset.weekdayDeal === '1',
        draftKey: `reservation_draft_${root.dataset.venueSlug}`,
        tokenKey: `concierge_token_${root.dataset.venueSlug}`,
    };

    const fieldStep = {
        event_date: 1, event_type: 2, guests: 2, hall_slug: 3, extra_hours: 3,
        client_name: 4, contact_type: 4, contact_value: 4, special_request: 4,
    };

    let current = 1;
    let quote = null;
    let busy = false;

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const money = (value) => `${config.currency} ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`;

    function formatDate(iso) {
        const [year, month, day] = iso.split('-').map(Number);
        return new Intl.DateTimeFormat(config.locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    }

    const datePicker = createDatePicker({
        root: root.querySelector('[data-date-picker]'),
        eventDate: form.elements.event_date,
        config,
        labels,
        formatDate,
        onChange: () => {
            clearError();
            form.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });

    function values() {
        const data = new FormData(form);
        return {
            event_date: data.get('event_date') || '',
            event_type: data.get('event_type') || 'akad_reception',
            guests: Number(data.get('guests')) || 0,
            hall_slug: data.get('hall_slug') || '',
            extra_hours: extraHoursField.hidden ? 0 : Number(data.get('extra_hours')) || 0,
            client_name: (data.get('client_name') || '').trim(),
            contact_type: data.get('contact_type') || 'whatsapp',
            contact_value: (data.get('contact_value') || '').trim(),
            special_request: (data.get('special_request') || '').trim(),
        };
    }

    function saveDraft() {
        try {
            sessionStorage.setItem(config.draftKey, JSON.stringify({ step: current, values: values() }));
        } catch { /* storage unavailable: the wizard still works without it */ }
    }

    function clearDraft() {
        try {
            sessionStorage.removeItem(config.draftKey);
        } catch { /* nothing to clear */ }
    }

    function restoreDraft() {
        try {
            const draft = JSON.parse(sessionStorage.getItem(config.draftKey) || 'null');
            if (!draft) return;
            const { values: saved } = draft;
            ['event_date', 'guests', 'client_name', 'contact_value', 'special_request'].forEach((name) => {
                if (saved[name] !== undefined && saved[name] !== '' && saved[name] !== 0) form.elements[name].value = saved[name];
            });
            if (saved.event_type) form.elements.event_type.value = saved.event_type;
            if (saved.contact_type) form.elements.contact_type.value = saved.contact_type;
            if (saved.hall_slug) form.elements.hall_slug.value = saved.hall_slug;
            if (saved.extra_hours) form.elements.extra_hours.value = saved.extra_hours;
            current = Math.min(Math.max(Number(draft.step) || 1, 1), steps.length);
        } catch { /* ignore a corrupt draft */ }
    }

    function clearError() {
        errorBox.hidden = true;
        errorText.textContent = '';
        alternativesBox.hidden = true;
        alternativesBox.querySelector('ul').replaceChildren();
    }

    function showError(message, alternatives = []) {
        errorText.textContent = message;
        errorBox.hidden = false;

        const list = alternativesBox.querySelector('ul');
        list.replaceChildren();
        alternatives.forEach((alternative) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'wizard-secondary';
            button.textContent = `${alternative.name} · ${money(alternative.total)}`;
            button.addEventListener('click', () => {
                form.elements.hall_slug.value = alternative.slug;
                refreshHalls();
                clearError();
                saveDraft();
            });
            item.appendChild(button);
            list.appendChild(item);
        });
        alternativesBox.hidden = alternatives.length === 0;
    }

    function selectedOption() {
        const slug = form.elements.hall_slug.value;
        return hallOptions.find((option) => option.querySelector('input').value === slug) || null;
    }

    function fits(option, guests) {
        return guests >= 1 && guests <= Number(option.dataset.maxGuests);
    }

    function refreshHalls() {
        const { guests } = values();

        hallOptions.forEach((option) => {
            const input = option.querySelector('input');
            const suitable = fits(option, guests);
            input.disabled = !suitable;
            option.classList.toggle('is-disabled', !suitable);
            option.querySelector('[data-hall-hint]').textContent = suitable ? '' : labels.too_small;
            if (!suitable && input.checked) input.checked = false;
        });

        const chosen = selectedOption();
        extraHoursField.hidden = !chosen || chosen.dataset.extraHours !== '1';
        if (extraHoursField.hidden) {
            form.elements.extra_hours.value = '0';
        } else {
            extraHoursHint.textContent = text(labels.extra_hours_hint, { price: money(chosen.dataset.extraHourPrice) });
        }
    }

    function refreshDateHint() {
        const date = form.elements.event_date.value;

        if (date) dateHint.textContent = formatDate(date);
        else if (!datePicker.hasOpenDates) dateHint.textContent = labels.cal_none;
        else dateHint.textContent = labels.cal_pick;
    }

    function validate(step) {
        const data = values();

        if (step === 1) {
            if (!data.event_date) return { field: 'event_date', message: labels.cal_pick };
            if (data.event_date < config.today) return { field: 'event_date', message: labels.date_past };
        }

        if (step === 2) {
            if (data.guests < 1) return { field: 'guests', message: labels.invalid };
        }

        if (step === 3) {
            const chosen = selectedOption();
            if (!chosen) return { field: 'hall_slug', message: labels.select_hall };
            if (!fits(chosen, data.guests)) return { field: 'hall_slug', message: labels.too_small };
        }

        if (step === 4) {
            if (!data.client_name) return { field: 'client_name', message: labels.invalid };
            const valid = data.contact_type === 'email'
                ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_value)
                : /^\+?[0-9\s\-().]{6,20}$/.test(data.contact_value);
            if (!valid) return { field: 'contact_value', message: data.contact_type === 'email' ? labels.contact_email : labels.contact_phone };
        }

        return null;
    }

    function setBusy(value, label = null) {
        busy = value;
        [backButton, nextButton, submitButton].forEach((button) => { button.disabled = value; });
        if (label) nextButton.firstChild.textContent = `${label} `;
        else nextButton.firstChild.textContent = `${labels.next} `;
    }

    function showStep(step, focus = false) {
        current = step;
        steps.forEach((element, index) => { element.hidden = index + 1 !== step; });
        progress.forEach((element, index) => {
            element.classList.toggle('is-current', index + 1 === step);
            element.classList.toggle('is-done', index + 1 < step);
            if (index + 1 === step) element.setAttribute('aria-current', 'step');
            else element.removeAttribute('aria-current');
        });
        stepLabel.textContent = `${text(labels.step_of, { current: step, total: steps.length })} · ${labels.steps[step - 1]}`;
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;

        if (step === 2 || step === 3) refreshHalls();
        if (step === 3 && hallOptions.every((option) => option.classList.contains('is-disabled'))) showError(text(labels.capacity, { max: Math.max(...hallOptions.map((option) => Number(option.dataset.maxGuests))) }));
        if (step === steps.length) renderSummary();
        if (focus) steps[step - 1].querySelector('input:not([type="hidden"]):not([disabled]), textarea, [data-cal-focus]')?.focus({ preventScroll: true });
        saveDraft();
    }

    function goToField(field) {
        showStep(fieldStep[field] || current, true);
    }

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, body };
    }

    function eventPayload() {
        const { event_date, event_type, guests, hall_slug, extra_hours } = values();
        return { event_date, event_type, guests, hall_slug, extra_hours, locale: config.locale };
    }

    function handleFailure(result) {
        if (result.status === 422 && result.body.errors) {
            const [field, messages] = Object.entries(result.body.errors)[0];
            showError(messages[0], result.body.alternatives || []);
            if (fieldStep[field] && fieldStep[field] !== current) showStep(fieldStep[field]);
            return;
        }
        showError(labels.error_generic);
    }

    async function next() {
        if (busy || current >= steps.length) return;
        clearError();

        const problem = validate(current);
        if (problem) {
            showError(problem.message);
            form.elements[problem.field]?.focus?.();
            return;
        }

        if (current === 3) {
            setBusy(true, labels.checking);
            try {
                const result = await post(config.quoteUrl, eventPayload());
                if (!result.ok) {
                    handleFailure(result);
                    return;
                }
                quote = result.body;
            } catch {
                showError(labels.error_network);
                return;
            } finally {
                setBusy(false);
            }
        }

        showStep(current + 1, true);
    }

    function summaryRow(label, value, step) {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        term.textContent = label;
        const detail = document.createElement('dd');
        const span = document.createElement('span');
        span.textContent = value;
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.textContent = labels.edit;
        edit.addEventListener('click', () => showStep(step, true));
        detail.append(span, edit);
        wrapper.append(term, detail);
        return wrapper;
    }

    function renderSummary() {
        const data = values();
        const chosen = selectedOption();

        const rows = [
            summaryRow(labels.dates, new Intl.DateTimeFormat(config.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(...data.event_date.split('-').map((part, index) => (index === 1 ? Number(part) - 1 : Number(part))))), 1),
            summaryRow(labels.event, `${root.querySelector(`input[name="event_type"][value="${data.event_type}"]`)?.nextElementSibling?.textContent ?? ''} · ${data.guests} ${labels.guests_unit}`, 2),
            summaryRow(labels.hall, `${chosen ? chosen.dataset.name : ''}${data.extra_hours > 0 ? ` + ${data.extra_hours} ${labels.extra_hours.toLowerCase().replace(/\s*\(.*\)/, '')}` : ''}`, 3),
            summaryRow(labels.contact, `${data.client_name} · ${labels[data.contact_type]}: ${data.contact_value}`, 4),
        ];
        if (data.special_request) rows.push(summaryRow(labels.special, data.special_request, 4));

        root.querySelector('[data-summary]').replaceChildren(...rows);
        root.querySelector('[data-summary-total]').textContent = quote ? money(quote.grand_total) : '';

        const extra = root.querySelector('[data-summary-extra]');
        const hasExtra = Boolean(quote && quote.extra_hours_total > 0);
        extra.hidden = !hasExtra;
        if (hasExtra) extra.querySelector('[data-summary-extra-amount]').textContent = `+${money(quote.extra_hours_total)}`;

        const discount = root.querySelector('[data-summary-discount]');
        const hasDiscount = Boolean(quote && quote.discount_percent > 0);
        discount.hidden = !hasDiscount;
        if (hasDiscount) {
            discount.querySelector('[data-summary-discount-percent]').textContent = `−${quote.discount_percent}%`;
            discount.querySelector('[data-summary-discount-amount]').textContent = `−${money(quote.discount_total)}`;
        }

        root.querySelector('[data-summary-deposit-label]').textContent = text(labels.deposit, { percent: quote ? quote.deposit_percent : '' });
        root.querySelector('[data-summary-deposit]').textContent = quote ? money(quote.deposit_total) : '';
    }

    function showDone(result) {
        flow.hidden = true;
        done.hidden = false;
        root.querySelector('[data-done-reference]').textContent = result.reference;

        [['whatsapp', 'whatsapp_url'], ['phone', 'phone_url'], ['email', 'email_url']].forEach(([name, key]) => {
            const link = root.querySelector(`[data-done-${name}]`);
            const url = result.handover?.[key];
            link.hidden = !url;
            if (url) link.href = url;
        });
    }

    async function submit(event) {
        event.preventDefault();
        if (busy || current !== steps.length) return;
        clearError();

        const problem = [1, 2, 3, 4].map(validate).find(Boolean);
        if (problem) {
            showError(problem.message);
            goToField(problem.field);
            return;
        }

        setBusy(true);
        submitButton.textContent = labels.sending;

        try {
            const result = await post(config.submitUrl, {
                ...eventPayload(),
                client_name: values().client_name,
                contact_type: values().contact_type,
                contact_value: values().contact_value,
                special_request: values().special_request,
                client_token: localStorage.getItem(config.tokenKey) || undefined,
            });

            if (!result.ok) {
                handleFailure(result);
                return;
            }

            clearDraft();
            showDone(result.body);
        } catch {
            showError(labels.error_network);
        } finally {
            setBusy(false);
            submitButton.textContent = labels.submit;
        }
    }

    function reset() {
        form.reset();
        datePicker.clear();
        quote = null;
        clearError();
        done.hidden = true;
        flow.hidden = false;
        clearDraft();
        refreshDateHint();
        showStep(1, true);
    }

    function preselect(slug) {
        if (!flow.hidden) {
            refreshHalls();
            const option = hallOptions.find((candidate) => candidate.querySelector('input').value === slug);
            if (option && !option.querySelector('input').disabled) {
                form.elements.hall_slug.value = slug;
                refreshHalls();
                saveDraft();
            }
        }
    }

    form.addEventListener('input', () => { refreshDateHint(); if (current <= 3) refreshHalls(); saveDraft(); });
    form.addEventListener('change', () => { refreshHalls(); saveDraft(); });
    form.addEventListener('submit', submit);
    nextButton.addEventListener('click', next);
    backButton.addEventListener('click', () => { clearError(); showStep(Math.max(1, current - 1), true); });
    root.querySelector('[data-wizard-reset]').addEventListener('click', reset);
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && current < steps.length) {
            event.preventDefault();
            next();
        }
    });

    restoreDraft();
    datePicker.show();
    refreshDateHint();

    if (root.dataset.availabilityUrl) {
        fetch(root.dataset.availabilityUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('availability unavailable'))))
            .then((availability) => {
                datePicker.setAvailability(availability.dates);
                refreshDateHint();
            })
            .catch(() => { /* the server still checks the date when the client continues */ });
    }

    refreshHalls();
    showStep(current);
    preselect(root.dataset.preselectHall || '');
}

document.addEventListener('DOMContentLoaded', initReservationWizard);
