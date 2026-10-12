<?php

namespace App\SendRule;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks Jev (TypeSafe's classifier, through OpenRouter's System One endpoint) the owner's triage questions
 * in one request per reply. Jev receives the customer's message and the drafted reply, never a name, a phone
 * number or a customer card.
 */
class JevClassifier implements Classifier
{
    /**
     * @throws ClassifierFailed
     */
    public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
    {
        $config = config('services.jev');
        $model = (string) ($config['model'] ?? '');

        if ($model === '' || str_contains(strtolower($model), 'latest')) {
            throw new ClassifierFailed('The Jev model must be pinned to a version, never a "latest" alias.');
        }

        $response = Http::withToken((string) $config['key'])
            ->acceptJson()
            ->connectTimeout((int) $config['connect_timeout'])
            ->timeout((int) $config['timeout'])
            ->post(rtrim((string) $config['base_url'], '/').'/systemone', [
                'model' => $model,
                'state' => ['customer_message' => $customerMessage, 'draft_reply' => $draftReply],
                'questions' => $questions->toJevQuestions(),
            ])
            ->throw();

        Log::info('Jev classified a reply', [
            'status' => $response->status(),
            'customer_message_length' => mb_strlen($customerMessage),
            'draft_reply_length' => mb_strlen($draftReply),
        ]);

        return $this->read($response->json() ?? [], $questions);
    }

    /**
     * Read Jev's answers back. Plan 02-07 makes every missing or mistyped answer a failure.
     *
     * @param  array<string, mixed>  $body
     */
    private function read(array $body, TriageQuestions $questions): ClassifierResult
    {
        $answers = $body['answers'] ?? [];
        $intent = $answers['intent'] ?? [];

        $yesNo = [];

        foreach (array_keys($questions->yesNo) as $key) {
            $yesNo[$key] = (float) ($answers[$key]['noul'] ?? 0.0);
        }

        return new ClassifierResult(
            model: (string) ($body['model'] ?? ''),
            intent: (string) ($intent['choice'] ?? ''),
            intentConfidence: (float) ($intent['confidence'] ?? 0.0),
            intentProbabilities: array_map(floatval(...), $intent['probabilities'] ?? []),
            yesNo: $yesNo,
        );
    }
}
