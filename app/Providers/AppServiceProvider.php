<?php

namespace App\Providers;

use App\Support\SystemMailDisclaimer;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
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

            $this->redirectOutgoingMailIfConfigured($message);

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

    /**
     * En pruebas (p. ej. producción controlada) redirige todos los correos a MAIL_REDIRECT_TO.
     */
    private function redirectOutgoingMailIfConfigured(Email $message): void
    {
        $redirectTo = trim((string) config('mail.redirect_all_to', ''));
        if ($redirectTo === '' || ! filter_var($redirectTo, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $original = [];
        foreach (array_merge($message->getTo(), $message->getCc(), $message->getBcc()) as $address) {
            if ($address instanceof Address) {
                $original[] = $address->getAddress();
            }
        }
        $original = array_values(array_unique(array_filter($original)));

        $alreadyOnlyRedirect = $original === [$redirectTo];
        if ($alreadyOnlyRedirect) {
            return;
        }

        $headers = $message->getHeaders();
        $headers->remove('To');
        $headers->remove('Cc');
        $headers->remove('Bcc');
        $message->to($redirectTo);

        if ($original === []) {
            return;
        }

        $note = 'Destinatario(s) original(es): '.implode(', ', $original);
        $safeNote = htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = $message->getHtmlBody();
        if (is_resource($html)) {
            $html = stream_get_contents($html) ?: '';
        }
        if (is_string($html) && $html !== '') {
            $message->html('<p style="font-size:12px;color:#666;margin:0 0 12px;">'.$safeNote.'</p>'.$html);
        }

        $text = $message->getTextBody();
        if (is_resource($text)) {
            $text = stream_get_contents($text) ?: '';
        }
        if (is_string($text) && $text !== '') {
            $message->text($note."\n\n".$text);
        }
    }
}
