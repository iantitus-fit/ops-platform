<?php

namespace App\Filament\Resources\Contacts\RelationManagers;

use App\Actions\CloseProject;
use App\Actions\OpenProject;
use App\Actions\RenameProject;
use App\Actions\ReopenProject;
use App\Enums\ProjectActor;
use App\Icm\ActiveConfig;
use App\Models\Project;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The contact screen's list of projects (D-14), in the business's word. Every write goes through the four project
 * actions (D-11): this class has no Filament create, edit, delete or associate action and touches no model itself.
 * Every project shows whether or not the leads room is live, because the list does not ask about rooms (D-15,
 * research A2). The wording is a placeholder until Phase 11.
 */
class ProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    /**
     * Filament draws a relation manager lazily by default: the first response holds a placeholder that loads the
     * table only when it scrolls into view. The placeholder has nothing focusable, so a keyboard user tabbed past it
     * and the list never loaded (WCAG 2.1.1, UAT gap G-07-2.1). The dashboard widgets and room sections set the same
     * flag (02-23, 04-RESEARCH).
     */
    protected static bool $isLazy = false;

    /**
     * The tab's title is the business's plural word: "Jobs", "Matters".
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return app(ActiveConfig::class)->projectWord()->plural();
    }

    /**
     * Filament makes a relation manager on a view page read-only unless it says otherwise, and a read-only one hides
     * every action (research Pitfall 10). The actions here are the whole point of the list.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $word = app(ActiveConfig::class)->projectWord();
        $timezone = app(ActiveConfig::class)->displayTimezone();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withNumber()->with('openedByUser'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('label')
                    ->label($word->singular())
                    ->state(fn (Project $record): string => $record->label($word, (int) $record->number)),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Project $record): string => $record->isOpen() ? 'Open' : 'Closed'),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime(timezone: $timezone),
                TextColumn::make('opened_by')
                    ->label('Opened by')
                    ->state(fn (Project $record): string => $record->opened_by === ProjectActor::Lead
                        ? 'New lead'
                        : ($record->openedByUser?->name ?? 'A removed user')),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime(timezone: $timezone)
                    ->placeholder('(open)'),
            ])
            ->headerActions([
                Action::make('new')
                    ->label('New '.mb_strtolower($word->singular()))
                    ->requiresConfirmation()
                    ->modalHeading('New '.mb_strtolower($word->singular()))
                    ->modalDescription('Open a new '.mb_strtolower($word->singular()).' for this customer? It takes the next number and cannot be deleted, only closed.')
                    ->modalSubmitActionLabel('Open '.mb_strtolower($word->singular()))
                    ->action(function () use ($word): void {
                        $project = app(OpenProject::class)->byHand($this->getOwnerRecord(), auth()->user());

                        $number = $this->getOwnerRecord()->projects()->where('id', '<=', $project->id)->count();

                        Notification::make()
                            ->title(e('Opened '.$project->label($word, $number)))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('close')
                    ->label('Close')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Project $record): bool => $record->isOpen())
                    ->action(function (Project $record): void {
                        app(CloseProject::class)->handle($record, auth()->user());

                        Notification::make()->title('Closed')->success()->send();
                    }),
                Action::make('reopen')
                    ->label('Reopen')
                    ->visible(fn (Project $record): bool => ! $record->isOpen())
                    ->action(function (Project $record): void {
                        app(ReopenProject::class)->handle($record, auth()->user());

                        Notification::make()->title('Reopened')->success()->send();
                    }),
                Action::make('rename')
                    ->label('Rename')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->maxLength(RenameProject::MAX_LENGTH)
                            ->validationMessages(['max' => 'A name can be at most '.RenameProject::MAX_LENGTH.' characters.']),
                    ])
                    ->fillForm(fn (Project $record): array => ['name' => $record->name])
                    ->action(function (Project $record, array $data): void {
                        try {
                            app(RenameProject::class)->handle($record, auth()->user(), $data['name'] ?? null);
                        } catch (DomainException $exception) {
                            // Filament renders a title as HTML, and the refusal is plain words about owner text.
                            Notification::make()->title(e($exception->getMessage()))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Renamed')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No '.mb_strtolower($word->plural()).' yet');
    }
}
