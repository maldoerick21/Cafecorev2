<?php
class ExcelExport
{
    public static function styled(mixed $value, int $style): array
    {
        return ['value' => $value, 'style' => $style];
    }

    public static function download(string $filename, array $sheets): void
    {
        if (!function_exists('gzdeflate')) {
            http_response_code(500);
            exit('Excel export is unavailable because PHP zlib is not enabled.');
        }

        $files = [
            '[Content_Types].xml' => self::contentTypes(count($sheets)),
            '_rels/.rels' => self::rootRelationships(),
            'xl/workbook.xml' => self::workbookXml(array_keys($sheets)),
            'xl/_rels/workbook.xml.rels' => self::workbookRelationships(count($sheets)),
            'xl/styles.xml' => self::stylesXml(),
        ];
        foreach (array_values($sheets) as $index => $sheet) {
            $definition = isset($sheet['rows']) && is_array($sheet['rows'])
                ? $sheet
                : ['rows' => $sheet, 'autoFilter' => $index > 0];
            $files['xl/worksheets/sheet' . ($index + 1) . '.xml'] = self::worksheetXml($definition['rows'], $definition);
        }

        $workbook = self::zip($files);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($workbook));
        header('Cache-Control: no-store, max-age=0');
        echo $workbook;
        exit;
    }

    private static function contentTypes(int $sheetCount): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml" />'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml" />';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml" />';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml" />'
            . '<Default Extension="xml" ContentType="application/xml" />'
            . $overrides
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml" />'
            . '</Relationships>';
    }

    private static function workbookXml(array $sheetNames): string
    {
        $sheets = '';
        foreach ($sheetNames as $index => $name) {
            $sheets .= '<sheet name="' . self::xml((string) $name) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '" />';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0" /></bookViews><sheets>' . $sheets . '</sheets></workbook>';
    }

    private static function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml" />';
        }
        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml" />';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;₱&quot;#,##0.00;[Red]-&quot;₱&quot;#,##0.00" /></numFmts>'
            . '<fonts count="3"><font><sz val="11" /><color rgb="FF292538" /><name val="Calibri" /></font><font><b /><sz val="11" /><color rgb="FFFFFFFF" /><name val="Calibri" /></font><font><b /><sz val="16" /><color rgb="FFFFFFFF" /><name val="Calibri" /></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none" /></fill><fill><patternFill patternType="gray125" /></fill><fill><patternFill patternType="solid"><fgColor rgb="FF211D35" /><bgColor indexed="64" /></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF5EFE6" /><bgColor indexed="64" /></patternFill></fill></fills>'
            . '<borders count="2"><border><left /><right /><top /><bottom /><diagonal /></border><border><left style="thin"><color rgb="FFE5D9C9" /></left><right style="thin"><color rgb="FFE5D9C9" /></right><top style="thin"><color rgb="FFE5D9C9" /></top><bottom style="thin"><color rgb="FFE5D9C9" /></bottom><diagonal /></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" /></cellStyleXfs>'
            . '<cellXfs count="13">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" />'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" /></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right" vertical="center" /></xf>'
            . '<xf numFmtId="164" fontId="1" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center" /></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" /></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" /></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" /></xf>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right" vertical="center" /></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" /></xf>'
            . '<xf numFmtId="1" fontId="1" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center" /></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1" /></xf>'
            . '<xf numFmtId="10" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right" vertical="center" /></xf>'
            . '<xf numFmtId="10" fontId="1" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center" /></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0" /></cellStyles>'
            . '</styleSheet>';
    }

    private static function worksheetXml(array $rows, array $options = []): string
    {
        $sheetRows = '';
        $maxColumns = 1;
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $maxColumns = max($maxColumns, count($row));
            $cells = '';
            foreach (array_values($row) as $columnIndex => $cell) {
                $cellValue = $cell;
                $style = 0;
                if (is_array($cell) && array_key_exists('value', $cell)) {
                    $cellValue = $cell['value'];
                    $style = max(0, (int) ($cell['style'] ?? 0));
                }
                $reference = self::columnName($columnIndex + 1) . $excelRow;
                $styleAttribute = $style > 0 ? ' s="' . $style . '"' : '';
                if (is_int($cellValue) || is_float($cellValue)) {
                    $number = is_float($cellValue) ? sprintf('%.15g', $cellValue) : (string) $cellValue;
                    $cells .= '<c r="' . $reference . '"' . $styleAttribute . '><v>' . $number . '</v></c>';
                } else {
                    $value = self::xml((string) ($cellValue ?? ''));
                    $cells .= '<c r="' . $reference . '" t="inlineStr"' . $styleAttribute . '><is><t xml:space="preserve">' . $value . '</t></is></c>';
                }
            }
            $sheetRows .= '<row r="' . $excelRow . '">' . $cells . '</row>';
        }
        $lastRow = max(1, count($rows));
        $merges = array_values(array_filter($options['mergeCells'] ?? [], static fn ($ref): bool => is_string($ref) && preg_match('/^[A-Z]+[0-9]+:[A-Z]+[0-9]+$/', $ref)));
        foreach ($merges as $merge) {
            if (preg_match('/([A-Z]+)[0-9]+:([A-Z]+)[0-9]+$/', $merge, $match)) {
                $maxColumns = max($maxColumns, self::columnNumber($match[1]), self::columnNumber($match[2]));
            }
        }
        $lastColumn = self::columnName($maxColumns);
        $autoFilter = !empty($options['autoFilter']) && $lastRow > 1
            ? '<autoFilter ref="' . self::xml((string) ($options['filterRange'] ?? ('A1:' . $lastColumn . $lastRow))) . '" />'
            : '';
        $columns = '';
        foreach (($options['columnWidths'] ?? []) as $column => $width) {
            $column = (int) $column;
            $width = (float) $width;
            if ($column > 0 && $width > 0) {
                $columns .= '<col min="' . $column . '" max="' . $column . '" width="' . sprintf('%.2f', $width) . '" customWidth="1" />';
            }
        }
        $columnsXml = $columns === '' ? '' : '<cols>' . $columns . '</cols>';
        $freezeRows = max(0, (int) ($options['freezeRows'] ?? 0));
        $pane = $freezeRows > 0
            ? '<pane ySplit="' . $freezeRows . '" topLeftCell="A' . ($freezeRows + 1) . '" activePane="bottomLeft" state="frozen" /><selection pane="bottomLeft" activeCell="A' . ($freezeRows + 1) . '" sqref="A' . ($freezeRows + 1) . '" />'
            : '';
        $mergesXml = $merges
            ? '<mergeCells count="' . count($merges) . '">' . implode('', array_map(static fn (string $ref): string => '<mergeCell ref="' . $ref . '" />', $merges)) . '</mergeCells>'
            : '';
        $rowHeights = $options['rowHeights'] ?? [];
        if (is_array($rowHeights) && $rowHeights) {
            $sheetRows = preg_replace_callback('/<row r="(\d+)"/', static function (array $match) use ($rowHeights): string {
                $height = $rowHeights[(int) $match[1]] ?? null;
                return is_numeric($height) && (float) $height > 0
                    ? '<row r="' . $match[1] . '" ht="' . sprintf('%.2f', (float) $height) . '" customHeight="1"'
                    : $match[0];
            }, $sheetRows) ?? $sheetRows;
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '" />'
            . '<sheetViews><sheetView workbookViewId="0">' . $pane . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="20" />' . $columnsXml
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . $autoFilter
            . $mergesXml
            . '</worksheet>';
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private static function columnNumber(string $name): int
    {
        $number = 0;
        foreach (str_split($name) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }
        return $number;
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function zip(array $files): string
    {
        $localFiles = '';
        $centralDirectory = '';
        $offset = 0;
        $dosTime = 0;
        $dosDate = 33; // 1980-01-01

        foreach ($files as $name => $contents) {
            $name = (string) $name;
            $contents = (string) $contents;
            $compressed = gzdeflate($contents, 6);
            if ($compressed === false) {
                throw new RuntimeException('Could not compress the Excel workbook.');
            }
            $checksum = crc32($contents);
            $compressedSize = strlen($compressed);
            $contentsSize = strlen($contents);
            $nameSize = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, $checksum, $compressedSize, $contentsSize, $nameSize, 0);
            $centralHeader = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate, $checksum, $compressedSize, $contentsSize, $nameSize, 0, 0, 0, 0, 0, $offset);
            $localFiles .= $localHeader . $name . $compressed;
            $centralDirectory .= $centralHeader . $name;
            $offset += strlen($localHeader) + $nameSize + $compressedSize;
        }

        $centralOffset = strlen($localFiles);
        $fileCount = count($files);
        $endOfCentralDirectory = pack('VvvvvVVv', 0x06054b50, 0, 0, $fileCount, $fileCount, strlen($centralDirectory), $centralOffset, 0);
        return $localFiles . $centralDirectory . $endOfCentralDirectory;
    }
}
