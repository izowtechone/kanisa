<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><h2 class="text-3xl font-bold text-white">{{ __('finance.manage_categories') }} · {{ __('finance.types.'.$type) }}</h2><p class="mt-2 text-sm text-slate-400">{{ __('finance.description') }}</p></div>
            @can('manage finance categories')
                <a href="{{ route('finance.categories.create', $type) }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.add_category') }}</a>
            @endcan
        </div>
        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif
        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">{{ __('finance.category_code') }}</th><th class="px-5 py-3">{{ __('finance.category_name') }}</th><th class="px-5 py-3">{{ __('finance.status') }}</th><th class="px-5 py-3">{{ __('finance.transaction_number') }}</th><th class="px-5 py-3">{{ __('finance.actions') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="px-5 py-4 font-mono text-slate-300">{{ $category->code }}</td>
                            <td class="px-5 py-4"><span class="font-semibold text-white">{{ $category->localizedName() }}</span>@if ($category->description)<span class="mt-1 block max-w-lg text-xs text-slate-400">{{ $category->description }}</span>@endif</td>
                            <td class="px-5 py-4"><span class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300">{{ $category->is_active ? __('finance.active') : __('finance.inactive') }}</span></td>
                            <td class="px-5 py-4 text-slate-300">{{ $category->transactions_count }}</td>
                            <td class="px-5 py-4"><div class="flex gap-3">
                                @can('manage finance categories')<a href="{{ route('finance.categories.edit', [$type, $category]) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('members.edit') }}</a>
                                <form method="POST" action="{{ route('finance.categories.toggle', [$type, $category]) }}">@csrf @method('PATCH')<button class="font-semibold text-slate-300 hover:text-white">{{ $category->is_active ? __('finance.deactivate') : __('finance.activate') }}</button></form>@endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('finance.no_categories') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $categories->links() }}</div>
    </div>
</x-app-layout>
