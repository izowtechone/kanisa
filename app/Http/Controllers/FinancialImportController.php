<?php

namespace App\Http\Controllers;

use App\Models\FinanceCategory;
use App\Models\FinancialImport;
use App\Models\Member;
use App\Services\FinancialCsvSanitizer;
use App\Services\FinancialTransactionService;
use App\Services\MemberSpreadsheet;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialImportController extends Controller
{
    private const HEADERS = [
        'envelope_code',
        'amount',
        'payment_method',
        'transaction_date',
        'reference_number',
        'description',
        'notes',
    ];

    private const REQUIRED_HEADERS = [
        'envelope_code',
        'amount',
        'payment_method',
        'transaction_date',
    ];

    public function __construct(
        private readonly MemberSpreadsheet $spreadsheet,
        private readonly FinancialTransactionService $transactions,
        private readonly FinancialCsvSanitizer $csvSanitizer,
    ) {}

    public function create(): View
    {
        return view('finance.imports.create', [
            'categories' => FinanceCategory::query()
                ->whereIn('type', ['offering', 'tithe'])
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'type', 'name', 'name_en', 'name_sw']),
        ]);
    }

    public function template(string $format)
    {
        if ($format === 'csv') {
            $stream = fopen('php://temp', 'r+');

            if ($stream === false) {
                throw new \RuntimeException('Unable to create the offering template.');
            }

            fputcsv($stream, self::HEADERS, ',', '"', '');
            rewind($stream);
            $contents = stream_get_contents($stream);
            fclose($stream);

            if ($contents === false) {
                throw new \RuntimeException('Unable to read the offering template.');
            }

            return response($contents, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="envelope-offerings-template.csv"',
            ]);
        }

        return response($this->spreadsheet->generateXlsx([self::HEADERS]), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="envelope-offerings-template.xlsx"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'extensions:csv,xlsx', 'max:10240'],
            'category_id' => ['required', 'integer', 'exists:finance_categories,id'],
        ]);

        $category = FinanceCategory::query()
            ->whereKey($validated['category_id'])
            ->whereIn('type', ['offering', 'tithe'])
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => __('finance.import_category_invalid'),
            ]);
        }

        $file = $request->file('file');
        $fileHash = hash_file('sha256', $file->getRealPath());

        if ($fileHash === false) {
            throw ValidationException::withMessages([
                'file' => __('finance.import_file_unreadable'),
            ]);
        }
        $existingImport = FinancialImport::query()->where('file_hash', $fileHash)->first();

        if ($existingImport !== null) {
            if ($existingImport->uploaded_by !== Auth::id()) {
                throw ValidationException::withMessages([
                    'file' => __('finance.import_file_already_processed'),
                ]);
            }

            return redirect()->route('finance.imports.show', $existingImport);
        }

        $format = strtolower($file->getClientOriginalExtension());
        $spreadsheetRows = $this->spreadsheet->read($file->getRealPath(), $format);
        $previewRows = $this->validateRows($spreadsheetRows, $format === 'xlsx');
        $validRows = count(array_filter($previewRows, static fn (array $row): bool => $row['is_valid']));
        try {
            $import = FinancialImport::query()->create([
                'uuid' => (string) Str::uuid(),
                'uploaded_by' => Auth::id(),
                'category_id' => $category->id,
                'file_hash' => $fileHash,
                'file_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'status' => 'preview',
                'total_rows' => count($previewRows),
                'valid_rows' => $validRows,
                'invalid_rows' => count($previewRows) - $validRows,
                'rows' => $previewRows,
            ]);
        } catch (QueryException $exception) {
            $duplicate = FinancialImport::query()->where('file_hash', $fileHash)->first();

            if ($duplicate === null) {
                throw $exception;
            }

            if ($duplicate->uploaded_by !== Auth::id()) {
                throw ValidationException::withMessages([
                    'file' => __('finance.import_file_already_processed'),
                ]);
            }

            return redirect()->route('finance.imports.show', $duplicate);
        }

        return redirect()->route('finance.imports.show', $import);
    }

    public function show(FinancialImport $financialImport): View
    {
        $this->authorizeOwner($financialImport);

        return view('finance.imports.show', [
            'financialImport' => $financialImport->load('category:id,name,name_en,name_sw,type'),
            'duplicateRows' => count(array_filter(
                $financialImport->rows,
                static fn (array $row): bool => $row['is_duplicate'],
            )),
        ]);
    }

    public function confirm(FinancialImport $financialImport): RedirectResponse
    {
        $this->authorizeOwner($financialImport);

        $result = DB::transaction(function () use ($financialImport): array {
            $lockedImport = FinancialImport::query()->lockForUpdate()->findOrFail($financialImport->id);

            if ($lockedImport->status === 'completed') {
                return ['count' => $lockedImport->valid_rows, 'already_completed' => true];
            }

            if ($lockedImport->status !== 'preview') {
                throw ValidationException::withMessages([
                    'import' => __('finance.import_not_ready'),
                ]);
            }

            $created = 0;

            foreach ($lockedImport->rows as $row) {
                if (! $row['is_valid']) {
                    continue;
                }

                $member = Member::query()
                    ->where('envelope_number', $row['data']['envelope_code'])
                    ->where('status', 'active')
                    ->first();

                if ($member === null) {
                    throw ValidationException::withMessages([
                        'import' => __('finance.import_member_changed'),
                    ]);
                }

                $this->transactions->create([
                    'idempotency_key' => (string) Str::uuid(),
                    'type' => $lockedImport->category->type,
                    'category_id' => $lockedImport->category_id,
                    'member_id' => $member->id,
                    'envelope_code' => $member->envelope_number,
                    'amount' => $row['data']['amount'],
                    'payment_method' => $row['data']['payment_method'],
                    'transaction_date' => $row['data']['transaction_date'],
                    'reference_number' => $row['data']['reference_number'] ?: null,
                    'description' => $row['data']['description'] ?: null,
                    'notes' => $row['data']['notes'] ?: null,
                ], Auth::user());

                $created++;
            }

            $lockedImport->update([
                'status' => 'completed',
            ]);

            return ['count' => $created, 'already_completed' => false];
        });

        if ($result['already_completed']) {
            return redirect()
                ->route('finance.imports.show', $financialImport)
                ->with('success', __('finance.import_already_completed'));
        }

        return redirect()
            ->route('finance.imports.show', $financialImport)
            ->with('success', __('finance.import_completed', ['count' => $result['count']]));
    }

    public function report(FinancialImport $financialImport): StreamedResponse
    {
        $this->authorizeOwner($financialImport);

        return response()->streamDownload(function () use ($financialImport): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new \RuntimeException('Unable to open the import report stream.');
            }

            fputcsv($output, [
                __('finance.excel_row'),
                ...self::HEADERS,
                __('finance.validation_errors'),
            ], ',', '"', '');

            foreach ($financialImport->rows as $row) {
                if ($row['is_valid']) {
                    continue;
                }

                $rowData = [
                    $row['row_number'],
                    ...array_values($row['data']),
                    implode('; ', $row['errors']),
                ];

                fputcsv($output, array_map(
                    fn (?string $value): string => $this->csvSanitizer->escape($value),
                    array_map(static fn (mixed $value): ?string => $value === null ? null : (string) $value, $rowData),
                ), ',', '"', '');
            }

            fclose($output);
        }, 'envelope-import-rejected-rows.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, array{row_number: int, cells: array<int, string>}>  $rows
     * @return array<int, array{row_number: int, data: array<string, string>, errors: array<int, string>, is_valid: bool, is_duplicate: bool}>
     */
    private function validateRows(array $rows, bool $isExcel): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('finance.import_file_empty'),
            ]);
        }

        $headerRow = array_shift($rows);
        $headers = array_map(
            static fn (string $header): string => strtolower(trim($header)),
            $headerRow['cells'],
        );

        if (count($headers) !== count(array_unique($headers))
            || array_diff(self::REQUIRED_HEADERS, $headers) !== []
            || array_diff($headers, self::HEADERS) !== []) {
            throw ValidationException::withMessages([
                'file' => __('finance.import_headers_invalid'),
            ]);
        }

        if (count($rows) > 1000) {
            throw ValidationException::withMessages([
                'file' => __('finance.import_row_limit', ['count' => 1000]),
            ]);
        }

        $seenEnvelopes = [];
        $previewRows = [];

        foreach ($rows as $row) {
            $cells = $row['cells'];

            if (count(array_filter($cells, static fn (string $cell): bool => trim($cell) !== '')) === 0) {
                continue;
            }

            $data = array_fill_keys(self::HEADERS, '');

            foreach ($headers as $index => $header) {
                $data[$header] = trim((string) ($cells[$index] ?? ''));
            }

            if ($isExcel) {
                $data['transaction_date'] = $this->excelDate($data['transaction_date']);
            }

            $errors = [];
            $isDuplicate = false;
            $envelopeCode = $data['envelope_code'];

            if ($envelopeCode === '' || mb_strlen($envelopeCode) > 255) {
                $errors[] = __('finance.import_error_envelope');
            } elseif (isset($seenEnvelopes[$envelopeCode])) {
                $isDuplicate = true;
                $errors[] = __('finance.import_error_duplicate_envelope');
            } else {
                $seenEnvelopes[$envelopeCode] = true;

                $memberExists = Member::query()
                    ->where('envelope_number', $envelopeCode)
                    ->where('status', 'active')
                    ->exists();

                if (! $memberExists) {
                    $errors[] = __('finance.import_error_member_not_found');
                }
            }

            if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $data['amount'])
                || preg_match('/^0(?:\.0{1,2})?$/', $data['amount'])) {
                $errors[] = __('finance.import_error_amount');
            }

            if (! in_array($data['payment_method'], ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other'], true)) {
                $errors[] = __('finance.import_error_payment_method');
            }

            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['transaction_date']);

            if ($date === false || $date->format('Y-m-d') !== $data['transaction_date'] || $data['transaction_date'] > now()->toDateString()) {
                $errors[] = __('finance.import_error_date');
            }

            if (mb_strlen($data['description']) > 500 || mb_strlen($data['notes']) > 5000) {
                $errors[] = __('finance.import_error_text_too_long');
            }

            if (mb_strlen($data['reference_number']) > 100) {
                $errors[] = __('finance.import_error_reference');
            }

            $previewRows[] = [
                'row_number' => $row['row_number'],
                'data' => $data,
                'errors' => $errors,
                'is_valid' => $errors === [],
                'is_duplicate' => $isDuplicate,
            ];
        }

        if ($previewRows === []) {
            throw ValidationException::withMessages([
                'file' => __('finance.import_file_empty'),
            ]);
        }

        return $previewRows;
    }

    private function excelDate(string $value): string
    {
        if (! preg_match('/^\d{1,7}(?:\.\d+)?$/', $value)) {
            return $value;
        }

        $serial = (int) $value;

        if ($serial < 61 || $serial > 2958465) {
            return $value;
        }

        return (new \DateTimeImmutable('1899-12-30'))
            ->modify('+'.$serial.' days')
            ->format('Y-m-d');
    }

    private function authorizeOwner(FinancialImport $financialImport): void
    {
        abort_unless($financialImport->uploaded_by === Auth::id(), 403);
    }
}
