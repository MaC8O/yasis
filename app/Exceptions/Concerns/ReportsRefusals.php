<?php

namespace App\Exceptions\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * How a business-rule refusal is logged. The person always sees the same warning on the page
 * (the exception's render()); this only decides what goes to laravel.log.
 *
 * - Normal refusals (`new ...(message)`): a refresh, a double click, two tabs, a page that went
 *   stale while someone else acted, a value past a limit. Expected answers, so nothing is logged.
 * - Unexpected refusals ({@see unexpected()}): an action the pages never offer in that state, so
 *   reaching it takes a hand-made request or a page bug. One WARNING line with the user ID and a
 *   fixed action key only. Never the message (it can name people), never a stack trace.
 *
 * Laravel calls report() instead of its default reporting, which wrote an ERROR with the full
 * message, the user ID and a stack trace.
 */
trait ReportsRefusals
{
    private ?string $unexpectedAction = null;

    /** @param string $action a fixed key such as "account.deactivate_self": no names, no IDs */
    public static function unexpected(string $action, string $message): static
    {
        $refusal = new static($message);
        $refusal->unexpectedAction = $action;

        return $refusal;
    }

    public function unexpectedAction(): ?string
    {
        return $this->unexpectedAction;
    }

    public function report(): void
    {
        if ($this->unexpectedAction !== null) {
            Log::warning('Refused a request the page does not offer.', [
                'user_id' => auth()->id(),
                'action' => $this->unexpectedAction,
            ]);
        }
    }
}
