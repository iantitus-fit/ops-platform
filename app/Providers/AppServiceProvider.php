<?php

namespace App\Providers;

use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Channels\TwilioTextChannel;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * ActiveConfig is bound as scoped: the container hands out one instance per request or
     * queued job and builds a fresh one for the next. The queue worker forgets scoped
     * instances after every job, so a long-running worker picks up a sync that ran in
     * another process. A plain singleton would serve the old version until the worker restarts.
     *
     * TextChannel is bound to a closure that reads channels.text.driver each time it is
     * asked for, so asking for the interface gives whichever driver the config names.
     * FakeTextChannel is a singleton so the texts it remembers survive between asks.
     */
    public function register(): void
    {
        $this->app->scoped(ActiveConfig::class);

        $this->app->singleton(FakeTextChannel::class);
        $this->app->bind(TextChannel::class, function (Application $app): TextChannel {
            $driver = config('channels.text.driver');

            return match ($driver) {
                'fake' => $app->make(FakeTextChannel::class),
                'twilio' => $app->make(TwilioTextChannel::class),
                default => throw new InvalidArgumentException("Unknown text channel driver [{$driver}]. Use fake or twilio."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * The morph map stores short names such as "message" in activities.subject_type instead
     * of class names, and refuses any model that is not listed.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'contact' => Contact::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
            'user' => User::class,
        ]);
    }
}
