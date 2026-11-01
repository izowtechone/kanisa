<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Models\Setting;
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

    public function show(string $receiptNumber): View
    {
        $transaction = FinancialTransaction::query()
            ->where('receipt_number', $receiptNumber)
            ->where('status', 'approved')
            ->with([
                'member:id,first_name,middle_name,last_name,membership_number',
                'category:id,name',
                'campaign:id,name',
                'creator:id,name',
            ])
            ->firstOrFail();

        return view('finance.receipts.show', [
            'transaction' => $transaction,
            'churchName' => Setting::getValue('church_name', config('app.name')),
            'churchAddress' => Setting::getValue('system_address', ''),
            'churchPhone' => Setting::getValue('system_phone', ''),
            'churchEmail' => Setting::getValue('system_email', ''),
            'churchLogo' => Setting::getValue('church_logo', ''),
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
