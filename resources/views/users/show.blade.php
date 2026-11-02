<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('users.index') }}" class="text-sm font-semibold text-blue-300 hover:text-blue-200">{{ __('users.back_to_list') }}</a>
                <h2 class="mt-2 text-3xl font-bold text-white">{{ $managedUser->name }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ $managedUser->email }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can('edit users')
                    <a href="{{ route('users.edit', $managedUser) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('users.edit') }}</a>
                    <form method="POST" action="{{ route('users.password-reset', $managedUser) }}" onsubmit="return window.confirm(@js(__('users.confirm_password_reset')))">
                        @csrf
                        <button type="submit" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2.5 text-sm font-semibold text-blue-200 hover:bg-blue-500/20">{{ __('users.reset_password') }}</button>
                    </form>
                @endcan
                @can('deactivate users')
                    <form method="POST" action="{{ route('users.status', $managedUser) }}" onsubmit="return window.confirm(@js(__('users.confirm_status')))">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $managedUser->status === 'active' ? 'inactive' : 'active' }}">
                        <button type="submit" class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-200 hover:bg-amber-500/20">
                            {{ __('users.statuses.'.$managedUser->status) }} · {{ __('users.statuses.'.($managedUser->status === 'active' ? 'inactive' : 'active')) }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-6 sm:grid-cols-2">
            <div><p class="text-xs text-slate-500">{{ __('users.phone') }}</p><p class="mt-1 text-slate-200">{{ $managedUser->phone ?: '—' }}</p></div>
            <div><p class="text-xs text-slate-500">{{ __('users.status') }}</p><p class="mt-1 text-slate-200">{{ __('users.statuses.'.$managedUser->status) }}</p></div>
            <div><p class="text-xs text-slate-500">{{ __('users.roles') }}</p><p class="mt-1 text-slate-200">{{ $managedUser->roles->pluck('name')->join(', ') ?: __('users.no_roles') }}</p></div>
            <div><p class="text-xs text-slate-500">{{ __('users.member') }}</p><p class="mt-1 text-slate-200">{{ $managedUser->member ? trim($managedUser->member->first_name.' '.$managedUser->member->last_name).' · '.$managedUser->member->membership_number : __('users.no_member') }}</p></div>
            <div><p class="text-xs text-slate-500">{{ __('users.created_at') }}</p><p class="mt-1 text-slate-200">{{ $managedUser->created_at->format('Y-m-d H:i') }}</p></div>
            <div><p class="text-xs text-slate-500">{{ __('users.last_login') }}</p><p class="mt-1 text-slate-200">{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? __('users.never_logged_in') }}</p></div>
            <div><p class="text-xs text-slate-500">{{ __('users.email') }}</p><p class="mt-1 text-slate-200">{{ $managedUser->hasVerifiedEmail() ? __('users.email_verified') : __('users.email_unverified') }}</p></div>
        </section>

        @can('manage permissions')
            <form method="POST" action="{{ route('users.permissions.update', $managedUser) }}" onsubmit="return window.confirm(@js(__('users.confirm_permissions')))" class="space-y-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-6">
                @csrf
                @method('PUT')
                <div>
                    <h3 class="font-semibold text-white">{{ __('users.permissions') }}</h3>
                    <p class="mt-1 text-xs text-slate-400">{{ __('users.permissions_help') }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissions as $permission)
                        <label class="flex items-start gap-2 rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-xs text-slate-300">
                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($managedUser->permissions->contains('id', $permission->id)) class="mt-0.5 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                            <span>{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('users.permissions') }}</button>
            </form>
        @endcan
    </div>
</x-app-layout>
