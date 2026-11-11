<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Ai\FrontDeskPrompt;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * D-39 as amended (Ian, 2026-11-11, option A; UAT gaps G-08-4 and G-08-4b): what the model reads is corrected, and a
 * Booking section, built from the same conditions the send uses, tells it what code adds to a reply. A sentence in the
 * section is there only when code will do what it says.
 */
it('tells a booked customer\'s run that the system adds the visit\'s day and time (D-39 amended)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();

    frontDeskCustomerTexts('What time are you coming?');

    Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();

    fakeClassifier()->answerReading('scheduling');
    scriptFrontDeskReplies(['reply' => 'Thanks for checking.', 'label' => 'scheduling', 'service_slug' => 'ac-repair']);
    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertPrompted(function ($prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, "## Booking\n\n".FrontDeskPrompt::BOOKING_VISIT_BOOKED)
            && str_contains($instructions, 'A visit the customer card lists as booked was recorded by the business\'s own booking system, so it is the business\'s booking.')
            && strpos($instructions, '## Customer card') < strpos($instructions, '## Booking')
            && strpos($instructions, '## Booking') < strpos($instructions, '## Right now')
            && stripos($instructions, '/book/') === false
            && ! str_contains($instructions, 'The business books these services online');
    });
});
