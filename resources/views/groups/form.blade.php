<x-app-layout>
    @php
        $inputClass = 'w-full rounded-lg border bg-[#0b1120] px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500';
        $labelClass = 'mb-2 block text-sm font-medium text-gray-300';
        $selectedDepartmentId = old('department_id', $group?->department_id ?? request()->query('department_id'));
        $selectedLeaderId = old('leader_member_id', $group?->leader_member_id);
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('groups.title') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __($group ? 'groups.edit_title' : 'groups.create_title') }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ __($group ? 'groups.edit_description' : 'groups.create_description') }}</p>
            </div>
            <a href="{{ route('groups.index') }}" class="rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('groups.back_to_list') }}</a>
        </div>

        @if ($departments->isEmpty())
            <div role="status" class="rounded-xl border border-amber-700/60 bg-amber-950/40 p-5 text-sm text-amber-200">
                {{ __('groups.no_departments_available') }}
                @can('create departments')
                    <a href="{{ route('departments.create') }}" class="ml-1 font-semibold underline underline-offset-4">{{ __('departments.create') }}</a>
                @endcan
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-red-300">
                <p class="mb-2 font-semibold">{{ __('groups.validation_heading') }}</p>
                <ul class="list-inside list-disc space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($departments->isNotEmpty())
        <form method="POST" action="{{ $group ? route('groups.update', $group) : route('groups.store') }}" class="space-y-6 rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl sm:p-8">
            @csrf
            @if ($group)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="group-department" class="{{ $labelClass }}">{{ __('groups.department') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                    <select id="group-department" name="department_id" required class="{{ $inputClass }} {{ $errors->has('department_id') ? 'border-red-600' : 'border-gray-700' }}">
                        <option value="">{{ __('groups.choose_department') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="group-code" class="{{ $labelClass }}">{{ __('groups.code') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                    <input id="group-code" name="code" value="{{ old('code', $group?->code) }}" required maxlength="50" autocomplete="off" @error('code') aria-invalid="true" @enderror class="{{ $inputClass }} {{ $errors->has('code') ? 'border-red-600' : 'border-gray-700' }}">
                    @error('code')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="group-name" class="{{ $labelClass }}">{{ __('groups.name') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                    <input id="group-name" name="name" value="{{ old('name', $group?->name) }}" required maxlength="150" @error('name') aria-invalid="true" @enderror class="{{ $inputClass }} {{ $errors->has('name') ? 'border-red-600' : 'border-gray-700' }}">
                    @error('name')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="group-leader" class="{{ $labelClass }}">{{ __('groups.leader') }}</label>
                    <select id="group-leader" name="leader_member_id" class="{{ $inputClass }} border-gray-700">
                        <option value="">{{ __('groups.choose_leader') }}</option>
                        @foreach ($leaders as $leader)
                            <option value="{{ $leader->id }}" @selected((string) $selectedLeaderId === (string) $leader->id)>
                                {{ trim($leader->first_name.' '.($leader->middle_name ? $leader->middle_name.' ' : '').$leader->last_name) }} · {{ $leader->membership_number }}
                            </option>
                        @endforeach
                    </select>
                    @error('leader_member_id')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="group-description" class="{{ $labelClass }}">{{ __('groups.description') }}</label>
                    <textarea id="group-description" name="description" rows="4" maxlength="5000" placeholder="{{ __('groups.description_placeholder') }}" @error('description') aria-invalid="true" @enderror class="{{ $inputClass }} {{ $errors->has('description') ? 'border-red-600' : 'border-gray-700' }}">{{ old('description', $group?->description) }}</textarea>
                    @error('description')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label for="group-active" class="inline-flex items-center gap-3 text-sm text-gray-300">
                        <input id="group-active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $group?->is_active ?? true)) class="rounded border-gray-600 bg-slate-900 text-blue-500 focus:ring-blue-500">
                        {{ __('groups.active') }}
                    </label>
                    @error('is_active')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-gray-800 pt-5">
                <a href="{{ route('groups.index') }}" class="rounded-lg border border-gray-700 px-5 py-3 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('groups.cancel') }}</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('groups.save') }}</button>
            </div>
        </form>
        @endif
    </div>
</x-app-layout>
