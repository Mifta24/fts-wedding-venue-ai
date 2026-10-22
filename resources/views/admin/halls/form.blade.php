@php
    $isEdit = $hall->exists;
@endphp

<x-admin-layout :title="$isEdit ? 'Ubah hall' : 'Tambah hall'">
    <form method="POST" action="{{ $isEdit ? route('admin.halls.update', $hall) : route('admin.halls.store') }}" class="space-y-8">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Informasi dasar</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Nama (Indonesia, bawaan)</label>
                    <input type="text" name="name" value="{{ old('name', $hall->name) }}" required
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Deskripsi (Indonesia, bawaan)</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $hall->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Nama (Inggris)</label>
                    <input type="text" name="translations[en][name]" value="{{ old('translations.en.name', $hall->translations['en']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Nama (Jepang)</label>
                    <input type="text" name="translations[ja][name]" value="{{ old('translations.ja.name', $hall->translations['ja']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Deskripsi (Inggris)</label>
                    <textarea name="translations[en][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.en.description', $hall->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Deskripsi (Jepang)</label>
                    <textarea name="translations[ja][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.ja.description', $hall->translations['ja']['description'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Ruang & kapasitas</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Luas (m²)</label>
                    <input type="number" name="size_sqm" value="{{ old('size_sqm', $hall->size_sqm) }}" min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Suasana</label>
                    <select name="setting" class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm">
                        @foreach (\App\Models\Hall::SETTINGS as $setting)
                            <option value="{{ $setting }}" @selected(old('setting', $hall->setting) === $setting)>{{ \App\Models\Hall::settingLabel($setting) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Tamu minimum</label>
                    <input type="number" name="min_guests" value="{{ old('min_guests', $hall->min_guests) }}" required min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Tamu maksimum</label>
                    <input type="number" name="max_guests" value="{{ old('max_guests', $hall->max_guests) }}" required min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Pemandangan</label>
                    <input type="text" name="view_type" value="{{ old('view_type', $hall->view_type) }}" placeholder="taman, danau, kota…" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Gaya penataan tempat duduk (pisahkan dengan koma)</label>
                    <input type="text" name="seating_styles" value="{{ old('seating_styles', implode(', ', $hall->seating_styles ?? [])) }}" placeholder="banquet, theatre, cocktail, long_table, lounge" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Harga & fasilitas termasuk</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Tarif dasar / acara</label>
                    <input type="number" name="base_price" value="{{ old('base_price', $hall->base_price) }}" required step="1000" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Harga jam tambahan</label>
                    <input type="number" name="extra_hour_price" value="{{ old('extra_hour_price', $hall->extra_hour_price) }}" step="1000" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Urutan</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $hall->sort_order) }}"  class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="catering_included" value="1" @checked(old('catering_included', $hall->catering_included)) class="rounded border-stone-300">
                    Termasuk paket katering
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="extra_hour_available" value="1" @checked(old('extra_hour_available', $hall->extra_hour_available)) class="rounded border-stone-300">
                    Jam tambahan tersedia
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $hall->is_active)) class="rounded border-stone-300">
                    Terbit (tampil di situs)
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-stone-700">Fasilitas (pisahkan dengan koma)</label>
                <input type="text" name="amenities" value="{{ old('amenities', implode(', ', $hall->amenities ?? [])) }}" placeholder="air_conditioning, sound_system, led_screen, stage, bridal_room, parking, generator"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Foto</h2>

            @if ($isEdit && $hall->images->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($hall->images as $image)
                        <div class="flex items-center gap-3 rounded-lg border border-stone-200 p-2">
                            <img src="{{ $image->image_source }}" alt="{{ $image->alt_text }}" class="h-14 w-20 rounded object-cover">
                            <div class="flex-1 text-xs text-stone-500">
                                <p class="truncate">{{ $image->image_url }}</p>
                                <p>Tag: {{ implode(', ', $image->tags ?? []) }}</p>
                            </div>
                            <label class="flex items-center gap-1 text-xs text-red-600">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" class="rounded border-stone-300">
                                Hapus
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs text-stone-500">Tambahkan foto lewat URL (mis. tautan Unsplash atau CDN). Beri tag agar AI Concierge bisa memilih foto yang tepat — mis. "ceremony, decor" atau "reception, exterior".</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="mt-2 grid grid-cols-6 gap-2">
                    <input type="url" name="new_images[{{ $i }}][url]" placeholder="https://…" class="col-span-3 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][tags]" placeholder="ceremony, decor" class="col-span-2 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][alt]" placeholder="Teks alternatif" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            @endfor
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.halls.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-600 hover:bg-stone-50">Batal</a>
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Simpan hall</button>
        </div>
    </form>
</x-admin-layout>
