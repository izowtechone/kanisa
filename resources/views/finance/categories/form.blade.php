<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6">
        <div><h2 class="text-3xl font-bold text-white">{{ $category->exists ? __('finance.edit_category') : __('finance.add_category') }}</h2><p class="mt-2 text-sm text-slate-400">{{ $category->exists ? __('finance.edit_category_description') : __('finance.create_category_description') }}</p></div>
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $category->exists ? route('finance.categories.update', [$type, $category]) : route('finance.categories.store', $type) }}" class="space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf
            @if ($category->exists) @method('PUT') @endif
            <div class="rounded-lg border border-slate-800 bg-[#0b1120] px-4 py-3 text-sm text-slate-300">{{ __('finance.type') }}: {{ __('finance.types.'.$type) }}</div>
            <label class="block space-y-2 text-sm text-slate-300"><span>{{ __('finance.category_code') }}</span><input required name="code" value="{{ old('code', $category->code) }}" maxlength="40" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('code')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
            <label class="block space-y-2 text-sm text-slate-300"><span>{{ __('finance.category_name') }}</span><input required name="name" value="{{ old('name', $category->name) }}" maxlength="150" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('name')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
            <label class="block space-y-2 text-sm text-slate-300"><span>{{ __('finance.category_description') }}</span><textarea name="description" rows="4" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old('description', $category->description) }}</textarea>@error('description')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
            <div class="flex justify-end gap-3"><a href="{{ route('finance.categories.index', $type) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.cancel') }}</a><button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.save_category') }}</button></div>
        </form>
    </div>
</x-app-layout>
