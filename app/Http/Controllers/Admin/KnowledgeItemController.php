<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\VenueKnowledgeItem;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeItemController extends Controller
{
    use ResolvesCurrentVenue;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $items = $venue->knowledgeItems()->orderBy('category')->orderBy('sort_order')->get();

        return view('admin.knowledge-items.index', compact('venue', 'items'));
    }

    public function create(Request $request): View
    {
        $venue = $this->currentVenue($request);

        return view('admin.knowledge-items.form', [
            'venue' => $venue,
            'item' => new VenueKnowledgeItem(['category' => VenueKnowledgeItem::CATEGORY_GENERAL, 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = $this->currentVenue($request);

        $item = $venue->knowledgeItems()->create($this->validated($request));
        $this->activity->record($venue, $request->user(), 'knowledge.created', "Entri \"{$item->title}\" ditambahkan.", $item);

        return redirect()->route('admin.knowledge-items.index')->with('status', "Entri \"{$item->title}\" berhasil ditambahkan.");
    }

    public function edit(Request $request, VenueKnowledgeItem $knowledgeItem): View
    {
        $venue = $this->currentVenue($request);
        abort_if($knowledgeItem->venue_id !== $venue->id, 404);

        return view('admin.knowledge-items.form', [
            'venue' => $venue,
            'item' => $knowledgeItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, VenueKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($knowledgeItem->venue_id !== $venue->id, 404);

        $knowledgeItem->update($this->validated($request));
        $this->activity->record($venue, $request->user(), 'knowledge.updated', "Entri \"{$knowledgeItem->title}\" diperbarui.", $knowledgeItem);

        return redirect()->route('admin.knowledge-items.index')->with('status', "Entri \"{$knowledgeItem->title}\" berhasil diperbarui.");
    }

    public function destroy(Request $request, VenueKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($knowledgeItem->venue_id !== $venue->id, 404);

        $knowledgeItem->delete();
        $this->activity->record($venue, $request->user(), 'knowledge.deleted', "Entri \"{$knowledgeItem->title}\" dihapus.", $knowledgeItem);

        return redirect()->route('admin.knowledge-items.index')->with('status', 'Entri dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', $this->categories())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'translations.en.title' => ['nullable', 'string'],
            'translations.en.body' => ['nullable', 'string'],
            'translations.ja.title' => ['nullable', 'string'],
            'translations.ja.body' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'],
            'translations' => [
                'en' => ['title' => $data['translations']['en']['title'] ?? null, 'body' => $data['translations']['en']['body'] ?? null],
                'ja' => ['title' => $data['translations']['ja']['title'] ?? null, 'body' => $data['translations']['ja']['body'] ?? null],
            ],
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->values()->all(),
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function categories(): array
    {
        return [
            VenueKnowledgeItem::CATEGORY_GENERAL,
            VenueKnowledgeItem::CATEGORY_SERVICES,
            VenueKnowledgeItem::CATEGORY_CATERING,
            VenueKnowledgeItem::CATEGORY_POLICIES,
            VenueKnowledgeItem::CATEGORY_ACCESS,
            VenueKnowledgeItem::CATEGORY_FAQ,
        ];
    }
}
