<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-white">{{ __('users.title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('users.description') }}</p>
            </div>
            @can('create users')
                <a href="{{ route('users.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('users.create') }}</a>
            @endcan
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('users.index') }}" class="grid gap-3 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-2 lg:grid-cols-4">
            <label class="sr-only" for="user-search">{{ __('users.search') }}</label>
            <input id="user-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('users.search') }}" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
            <label class="sr-only" for="user-status">{{ __('users.filter_status') }}</label>
            <select id="user-status" name="status" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                <option value="">{{ __('users.all_statuses') }}</option>
                @foreach (['active', 'inactive', 'suspended'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('users.statuses.'.$status) }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="user-role">{{ __('users.filter_role') }}</label>
            <select id="user-role" name="role_id" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                <option value="">{{ __('users.all_roles') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) ($filters['role_id'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('finance.filter') }}</button>
        </form>

        @if ($users->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-8 text-center text-slate-400">{{ __('users.no_users') }}</div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">{{ __('users.name') }}</th>
                            <th class="px-5 py-3">{{ __('users.email') }}</th>
                            <th class="px-5 py-3">{{ __('users.phone') }}</th>
                            <th class="px-5 py-3">{{ __('users.roles') }}</th>
                            <th class="px-5 py-3">{{ __('users.member') }}</th>
                            <th class="px-5 py-3">{{ __('users.status') }}</th>
                            <th class="px-5 py-3">{{ __('users.last_login') }}</th>
                            <th class="px-5 py-3">{{ __('users.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($users as $managedUser)
                            <tr>
                                <td class="px-5 py-4 font-semibold text-white">{{ $managedUser->name }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $managedUser->email }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $managedUser->phone ?: '—' }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $managedUser->roles->pluck('name')->join(', ') ?: __('users.no_roles') }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $managedUser->member?->membership_number ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ __('users.statuses.'.$managedUser->status) }}</td>
                                <td class="px-5 py-4 text-slate-400">{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? __('users.never_logged_in') }}</td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('users.show', $managedUser) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('users.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $users->links() }}</div>
        @endif
    </div>
</x-app-layout>
