<?php

namespace App\Console\Commands;

use App\Enums\DraftResolution;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Message;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints every send-or-draft decision the front desk made, with what the owner did about it, as JSON lines (D-07).
 *
 * One line is one decision: the agent's reply, the verdict that sent or held it, and, for a held draft, whether the
 * owner approved it, edited it, discarded it or a newer draft replaced it. Together they are the labeled data the
 * thresholds are tuned from later. Holding, handoff and life-safety texts are not decisions and are left out.
 *
 * The app writes no file: the lines go to standard output and the owner redirects them to a file.
 * Without --without-text the lines hold the customers' own words, so keep that file private and share only an export
 * made with --without-text.
 */
#[Signature('gate:export {--since= : Only decisions on or after this date (YYYY-MM-DD)} {--without-text : Leave out every customer and reply text}')]
#[Description("Print the front desk's send-or-draft decisions and the owner's actions as JSON lines (D-07)")]
class GateExport extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $since = null;

        if ($this->option('since') !== null) {
            $since = $this->parseSince((string) $this->option('since'));

            if ($since === null) {
                $this->error('The --since date must look like YYYY-MM-DD, for example 2026-10-01.');

                return self::FAILURE;
            }
        }

        $withoutText = (bool) $this->option('without-text');

        Message::query()
            ->where('kind', MessageKind::AgentReply->value)
            ->whereNotNull('gate_outcome')
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->lazyById()
            ->each(function (Message $message) use ($withoutText): void {
                $line = json_encode(
                    $this->decision($message, $withoutText),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );

                // Raw, so a customer's "<info>" is printed as written and not read as console formatting.
                $this->getOutput()->write($line.PHP_EOL, false, OutputInterface::OUTPUT_RAW);
            });

        return self::SUCCESS;
    }

    /**
     * The start of the given day in UTC, or null when the text is not a real date written YYYY-MM-DD.
     */
    private function parseSince(string $text): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $text ? $date : null;
    }

    /**
     * One decision as the array that becomes a line.
     *
     * The final text is what went out, so a draft nobody sent has none. The original text is the agent's own words:
     * the saved original when the owner edited the draft, otherwise the message body.
     *
     * @return array<string, mixed>
     */
    private function decision(Message $message, bool $withoutText): array
    {
        $gate = $message->gate ?? [];
        $finalText = in_array($message->status, [MessageStatus::Draft, MessageStatus::Withdrawn], true) ? null : $message->body;
        $originalText = $message->original_body ?? $message->body;

        $line = [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'decided_at' => $message->created_at->utc()->toIso8601String(),
            'outcome' => $message->gate_outcome->value,
            'reason_code' => $message->gate_reason,
            'reason_plain' => $gate['reason']['plain'] ?? null,
            'label' => $message->label?->value,
            'verdict' => $gate,
        ];

        if ($withoutText) {
            $line['verdict'] = $this->withoutText($gate);
            $line['final_text_length'] = $finalText === null ? null : mb_strlen($finalText);
            $line['original_text_length'] = mb_strlen($originalText);
        } else {
            $line['final_text'] = $finalText;
            $line['original_text'] = $originalText;
        }

        return [
            ...$line,
            'resolution' => $message->resolution?->value,
            'resolved_at' => $message->resolved_at?->utc()->toIso8601String(),
            'resolved_by' => $message->resolved_by,
            'owner_edited' => $message->resolution === DraftResolution::Edited,
        ];
    }

    /**
     * The stored verdict with every piece of text replaced by its length: the customer's words, the agent's words,
     * the words a code check matched, and the error messages, which a provider can fill with the text it was sent.
     *
     * @param  array<string, mixed>  $gate
     * @return array<string, mixed>
     */
    private function withoutText(array $gate): array
    {
        $gate['inputs'] = $this->lengthsFor($gate['inputs'] ?? [], ['customer_text', 'agent_reply']);

        foreach ($gate['checks']['code']['findings'] ?? [] as $index => $finding) {
            $gate['checks']['code']['findings'][$index] = $this->lengthsFor($finding, ['match']);
        }

        if (isset($gate['checks']['classifier'])) {
            $gate['checks']['classifier'] = $this->lengthsFor($gate['checks']['classifier'], ['error']);
        }

        foreach ($gate['failed_attempts'] ?? [] as $index => $attempt) {
            $gate['failed_attempts'][$index] = $this->lengthsFor($attempt, ['error']);
        }

        return $gate;
    }

    /**
     * The given keys swapped for `<key>_length`, wherever they hold text. A key that holds nothing stays as it is.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function lengthsFor(array $values, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_string($values[$key])) {
                $values["{$key}_length"] = mb_strlen($values[$key]);
                unset($values[$key]);
            }
        }

        return $values;
    }
}
