<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Actions\CloseConversation;
use App\Actions\ReopenConversation;
use App\Enums\ConversationStatus;
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
