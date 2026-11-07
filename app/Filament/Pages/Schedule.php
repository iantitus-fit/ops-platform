<?php

namespace App\Filament\Pages;

use App\Actions\BlockTime;
use App\Actions\RemoveBlock;
use App\Enums\AppointmentKind;
use App\Enums\AppointmentState;
use App\Filament\Resources\Contacts\ContactResource;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use App\Rooms\ScheduleAndBooking\VisitActions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The owner's schedule (BOOK-03, D-40): every booked visit and blocked span from today on, grouped under the business's
 * day, with a Show filter for past, cancelled and done. It is the one screen that lists both kinds of row in the one
 * appointments table, so it reads the appointment model's query with its kind scope removed (A3 keeps the customer card,
 * the timeline and the contact screen on the scoped model). A block comes back hydrated as an Appointment here, so each
 * row action is shown only for its own kind and re-reads its row through its own kind's scoped model (T-08-59). The page
 * writes nothing itself: Block time goes through BlockTime (D-15). It is a stock Filament page and the wording follows the
 * Phase 8 UI spec.
 */
class Schedule extends Page implements HasActions, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.pages.schedule';

    /**
     * The browser event that moves keyboard focus to the Block time button (D-43). Remove block, Cancel visit and Mark
     * done each make their own button leave the row, so focus would fall to the page body.
     */
    private const string FOCUS_EVENT = 'schedule-focus-block-time';

    /**
     * The page is for a business that has the room: without it the page is not in the navigation and its address is
     * forbidden, so nothing on it can be reached to act on a schedule that is off (D-03).
     */
    public static function canAccess(): bool
    {
        return app(ActiveConfig::class)->hasRoom('schedule_and_booking');
    }

    public function table(Table $table): Table
    {
        $config = app(ActiveConfig::class);
        $zone = $config->displayTimezone();
        $word = $config->projectWord();

        return $table
            ->query(fn (): Builder => Appointment::query()
                ->withoutGlobalScope('kind')
                ->with([
                    'contact',
                    'project' => fn ($project) => $project->withNumber(),
                    'bookedByUser',
                ]))
            ->defaultGroup(
                Group::make('day')
                    ->getKeyFromRecordUsing(fn (Appointment $record): string => CarbonImmutable::instance($record->starts_at)->setTimezone($zone)->format('Y-m-d'))
                    ->getTitleFromRecordUsing(fn (Appointment $record): string => BookingSettings::dayText($record->starts_at, $zone))
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('appointments.starts_at')->orderBy('appointments.id')),
            )
            ->groupingSettingsHidden()
            ->groupingDirectionSettingHidden()
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Time')
                    ->state(fn (Appointment $record): string => BookingSettings::spanText($record->starts_at, $record->ends_at, $zone)),
                TextColumn::make('what')
                    ->label('What')
                    ->wrap()
                    ->listWithLineBreaks()
                    ->state(fn (Appointment $record): array => $this->isBlock($record)
                        ? array_values(array_filter(['Blocked', $record->note, 'Blocked by '.($record->bookedByUser?->name ?? 'a removed user')], fn (?string $line): bool => $line !== null && $line !== ''))
                        : [$record->service_name]),
                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (Appointment $record): ?string => $this->isBlock($record) ? null : ($record->contact?->name ?: '(no name)'))
                    ->url(fn (Appointment $record): ?string => $record->contact === null ? null : ContactResource::getUrl('view', ['record' => $record->contact])),
                TextColumn::make('project')
                    ->label($word->singular())
                    ->state(fn (Appointment $record): ?string => $record->project === null ? null : $record->project->label($word, (int) $record->project->number)),
                TextColumn::make('length')
                    ->label('Length')
                    ->state(fn (Appointment $record): string => $this->lengthText($record)),
                TextColumn::make('note')
                    ->label('Visit note')
                    ->wrap()
                    ->state(fn (Appointment $record): ?string => $this->isBlock($record) ? null : $record->note),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Appointment $record): string => $this->isBlock($record) ? 'Blocked' : ucfirst($record->state->value))
                    ->color(fn (string $state): string => match ($state) {
                        'Done' => 'success',
                        'Cancelled' => 'gray',
                        'Blocked' => 'warning',
                        default => 'info',
                    }),
            ])
            ->filters([
                SelectFilter::make('show')
                    ->label('Show')
                    ->native()
                    ->selectablePlaceholder(false)
                    ->options([
                        'upcoming' => 'Upcoming',
                        'past' => 'Past',
                        'cancelled' => 'Cancelled',
                        'done' => 'Done',
                    ])
                    ->default('upcoming')
                    ->query(fn (Builder $query, array $data): Builder => $this->showRows($query, (string) ($data['value'] ?? 'upcoming'), $zone)),
            ])
            ->recordActions([
                VisitActions::markDone(self::FOCUS_EVENT),
                VisitActions::cancelVisit(self::FOCUS_EVENT),
                VisitActions::editNote(),
                $this->removeBlockAction($zone),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->paginationPageOptions([25])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(fn (): string => $this->showing() === 'upcoming' ? 'Nothing scheduled' : 'No visits in this view')
            ->emptyStateDescription(fn (): string => $this->showing() === 'upcoming' ? 'Booked visits and blocked time show here.' : 'Switch the Show filter to Upcoming.');
    }

    /**
     * Block time (D-16): holds a span with no customer, job or text. The closure hands the form to BlockTime and turns a
     * refusal that names a field into a validation error on that field, shown beside it; any other refusal is a notice.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->blockTimeAction()];
    }

    private function blockTimeAction(): Action
    {
        return Action::make('blockTime')
            ->label('Block time')
            /*
             * The deliberate focus target after Remove block, Cancel visit or Mark done (D-43, WCAG 2.4.3). Each makes its own
             * row button leave the page, so focus would fall to the page body; the page dispatches this event and this
             * button takes it.
             */
            ->extraAttributes(VisitActions::focusTarget(self::FOCUS_EVENT))
            ->modalHeading('Block time')
            ->modalSubmitActionLabel('Block time')
            ->schema(fn (): array => [
                DateTimePicker::make('starts_at')
                    ->label('Starts')
                    ->native()
                    ->seconds(false)
                    ->timezone(app(ActiveConfig::class)->displayTimezone())
                    ->helperText('Times are in '.app(ActiveConfig::class)->displayTimezone().'.')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label('Ends')
                    ->native()
                    ->seconds(false)
                    ->timezone(app(ActiveConfig::class)->displayTimezone())
                    ->required(),
                Textarea::make('note')
                    ->label('Note')
                    ->rows(2)
                    ->maxLength(BlockTime::NOTE_MAX_LENGTH)
                    ->validationMessages(['max' => 'A block note can be at most '.BlockTime::NOTE_MAX_LENGTH.' characters.']),
            ])
            ->action(function (array $data): void {
                $zone = app(ActiveConfig::class)->timezone();
                $startsAt = CarbonImmutable::parse($data['starts_at'], config('app.timezone'));
                $endsAt = CarbonImmutable::parse($data['ends_at'], config('app.timezone'));

                try {
                    $block = app(BlockTime::class)->handle(auth()->user(), $startsAt, $endsAt, $data['note'] ?? null);
                } catch (BookingRefused $refusal) {
                    if ($refusal->field === null) {
                        // Filament renders a title as HTML, and the refusal is plain words.
                        Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                        return;
                    }

                    throw ValidationException::withMessages([$this->fieldPath($refusal->field) => $refusal->getMessage()]);
                }

                Notification::make()
                    ->title(e('Blocked '.BookingSettings::dayText($block->starts_at, $zone).', '.BookingSettings::spanText($block->starts_at, $block->ends_at, $zone).'.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Remove block (D-16, U-34): shown on a block that still holds time and on no appointment's row, asks first, and says
     * the time can be booked again. The button's name carries the span (U-33) and is escaped. The closure reads its row
     * again through the scoped ScheduleBlock model and does nothing when it finds none: an appointment's id, or a row that
     * is gone (A3, T-08-59).
     */
    private function removeBlockAction(string $zone): Action
    {
        $span = fn (Appointment $record): string => BookingSettings::dayText($record->starts_at, $zone).', '.BookingSettings::spanText($record->starts_at, $record->ends_at, $zone);

        return Action::make('removeBlock')
            ->label('Remove block')
            ->color('gray')
            ->visible(fn (Appointment $record): bool => $this->isBlock($record) && $record->isBooked())
            ->extraAttributes(fn (Action $action, Appointment $record): array => ['aria-label' => e($action->getLabel().': '.$span($record))])
            ->requiresConfirmation()
            ->modalHeading(fn (Appointment $record): string => 'Remove the block on '.$span($record).'?')
            ->modalDescription('Customers can book that time again.')
            ->modalSubmitActionLabel('Remove block')
            ->action(function (Appointment $record): void {
                $block = ScheduleBlock::find($record->id);

                if ($block === null) {
                    Notification::make()->title(VisitActions::ROW_GONE)->danger()->send();

                    return;
                }

                try {
                    app(RemoveBlock::class)->handle($block, auth()->user());
                } catch (BookingRefused $refusal) {
                    Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                    return;
                }

                Notification::make()->title('Block removed')->success()->send();

                $this->dispatch(self::FOCUS_EVENT);
            });
    }

    /**
     * The rows one Show choice lists (D-40). Upcoming is every booked visit and block that has not ended before today
     * began, in the business's zone; Past is what started before today and is booked or done (a removed block holds no
     * time and shows nowhere); Cancelled and Done are visits only, because a removed block is not a cancelled visit and a
     * block is never done.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private function showRows(Builder $query, string $show, string $zone): Builder
    {
        $today = CarbonImmutable::now($zone)->startOfDay()->utc();

        return match ($show) {
            'past' => $query
                ->whereIn('appointments.state', [AppointmentState::Booked->value, AppointmentState::Done->value])
                ->where('appointments.starts_at', '<', $today),
            'cancelled' => $query
                ->where('appointments.kind', AppointmentKind::Appointment->value)
                ->where('appointments.state', AppointmentState::Cancelled->value),
            'done' => $query->where('appointments.state', AppointmentState::Done->value),
            default => $query
                ->where('appointments.state', AppointmentState::Booked->value)
                ->where('appointments.ends_at', '>=', $today),
        };
    }

    /**
     * The Show choice now in force: Upcoming until the owner picks another.
     */
    private function showing(): string
    {
        return (string) ($this->tableFilters['show']['value'] ?? 'upcoming');
    }

    private function isBlock(Appointment $record): bool
    {
        return $record->kind === AppointmentKind::Block;
    }

    /**
     * The length as the owner reads it: "1 hr 30 min", "1 hr", "45 min".
     */
    private function lengthText(Appointment $record): string
    {
        $minutes = (int) round($record->starts_at->diffInMinutes($record->ends_at, true));
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest} min",
            $rest === 0 => "{$hours} hr",
            default => "{$hours} hr {$rest} min",
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
}
