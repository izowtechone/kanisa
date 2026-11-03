<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div><h2 class="text-3xl font-bold text-white">{{ __('finance.import_title') }}</h2><p class="mt-2 text-sm text-slate-400">{{ __('finance.import_description') }}</p></div>
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            <div class="flex flex-wrap gap-3"><a href="{{ route('finance.imports.template', 'xlsx') }}" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2.5 text-sm font-semibold text-blue-300 hover:bg-blue-500/20">{{ __('finance.download_excel_template') }}</a><a href="{{ route('finance.imports.template', 'csv') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.download_csv_template') }}</a></div>
            <p class="mt-4 text-sm text-slate-400">{{ __('finance.supported_formats') }}</p>
            <div class="mt-5 rounded-xl border border-blue-500/20 bg-blue-500/5 p-4 text-sm leading-6 text-slate-300">{{ __('finance.import_instructions') }}</div>
            <form method="POST" action="{{ route('finance.imports.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                @csrf
                <label class="block space-y-2 text-sm text-slate-300"><span>{{ __('finance.upload_category') }} <span class="text-rose-400">*</span></span><select name="category_id" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.choose_category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ __('finance.types.'.$category->type) }} · {{ $category->localizedName() }}</option>@endforeach</select>@error('category_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                <label class="block space-y-2 text-sm text-slate-300"><span>{{ __('finance.upload_file') }} <span class="text-rose-400">*</span></span><input type="file" name="file" required accept=".csv,.xlsx" class="block w-full rounded-lg border border-slate-700 bg-[#0b1120] p-3 text-sm text-slate-300 file:mr-4 file:rounded-md file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-slate-100">@error('file')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                <div class="flex justify-end"><button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.upload_validate') }}</button></div>
            </form>
        </section>
    </div>
</x-app-layout>
