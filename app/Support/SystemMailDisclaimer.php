<?php

namespace App\Support;

/**
 * Aviso legal común para correos salientes (evita duplicarlo en cada plantilla).
 */
final class SystemMailDisclaimer
{
    private const HTML_MARKER = '<!--porres-system-mail-disclaimer-->';

    private const PLAIN_SNIPPET = 'Este es un mensaje automático generado por el sistema.';

    public static function htmlFragment(): string
    {
        $line1 = 'Este es un mensaje automático generado por el sistema.';
        $line2 = 'Por favor no responda a este correo; las consultas y gestiones deben realizarse en la aplicación.';

        return self::HTML_MARKER
            .'<hr style="border:none;border-top:1px solid #ddd;margin:18px 0 12px;" />'
            .'<p style="font-size:12px;color:#555;line-height:1.45;margin:0;">'
            .'<span style="display:block;margin-bottom:6px;">'.e($line1).'</span>'
            .'<span style="display:block;">'.e($line2).'</span>'
            .'</p>';
    }

    public static function appendHtmlIfMissing(?string $html): string
    {
        $html = $html ?? '';
        if (str_contains($html, self::HTML_MARKER)) {
            return $html;
        }

        if ($html === '') {
            return self::htmlFragment();
        }

        return $html.self::htmlFragment();
    }

    /**
     * @param  resource|string|null  $text
     */
    public static function appendPlainIfMissing($text): string
    {
        if (is_resource($text)) {
            $text = stream_get_contents($text) ?: '';
        } else {
            $text = (string) ($text ?? '');
        }

        if (str_contains($text, self::PLAIN_SNIPPET)) {
            return $text;
        }

        $suffix = "\n\n---\n".self::PLAIN_SNIPPET."\nPor favor no responda a este correo; utilice la aplicación.\n";

        if ($text === '') {
            return ltrim($suffix);
        }

        return $text.$suffix;
    }
}
