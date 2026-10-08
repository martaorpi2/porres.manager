<?php

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Extrae textos con posición de un PDF simple (FlateDecode, Tj).
 */
final class PdfText
{
    /**
     * @return list<array{page: int, x: float, y: float, text: string}>
     */
    public function items(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || ! str_starts_with($raw, '%PDF')) {
            throw new RuntimeException('El archivo no es un PDF.');
        }

        $items = [];
        $page = 0;
        $offset = 0;
        while (($pos = strpos($raw, '/FlateDecode', $offset)) !== false) {
            $headerStart = strrpos(substr($raw, 0, $pos), '<<');
            $header = $headerStart === false ? '' : substr($raw, $headerStart, $pos - $headerStart + 40);
            if (! preg_match('/\/Length\s+(\d+)/', $header, $lengthMatch)) {
                $offset = $pos + 12;
                continue;
            }
            $length = (int) $lengthMatch[1];
            if ($length < 1) {
                $offset = $pos + 12;
                continue;
            }
            $streamPos = strpos($raw, 'stream', $pos);
            if ($streamPos === false) {
                break;
            }
            $dataStart = $streamPos + 6;
            while (isset($raw[$dataStart]) && ($raw[$dataStart] === "\r" || $raw[$dataStart] === "\n" || $raw[$dataStart] === ' ')) {
                $dataStart++;
            }
            $decoded = @gzuncompress(substr($raw, $dataStart, $length));
            if (! is_string($decoded)) {
                $decoded = @gzinflate(substr($raw, $dataStart + 2, max(0, $length - 2)));
            }
            $offset = $dataStart + $length;
            if (! is_string($decoded) || ! str_contains($decoded, 'Tj')) {
                continue;
            }
            $found = $this->textItems($decoded);
            if ($found === []) {
                continue;
            }
            $page++;
            foreach ($found as $item) {
                $item['page'] = $page;
                $items[] = $item;
            }
        }

        if ($items === []) {
            throw new RuntimeException('No se pudo leer el texto del PDF.');
        }

        return $items;
    }

    /**
     * @param  list<array{page: int, x: float, y: float, text: string}>  $items
     * @return list<list<array{page: int, x: float, y: float, text: string}>>
     */
    public function lines(array $items, float $tolerance = 1.0): array
    {
        $sorted = $items;
        usort($sorted, function (array $a, array $b): int {
            if ($a['page'] !== $b['page']) {
                return $a['page'] <=> $b['page'];
            }
            if (abs($a['y'] - $b['y']) > 0.4) {
                return $b['y'] <=> $a['y'];
            }

            return $a['x'] <=> $b['x'];
        });

        $lines = [];
        $current = [];
        $page = null;
        $y = null;
        foreach ($sorted as $item) {
            $breaks = $current !== [] && ($item['page'] !== $page || abs($item['y'] - $y) > $tolerance);
            if ($breaks) {
                $lines[] = $current;
                $current = [];
            }
            $page = $item['page'];
            $y = $item['y'];
            $current[] = $item;
        }
        if ($current !== []) {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @return list<array{x: float, y: float, text: string}>
     */
    private function textItems(string $content): array
    {
        $items = [];
        $pattern = '/(?:1\s+0\s+0\s+1\s+|(?<![.\d]))([\d.\-]+)\s+([\d.\-]+)\s+(?:Tm|Td)(?:(?!Tm|Td).){0,180}?\(((?:\\\\.|[^\\\\)])*)\)\s*Tj/s';
        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $match) {
            $text = $this->unescape($match[3]);
            if ($text === '') {
                continue;
            }
            $items[] = [
                'x' => round((float) $match[1], 2),
                'y' => round((float) $match[2], 2),
                'text' => $text,
            ];
        }

        return $items;
    }

    private function unescape(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', fn (array $match) => chr(octdec($match[1])), $value) ?? $value;
        $value = strtr($value, [
            '\\n' => "\n",
            '\\r' => '',
            '\\t' => ' ',
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ]);
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim($value);
    }
}
