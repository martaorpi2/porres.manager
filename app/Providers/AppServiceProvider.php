<?php

namespace App\Providers;

use App\Support\SystemMailDisclaimer;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Email;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $message = $event->message;
            if (! $message instanceof Email) {
                return;
            }

            $html = $message->getHtmlBody();
            if (is_resource($html)) {
                $html = stream_get_contents($html) ?: null;
            }
            if (is_string($html) && $html !== '') {
                $message->html(SystemMailDisclaimer::appendHtmlIfMissing($html));
            }

            $text = $message->getTextBody();
            if ($text !== null && $text !== '') {
                $message->text(SystemMailDisclaimer::appendPlainIfMissing($text));
            }
        });
    }
}
