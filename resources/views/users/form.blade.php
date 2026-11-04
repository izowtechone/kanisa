<form
    x-data="{ saving: false }"
    @submit="if (saving) { $event.preventDefault(); } else { saving = true; }"
    method="POST"
    action="{{ $isEdit ? route('users.update', $managedUser) : route('users.store') }}"
    class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-6 shadow-xl"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-4 text-sm text-red-300">
            <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        <label class="block space-y-2 text-sm text-slate-300">
            <span>{{ __('users.name') }}</span>
            <input name="name" value="{{ old('name', $managedUser?->name) }}" maxlength="255" required autocomplete="name" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
            @error('name')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
        </label>
        <label class="block space-y-2 text-sm text-slate-300">
            <span>{{ __('users.email') }}</span>
            <input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" maxlength="255" required autocomplete="email" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
            @error('email')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
        </label>
        <label class="block space-y-2 text-sm text-slate-300">
            <span>{{ __('users.phone') }}</span>
            <input type="tel" name="phone" value="{{ old('phone', $managedUser?->phone) }}" maxlength="30" autocomplete="tel" @required(! $isEdit) class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
            @error('phone')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            @unless ($isEdit)<span class="text-xs text-slate-500">{{ __('users.phone_required_for_invitation') }}</span>@endunless
        </label>
        <label class="block space-y-2 text-sm text-slate-300">
            <span>{{ __('users.member') }}</span>
            <select name="member_id" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                <option value="">{{ __('users.select_member') }}</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((string) old('member_id', $managedUser?->member_id) === (string) $member->id)>
                        {{ $member->membership_number }} — {{ trim($member->first_name.' '.$member->middle_name.' '.$member->last_name) }}
                    </option>
                @endforeach
            </select>
            <span class="text-xs text-slate-500">{{ __('users.member_link_help') }}</span>
            @error('member_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
        </label>
        @unless ($isEdit)
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('users.status') }}</span>
                <select name="status" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @foreach (['active', 'inactive', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ __('users.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
    </div>

    @role('Super Admin')
        <label class="block space-y-2 text-sm text-slate-300">
            <span>{{ __('users.roles') }}</span>
            <select name="role_ids[]" multiple size="5" @required(! $isEdit) class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                @php($selectedRoleIds = old('role_ids', $managedUser?->roles?->pluck('id')->all() ?? []))
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(in_array($role->id, $selectedRoleIds))>{{ $role->display_name }}</option>
                @endforeach
            </select>
            <span class="text-xs text-slate-500">{{ __('users.roles_help') }}</span>
            @error('role_ids')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
        </label>
    @endrole

    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-800 pt-5">
        <a href="{{ $isEdit ? route('users.show', $managedUser) : route('users.index') }}" class="rounded-lg border border-slate-700 px-5 py-3 text-sm text-slate-300 hover:bg-slate-800">{{ __('members.cancel') }}</a>
        <button type="submit" :disabled="saving" class="rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
            <span x-show="!saving">{{ __($isEdit ? 'users.update' : 'users.save') }}</span>
            <span x-cloak x-show="saving">{{ __('members.saving') }}</span>
        </button>
    </div>
</form>
