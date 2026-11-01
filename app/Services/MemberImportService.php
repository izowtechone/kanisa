<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberImport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MemberImportService
{
    public const MAX_ROWS = 5000;

    public const MAX_FILE_SIZE_KILOBYTES = 10240;

    public function __construct(
        private readonly MemberSpreadsheet $spreadsheet,
        private readonly MembershipNumberGenerator $membershipNumbers,
    ) {}

    public function preview(User $user, UploadedFile $file): MemberImport
    {
        $format = strtolower($file->getClientOriginalExtension());
        $spreadsheetRows = $this->spreadsheet->read($file->getRealPath(), $format);
        $rows = $this->validateRows($spreadsheetRows, $format);
        $totalRows = count($rows);
        $duplicateRows = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['duplicate'],
        ));
        $invalidRows = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['errors'] !== [],
        ));
        $previewPath = sprintf(
            'member-imports/%s/%s.json',
            $user->getKey(),
            Str::uuid(),
        );
        $preview = [
            'rows' => $rows,
        ];
        $contents = json_encode($preview, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (! Storage::disk('local')->put($previewPath, $contents)) {
            throw new RuntimeException('Unable to store the private member import preview.');
        }

        return MemberImport::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'preview_path' => $previewPath,
            'total_rows' => $totalRows,
            'valid_rows' => $totalRows - $invalidRows,
            'invalid_rows' => $invalidRows,
            'duplicate_rows' => $duplicateRows,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function rows(MemberImport $memberImport): array
    {
        if ($memberImport->status === 'completed') {
            return [];
        }

        $this->ensureNotExpired($memberImport);
        $contents = Storage::disk('local')->get($memberImport->preview_path);
        $preview = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return $preview['rows'];
    }

    public function process(MemberImport $memberImport, User $user): MemberImport
    {
        $previewPath = $memberImport->preview_path;
        $processedImport = DB::transaction(function () use ($memberImport, $user): MemberImport {
            $lockedImport = MemberImport::query()
                ->whereKey($memberImport->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedImport->user_id === $user->getKey(), 404);

            if ($lockedImport->status === 'completed') {
                return $lockedImport;
            }

            $this->ensureNotExpired($lockedImport);
            $rows = $this->rows($lockedImport);
            $importedRows = 0;
            $failedRows = 0;
            $duplicateRows = $lockedImport->duplicate_rows;

            foreach ($rows as &$row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $envelopeNumber = $row['values']['envelope_number'];

                if ($this->envelopeNumberExists($envelopeNumber)) {
                    $row['errors'][] = __('members.envelope_number_taken');
                    $row['duplicate'] = true;
                    $duplicateRows++;

                    continue;
                }

                try {
                    DB::transaction(function () use ($row): void {
                        Member::query()->create([
                            ...$row['values'],
                            'membership_number' => $this->membershipNumbers->generate(),
                        ]);
                    });

                    $importedRows++;
                } catch (QueryException $exception) {
                    if (! $this->isUniqueConstraintViolation($exception)) {
                        throw $exception;
                    }

                    $row['errors'][] = __('members.import_duplicate_conflict');
                    $row['duplicate'] = true;
                    $duplicateRows++;
                }
            }
            unset($row);

            $failedRows = 0;
            $skippedRows = $lockedImport->total_rows - $importedRows - $failedRows;
            $reportPath = sprintf(
                'member-import-reports/%s/%s.csv',
                $user->getKey(),
                $lockedImport->uuid,
            );
            $report = $this->buildReportContents($rows);

            if (! Storage::disk('local')->put($reportPath, $report)) {
                throw new RuntimeException('Unable to store the private member import report.');
            }

            $this->updatePreview($lockedImport, $rows);

            $lockedImport->forceFill([
                'report_path' => $reportPath,
                'duplicate_rows' => $duplicateRows,
                'imported_rows' => $importedRows,
                'failed_rows' => $failedRows,
                'skipped_rows' => $skippedRows,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            return $lockedImport;
        });

        if ($processedImport->status === 'completed' && $processedImport->preview_path === $previewPath) {
            Storage::disk('local')->delete($previewPath);
        }

        return $processedImport;
    }

    public function reportContents(MemberImport $memberImport): string
    {
        if ($memberImport->status === 'completed') {
            abort_unless(
                $memberImport->report_path !== null
                    && Storage::disk('local')->exists($memberImport->report_path),
                404,
            );

            return Storage::disk('local')->get($memberImport->report_path);
        }

        return $this->buildReportContents($this->rows($memberImport));
    }

    /**
     * @param  list<array{row_number: int, cells: list<string>}>  $spreadsheetRows
     * @return list<array{row_number: int, values: array<string, mixed>, errors: list<string>, duplicate: bool}>
     */
    private function validateRows(array $spreadsheetRows, string $format): array
    {
        if ($spreadsheetRows === []) {
            throw ValidationException::withMessages([
                'file' => __('members.missing_header_row'),
            ]);
        }

        $headerRow = array_shift($spreadsheetRows);
        $headers = array_map(
            static fn (string $header): string => Str::lower(trim($header)),
            $headerRow['cells'],
        );

        if ($headers !== []) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages([
                'file' => __('members.duplicate_headers'),
            ]);
        }

        $allowedHeaders = MemberSpreadsheet::HEADERS;
        $missingHeaders = array_diff(['first_name', 'last_name', 'gender', 'envelope_number'], $headers);
        $unknownHeaders = array_diff($headers, $allowedHeaders);

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => __('members.missing_required_columns', [
                    'columns' => implode(', ', $missingHeaders),
                ]),
            ]);
        }

        if ($unknownHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => __('members.unknown_columns', [
                    'columns' => implode(', ', $unknownHeaders),
                ]),
            ]);
        }

        $columnIndexes = array_flip($headers);

        if (count($spreadsheetRows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('members.too_many_rows', ['count' => self::MAX_ROWS]),
            ]);
        }

        $rows = [];

        foreach ($spreadsheetRows as $spreadsheetRow) {
            $cells = $spreadsheetRow['cells'];

            if (count(array_filter($cells, static fn (string $cell): bool => trim($cell) !== '')) === 0) {
                continue;
            }

            $values = [];

            foreach ($allowedHeaders as $header) {
                $value = isset($columnIndexes[$header])
                    ? trim((string) ($cells[$columnIndexes[$header]] ?? ''))
                    : '';

                if ($format === 'xlsx' && in_array($header, ['date_of_birth', 'membership_date'], true)) {
                    $value = $this->normalizeExcelDate($value);
                }

                $values[$header] = $value === '' ? null : $value;
            }

            $values['gender'] = match (Str::lower((string) $values['gender'])) {
                'male' => 'Male',
                'female' => 'Female',
                default => $values['gender'],
            };
            $values['status'] = Str::lower((string) ($values['status'] ?: 'active'));

            $validator = Validator::make($values, [
                'first_name' => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'gender' => ['required', Rule::in(['Male', 'Female'])],
                'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'envelope_number' => ['required', 'string', 'max:255'],
                'membership_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ], $this->validationMessages(), $this->attributes());

            $rows[] = [
                'row_number' => $spreadsheetRow['row_number'],
                'values' => $values,
                'errors' => $validator->errors()->all(),
                'duplicate' => false,
            ];
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('members.too_many_rows', ['count' => self::MAX_ROWS]),
            ]);
        }

        $this->markDuplicateEnvelopes($rows);

        return $rows;
    }

    /**
     * @param  list<array{row_number: int, values: array<string, mixed>, errors: list<string>, duplicate: bool}>  $rows
     */
    private function markDuplicateEnvelopes(array &$rows): void
    {
        $envelopeGroups = [];

        foreach ($rows as $index => $row) {
            $envelopeNumber = $row['values']['envelope_number'];

            if ($envelopeNumber === null) {
                continue;
            }

            $normalized = mb_strtolower($envelopeNumber);
            $envelopeGroups[$normalized][] = $index;
        }

        $envelopeNumbers = array_keys($envelopeGroups);
        $existingEnvelopes = Member::withTrashed()
            ->whereIn(DB::raw('LOWER(envelope_number)'), $envelopeNumbers)
            ->pluck('envelope_number')
            ->map(static fn (string $number): string => mb_strtolower($number))
            ->all();
        $existingEnvelopeLookup = array_fill_keys($existingEnvelopes, true);

        foreach ($envelopeGroups as $normalized => $indexes) {
            $isDuplicate = count($indexes) > 1 || isset($existingEnvelopeLookup[$normalized]);

            if (! $isDuplicate) {
                continue;
            }

            foreach ($indexes as $index) {
                $rows[$index]['duplicate'] = true;
                $rows[$index]['errors'][] = isset($existingEnvelopeLookup[$normalized])
                    ? __('members.envelope_number_taken')
                    : __('members.duplicate_in_upload');
            }
        }
    }

    private function normalizeExcelDate(?string $value): ?string
    {
        if ($value === null || ! is_numeric($value)) {
            return $value;
        }

        $serial = (int) $value;

        if ($serial < 1 || $serial > 2958465) {
            return $value;
        }

        return CarbonImmutable::create(1899, 12, 30)
            ->addDays($serial)
            ->toDateString();
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'first_name' => __('members.first_name'),
            'middle_name' => __('members.middle_name'),
            'last_name' => __('members.last_name'),
            'gender' => __('members.gender'),
            'date_of_birth' => __('members.date_of_birth'),
            'phone' => __('members.phone'),
            'email' => __('members.email'),
            'address' => __('members.address'),
            'envelope_number' => __('members.envelope_number'),
            'membership_date' => __('members.membership_date'),
            'status' => __('members.status'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'required' => __('members.validation_required'),
            'string' => __('members.validation_string'),
            'email' => __('members.validation_email'),
            'max' => __('members.validation_max'),
            'date_format' => __('members.validation_date_format'),
            'before_or_equal' => __('members.validation_before_or_equal'),
            'in' => __('members.validation_in'),
        ];
    }

    /**
     * @param  list<array{row_number: int, values: array<string, mixed>, errors: list<string>, duplicate: bool}>  $rows
     */
    private function buildReportContents(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to create the member import report stream.');
        }

        fputcsv(
            $stream,
            ['excel_row', ...MemberSpreadsheet::HEADERS, 'errors'],
            ',',
            '"',
            '',
        );

        foreach ($rows as $row) {
            if ($row['errors'] === []) {
                continue;
            }

            $values = array_map(
                fn (string $header): string => $this->safeCsvValue((string) ($row['values'][$header] ?? '')),
                MemberSpreadsheet::HEADERS,
            );

            fputcsv(
                $stream,
                [
                    $row['row_number'],
                    ...$values,
                    $this->safeCsvValue(implode('; ', $row['errors'])),
                ],
                ',',
                '"',
                '',
            );
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the member import report stream.');
        }

        return $contents;
    }

    private function safeCsvValue(string $value): string
    {
        if (preg_match('/^\s*[=+\-@]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<array{row_number: int, values: array<string, mixed>, errors: list<string>, duplicate: bool}>  $rows
     */
    private function updatePreview(MemberImport $memberImport, array $rows): void
    {
        $contents = json_encode(['rows' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (! Storage::disk('local')->put($memberImport->preview_path, $contents)) {
            throw new RuntimeException('Unable to update the private member import preview.');
        }
    }

    private function envelopeNumberExists(string $envelopeNumber): bool
    {
        return Member::withTrashed()
            ->whereRaw('LOWER(envelope_number) = ?', [mb_strtolower($envelopeNumber)])
            ->exists();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $driver = DB::connection()->getDriverName();
        $sqlState = (string) $exception->getCode();
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = mb_strtolower($exception->getMessage());

        return match ($driver) {
            'mysql' => $sqlState === '23000' && $errorCode === 1062,
            'pgsql' => $sqlState === '23505',
            'sqlite' => in_array($errorCode, [19, 1555, 2067], true)
                && str_contains($message, 'members.'),
            default => false,
        };
    }

    private function ensureNotExpired(MemberImport $memberImport): void
    {
        if ($memberImport->expires_at->isPast() && $memberImport->status !== 'completed') {
            abort(410, __('members.import_expired'));
        }

        if (! Storage::disk('local')->exists($memberImport->preview_path)
            && $memberImport->status !== 'completed') {
            abort(404);
        }
    }
}
