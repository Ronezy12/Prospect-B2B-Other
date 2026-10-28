<?php

namespace App\Services\Import;

use Generator;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use RuntimeException;

/**
 * Lecture uniforme des fichiers CSV et Excel. Les lignes sont rendues sous
 * forme de listes de cellules (index de colonne → valeur texte).
 */
class ImportReader
{
    private const XLSX_CHUNK = 1000;

    public function __construct(private readonly string $path, private readonly string $type)
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Fichier d\'import introuvable.');
        }
    }

    /** @return array<int, string> */
    public function headers(): array
    {
        foreach ($this->rows(includeHeader: true) as $row) {
            return array_map(fn ($h) => trim((string) $h), $row);
        }

        return [];
    }

    /** @return array<int, array<int, string>> */
    public function preview(int $limit): array
    {
        $rows = [];
        foreach ($this->rows() as $number => $row) {
            $rows[$number] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->rows() as $_) {
            $count++;
        }

        return $count;
    }

    /**
     * @return Generator<int, array<int, string>> numéro de ligne du fichier (1 = en-tête) → cellules
     */
    public function rows(bool $includeHeader = false): Generator
    {
        $generator = $this->type === 'csv' ? $this->csvRows() : $this->xlsxRows();

        foreach ($generator as $number => $row) {
            if ($number === 1 && ! $includeHeader) {
                continue;
            }
            if (! $includeHeader && $this->isEmpty($row)) {
                continue;
            }
            yield $number => $row;
        }
    }

    private function csvRows(): Generator
    {
        $content = file_get_contents($this->path);
        if ($content === false) {
            throw new RuntimeException('Lecture du fichier impossible.');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        // Excel enregistre souvent les CSV en Windows-1252.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $reader = Reader::fromString($content);
        $reader->setDelimiter($this->detectDelimiter($content));

        $number = 0;
        foreach ($reader->getRecords() as $record) {
            $number++;
            yield $number => array_map(fn ($v) => trim((string) $v), array_values($record));
        }
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n") ?: '';
        $counts = [];
        foreach ([';', ',', "\t", '|'] as $delimiter) {
            $counts[$delimiter] = substr_count($firstLine, $delimiter);
        }
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function xlsxRows(): Generator
    {
        $reader = IOFactory::createReader(ucfirst($this->type));
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $info = $reader->listWorksheetInfo($this->path);
        $totalRows = (int) ($info[0]['totalRows'] ?? 0);
        $totalColumns = (int) ($info[0]['totalColumns'] ?? 0);
        $filter = new class implements IReadFilter
        {
            public int $start = 1;

            public int $end = 1;

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->start && $row <= $this->end;
            }
        };
        $reader->setReadFilter($filter);

        // Chargement par blocs pour borner la mémoire sur les gros classeurs.
        for ($start = 1; $start <= $totalRows; $start += self::XLSX_CHUNK) {
            $filter->start = $start;
            $filter->end = $start + self::XLSX_CHUNK - 1;
            $spreadsheet = $reader->load($this->path);
            $sheet = $spreadsheet->getSheet(0);
            $end = min($filter->end, $totalRows);
            for ($row = $start; $row <= $end; $row++) {
                $cells = [];
                for ($col = 1; $col <= $totalColumns; $col++) {
                    $value = $sheet->getCell([$col, $row])->getValue();
                    $cells[] = trim($this->stringify($value));
                }
                yield $row => $cells;
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    private function isEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }
}
