<?php
declare(strict_types=1);

namespace App\Application\Report;

final class PdfReport
{
    /** @param list<string> $columns @param list<list<string>> $rows */
    public function render(string $title, array $columns, array $rows): string
    {
        $lines = [$title, 'Gerado em ' . date('d/m/Y H:i'), '', implode(' | ', $columns), str_repeat('-', 100)];
        foreach ($rows ?: [['Nenhum registro encontrado.']] as $row) {
            foreach ($this->wrap(implode(' | ', $row), 105) as $line) $lines[] = $line;
        }
        $pages = array_chunk($lines, 46);
        $fontId = 3 + count($pages) * 2;
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageIds = [];
        foreach ($pages as $index => $pageLines) {
            $pageId = 3 + $index * 2;
            $contentId = $pageId + 1;
            $pageIds[] = $pageId . ' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $content = "BT\n/F1 9 Tf\n45 800 Td\n";
            foreach ($pageLines as $line) $content .= '(' . $this->escape($line) . ") Tj\n0 -16 Td\n";
            $content .= 'ET';
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($pages) . ' >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_keys($objects) as $id) $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
        return $pdf . 'trailer' . "\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    /** @return list<string> */
    private function wrap(string $text, int $length): array { return str_split($text, $length) ?: ['']; }
    private function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }
}