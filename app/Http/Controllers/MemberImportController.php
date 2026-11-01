<?php

namespace App\Http\Controllers;

use App\Models\MemberImport;
use App\Services\MemberImportService;
use App\Services\MembershipNumberGenerator;
use App\Services\MemberSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportController extends Controller
{
    public function create(MembershipNumberGenerator $membershipNumbers): View
    {
        return view('members.import', [
            'membershipNumberConfigured' => $membershipNumbers->preview() !== null,
        ]);
    }

    public function template(string $format, MemberSpreadsheet $spreadsheet): StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        if ($format === 'csv') {
            $contents = $spreadsheet->csvTemplate();

            return response()->streamDownload(
                static function () use ($contents): void {
                    echo $contents;
                },
                'members-template.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        $contents = $spreadsheet->xlsxTemplate();

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            'members-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function store(Request $request, MemberImportService $imports): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:'.MemberImportService::MAX_FILE_SIZE_KILOBYTES],
        ], [
            'file.required' => __('members.validation_file_required'),
            'file.file' => __('members.invalid_file_type'),
            'file.extensions' => __('members.invalid_file_type'),
            'file.max' => __('members.validation_file_max', [
                'size' => MemberImportService::MAX_FILE_SIZE_KILOBYTES / 1024,
            ]),
        ]);

        $memberImport = $imports->preview($request->user(), $validated['file']);

        return redirect()
            ->route('members.import.show', $memberImport)
            ->with('success', __('members.preview_description'));
    }

    public function show(
        MemberImport $memberImport,
        MemberImportService $imports,
        MembershipNumberGenerator $membershipNumbers,
    ): View {
        $this->authorizeImport($memberImport);

        return view('members.import-preview', [
            'memberImport' => $memberImport,
            'rows' => $imports->rows($memberImport),
            'membershipNumberConfigured' => $membershipNumbers->preview() !== null,
        ]);
    }

    public function confirm(
        MemberImport $memberImport,
        Request $request,
        MemberImportService $imports,
        MembershipNumberGenerator $membershipNumbers,
    ): RedirectResponse {
        $this->authorizeImport($memberImport);

        if ($memberImport->valid_rows > 0 && $membershipNumbers->preview() === null) {
            throw ValidationException::withMessages([
                'church_short_name' => __('members.configure_church_short_name'),
            ]);
        }

        $processedImport = $imports->process($memberImport, $request->user());

        return redirect()
            ->route('members.import.show', $processedImport)
            ->with('success', __('members.import_complete'));
    }

    public function report(MemberImport $memberImport, MemberImportService $imports): StreamedResponse
    {
        $this->authorizeImport($memberImport);
        $contents = $imports->reportContents($memberImport);

        if (trim($contents) === '') {
            throw ValidationException::withMessages([
                'file' => __('members.report_unavailable'),
            ]);
        }

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            'members-rejected-'.$memberImport->uuid.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function authorizeImport(MemberImport $memberImport): void
    {
        abort_unless($memberImport->user_id === Auth::id(), 404);
    }
}
