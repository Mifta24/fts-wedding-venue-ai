<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\HandoverRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('conversations:prune {--days= : Delete conversations idle for more than this many days (default: CONCIERGE_RETENTION_DAYS, 90)} {--dry-run : Only count what would be deleted}')]
#[Description('Delete old concierge conversations, their messages and resolved handovers, so client names and phone numbers are not kept forever. Conversations with an open handover are kept.')]
class PruneConversations extends Command
{
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('services.concierge.retention_days'));

        if ($days < 1) {
            $this->error('The retention period must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $stale = Conversation::query()
            ->whereRaw('coalesce(last_message_at, created_at) < ?', [$cutoff])
            ->whereDoesntHave('handoverRequest', fn ($query) => $query->where('status', HandoverRequest::STATUS_OPEN));

        $count = (clone $stale)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} conversation(s) idle for more than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;

        (clone $stale)->select('id')->chunkById(500, function ($conversations) use (&$deleted) {
            $deleted += Conversation::whereKey($conversations->modelKeys())->delete();
        });

        $this->info("Deleted {$deleted} conversation(s) idle for more than {$days} days.");

        return self::SUCCESS;
    }
}
