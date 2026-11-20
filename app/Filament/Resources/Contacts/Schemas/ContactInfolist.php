<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Contact;
use App\Rooms\OwnerIntelligence\DurationText;
use App\Rooms\OwnerIntelligence\TimeToBooked;
use App\Rooms\RoomRegistry;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

class ContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = app(ActiveConfig::class)->displayTimezone();

        return $schema
            ->components([
                Section::make('Contact')
                    ->schema([
                        TextEntry::make('name')
                            ->placeholder('(no name)'),
                        TextEntry::make('phone_e164')
                            ->label('Phone')
                            ->placeholder('(no phone)'),
                        TextEntry::make('email')
                            ->placeholder('(no email)'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('lead_source_key')
                            ->label('Source')
                            ->badge(),
                        TextEntry::make('acquisition_cost_cents')
                            ->label('Cost per lead')
                            ->money('USD', divideBy: 100)
                            ->placeholder('(unknown)'),
                        TextEntry::make('created_at')
                            ->label('First contact')
                            ->dateTime(timezone: $timezone),
                        TextEntry::make('last_activity_at')
                            ->label('Last activity')
                            ->dateTime(timezone: $timezone)
                            ->placeholder('(none)'),
                        TextEntry::make('next_appointment')
                            ->label('Next appointment')
                            ->state(fn (Contact $record): ?string => self::nextAppointmentLine($record, $timezone))
                            ->visible(fn (?string $state): bool => filled($state)),
                        TextEntry::make('time_to_booked')
                            ->label('Time to booked')
                            ->state(fn (Contact $record): ?string => self::timeToBookedLine($record))
                            ->visible(fn (?string $state): bool => filled($state)),
                        TextEntry::make('automatic_texts')
                            ->label('Automatic texts')
                            ->state(fn (Contact $record): string => self::automaticTextsLine($record, $timezone))
                            ->visible(fn (Contact $record): bool => ! $record->hasTextedBusiness()),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Timeline')
                    ->description('Newest first')
                    ->schema([
                        RepeatableEntry::make('timeline')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('occurred_at')
                                    ->label('When')
                                    ->dateTime(timezone: $timezone),
                                TextEntry::make('type')
                                    ->badge()
                                    ->formatStateUsing(fn (ActivityType $state, Activity $record): string => self::activityBadge($state, $record)),
                                TextEntry::make('summary'),
                                TextEntry::make('subject.body')
                                    ->label('Message')
                                    ->visible(fn (?string $state): bool => filled($state)),
                                TextEntry::make('lead_source_key')
                                    ->label('Source')
                                    ->badge()
                                    ->visible(fn (?string $state): bool => filled($state)),
                                TextEntry::make('cost_cents')
                                    ->label('Cost')
                                    ->money('USD', divideBy: 100)
                                    ->visible(fn (?int $state): bool => $state !== null),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The soonest visit still booked ahead, in one line (D-38, U-21): the day, the time on the business's clock, the
     * service and the job in the business's word, such as "Tuesday, November 3, 2026 at 10:00 AM, AC repair, Job 2". Null
     * when no visit is booked ahead, so the entry is left out. A cancelled or finished visit never counts (D-14).
     */
    private static function nextAppointmentLine(Contact $contact, string $timezone): ?string
    {
        $appointment = $contact->appointments()
            ->booked()
            ->ahead()
            ->orderBy('starts_at')
            ->orderBy('id')
            ->with(['project' => fn ($project) => $project->withNumber()])
            ->first();

        if ($appointment === null) {
            return null;
        }

        $when = CarbonImmutable::instance($appointment->starts_at)->setTimezone($timezone)->format('l, F j, Y \a\t g:i A');
        $job = $appointment->project === null
            ? null
            : $appointment->project->label(app(ActiveConfig::class)->projectWord(), (int) $appointment->project->number);

        return implode(', ', array_filter([$when, $appointment->service_name, $job]));
    }

    /**
     * The contact's own time to booked in the business's words, such as "42 minutes" (D-05). Null, so the entry is left
     * out, when the contact has no number (D-03), when the owner intelligence room is off (D-08), and when the number
     * cannot be worked out: that last case is reported to the log and never breaks the screen (UI-SPEC, Ian 2026-11-14).
     * TimeToBooked is resolved from the container so a test can swap it.
     */
    private static function timeToBookedLine(Contact $contact): ?string
    {
        try {
            if (! app(ActiveConfig::class)->hasRoom('owner_intelligence')) {
                return null;
            }

            $seconds = app(TimeToBooked::class)->forContact($contact->id);

            return $seconds === null ? null : DurationText::format($seconds);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The automatic-texts switch in words (U-26): Off, or On with who turned it on and when. A removed user is named as one.
     */
    private static function automaticTextsLine(Contact $contact, string $timezone): string
    {
        if ($contact->automatic_texts_on_at === null) {
            return 'Off';
        }

        $who = $contact->automaticTextsTurnedOnBy?->name ?? 'a removed user';
        $when = CarbonImmutable::instance($contact->automatic_texts_on_at)->setTimezone($timezone)->format('M j, Y');

        return "On, turned on by {$who} on {$when}";
    }

    /**
     * The badge on a timeline entry: the room's own label for an entry a room wrote, "Room entry" when the room's code
     * no longer registers it, and the type's own value for every other entry, as before.
     */
    private static function activityBadge(ActivityType $type, Activity $activity): string
    {
        if ($type !== ActivityType::RoomEntry) {
            return $type->value;
        }

        $entry = app(RoomRegistry::class)->timelineEntry((string) $activity->room, (string) $activity->entry);

        return $entry === null ? 'Room entry' : ucfirst($entry->label);
    }
}
