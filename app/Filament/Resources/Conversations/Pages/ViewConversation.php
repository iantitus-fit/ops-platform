<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Actions\CloseConversation;
use App\Actions\HandBackConversation;
use App\Actions\PauseConversation;
use App\Actions\ReopenConversation;
use App\Enums\ConversationStatus;
use App\Enums\PausedReason;
use App\Filament\Resources\Conversations\ConversationResource;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('takeOver')
                ->label('Take over')
                ->color('warning')
                ->visible(fn (): bool => app(PauseConversation::class)->isAvailableFor($this->getRecord()))
                ->action(function (PauseConversation $pauseConversation): void {
                    $pauseConversation->handle($this->getRecord(), PausedReason::TakenOver, auth()->user());

                    Notification::make()->title(e("You're handling this conversation"))->success()->send();
                }),
            Action::make('handBack')
                ->label('Hand back')
                ->visible(fn (): bool => app(HandBackConversation::class)->isAvailableFor($this->getRecord()))
                ->action(function (HandBackConversation $handBackConversation): void {
                    $handBackConversation->handle($this->getRecord(), auth()->user());

                    Notification::make()->title(e('Handed back to the assistant'))->success()->send();
                }),
            Action::make('close')
                ->label('Close conversation')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === ConversationStatus::Open)
                ->action(function (CloseConversation $closeConversation): void {
                    $closeConversation->handle($this->getRecord());

                    Notification::make()->title('Conversation closed')->success()->send();
                }),
            Action::make('reopen')
                ->label('Reopen conversation')
                ->visible(fn (): bool => app(ReopenConversation::class)->isAvailableFor($this->getRecord()))
                ->action(function (ReopenConversation $reopenConversation): void {
                    try {
                        $reopenConversation->handle($this->getRecord());
                    } catch (DomainException $exception) {
                        // The refusal names the customer, and Filament renders a title as HTML.
                        Notification::make()->title(e($exception->getMessage()))->danger()->send();

                        return;
                    }

                    Notification::make()->title('Conversation reopened')->success()->send();
                }),
        ];
    }
}
