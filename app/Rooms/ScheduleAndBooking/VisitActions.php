<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Actions\BookAppointment;
use App\Actions\CancelAppointment;
use App\Actions\EditVisitNote;
use App\Actions\MarkAppointmentDone;
use App\Enums\AppointmentKind;
use App\Enums\ProjectWord;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * The three row buttons an owner uses on a booked visit, built once for every screen that lists visits: the contact
 * screen's Appointments tab and the Schedule page (D-14, D-18, D-24). Each builder only builds a Filament action and
 * calls the named action; nothing here writes (D-15). Sharing the definitions keeps the dialogs, labels and aria-labels
 * identical wherever the buttons appear.
 *
 * A visit action is shown only for a row of kind appointment. The Schedule page lists blocks through the same query and
 * hydrates them as appointments, so each action's closure also reads its row again through the scoped Appointment model
 * and does nothing when it finds none: a block's id, or a row that is gone (A3, T-08-59).
 *
 * Mark done and Cancel visit make their own button leave the row, so Filament has no trigger to hand keyboard focus
 * back to and it falls to the page body (WCAG 2.4.3). Each builder takes the name of a browser event the screen's chosen
 * button listens for (focusTarget()), and dispatches it once the action succeeds.
 */
class VisitActions
{
    /**
     * The words shown when a row's id no longer names a visit of the kind the button is for.
     */
    public const string ROW_GONE = 'That row is no longer on the schedule.';

    /**
     * Mark done (D-14, D-24, U-28): shown only once the visit has started, to the minute, and hidden before that rather
     * than disabled. Done is not undone, and the dialog says so. On the job's last booked visit it offers one unchecked
     * box to close the job too (D-24), so no job closes unless the owner ticks it (07 D-05). The box is decided when the
     * dialog opens, from the job's own booked visits.
     */
    public static function markDone(string $focusEvent): Action
    {
        $word = app(ActiveConfig::class)->projectWord();

        return Action::make('markDone')
            ->label('Mark done')
            ->visible(fn (Appointment $record): bool => self::isVisit($record) && $record->isBooked() && $record->starts_at->lessThanOrEqualTo(now()))
            ->extraAttributes(fn (Action $action, Appointment $record): array => self::rowButtonName($action, $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Appointment $record): string => 'Mark '.self::visitText($record).' as done?')
            ->modalDescription('This cannot be undone.')
            ->modalSubmitActionLabel('Mark done')
            ->schema(fn (Appointment $record): array => self::isLastBookedVisit($record) ? [
                Checkbox::make('alsoClose')
                    ->label('Also close '.self::jobLabel($record, $word))
                    ->helperText('Leave this off if more visits are still to come.')
                    ->default(false),
            ] : [])
            ->action(function (Appointment $record, array $data, $livewire) use ($focusEvent): void {
                $visit = Appointment::find($record->id);

                if ($visit === null) {
                    Notification::make()->title(self::ROW_GONE)->danger()->send();

                    return;
                }

                try {
                    app(MarkAppointmentDone::class)->handle($visit, auth()->user(), (bool) ($data['alsoClose'] ?? false));
                } catch (DomainException $refusal) {
                    // Filament renders a title as HTML, and the refusal is plain words about owner text.
                    Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                    return;
                }

                Notification::make()->title('Marked done')->success()->send();

                $livewire->dispatch($focusEvent);
            });
    }

    /**
     * Cancel visit (D-14, D-16, U-34): visible while the visit is booked (U-28, hidden and not disabled), asks first, and
     * says the customer is not texted. The button's name carries the visit (U-33) and is escaped, because Filament prints
     * extra attributes as they are and a service name is owner text. A refusal is a notice with its reason.
     */
    public static function cancelVisit(string $focusEvent): Action
    {
        return Action::make('cancelVisit')
            ->label('Cancel visit')
            ->color('gray')
            ->visible(fn (Appointment $record): bool => self::isVisit($record) && $record->isBooked())
            ->extraAttributes(fn (Action $action, Appointment $record): array => self::rowButtonName($action, $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Appointment $record): string => 'Cancel '.self::visitText($record).'?')
            ->modalDescription(fn (Appointment $record): string => ($record->contact?->name ?: '(no name)').' is not texted about this, so tell them yourself. A cancelled visit cannot be restored. To fix it, book a new time.')
            ->modalSubmitActionLabel('Cancel appointment')
            ->modalCancelActionLabel('Keep appointment')
            ->action(function (Appointment $record, $livewire) use ($focusEvent): void {
                $visit = Appointment::find($record->id);

                if ($visit === null) {
                    Notification::make()->title(self::ROW_GONE)->danger()->send();

                    return;
                }

                try {
                    app(CancelAppointment::class)->handle($visit, auth()->user());
                } catch (BookingRefused $refusal) {
                    // Filament renders a title as HTML, and the refusal is plain words.
                    Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                    return;
                }

                Notification::make()->title('Cancelled')->success()->send();

                $livewire->dispatch($focusEvent);
            });
    }

