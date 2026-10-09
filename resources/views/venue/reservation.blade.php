<section class="lobby-content wizard-panel stage-panel-right @container" aria-label="{{ $wizard['title'] }}">
    <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
    <p class="lobby-eyebrow">{{ $lobby['chapter'] }} {{ $chapter }} · {{ $venue->name }}</p>
    <h2>{{ $wizard['title'] }}</h2>

    <div class="wizard" data-wizard
        data-quote-url="{{ route('reservation.quote', $venue->slug) }}"
        data-availability-url="{{ route('reservation.availability', $venue->slug) }}"
        data-submit-url="{{ route('reservation.store', $venue->slug) }}"
        data-venue-slug="{{ $venue->slug }}"
        data-locale="{{ $locale }}"
        data-currency="{{ $venue->currency }}"
        data-today="{{ $today }}"
        data-weekday-deal="{{ $venue->weekday_discount_percent > 0 ? 1 : 0 }}"
        data-preselect-hall="{{ $preselectedHall ?? '' }}"
    >
        <div data-wizard-flow>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">{{ $wizard['intro'] }}</p>

            <div class="wizard-progress" aria-label="{{ $wizard['title'] }}">
                <p class="wizard-step-label" data-wizard-step-label aria-live="polite"></p>
                <ol>
                    @foreach ($wizard['steps'] as $stepLabel)
                        <li data-progress-step><span>{{ $stepLabel }}</span></li>
                    @endforeach
                </ol>
            </div>

            <form data-wizard-form novalidate autocomplete="on">
                <div data-step="1" class="wizard-step wizard-step-wide">
                    <input type="hidden" name="event_date" required>

                    <div class="wizard-dates" data-date-picker>
                        <div class="wizard-slots">
                            <div class="wizard-slot" data-slot="event_date"><span>{{ $wizard['event_date'] }}</span><strong data-slot-value>{{ $wizard['cal_choose'] }}</strong></div>
                        </div>
                        <div class="wizard-cal">
                            <div class="wizard-cal-head">
                                <button type="button" data-cal-prev aria-label="{{ $wizard['cal_prev'] }}">←</button>
                                <strong data-cal-title aria-live="polite"></strong>
                                <button type="button" data-cal-next aria-label="{{ $wizard['cal_next'] }}">→</button>
                            </div>
                            <div class="wizard-cal-week" data-cal-weekdays aria-hidden="true"></div>
                            <div class="wizard-cal-grid" data-cal-grid></div>
                        </div>
                        <div class="wizard-dates-extra">
                            <p class="wizard-hint" data-date-hint aria-live="polite"></p>
                            @if ($wizard['weekday_hint'])
                                <p class="wizard-deal"><span aria-hidden="true">%</span>{{ $wizard['weekday_hint'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div data-step="2" class="wizard-step" hidden>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['event_type'] }}</legend>
                        <div class="wizard-pills">
                            @foreach ($eventTypes as $type => $typeLabel)
                                <label><input type="radio" name="event_type" value="{{ $type }}" @checked($type === 'akad_reception')><span>{{ $typeLabel }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field">
                        <span>{{ $wizard['guests'] }}</span>
                        <input type="number" name="guests" min="1" max="{{ $maxGuests }}" value="200" inputmode="numeric" required>
                    </label>
                    <p class="wizard-hint wizard-field-wide">{{ $wizard['guests_hint'] }}</p>
                </div>

                <div data-step="3" class="wizard-step wizard-step-wide" hidden>
                    <fieldset>
                        <legend>{{ $wizard['choose_hall'] }}</legend>
                        <div class="wizard-halls">
                            @foreach ($halls as $hall)
                                <label class="wizard-hall" data-hall-option
                                    data-name="{{ $hall->translatedName($locale) }}"
                                    data-min-guests="{{ $hall->min_guests }}"
                                    data-max-guests="{{ $hall->max_guests }}"
                                    data-extra-hours="{{ $hall->extra_hour_available ? 1 : 0 }}"
                                    data-extra-hour-price="{{ (float) $hall->extra_hour_price }}"
                                >
                                    <input type="radio" name="hall_slug" value="{{ $hall->slug }}">
                                    <span class="wizard-hall-body">
                                        <strong>{{ $hall->translatedName($locale) }}</strong>
                                        <span>{{ str_replace([':min', ':max'], [(string) $hall->min_guests, (string) $hall->max_guests], $wizard['fits']) }}</span>
                                        <span class="wizard-hall-price">{{ $labels['from'] }} {{ $venue->currency }} {{ number_format((float) $hall->base_price, 0, ',', '.') }} {{ $labels['per_event'] }}</span>
                                        <span class="wizard-hall-hint" data-hall-hint></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field" data-extra-hours-field hidden>
                        <span>{{ $wizard['extra_hours'] }}</span>
                        <select name="extra_hours">
                            @for ($hours = 0; $hours <= $maxExtraHours; $hours++)
                                <option value="{{ $hours }}">{{ $hours }}</option>
                            @endfor
                        </select>
                        <small data-extra-hours-hint></small>
                    </label>
                </div>

                <div data-step="4" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['name'] }}</span>
                        <input type="text" name="client_name" maxlength="100" autocomplete="name" required>
                    </label>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['contact_method'] }}</legend>
                        <div class="wizard-pills">
                            @foreach (['whatsapp', 'phone', 'email'] as $method)
                                <label><input type="radio" name="contact_type" value="{{ $method }}" @checked($method === 'whatsapp')><span>{{ $wizard[$method] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['contact_value'] }}</span>
                        <input type="text" name="contact_value" maxlength="120" autocomplete="tel" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['special'] }}</span>
                        <textarea name="special_request" rows="2" maxlength="500" placeholder="{{ $wizard['special_placeholder'] }}"></textarea>
                    </label>
                </div>

                <div data-step="5" class="wizard-step wizard-step-wide" hidden>
                    <dl class="wizard-summary" data-summary></dl>
                    <p class="wizard-discount" data-summary-extra hidden><span>{{ $wizard['extra_hours_total'] }}</span> <strong data-summary-extra-amount></strong></p>
                    <p class="wizard-discount" data-summary-discount hidden><span>{{ $wizard['discount'] }} <b data-summary-discount-percent></b></span> <strong data-summary-discount-amount></strong></p>
                    <p class="wizard-total"><span>{{ $wizard['estimated_total'] }}</span> <strong data-summary-total></strong></p>
                    <p class="wizard-deposit"><span data-summary-deposit-label></span> <strong data-summary-deposit></strong></p>
                    <p class="wizard-hint">{{ $wizard['disclaimer'] }}</p>
                </div>

                <div class="wizard-error" data-wizard-error role="alert" hidden>
                    <p data-wizard-error-text></p>
                    <div class="wizard-alternatives" data-wizard-alternatives hidden>
                        <p>{{ $wizard['available_instead'] }}</p>
                        <ul></ul>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="wizard-secondary" data-wizard-back hidden>{{ $wizard['back'] }}</button>
                    <button type="button" class="lobby-action" data-wizard-next>{{ $wizard['next'] }} <span aria-hidden="true">→</span></button>
                    <button type="submit" class="lobby-action" data-wizard-submit hidden>{{ $wizard['submit'] }}</button>
                </div>
            </form>
        </div>

        <div class="wizard-done" data-wizard-done hidden>
            <p class="lobby-eyebrow">{{ $wizard['done_title'] }}</p>
            <p class="wizard-reference-label">{{ $wizard['reference'] }}</p>
            <p class="wizard-reference" data-done-reference></p>
            <p class="wizard-status">{{ $wizard['awaiting'] }}</p>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-stone-600">{{ $wizard['done_hint'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="lobby-action" data-done-whatsapp target="_blank" rel="noopener" hidden>{{ $wizard['send_whatsapp'] }} <span aria-hidden="true">↗</span></a>
                <a class="wizard-secondary" data-done-phone hidden>{{ $wizard['call_venue'] }}</a>
                <a class="wizard-secondary" data-done-email hidden>{{ $wizard['email_venue'] }}</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="wizard-secondary">{{ $lobby['back'] }}</a>
                <button type="button" class="wizard-secondary" data-wizard-reset>{{ $wizard['new_request'] }}</button>
            </div>
        </div>

        <script type="application/json" data-wizard-labels>@json($wizard)</script>
    </div>
</section>
