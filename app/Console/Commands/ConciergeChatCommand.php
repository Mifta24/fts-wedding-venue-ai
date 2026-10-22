<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Services\Concierge\ConciergeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('concierge:chat {venue? : Venue slug} {--locale=id : id|en|ja}')]
#[Description('Talk to the AI Concierge for a wedding venue from the terminal, for testing the tool-calling loop before the web UI exists.')]
class ConciergeChatCommand extends Command
{
    public function handle(ConciergeService $service): int
    {
        $venue = $this->argument('venue')
            ? Venue::where('slug', $this->argument('venue'))->first()
            : Venue::first();

        if (! $venue) {
            $this->error('No venue found. Seed one first: php artisan db:seed');

            return self::FAILURE;
        }

        if (blank(config('services.local_llm.base_url'))) {
            $this->error('LOCAL_LLM_BASE_URL is not set in .env — the concierge has no model endpoint to call.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $conversation = $service->startConversation($venue, $locale);

        $this->info("Chatting with the AI Concierge for {$venue->name} ({$locale}). Type 'exit' to quit.");
        $this->newLine();

        while (true) {
            $clientMessage = $this->ask('You');

            if ($clientMessage === null || in_array(trim($clientMessage), ['exit', 'quit'], true)) {
                break;
            }

            $message = $service->reply($venue, $conversation, $clientMessage);

            $this->newLine();
            $this->line('<fg=cyan>Concierge:</> '.($message->content ?? '(no text — see UI payload below)'));

            if ($message->ui_payload) {
                $this->line('<fg=gray>[ui_payload] '.json_encode($message->ui_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</>');
            }

            $conversation->refresh();
            if ($conversation->isHandedOver()) {
                $this->warn('Conversation handed over to human staff. Ending session.');
                break;
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