    /**
     * Edit note (D-18): visible while the visit is booked, filled with the current note, up to 200 characters, refused
     * beside the field when longer. Clearing the box clears the note. The row and its button stay, so it needs no focus
     * target.
     */
    public static function editNote(): Action
    {
        return Action::make('editNote')
            ->label('Edit note')
            ->color('gray')
            ->visible(fn (Appointment $record): bool => self::isVisit($record) && $record->isBooked())
            ->extraAttributes(fn (Action $action, Appointment $record): array => self::rowButtonName($action, $record))
            ->modalHeading(fn (Appointment $record): string => 'Visit note: '.self::visitText($record))
            ->modalSubmitActionLabel('Save note')
            ->schema([
                Textarea::make('note')
                    ->label('Visit note')
                    ->rows(3)
                    ->maxLength(BookAppointment::NOTE_MAX_LENGTH)
                    ->validationMessages(['max' => 'A note can be at most '.BookAppointment::NOTE_MAX_LENGTH.' characters.']),
            ])
            ->fillForm(fn (Appointment $record): array => ['note' => $record->note])
            ->action(function (Appointment $record, array $data): void {
                $visit = Appointment::find($record->id);

                if ($visit === null) {
                    Notification::make()->title(self::ROW_GONE)->danger()->send();

                    return;
                }

                try {
                    app(EditVisitNote::class)->handle($visit, auth()->user(), $data['note'] ?? null);
                } catch (DomainException $refusal) {
                    Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                    return;
                }

                Notification::make()->title('Visit note saved')->success()->send();
            });
    }

    /**
     * The attributes that make a button take keyboard focus when the browser event $event arrives (D-43, WCAG 2.4.3).
     * The listener runs in $nextTick, after Livewire has drawn the row's new state.
     *
     * @return array<string, string>
     */
    public static function focusTarget(string $event): array
    {
        return ['x-on:'.$event.'.window' => '$nextTick(() => $el.focus())'];
    }

    /**
     * The visit as the owner reads it in a dialog or a button's name: "AC repair, Thursday, November 5 at 10:00 AM", in
     * the business's zone. One place, so every row button and dialog says it the same way.
     */
    public static function visitText(Appointment $record): string
    {
        $zone = app(ActiveConfig::class)->displayTimezone();

        return $record->service_name.', '.BookingSettings::dayText($record->starts_at, $zone).' at '.BookingSettings::timeText($record->starts_at, $zone);
    }

    /**
     * A screen reader names each row's button by its visit ('Cancel visit: AC repair, Thursday, November 5 at 10:00 AM')
     * while the visible words stay the same and come first, so voice control's "click Cancel visit" still matches (WCAG
     * 2.5.3, U-33). The name is built from the action's own label so it cannot drift from the visible text. e() is
     * mandatory: Filament prints extra attributes without escaping (T-07-25, T-08-57).
     *
     * @return array{aria-label: string}
     */
    public static function rowButtonName(Action $action, Appointment $record): array
    {
        return ['aria-label' => e($action->getLabel().': '.self::visitText($record))];
    }

    /**
     * Whether the row is a customer's visit and not a block (A3). The Appointments tab only holds visits; the Schedule
     * page holds both and hydrates a block as an appointment.
     */
    private static function isVisit(Appointment $record): bool
    {
        return $record->kind === AppointmentKind::Appointment;
    }

    /**
     * Whether no other appointment on this visit's job is still booked, so this is the job's last booked visit (D-24):
     * ahead or not, any other booked visit means more may be still to come.
     */
    private static function isLastBookedVisit(Appointment $record): bool
    {
        return $record->project !== null
            && ! $record->project->appointments()->booked()->whereKeyNot($record->id)->exists();
    }

    /**
     * The visit's job as the screen names it: its own name, else the business's word and the job's number.
     */
    private static function jobLabel(Appointment $record, ProjectWord $word): string
    {
        return $record->project === null ? $word->singular() : $record->project->label($word, (int) $record->project->number);
    }
}
