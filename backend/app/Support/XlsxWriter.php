<?php

namespace App\Support;

use ZipArchive;

/**
 * Membuat file Excel (.xlsx) satu lembar tanpa package tambahan.
 * File .xlsx adalah zip berisi beberapa file XML; ZipArchive bawaan PHP sudah cukup.
 */
class XlsxWriter
{
    /**
     * @param  array<int, string>  $titles  baris judul di atas tabel
     * @param  array<int, string>  $headers  nama kolom
     * @param  array<int, array<int, string|int|float>>  $rows  isi tabel (angka ditulis sebagai angka)
     * @param  array<int, array<int, string|int|float>>  $footer  baris total (huruf tebal)
     * @param  array<int, int>  $widths  lebar kolom (satuan karakter Excel)
     * @return string path file sementara
     */
    public static function make(string $sheetName, array $titles, array $headers, array $rows, array $footer, array $widths): string
    {
        $xml = [];
        $r = 1;
        foreach ($titles as $i => $title) {
            $xml[] = self::row($r++, [$title], $i === 0 ? 1 : 4);
        }
        $r++; // satu baris kosong
        $headerRow = $r;
        $xml[] = self::row($r++, $headers, 2);
        foreach ($rows as $row) {
            $xml[] = self::row($r++, $row, 5);
        }
        foreach ($footer as $row) {
            $xml[] = self::row($r++, $row, 3);
        }

        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }
        $lastCol = self::col(count($headers) - 1);

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols><sheetData>'.implode('', $xml).'</sheetData>'
            .'<autoFilter ref="A'.$headerRow.':'.$lastCol.($headerRow + count($rows)).'"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::esc(mb_substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.self::esc(mb_substr($sheetName, 0, 31)).'\'!$A$'.$headerRow.':$'.$lastCol.'$'.($headerRow + count($rows)).'</definedName></definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    /** Gaya sel: 0 bawaan (tanpa garis), 1 judul besar, 2 kepala tabel, 3 baris total, 4 keterangan, 5 isi tabel. */
    private static function styles(): string
    {
        $border = '<border><left style="thin"><color rgb="FFBFC8DA"/></left><right style="thin"><color rgb="FFBFC8DA"/></right>'
            .'<top style="thin"><color rgb="FFBFC8DA"/></top><bottom style="thin"><color rgb="FFBFC8DA"/></bottom><diagonal/></border>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="5">'
            .'<font><sz val="11"/><name val="Arial"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF1B3F8F"/><name val="Arial"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            .'<font><b/><sz val="11"/><name val="Arial"/></font>'
            .'<font><sz val="10"/><color rgb="FF555555"/><name val="Arial"/></font>'
            .'</fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1B3F8F"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF2FA"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'.$border.'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private static function row(int $r, array $values, int $style): string
    {
        $cells = '';
        foreach (array_values($values) as $i => $v) {
            $ref = self::col($i).$r;
            $cells .= is_int($v) || is_float($v)
                ? '<c r="'.$ref.'" s="'.$style.'"><v>'.$v.'</v></c>'
                : '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $v).'</t></is></c>';
        }

        return '<row r="'.$r.'">'.$cells.'</row>';
    }

    /** 0 => A, 1 => B, ... 26 => AA */
    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function esc(string $text): string
    {
        // Karakter kontrol tidak boleh ada di XML.
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
