<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Services\AppSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('finance.receipts.index', [
            'transactions' => FinancialTransaction::query()
                ->where('status', 'approved')
                ->with(['member:id,first_name,middle_name,last_name,membership_number'])
                ->when($validated['search'] ?? null, function ($query, string $search): void {
                    $query->where(function ($nestedQuery) use ($search): void {
                        $nestedQuery
                            ->where('receipt_number', 'like', '%'.$search.'%')
                            ->orWhere('transaction_number', 'like', '%'.$search.'%')
                            ->orWhere('envelope_code', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'search' => $validated['search'] ?? '',
        ]);
    }

    public function show(string $receiptNumber, AppSettings $settings): View
    {
        $transaction = FinancialTransaction::query()
            ->where('receipt_number', $receiptNumber)
            ->where('status', 'approved')
            ->with([
                'member:id,first_name,middle_name,last_name,membership_number',
                'category:id,name,name_en,name_sw',
                'campaign:id,name',
                'creator:id,name',
            ])
            ->firstOrFail();

        return view('finance.receipts.show', [
            'transaction' => $transaction,
            'churchName' => $settings->churchName() ?? config('app.name'),
            'churchAddress' => $settings->churchAddress() ?? '',
            'churchPhone' => $settings->churchPhone() ?? '',
            'churchEmail' => $settings->churchEmail() ?? '',
            'churchLogo' => $settings->get('church_logo', ''),
        ]);
    }

    public function verify(string $receiptNumber): View
    {
        $transaction = FinancialTransaction::query()
            ->where('receipt_number', $receiptNumber)
            ->first(['id', 'status']);

        return view('finance.receipts.verify', [
            'isValid' => $transaction?->status === 'approved',
            'receiptNumber' => $receiptNumber,
        ]);
    }
}
