@php
    $field = 'mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm';
@endphp

<x-admin-layout title="Pengaturan gedung">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-8">
        @csrf @method('PUT')

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Gedung</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label for="name" class="block text-sm font-medium text-stone-700">Nama</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $venue->name) }}" required class="{{ $field }}">
                </div>
                <div class="col-span-2">
                    <label for="description" class="block text-sm font-medium text-stone-700">Deskripsi (Indonesia, bawaan)</label>
                    <textarea id="description" name="description" rows="2" class="{{ $field }}">{{ old('description', $venue->description) }}</textarea>
                </div>
                <div>
                    <label for="description_en" class="block text-sm font-medium text-stone-700">Deskripsi (Inggris)</label>
                    <textarea id="description_en" name="translations[en][description]" rows="2" class="{{ $field }}">{{ old('translations.en.description', $venue->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="description_ja" class="block text-sm font-medium text-stone-700">Deskripsi (Jepang)</label>
                    <textarea id="description_ja" name="translations[ja][description]" rows="2" class="{{ $field }}">{{ old('translations.ja.description', $venue->translations['ja']['description'] ?? '') }}</textarea>
                </div>
                <div class="col-span-2">
                    <label for="address" class="block text-sm font-medium text-stone-700">Alamat</label>
                    <input id="address" type="text" name="address" value="{{ old('address', $venue->address) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-stone-700">Kota</label>
                    <input id="city" type="text" name="city" value="{{ old('city', $venue->city) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="country" class="block text-sm font-medium text-stone-700">Negara</label>
                    <input id="country" type="text" name="country" value="{{ old('country', $venue->country) }}" class="{{ $field }}">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Kontak</h2>
            <p class="mt-1 text-xs text-stone-500">Pasangan menghubungi tim pernikahan lewat kontak ini. Ringkasan permintaan tanggal juga dikirim lewat WhatsApp, telepon, atau email.</p>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <label for="phone" class="block text-sm font-medium text-stone-700">Telepon</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $venue->phone) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="whatsapp" class="block text-sm font-medium text-stone-700">WhatsApp</label>
                    <input id="whatsapp" type="text" name="whatsapp" value="{{ old('whatsapp', $venue->whatsapp) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $venue->email) }}" class="{{ $field }}">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Jam acara, bahasa, dan zona waktu</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label for="event_start_time" class="block text-sm font-medium text-stone-700">Acara mulai</label>
                    <input id="event_start_time" type="time" name="event_start_time" value="{{ old('event_start_time', substr((string) $venue->event_start_time, 0, 5)) }}" required class="{{ $field }}">
                </div>
                <div>
                    <label for="event_end_time" class="block text-sm font-medium text-stone-700">Acara selesai</label>
                    <input id="event_end_time" type="time" name="event_end_time" value="{{ old('event_end_time', substr((string) $venue->event_end_time, 0, 5)) }}" required class="{{ $field }}">
                </div>
                <div>
                    <label for="default_locale" class="block text-sm font-medium text-stone-700">Bahasa bawaan</label>
                    <select id="default_locale" name="default_locale" class="{{ $field }}">
                        @foreach (['id' => 'Indonesia', 'en' => 'Inggris', 'ja' => 'Jepang'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('default_locale', $venue->default_locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-stone-700">Zona waktu</label>
                    <input id="timezone" type="text" name="timezone" value="{{ old('timezone', $venue->timezone) }}" required list="timezones" class="{{ $field }}">
                    <datalist id="timezones">
                        @foreach (['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Tokyo', 'Asia/Singapore'] as $zone)
                            <option value="{{ $zone }}">
                        @endforeach
                    </datalist>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Diskon hari kerja & uang muka</h2>
            <p class="mt-1 text-xs text-stone-500">Diskon hari kerja berlaku untuk acara Senin sampai Kamis, baik di formulir reservasi maupun lewat AI concierge. Uang muka adalah bagian dari total yang dibayar pasangan untuk mengamankan tanggal.</p>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label for="weekday_discount_percent" class="block text-sm font-medium text-stone-700">Diskon hari kerja (%)</label>
                    <input id="weekday_discount_percent" type="number" name="weekday_discount_percent" value="{{ old('weekday_discount_percent', $venue->weekday_discount_percent) }}" min="0" max="90" required class="{{ $field }}">
                </div>
                <div>
                    <label for="deposit_percent" class="block text-sm font-medium text-stone-700">Uang muka (%)</label>
                    <input id="deposit_percent" type="number" name="deposit_percent" value="{{ old('deposit_percent', $venue->deposit_percent) }}" min="0" max="100" required class="{{ $field }}">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Visibilitas</h2>
            <div class="mt-4">
                <label for="public_status" class="block text-sm font-medium text-stone-700">Halaman publik</label>
                <select id="public_status" name="public_status" class="{{ $field }} sm:w-60">
                    <option value="published" @selected(old('public_status', $venue->public_status) === 'published')>Terbit — terlihat oleh pasangan</option>
                    <option value="draft" @selected(old('public_status', $venue->public_status) === 'draft')>Draf — disembunyikan dari pasangan</option>
                </select>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Simpan pengaturan</button>
        </div>
    </form>
</x-admin-layout>
