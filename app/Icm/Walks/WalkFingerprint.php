<?php

namespace App\Icm\Walks;

use App\Ai\AgentFile;
use App\Ai\FrontDeskPrompt;
use App\Icm\ActiveConfig;
use App\SendRule\TriageQuestions;

/**
 * The two hashes a recording is matched with.
 *
 * The script hash covers a walk's customer turns and nothing else (10 D-05): a changed customer turn makes the recording
 * no longer match the walk, while a changed check, persona, wait, booking or start does not, because none of those
 * changes what the model or Jev was asked.
 *
 * The fingerprint covers what the front desk model and Jev read that does not change from run to run (10 D-04): the
 * front desk prompt built from the candidate, and the questions Jev is asked. A recording made against another
 * fingerprint is stale.
 */
class WalkFingerprint
{
    /**
     * The hash of the customer's texts in script order, joined by a NUL byte, each text as exact bytes: no trimming and
     * no case folding, so even one changed space is a new conversation.
     */
    public static function scriptHash(WalkFile $walk): string
    {
        return hash('sha256', implode("\0", $walk->customerTurns()));
    }

    /**
     * The hash of the prompt material and the Jev questions. A NUL byte stands between them, so the end of one cannot be
     * mistaken for the start of the other.
     *
     * @param  array<string, mixed>  $jevQuestions  TriageQuestions::toJevQuestions().
     */
    public static function of(string $promptMaterial, array $jevQuestions): string
    {
        return hash('sha256', $promptMaterial."\0".json_encode($jevQuestions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * The fingerprint of the live version, which inside a walk's world is the candidate: the prompt material of the agent
     * that answers texts (empty when no agent file does) and the Jev questions from rules/triage.md.
     */
    public static function forActiveConfig(): string
    {
        $config = app(ActiveConfig::class);
        $agent = AgentFile::forChannel($config, 'sms');
        $material = $agent === null ? '' : app(FrontDeskPrompt::class)->walkFingerprintMaterial($agent);

        return self::of($material, TriageQuestions::fromConfig($config->triage())->toJevQuestions());
    }
}
