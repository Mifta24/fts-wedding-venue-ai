        <div id="concierge-app"
            class="stage-chat"
            data-inline="true"
            data-label-connection-error="{{ $lobby['connection_error'] }}"
            data-venue-slug="{{ $venue->slug }}"
            data-locale="{{ $locale }}"
            data-start-url="{{ route('concierge.start', $venue->slug) }}"
            data-message-url="{{ route('concierge.message', $venue->slug) }}"
            data-history-url="{{ route('concierge.history', $venue->slug) }}"
            data-storage-key="concierge_token_{{ $venue->slug }}"
            data-label-placeholder="{{ $labels['chat_placeholder'] }}"
            data-label-send="{{ $labels['chat_send'] }}"
            data-label-open="{{ $labels['chat_open'] }}"
            data-label-close="{{ $labels['chat_close'] }}"
            data-label-intro="{{ $labels['chat_intro'] }}"
            data-label-catering-included="{{ $labels['catering_included'] }}"
            data-label-max-guests="{{ $labels['max_guests'] }}"
            data-label-catering-excluded="{{ $labels['chat_catering_excluded'] }}"
            data-label-deposit="{{ $labels['chat_deposit'] }}"
            data-label-per-event="{{ $labels['per_event'] }}"
            data-setting-labels="{{ $labels['setting_labels_json'] }}"
            data-event-labels="{{ $labels['event_labels_json'] }}"
            data-label-discount="{{ $labels['chat_discount'] }}"
            data-label-no-availability="{{ $labels['chat_no_availability'] }}"
            data-label-booking-received="{{ $labels['chat_booking_received'] }}"
            data-label-reference="{{ $labels['chat_reference'] }}"
            data-label-view-suffix="{{ $labels['chat_view_suffix'] }}"
            data-label-thinking="{{ $labels['thinking'] }}"
            data-label-handed-over="{{ $labels['handed_over'] }}"
            data-label-status-sent="{{ $labels['chat_status_sent'] }}"
            data-label-status-waiting="{{ $labels['chat_status_waiting'] }}"
            data-label-status-replied="{{ $labels['chat_status_replied'] }}"
            data-label-draft-title="{{ $labels['chat_draft_title'] }}"
            data-label-draft-body="{{ $labels['chat_draft_body'] }}"
            data-label-draft-keep="{{ $labels['chat_draft_keep'] }}"
            data-label-draft-discard="{{ $labels['chat_draft_discard'] }}"
            data-label-view-details="{{ $labels['view_details'] }}"
            data-label-hall-details-question="{{ $labels['hall_details_question'] }}"
            data-label-book-now="{{ $labels['book_now'] }}"
            data-label-menu-heading="{{ $labels['menu_heading'] }}"
            data-label-staff="{{ $labels['menu_staff'] }}"
            data-label-error="{{ $labels['chat_error'] }}"
            data-label-slow="{{ $labels['chat_slow'] }}"
            data-label-retry="{{ $labels['chat_retry'] }}"
            data-currency="{{ $venue->currency }}"
            data-lobby-url="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}"
            data-hall-url="{{ route('venue.hall', ['venueSlug' => $venue->slug, 'hallSlug' => '__SLUG__', 'lang' => $locale]) }}"
            data-staff-url="{{ route('venue.staff', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}"
            data-reservation-url="{{ route('venue.reservation', ['venueSlug' => $venue->slug, 'lang' => $locale, 'hall' => '__SLUG__']) }}"
        >
            <section id="concierge-chat-log" class="chat-log" aria-label="{{ $labels['chat_heading'] }}" aria-hidden="true">
                <header class="chat-log-header">
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="chat-log-title">
                            {{ $labels['chat_heading'] }}
                            <span class="status-dot" aria-hidden="true"></span>
                        </p>
                        <p class="chat-log-subtitle">{{ $labels['chat_subtitle'] }}</p>
                        <p data-chat-status class="chat-status" aria-live="polite" hidden></p>
                    </div>
                    <button type="button" data-chat-close class="chat-log-close" aria-label="{{ $labels['chat_close'] }}"><span aria-hidden="true">×</span></button>
                </header>

                <div data-messages role="log" aria-live="polite" aria-label="{{ $labels['chat_heading'] }}" class="chat-log-messages space-y-3"></div>

                <div data-thinking-indicator class="chat-thinking" role="status" hidden>
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <span class="chat-thinking-bubble">
                        <span class="chat-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span>{{ $labels['thinking'] }}</span>
                    </span>
                </div>

                <div data-status-banner class="hidden border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800"></div>
            </section>

            <form data-chat-form data-chat-composer class="chat-bar" aria-hidden="true">
                <input
                    type="text"
                    data-chat-input
                    aria-label="{{ $labels['chat_placeholder'] }}"
                    maxlength="4000"
                    placeholder="{{ $labels['chat_placeholder'] }}"
                    autocomplete="off"
                >
                <button type="submit" data-chat-submit>{{ $labels['chat_send'] }}</button>
            </form>

            <div data-draft-dialog class="chat-draft-dialog" hidden aria-hidden="true" role="dialog" aria-modal="false" aria-labelledby="chat-draft-title">
                <p id="chat-draft-title" data-draft-title class="font-semibold">{{ $labels['chat_draft_title'] }}</p>
                <p data-draft-body class="mt-1 text-xs leading-relaxed opacity-80">{{ $labels['chat_draft_body'] }}</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" data-draft-keep class="chat-mini-button">{{ $labels['chat_draft_keep'] }}</button>
                    <button type="button" data-draft-discard class="chat-mini-button is-primary">{{ $labels['chat_draft_discard'] }}</button>
                </div>
            </div>

            <button type="button" data-chat-launcher class="chat-launcher" aria-controls="concierge-chat-log" aria-expanded="false">
                <span class="chat-launcher-avatar" aria-hidden="true"></span>
                <span class="chat-launcher-text"><span class="chat-launcher-label">{{ $labels['chat_heading'] }}</span><span>{{ $labels['chat_open'] }}</span></span>
                <span class="chat-launcher-arrow" aria-hidden="true">↗</span>
            </button>

        </div>
