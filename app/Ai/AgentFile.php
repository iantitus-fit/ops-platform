<?php

namespace App\Ai;

use App\Icm\ActiveConfig;

/**
 * One agent file as code sees it: typed, and read from the live version, never from disk.
 */
readonly class AgentFile
{
    /**
     * @param  list<array{provider: string, model: string}>  $fallback  Each fallback entry, in file order.
     * @param  list<string>  $loads  The folder paths of the files and folders the agent reads.
     */
    public function __construct(
        public string $name,
        public string $channel,
        public string $escalateTo,
        public string $body,
        private string $provider,
        private string $model,
        private array $fallback,
        private array $loads,
    ) {}

    /**
     * The agent that serves a channel in the live version, or null when no agent file serves it.
     */
    public static function forChannel(ActiveConfig $activeConfig, string $channel): ?self
    {
        $agent = $activeConfig->agent($channel);

        return $agent === null ? null : self::fromArray($agent);
    }

    /**
     * @param  array<string, mixed>  $agent  Front matter plus "body", as ActiveConfig::agent() returns it.
     */
    public static function fromArray(array $agent): self
    {
        return new self(
            name: $agent['name'],
            channel: $agent['channel'],
            escalateTo: $agent['escalate_to'],
            body: $agent['body'],
            provider: $agent['provider'],
            model: $agent['model'],
            fallback: array_values(array_map(
                fn (array $entry) => ['provider' => $entry['provider'], 'model' => $entry['model']],
                $agent['fallback'] ?? [],
            )),
            loads: array_values($agent['loads'] ?? []),
        );
    }

    /**
     * Every provider and model to try, the primary first and then each fallback in file order.
     *
     * @return list<array{provider: string, model: string}>
     */
    public function providerChain(): array
    {
        return [['provider' => $this->provider, 'model' => $this->model], ...$this->fallback];
    }

    /**
     * The folder paths the agent loads, as written in its file.
     *
     * @return list<string>
     */
    public function loads(): array
    {
        return $this->loads;
    }
}
