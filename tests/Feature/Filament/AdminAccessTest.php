<?php

use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;

it('sends a guest to the panel login page', function () {
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});

it('lets a signed-in user into the panel outside the local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->actingAs(User::factory()->create());
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->for($contact)->create();

    $this->get('/admin')->assertOk();
    $this->get('/admin/contacts')->assertOk();
    $this->get('/admin/conversations')->assertOk();
    $this->get(ContactResource::getUrl('view', ['record' => $contact]))->assertOk();
    $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->assertOk();
});
