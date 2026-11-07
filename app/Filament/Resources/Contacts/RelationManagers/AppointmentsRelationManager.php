<?php

namespace App\Filament\Resources\Contacts\RelationManagers;

use App\Actions\BookAppointment;
use App\Actions\CancelAppointment;
use App\Enums\ProjectWord;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The contact screen's list of appointments (BOOK-03) and the Book by hand dialog (D-16). It lists only appointments,
 * never a block, because it reads the contact's scoped appointments relation (A3). It has no Filament create, edit or
 * delete action: every write goes through BookAppointment (D-15). The wording follows the Phase 8 UI spec.
 */
class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    /**
     * Draw the tab with the page. A lazy tab has nothing focusable until it scrolls into view, so a keyboard user
     * tabbed past it (WCAG 2.1.1, the ProjectsRelationManager precedent).
     */
    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Appointments';
    }

    /**
     * Filament makes a relation manager on a view page read-only unless it says otherwise, and a read-only one hides
     * every action (research Pitfall 10).
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $config = app(ActiveConfig::class);
        $word = $config->projectWord();
        $zone = $config->displayTimezone();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['project' => fn ($project) => $project->withNumber()]))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Time')
                    ->state(fn (Appointment $record): string => CarbonImmutable::instance($record->starts_at)->setTimezone($zone)->format('l, F j, Y \a\t g:i A')),
                TextColumn::make('service_name')
                    ->label('Service'),
                TextColumn::make('project')
                    ->label($word->singular())
                    ->state(fn (Appointment $record): string => $record->project === null ? '' : $record->project->label($word, (int) $record->project->number)),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Appointment $record): string => ucfirst($record->state->value))
                    ->color(fn (string $state): string => match ($state) {
                        'Done' => 'success',
                        'Cancelled' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('note')
                    ->label('Visit note')
                    ->wrap(),
            ])
            ->headerActions([
                $this->bookByHandAction($word),
            ])
            ->recordActions([
                $this->cancelVisitAction(),
            ])
            ->emptyStateHeading('No appointments yet')
            ->emptyStateDescription('Use Book by hand to add one.');
    }

    /**
     * Book by hand (D-16, D-17). The closure hands the form to BookAppointment::byHand() and turns a refusal that names
     * a field into a validation error on that field, shown beside it; any other refusal is a notice.
     */
    private function bookByHandAction(ProjectWord $word): Action
    {
        return Action::make('bookByHand')
            ->label('Book by hand')
            ->modalHeading(fn (): string => 'Book a visit for '.$this->contactName())
            ->modalSubmitActionLabel('Book visit')
            ->visible(fn (): bool => app(ActiveConfig::class)->hasRoom('schedule_and_booking'))
            ->schema(fn (): array => [
                Select::make('service')
                    ->label('Service')
                    ->options(collect(app(ActiveConfig::class)->services())->mapWithKeys(fn (array $service): array => [$service['slug'] => $service['name']])->all())
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->label('Starts')
                    ->native()
                    ->seconds(false)
                    ->timezone(app(ActiveConfig::class)->displayTimezone())
                    ->helperText('Times are in '.app(ActiveConfig::class)->displayTimezone().'.')
                    ->required(),
                Select::make('project')
                    ->label($word->singular())
                    ->options($this->projectChoices($word))
                    ->default($this->defaultProjectChoice())
                    ->required()
                    ->validationMessages(['required' => 'Pick a job.']),
                Textarea::make('note')
                    ->label('Visit note')
                    ->rows(3)
                    ->maxLength(BookAppointment::NOTE_MAX_LENGTH)
                    ->validationMessages(['max' => 'A note can be at most '.BookAppointment::NOTE_MAX_LENGTH.' characters.']),
                Text::make($this->confirmationStatus()),
            ])
            ->action(function (array $data): void {
                $config = app(ActiveConfig::class);
                $zone = $config->timezone();
                $startsAt = CarbonImmutable::parse($data['starts_at'], config('app.timezone'));
                $choice = $data['project'] ?? null;

                try {
                    $appointment = app(BookAppointment::class)->byHand(
                        $this->getOwnerRecord(),
                        auth()->user(),
                        $data['service'],
                        $startsAt,
                        $choice === 'new' || $choice === null ? null : (int) $choice,
                        $data['note'] ?? null,
                    );
                } catch (BookingRefused $refusal) {
                    if ($refusal->field === null) {
                        // Filament renders a title as HTML, and the refusal is plain words.
                        Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                        return;
                    }

                    throw ValidationException::withMessages([$this->fieldPath($refusal->field) => $refusal->getMessage()]);
                }

                Notification::make()
                    ->title(e('Booked '.BookingSettings::dayText($appointment->starts_at, $zone).' at '.BookingSettings::timeText($appointment->starts_at, $zone).'.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Cancel visit (D-14, D-16, U-34): visible while the visit is booked (U-28, hidden and not disabled), asks first, and
     * says the customer is not texted. The button's name carries the visit (U-33) and is escaped, because Filament prints
     * extra attributes as they are and a service name is owner text. A refusal is a notice with its reason.
     */
    private function cancelVisitAction(): Action
    {
        return Action::make('cancelVisit')
            ->label('Cancel visit')
            ->color('gray')
            ->visible(fn (Appointment $record): bool => $record->isBooked())
            ->extraAttributes(fn (Action $action, Appointment $record): array => $this->rowButtonName($action, $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Appointment $record): string => 'Cancel '.$this->visitText($record).'?')
            ->modalDescription(fn (): string => ($this->contact()->name ?: '(no name)').' is not texted about this, so tell them yourself. A cancelled visit cannot be restored. To fix it, book a new time.')
            ->modalSubmitActionLabel('Cancel appointment')
            ->modalCancelActionLabel('Keep appointment')
            ->action(function (Appointment $record): void {
                try {
                    app(CancelAppointment::class)->handle($record, auth()->user());
                } catch (BookingRefused $refusal) {
                    // Filament renders a title as HTML, and the refusal is plain words.
                    Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                    return;
                }

                Notification::make()->title('Cancelled')->success()->send();
            });
    }

    /**
     * The visit as the owner reads it in a dialog or a button's name: "AC repair, Thursday, November 5 at 10:00 AM", in
     * the business's zone. One place, so every row button and dialog says it the same way.
     */
    private function visitText(Appointment $record): string
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
    private function rowButtonName(Action $action, Appointment $record): array
    {
        return ['aria-label' => e($action->getLabel().': '.$this->visitText($record))];
    }

    /**
     * The Job select's choices: the customer's open jobs by their label, then New, so a new job is always an explicit
     * choice (D-22).
     *
     * @return array<int|string, string>
     */
    private function projectChoices(ProjectWord $word): array
    {
        $choices = [];

        foreach ($this->openProjects() as $project) {
            $choices[$project->id] = $project->label($word, (int) $project->number);
        }

        $choices['new'] = 'New '.mb_strtolower($word->singular());

        return $choices;
    }

    /**
     * The one open job when there is only one, New when there is none, and nothing when there are two or more, so the
     * owner has to pick (D-22).
     */
    private function defaultProjectChoice(): int|string|null
    {
        $open = $this->openProjects();

        return match ($open->count()) {
            0 => 'new',
            1 => $open->first()->id,
            default => null,
        };
    }

    /**
     * The customer's open jobs, oldest first, each with its number.
     *
     * @return Collection<int, Model>
     */
    private function openProjects(): Collection
    {
        return $this->contact()->projects()->withNumber()->whereNull('closed_at')->orderBy('id')->get();
    }

    /**
     * The one line under the form saying whether a confirmation text will go out (U-25). It is a forecast the owner
     * reads, not a send: plans 08-11 and 08-12 send the text, and only to a customer who may get automatic texts. An
     * opted-out customer is named first, then one who has not agreed, then a business with no booking file.
     */
    private function confirmationStatus(): string
    {
        $contact = $this->contact();

        return match (true) {
            $contact->isOptedOut() => 'No confirmation text will go out. This customer has opted out.',
            ! $contact->mayGetAutomaticTexts() => 'No confirmation text will go out. This customer has not agreed to automatic texts.',
            BookingSettings::fromConfig(app(ActiveConfig::class)) === null => 'No confirmation text will go out. Customer texts for booking are off until rooms/schedule_and_booking/booking.md is added.',
            default => 'A confirmation text will go out.',
        };
    }

    /**
     * The form field's full name inside the open dialog, so an error lands beside the field.
     */
    private function fieldPath(string $field): string
    {
        $schemaPath = $this->getMountedActionSchema()?->getStatePath();

        return ($schemaPath === null || $schemaPath === '' ? 'mountedActions.0.data' : $schemaPath).'.'.$field;
    }

    private function contactName(): string
    {
        $contact = $this->contact();

        return $contact->name ?: $contact->phone_e164 ?: $contact->email ?: 'this customer';
    }

    private function contact(): Contact
    {
        return $this->getOwnerRecord();
    }
}
