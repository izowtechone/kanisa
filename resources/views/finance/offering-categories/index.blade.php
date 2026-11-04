<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-white">{{ __('finance.offering_categories_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.offering_categories_description') }}</p>
            </div>
            @canany(['create offering categories', 'manage finance categories'])
                <a href="{{ route('finance.offering-categories.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                    {{ __('finance.offering_category_add') }}
                </a>
            @endcanany
        </div>

        @if (session('error'))
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">{{ session('error') }}</div>
        @endif

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('finance.offering-categories.index') }}" x-data="{}" x-ref="searchForm" class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-[#111a2e] p-4 sm:flex-row">
            <label class="min-w-0 flex-1 space-y-1 text-sm text-slate-300">
                <span>{{ __('finance.search') }}</span>
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="{{ __('finance.offering_category_search_placeholder') }}"
                    aria-label="{{ __('finance.offering_category_search_placeholder') }}"
                    @input.debounce.300ms="$refs.searchForm.requestSubmit()"
                    class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"
                >
            </label>
            <label class="space-y-1 text-sm text-slate-300">
                <span>{{ __('finance.status') }}</span>
                <select name="status" @change="$refs.searchForm.requestSubmit()" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    <option value="">{{ __('finance.offering_category_all_statuses') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('finance.active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('finance.inactive') }}</option>
                </select>
            </label>
            <noscript>
                <button class="self-end rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200">{{ __('finance.filter') }}</button>
            </noscript>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">{{ __('finance.category_name_sw') }}</th>
                        <th class="px-5 py-3">{{ __('finance.category_name_en') }}</th>
                        <th class="px-5 py-3">{{ __('finance.category_code') }}</th>
                        <th class="px-5 py-3">{{ __('finance.category_description') }}</th>
                        <th class="px-5 py-3">{{ __('finance.status') }}</th>
                        <th class="px-5 py-3">{{ __('finance.offering_category_transaction_count') }}</th>
                        <th class="px-5 py-3">{{ __('finance.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-white">{{ $category->name_sw ?: $category->name }}</td>
                            <td class="px-5 py-4 text-slate-200">{{ $category->name_en ?: $category->name }}</td>
                            <td class="px-5 py-4 font-mono text-slate-300">{{ $category->code }}</td>
                            <td class="max-w-xs px-5 py-4 text-xs text-slate-400">{{ $category->description }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300">
                                    {{ $category->is_active ? __('finance.active') : __('finance.inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-300">{{ $category->transactions_count }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-3">
                                    @canany(['edit offering categories', 'manage finance categories'])
                                        <a href="{{ route('finance.offering-categories.edit', $category) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('members.edit') }}</a>
                                    @endcanany
                                    @canany(['toggle offering categories', 'manage finance categories'])
                                        <form method="POST" action="{{ route('finance.offering-categories.toggle', $category) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="font-semibold text-slate-300 hover:text-white">
                                                {{ $category->is_active ? __('finance.deactivate') : __('finance.activate') }}
                                            </button>
                                        </form>
                                    @endcanany
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('finance.offering_categories_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $categories->links() }}</div>
    </div>
</x-app-layout>
