<?php

namespace App\Services;

use finfo;
use Illuminate\Validation\ValidationException;
use Phar;
use PharData;
use RuntimeException;
use SimpleXMLElement;
use UnexpectedValueException;

class MemberSpreadsheet
{
    public const HEADERS = [
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'envelope_number',
        'membership_date',
        'status',
    ];

    private const MAIN_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const MAX_ARCHIVE_ENTRIES = 1000;

    private const MAX_UNCOMPRESSED_BYTES = 52428800;

    public function read(string $path, string $format): array
    {
        return match ($format) {
            'csv' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw ValidationException::withMessages([
                'file' => __('members.invalid_file_type'),
            ]),
        };
    }

    public function csvTemplate(): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to create the CSV template stream.');
        }

        fputcsv($stream, self::HEADERS, ',', '"', '');
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the CSV template stream.');
        }

        return $contents;
    }

    public function xlsxTemplate(): string
    {
        return $this->generateXlsx([self::HEADERS]);
    }

    public function generateXlsx(array $rows): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'members-template-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary Excel template.');
        }

        $archivePath = $temporaryPath.'.zip';
        unlink($temporaryPath);

        try {
            $archive = new PharData($archivePath, 0, null, Phar::ZIP);
            $archive['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>';
            $archive['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>';
            $archive['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="'.self::MAIN_NAMESPACE.'" xmlns:r="'.self::RELATIONSHIP_NAMESPACE.'">'
                .'<sheets><sheet name="Members" sheetId="1" r:id="rId1"/></sheets></workbook>';
            $archive['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>';
            $archive['xl/worksheets/sheet1.xml'] = $this->worksheetXml($rows);
            unset($archive);

            $contents = file_get_contents($archivePath);

            if ($contents === false) {
                throw new RuntimeException('Unable to read the generated Excel template.');
            }

            return $contents;
        } finally {
            if (isset($archive)) {
                unset($archive);
            }

            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    private function readCsv(string $path): array
    {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $acceptedMimeTypes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
        ];

        if (! in_array($mimeType, $acceptedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_csv_file'),
            ]);
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_csv_file'),
            ]);
        }

        $rows = [];
        $rowNumber = 0;

        try {
            while (($cells = fgetcsv($stream, escape: '')) !== false) {
                $rowNumber++;
                $cells[0] = isset($cells[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]) : '';

                $rows[] = [
                    'row_number' => $rowNumber,
                    'cells' => array_map(
                        static fn (mixed $value): string => trim((string) $value),
                        $cells,
                    ),
                ];
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        }

        $signature = fread($file, 4);
        fclose($file);

        if (! in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        }

        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            $entryCount = 0;
            $uncompressedBytes = 0;

            foreach ($archive as $entry) {
                $entryCount++;
                $uncompressedBytes += $entry->getSize();

                if ($entryCount > self::MAX_ARCHIVE_ENTRIES || $uncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    throw ValidationException::withMessages([
                        'file' => __('members.spreadsheet_too_large'),
                    ]);
                }
            }

            $workbook = $this->readXml($archive, 'xl/workbook.xml');
            $relationships = $this->readXml($archive, 'xl/_rels/workbook.xml.rels');
            $sheet = $workbook->sheets->sheet[0] ?? null;

            if ($sheet === null) {
                throw ValidationException::withMessages([
                    'file' => __('members.invalid_spreadsheet'),
                ]);
            }

            $relationshipId = (string) $sheet->attributes(self::RELATIONSHIP_NAMESPACE)->id;
            $target = null;

            foreach ($relationships->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId) {
                    $target = (string) $relationship['Target'];

                    if ((string) $relationship['TargetMode'] === 'External') {
                        $target = null;
                    }

                    break;
                }
            }

            $worksheetPath = $this->worksheetPath($target);
            $worksheet = $this->readXml($archive, $worksheetPath);
            $sharedStrings = $this->sharedStrings($archive);
            $rows = [];

            foreach ($worksheet->sheetData->row ?? [] as $row) {
                $cells = [];
                $rowNumber = (int) $row['r'];

                foreach ($row->c ?? [] as $cell) {
                    if (isset($cell->f)) {
                        throw ValidationException::withMessages([
                            'file' => __('members.formula_cells_not_supported'),
                        ]);
                    }

                    $columnIndex = $this->columnIndex((string) $cell['r']);

                    if ($columnIndex > 63) {
                        throw ValidationException::withMessages([
                            'file' => __('members.too_many_columns'),
                        ]);
                    }

                    $cells[$columnIndex] = $this->cellValue($cell, $sharedStrings);
                }

                if ($cells !== []) {
                    $lastColumnIndex = max(array_keys($cells));
                    $cells = array_replace(array_fill(0, $lastColumnIndex + 1, ''), $cells);
                }

                $rows[] = [
                    'row_number' => $rowNumber,
                    'cells' => array_values($cells),
                ];
            }

            return $rows;
        } catch (\PharException|UnexpectedValueException|\BadMethodCallException|RuntimeException $exception) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        } finally {
            if (isset($archive)) {
                unset($archive);
            }
        }
    }

    private function readXml(PharData $archive, string $path): SimpleXMLElement
    {
        if (! $archive->offsetExists($path)) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        }

        $contents = $archive[$path]->getContent();
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (! $xml instanceof SimpleXMLElement) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        }

        return $xml;
    }

    private function sharedStrings(PharData $archive): array
    {
        if (! $archive->offsetExists('xl/sharedStrings.xml')) {
            return [];
        }

        $strings = [];
        $sharedStrings = $this->readXml($archive, 'xl/sharedStrings.xml');

        foreach ($sharedStrings->si as $sharedString) {
            $sharedString->registerXPathNamespace('x', self::MAIN_NAMESPACE);
            $text = '';

            foreach ($sharedString->xpath('.//x:t') ?: [] as $textNode) {
                $text .= (string) $textNode;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function worksheetPath(?string $target): string
    {
        if ($target === null || str_contains($target, '\\')) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        }

        $segments = [];

        foreach (explode('/', ltrim($target, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw ValidationException::withMessages([
                        'file' => __('members.invalid_spreadsheet'),
                    ]);
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $path = implode('/', $segments);

        if (! str_starts_with($path, 'xl/')) {
            $path = 'xl/'.$path;
        }

        return $path;
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $inlineString = $cell->is;
            $inlineString->registerXPathNamespace('x', self::MAIN_NAMESPACE);
            $text = '';

            foreach ($inlineString->xpath('.//x:t') ?: [] as $textNode) {
                $text .= (string) $textNode;
            }

            return $text;
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            $index = filter_var($value, FILTER_VALIDATE_INT);

            if ($index === false || ! array_key_exists($index, $sharedStrings)) {
                throw ValidationException::withMessages([
                    'file' => __('members.invalid_spreadsheet'),
                ]);
            }

            return $sharedStrings[$index];
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $value;
    }

    private function columnIndex(string $cellReference): int
    {
        if (! preg_match('/^([A-Z]+)\d+$/i', $cellReference, $matches)) {
            throw ValidationException::withMessages([
                'file' => __('members.invalid_spreadsheet'),
            ]);
        }

        $columnIndex = 0;

        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $columnIndex = $columnIndex * 26 + ord($letter) - ord('A') + 1;
        }

        return $columnIndex - 1;
    }

    private function worksheetXml(array $rows): string
    {
        $xmlRows = '';

        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xmlCells = '';

            foreach (array_values($row) as $columnIndex => $value) {
                $cellReference = $this->columnName($columnIndex + 1).$rowNumber;
                $escapedValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                $xmlCells .= '<c r="'.$cellReference.'" t="inlineStr"><is><t xml:space="preserve">'
                    .$escapedValue.'</t></is></c>';
            }

            $xmlRows .= '<row r="'.$rowNumber.'">'.$xmlCells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::MAIN_NAMESPACE.'"><sheetData>'.$xmlRows.'</sheetData></worksheet>';
    }

    private function columnName(int $columnNumber): string
    {
        $name = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $name = chr($remainder + ord('A')).$name;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $name;
    }
}
