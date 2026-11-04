<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">{{ $category->exists ? __('finance.offering_category_edit') : __('finance.offering_category_add') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __('finance.offering_category_form_description') }}</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $category->exists ? route('finance.offering-categories.update', $category) : route('finance.offering-categories.store') }}" class="space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.category_code') }}</span>
                    <input name="code" value="{{ old('code', $category->code) }}" maxlength="40" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    <span class="text-xs text-slate-500">{{ __('finance.offering_category_code_hint') }}</span>
                    @error('code')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('finance.category_name_sw') }}</span>
                    <input required name="name_sw" value="{{ old('name_sw', $category->name_sw) }}" maxlength="150" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @error('name_sw')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('finance.category_name_en') }}</span>
                    <input required name="name_en" value="{{ old('name_en', $category->name_en ?: $category->name) }}" maxlength="150" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @error('name_en')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('finance.category_description') }}</span>
                    <textarea name="description" rows="4" maxlength="2000" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old('description', $category->description) }}</textarea>
                    @error('description')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ route('finance.offering-categories.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.cancel') }}</a>
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.save_category') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
