<?php

namespace App\Support;

/**
 * Membuat PDF berisi satu tabel (A4 mendatar) tanpa package tambahan.
 * Memakai huruf Helvetica bawaan PDF, jadi tidak perlu menyematkan file font.
 * Cukup untuk laporan sederhana seperti rekap absensi.
 */
class PdfTable
{
    private const W = 842;   // lebar A4 mendatar (point)

    private const H = 595;   // tinggi A4 mendatar

    private const M = 36;    // margin

    private const ROW_H = 20;

    /** Lebar rata-rata huruf Helvetica (per 1 point ukuran huruf), dipakai untuk memotong teks panjang. */
    private const AVG = 0.52;

    private const DIGIT = 0.556;

    /**
     * @param  array<int, string>  $titles  baris 0 = judul, sisanya keterangan kecil
     * @param  array<int, array{0: string, 1: int, 2: string}>  $columns  [nama, lebar point, 'L' atau 'R']
     * @param  array<int, array<int, string|int>>  $rows
     * @param  array<int, string|int>|null  $total  baris total (huruf tebal)
     */
    public static function make(array $titles, array $columns, array $rows, ?array $total, string $note): string
    {
        $perPage = (int) floor((self::H - 2 * self::M - 70 - 40) / self::ROW_H);
        $chunks = array_chunk($rows, max(1, $perPage)) ?: [[]];
        $pages = [];
        foreach ($chunks as $i => $chunk) {
            $last = $i === count($chunks) - 1;
            $pages[] = self::page($titles, $columns, $chunk, $last ? $total : null, $last ? $note : '', $i + 1, count($chunks));
        }

        return self::document($pages);
    }

    private static function page(array $titles, array $columns, array $rows, ?array $total, string $note, int $no, int $count): string
    {
        $s = '';
        $y = self::H - self::M - 16;
        $s .= self::text(self::M, $y, $titles[0] ?? '', 16, true, '0.106 0.247 0.561');
        foreach (array_slice($titles, 1) as $t) {
            $y -= 16;
            $s .= self::text(self::M, $y, $t, 10, false, '0.33 0.33 0.33');
        }
        $y -= 22;

        $width = array_sum(array_column($columns, 1));
        // Kepala tabel
        $s .= self::rect(self::M, $y - self::ROW_H, $width, self::ROW_H, '0.106 0.247 0.561');
        $s .= self::cells($columns, array_column($columns, 0), $y, 9, true, '1 1 1');
        $y -= self::ROW_H;

        foreach ($rows as $k => $row) {
            if ($k % 2 === 1) {
                $s .= self::rect(self::M, $y - self::ROW_H, $width, self::ROW_H, '0.933 0.949 0.98');
            }
            $s .= self::cells($columns, $row, $y, 9, false, '0.1 0.1 0.1');
            $s .= self::line(self::M, $y - self::ROW_H, self::M + $width, $y - self::ROW_H);
            $y -= self::ROW_H;
        }
        if ($total) {
            $s .= self::rect(self::M, $y - self::ROW_H, $width, self::ROW_H, '0.86 0.89 0.95');
            $s .= self::cells($columns, $total, $y, 9, true, '0.1 0.1 0.1');
            $y -= self::ROW_H;
        }
        if ($note !== '') {
            $s .= self::text(self::M, $y - 16, $note, 8, false, '0.33 0.33 0.33');
        }
        $s .= self::text(self::W - self::M - 70, self::M - 14, "Halaman $no dari $count", 8, false, '0.45 0.45 0.45');

        return $s;
    }

    private static function cells(array $columns, array $values, float $top, int $size, bool $bold, string $color): string
    {
        $s = '';
        $x = self::M;
        foreach ($columns as $i => [$name, $w, $align]) {
            $text = self::fit((string) ($values[$i] ?? ''), $w - 10, $size);
            $tx = $align === 'R' ? $x + $w - 5 - self::width($text, $size) : $x + 5;
            $s .= self::text($tx, $top - self::ROW_H + 6.5, $text, $size, $bold, $color);
            $x += $w;
        }

        return $s;
    }

    private static function width(string $text, int $size): float
    {
        return ctype_digit($text) ? strlen($text) * self::DIGIT * $size : mb_strlen($text) * self::AVG * $size;
    }

    /** Potong teks yang lebih lebar dari kolom, diberi "..." di akhir. */
    private static function fit(string $text, float $max, int $size): string
    {
        if (self::width($text, $size) <= $max) {
            return $text;
        }
        while ($text !== '' && self::width($text.'...', $size) > $max) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'...';
    }

    private static function text(float $x, float $y, string $text, int $size, bool $bold, string $color): string
    {
        $font = $bold ? 'F2' : 'F1';

        return sprintf("BT %s rg /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $color, $font, $size, $x, $y, self::esc($text));
    }

    private static function rect(float $x, float $y, float $w, float $h, string $color): string
    {
        return sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $color, $x, $y, $w, $h);
    }

    private static function line(float $x1, float $y, float $x2, float $y2): string
    {
        return sprintf("0.75 0.78 0.85 RG 0.5 w %.2F %.2F m %.2F %.2F l S\n", $x1, $y, $x2, $y2);
    }

    /** Huruf diubah ke Windows-1252 (WinAnsi), lalu tanda kurung dan garis miring diberi escape. */
    private static function esc(string $text): string
    {
        $text = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' ']);
    }

    /** Susun objek PDF: katalog, daftar halaman, dua huruf, lalu tiap halaman dan isinya. */
    private static function document(array $pages): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        foreach ($pages as $i => $content) {
            $pageId = 5 + $i * 2;
            $kids[] = "$pageId 0 R";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pageId + 1).' 0 R >>';
            $objects[$pageId + 1] = '<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= isset($offsets[$id]) ? sprintf("%010d 00000 n \n", $offsets[$id]) : "0000000000 65535 f \n";
        }

        return $pdf."trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
