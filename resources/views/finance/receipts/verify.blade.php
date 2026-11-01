<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('finance.receipt_verification_title') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0b1120] font-sans text-slate-200 antialiased">
        <main class="mx-auto max-w-xl space-y-5 px-4 py-16">
            <h1 class="text-3xl font-bold text-white">{{ __('finance.receipt_verification_title') }}</h1>
            <div @class([
                'rounded-2xl border p-6',
                'border-emerald-800 bg-emerald-950/40 text-emerald-300' => $isValid,
                'border-amber-800 bg-amber-950/40 text-amber-300' => ! $isValid,
            ])>
                <p class="font-semibold">{{ $isValid ? __('finance.receipt_valid') : __('finance.receipt_invalid') }}</p>
                <p class="mt-2 break-all text-sm">{{ __('finance.receipt_number') }}: {{ $receiptNumber }}</p>
            </div>
            <p class="text-sm text-slate-400">{{ __('finance.public_verification_notice') }}</p>
        </main>
    </body>
</html>
