<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hall;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HallController extends Controller
{
    use ResolvesCurrentVenue;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $halls = $venue->halls()->withCount('images')->with('images')->orderBy('sort_order')->get();

        return view('admin.halls.index', compact('venue', 'halls'));
    }

    public function create(Request $request): View
    {
        $venue = $this->currentVenue($request);

        return view('admin.halls.form', [
            'venue' => $venue,
            'hall' => new Hall(['setting' => Hall::SETTING_INDOOR, 'min_guests' => 50, 'max_guests' => 300, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        $data = $this->validated($request);

        $hall = $venue->halls()->create($data);
        $this->syncImages($hall, $request);
        $this->activity->record($venue, $request->user(), 'hall.created', "Hall \"{$hall->name}\" ditambahkan.", $hall);

        return redirect()->route('admin.halls.index')->with('status', "Hall \"{$hall->name}\" berhasil ditambahkan.");
    }

    public function edit(Request $request, Hall $hall): View
    {
        $venue = $this->currentVenue($request);
        abort_if($hall->venue_id !== $venue->id, 404);

        $hall->load('images');

        return view('admin.halls.form', compact('venue', 'hall'));
    }

    public function update(Request $request, Hall $hall): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($hall->venue_id !== $venue->id, 404);

        $hall->update($this->validated($request, $hall));
        $this->syncImages($hall, $request);
        $this->activity->record($venue, $request->user(), 'hall.updated', "Hall \"{$hall->name}\" diperbarui.", $hall);

        return redirect()->route('admin.halls.index')->with('status', "Hall \"{$hall->name}\" berhasil diperbarui.");
    }

    public function destroy(Request $request, Hall $hall): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($hall->venue_id !== $venue->id, 404);

        $openBookings = $hall->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->whereDate('event_date', '>=', now($venue->timezone)->toDateString())
            ->count();

        if ($openBookings > 0) {
            return back()->with('error', "\"{$hall->name}\" masih punya {$openBookings} reservasi yang menunggu atau terkonfirmasi. Batalkan dulu, atau sembunyikan hall ini saja.");
        }

        $hall->delete();
        $this->activity->record($venue, $request->user(), 'hall.deleted', "Hall \"{$hall->name}\" dihapus.", $hall);

        return redirect()->route('admin.halls.index')->with('status', 'Hall dihapus.');
    }

    private function validated(Request $request, ?Hall $hall = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'size_sqm' => ['nullable', 'integer', 'min:1'],
            'setting' => ['required', Rule::in(Hall::SETTINGS)],
            'min_guests' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:5000', 'gte:min_guests'],
            'seating_styles' => ['nullable', 'string'],
            'view_type' => ['nullable', 'string', 'max:50'],
            'catering_included' => ['sometimes', 'boolean'],
            'extra_hour_available' => ['sometimes', 'boolean'],
            'extra_hour_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'amenities' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'new_images' => ['nullable', 'array', 'max:20'],
            'new_images.*.url' => ['nullable', 'url:https', 'max:2048'],
            'new_images.*.alt' => ['nullable', 'string', 'max:255'],
            'new_images.*.tags' => ['nullable', 'string', 'max:255'],
        ], [
            'max_guests.gte' => __('admin.halls.max_guests_gte', [], 'id'),
            'new_images.*.url.url' => __('admin.halls.image_url', [], 'id'),
        ]);

        $codes = fn (?string $list) => collect(explode(',', (string) $list))
            ->map(fn ($item) => Str::of($item)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $hall?->slug ?? $this->uniqueSlug($request->user()->currentVenue(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'size_sqm' => $data['size_sqm'] ?? null,
            'setting' => $data['setting'],
            'min_guests' => $data['min_guests'],
            'max_guests' => $data['max_guests'],
            'seating_styles' => $codes($data['seating_styles'] ?? null),
            'view_type' => $data['view_type'] ?? null,
            'catering_included' => $request->boolean('catering_included'),
            'extra_hour_available' => $request->boolean('extra_hour_available'),
            'extra_hour_price' => $data['extra_hour_price'] ?? null,
            'base_price' => $data['base_price'],
            'amenities' => $codes($data['amenities'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($venue, string $name): string
    {
        $base = Str::slug($name) ?: 'hall';
        $slug = $base;
        $suffix = 1;

        while ($venue->halls()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(Hall $hall, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $hall->images()->whereIn('id', $deleteIds)->get()->each->delete();
        }

        $newImages = collect($request->input('new_images', []))
            ->filter(fn ($row) => filled($row['url'] ?? null));

        $sort = $hall->images()->max('sort_order') + 1;
        foreach ($newImages as $row) {
            $hall->images()->create([
                'image_url' => $row['url'],
                'alt_text' => $row['alt'] ?? $hall->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
