<?php

use App\Filament\Pages\Auth\EditProfileWithPhone;
use App\Filament\Pages\Auth\RememberedLogin;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Livewire\Livewire;

it('opens the profile page for a signed-in owner', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/profile')->assertOk()->assertSee('Mobile phone for alerts');
});

it('saves the owner phone as E.164', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(EditProfileWithPhone::class)
        ->fillForm(['phone' => '(202) 555-0199'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->phone)->toBe('+12025550199');
});

it('refuses a phone it cannot read, in plain words, and keeps the old one', function () {
    $user = User::factory()->withPhone('+12025550199')->create();
    $this->actingAs($user);

    Livewire::test(EditProfileWithPhone::class)
        ->fillForm(['phone' => 'call me'])
        ->call('save')
        ->assertHasFormErrors(['phone']);

    expect($user->fresh()->phone)->toBe('+12025550199');
});

it('says what a good phone looks like when it refuses one', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(EditProfileWithPhone::class)
        ->fillForm(['phone' => 'call me'])
        ->call('save')
        ->assertSee('Use a real-format number like +12025550123.');
});

it('saves an empty phone as null', function () {
    $user = User::factory()->withPhone('+12025550199')->create();
    $this->actingAs($user);

    Livewire::test(EditProfileWithPhone::class)
        ->fillForm(['phone' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->phone)->toBeNull();
});

it('shows the phone already on file', function () {
    $this->actingAs(User::factory()->withPhone('+12025550199')->create());

    Livewire::test(EditProfileWithPhone::class)->assertFormSet(['phone' => '+12025550199']);
});

it('ticks remember me on the login page by default', function () {
    Livewire::test(RememberedLogin::class)->assertFormSet(['remember' => true]);
});

it('sends a signed-out owner to the conversation an alert link pointed at after they sign in', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $url = ConversationResource::getUrl('view', ['record' => $conversation]);

    $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

    Livewire::test(RememberedLogin::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertRedirect($url);

    $this->assertAuthenticatedAs($user);
});
