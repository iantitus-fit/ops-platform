<?php

namespace App\SendRule;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks Jev (TypeSafe's classifier, through OpenRouter's System One endpoint) the owner's triage questions
 * in one request per reply. Jev receives the customer's message and the drafted reply, never a name, a phone
 * number or a customer card.
 *
 * It fails closed: every way Jev can go wrong (no key, an unpinned model, an HTTP error, a timeout, a body that
 * is not JSON, a missing or mistyped answer) throws ClassifierFailed, which the send rule turns into a held
 * draft. It never retries, because a retry only delays the owner's draft.
 */
class JevClassifier implements Classifier
{
    /**
     * @throws ClassifierFailed
     */
    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        $config = config('services.jev');
        $key = trim((string) ($config['key'] ?? ''));
        $model = (string) ($config['model'] ?? '');

        if ($key === '') {
            throw new ClassifierFailed('No Jev key is set (JEV_API_KEY), so the reply cannot be checked.');
        }

        if ($model === '' || str_contains(strtolower($model), 'latest')) {
            throw new ClassifierFailed('The Jev model must be pinned to a version, never a "latest" alias.');
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->connectTimeout((int) $config['connect_timeout'])
                ->timeout((int) $config['timeout'])
                ->post(rtrim((string) $config['base_url'], '/').'/systemone', [
                    'model' => $model,
                    'state' => ['customer_message' => $customerMessage, 'draft_reply' => $draftReply],
                    'questions' => $questions->toJevQuestions(),
                ])
                ->throw();
        } catch (ConnectionException $exception) {
            throw $this->failed('Jev could not be reached or took too long to answer.', $exception);
        } catch (RequestException $exception) {
            throw $this->failed('Jev answered with HTTP status '.$exception->response->status().'.', $exception);
        }

        try {
            $result = $this->read($response, $questions);
        } catch (ClassifierFailed $exception) {
            Log::warning('Jev gave an answer that could not be used', ['reason' => $exception->getMessage()]);

            throw $exception;
        }

        Log::info('Jev classified a reply', [
            'status' => $response->status(),
            'customer_message_length' => mb_strlen($customerMessage),
            'draft_reply_length' => mb_strlen($draftReply),
        ]);

        return $result;
    }

    /**
     * Read Jev's answers back. Every question asked must come back with a usable answer, or the whole call counts
     * as failed: a choice among the options sent with a confidence from 0 to 1, and a number from 0 to 1 for each
     * yes or no question. A `type` that is present must be the type asked; a missing `type` is accepted when the
     * answer has the right shape.
     *
     * @throws ClassifierFailed
     */
    private function read(Response $response, TriageQuestions $questions): ClassifierResult
    {
        $body = $response->json();
        $answers = is_array($body) ? ($body['answers'] ?? null) : null;

        if (! is_array($answers)) {
            throw new ClassifierFailed('Jev answered, but the body had no answers to read.');
        }

        $intent = $this->answerFor($answers, 'intent', 'choice');
        $choice = $intent['choice'] ?? null;

        if (! is_string($choice) || ! array_key_exists($choice, $questions->intents)) {
            throw new ClassifierFailed('Jev chose an intent that was not one of the options sent.');
        }

        $yesNo = [];

        foreach (array_keys($questions->yesNo) as $key) {
            $answer = $this->answerFor($answers, (string) $key, 'noul');
            $yesNo[$key] = $this->fraction($answer['noul'] ?? null, (string) $key);
        }

        $probabilities = [];

        foreach (is_array($intent['probabilities'] ?? null) ? $intent['probabilities'] : [] as $option => $probability) {
            if (is_int($probability) || is_float($probability)) {
                $probabilities[$option] = (float) $probability;
            }
        }

        return new ClassifierResult(
            model: is_string($body['model'] ?? null) ? $body['model'] : '',
            intent: $choice,
            intentConfidence: $this->fraction($intent['confidence'] ?? null, 'intent confidence'),
            intentProbabilities: $probabilities,
            yesNo: $yesNo,
        );
    }

    /**
     * One question's answer, which must exist and, when it names a type, name the type that was asked.
     *
     * @param  array<array-key, mixed>  $answers
     * @return array<array-key, mixed>
     *
     * @throws ClassifierFailed
     */
    private function answerFor(array $answers, string $key, string $type): array
    {
        $answer = $answers[$key] ?? null;

        if (! is_array($answer)) {
            throw new ClassifierFailed("Jev gave no answer for {$key}.");
        }

        if (array_key_exists('type', $answer) && $answer['type'] !== $type) {
            throw new ClassifierFailed("Jev's answer for {$key} was not a {$type} answer.");
        }

        return $answer;
    }

    /**
     * A number from 0 to 1. Text such as "0.1" is refused: only a JSON number counts.
     *
     * @throws ClassifierFailed
     */
    private function fraction(mixed $value, string $name): float
    {
        if ((! is_int($value) && ! is_float($value)) || $value < 0 || $value > 1) {
            throw new ClassifierFailed("Jev's answer for {$name} was not a number from 0 to 1.");
        }

        return (float) $value;
    }

    /**
     * A failure that carries what went wrong, never the key or the texts sent.
     */
    private function failed(string $reason, Throwable $previous): ClassifierFailed
    {
        Log::warning('Jev could not classify a reply', ['reason' => $reason]);

        return new ClassifierFailed($reason, 0, $previous);
    }
}
