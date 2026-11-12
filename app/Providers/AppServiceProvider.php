<?php

namespace App\Providers;

use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Channels\TwilioTextChannel;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IntakeAnswer;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use App\Rooms\RoomRegistry;
use App\SendRule\Classifier;
use App\SendRule\JevClassifier;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

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
     *
     * Classifier is bound to JevClassifier. Tests replace it with a FakeClassifier in tests/Pest.php,
     * so no test can reach Jev.
     *
     * RoomRegistry is a singleton: every class that asks for it gets the same object. Rooms add themselves to it in
     * their providers' register(), and the sync and the dashboard read the same list. That is why AppServiceProvider
     * stays first in bootstrap/providers.php: it must bind the registry before any room provider asks for it.
     */
    public function register(): void
    {
        $this->app->scoped(ActiveConfig::class);

        $this->app->singleton(RoomRegistry::class);

        $this->app->bind(Classifier::class, JevClassifier::class);

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
     *
     * The "leads" rate limiter allows each IP address 10 lead form posts a minute.
     *
     * In local development `composer run dev` also runs the scheduler (schedule:work), so the
     * sweep for undispatched texts fires without a cron line.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'contact' => Contact::class,
            'conversation' => Conversation::class,
            'intake_answer' => IntakeAnswer::class,
            'message' => Message::class,
            'owner_alert' => OwnerAlert::class,
            'project' => Project::class,
            'user' => User::class,
        ]);

        RateLimiter::for('leads', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(30)->by($request->ip())->response(function (): Response {
            try {
                $business = app(ActiveConfig::class)->business();
            } catch (NoActiveVersion) {
                abort(404);
            }

            return response()->view('booking.rate-limited', [
                'businessName' => (string) $business['name'],
                'phone' => (string) $business['phone'],
                'phoneText' => PhoneNumber::national((string) $business['phone']),
            ], 429);
        }));

        if ($this->app->isLocal()) {
            DevCommands::artisan('schedule:work', 'schedule');
        }
    }
}
