<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Venue;
use App\Services\Concierge\ConciergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConciergeChatController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly ConciergeService $concierge) {}

    public function start(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);

        $locale = in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $venue->default_locale;

        $conversation = $this->concierge->startConversation($venue, $locale);

        return response()->json([
            'client_token' => $conversation->client_token,
            'locale' => $conversation->locale,
        ]);
    }

    public function message(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);

        $data = $request->validate([
            'client_token' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'scene' => ['nullable', Rule::in(Conversation::SCENES)],
            'selected_hall' => ['nullable', 'string', 'max:120'],
            'selected_service' => ['nullable', 'integer', 'min:1'],
            'reservation' => ['nullable', 'array'],
            'reservation.event_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.event_type' => ['nullable', Rule::in(Booking::EVENT_TYPES)],
            'reservation.guests' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'reservation.extra_hours' => ['nullable', 'integer', 'min:0', 'max:4'],
            'reservation.hall_slug' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $this->findConversation($venue, $data['client_token']);

        $this->concierge->rememberContext($venue, $conversation, [
            'scene' => $data['scene'] ?? null,
            'selected_service' => $data['selected_service'] ?? null,
            'selected_hall' => $data['selected_hall'] ?? ($data['reservation']['hall_slug'] ?? null),
            ...array_key_exists('reservation', $data) ? ['reservation' => array_filter($data['reservation'] ?? [], fn ($value) => $value !== null && $value !== '')] : [],
        ]);

        try {
            $assistantMessage = $this->concierge->reply($venue, $conversation, $data['message']);
        } catch (\Throwable $e) {
            Log::error('Concierge reply failed', ['venue_id' => $venue->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'concierge_unavailable',
                'message' => 'The AI Concierge is temporarily unavailable. Please try again in a moment.',
            ], 503);
        }

        return response()->json([
            'message' => $this->formatMessage($assistantMessage),
            'status' => $conversation->fresh()->status,
        ]);
    }

    public function history(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);

        $data = $request->validate(['client_token' => ['required', 'uuid']]);

        $conversation = $this->findConversation($venue, $data['client_token']);

        $messages = $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_CLIENT, ConversationMessage::ROLE_ASSISTANT, ConversationMessage::ROLE_SYSTEM])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationMessage $m) => $this->formatMessage($m))
            ->values();

        return response()->json([
            'messages' => $messages,
            'status' => $conversation->status,
        ]);
    }

    private function publishedVenue(string $venueSlug): Venue
    {
        $venue = Venue::where('slug', $venueSlug)->first();

        abort_if(! $venue || ! $venue->isPublished(), 404);

        return $venue;
    }

    private function findConversation(Venue $venue, string $clientToken): Conversation
    {
        $conversation = Conversation::where('venue_id', $venue->id)
            ->where('client_token', $clientToken)
            ->first();

        if (! $conversation) {
            throw ValidationException::withMessages([
                'client_token' => 'This conversation no longer exists. Please start a new one.',
            ]);
        }

        return $conversation;
    }

    private function formatMessage(ConversationMessage $message): array
    {
        return [
            'role' => $message->role,
            'content' => $message->content,
            'ui_payload' => $message->ui_payload,
            'suggested_actions' => $this->suggestedActions($message->ui_payload),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Interface actions that follow from what the concierge just showed, so
     * the client can step into the matching scene instead of typing again.
     *
     * @param  list<array<string, mixed>>|null  $uiPayload
     * @return list<array{action: string, hall?: string}>
     */
    private function suggestedActions(?array $uiPayload): array
    {
        $actions = [];

        foreach ($uiPayload ?? [] as $payload) {
            $type = $payload['type'] ?? null;
            $hall = $payload['hall']['hall_slug'] ?? $payload['quote']['hall_slug'] ?? null;

            if ($type === 'hall_detail' && $hall) {
                $actions[] = ['action' => 'view_hall', 'hall' => $hall];
                $actions[] = ['action' => 'reserve', 'hall' => $hall];
            } elseif ($type === 'availability' && ($payload['available'] ?? false) && $hall) {
                $actions[] = ['action' => 'reserve', 'hall' => $hall];
            } elseif ($type === 'handover') {
                $actions[] = ['action' => 'staff'];
            }
        }

        return collect($actions)->unique(fn (array $action) => $action['action'].($action['hall'] ?? ''))->values()->all();
    }
}
