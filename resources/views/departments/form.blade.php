<x-app-layout>
    @php
        $inputClass = 'w-full rounded-lg border bg-[#0b1120] px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500';
        $labelClass = 'mb-2 block text-sm font-medium text-gray-300';
        $selectedLeaderId = old('leader_member_id', $department?->leader_member_id);
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('departments.title') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __($department ? 'departments.edit_title' : 'departments.create_title') }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ __($department ? 'departments.edit_description' : 'departments.create_description') }}</p>
            </div>
            <a href="{{ route('departments.index') }}" class="rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('departments.back_to_list') }}</a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-red-300">
                <p class="mb-2 font-semibold">{{ __('departments.validation_heading') }}</p>
                <ul class="list-inside list-disc space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $department ? route('departments.update', $department) : route('departments.store') }}" class="space-y-6 rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl sm:p-8">
            @csrf
            @if ($department)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="department-code" class="{{ $labelClass }}">{{ __('departments.code') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                    <input id="department-code" name="code" value="{{ old('code', $department?->code) }}" required maxlength="50" autocomplete="off" @error('code') aria-invalid="true" @enderror class="{{ $inputClass }} {{ $errors->has('code') ? 'border-red-600' : 'border-gray-700' }}">
                    @error('code')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="department-name" class="{{ $labelClass }}">{{ __('departments.name') }} <span class="text-red-400" aria-hidden="true">*</span></label>
                    <input id="department-name" name="name" value="{{ old('name', $department?->name) }}" required maxlength="150" @error('name') aria-invalid="true" @enderror class="{{ $inputClass }} {{ $errors->has('name') ? 'border-red-600' : 'border-gray-700' }}">
                    @error('name')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="department-leader" class="{{ $labelClass }}">{{ __('departments.leader') }}</label>
                    <select id="department-leader" name="leader_member_id" class="{{ $inputClass }} border-gray-700">
                        <option value="">{{ __('departments.choose_leader') }}</option>
                        @foreach ($leaders as $leader)
                            <option value="{{ $leader->id }}" @selected((string) $selectedLeaderId === (string) $leader->id)>
                                {{ trim($leader->first_name.' '.($leader->middle_name ? $leader->middle_name.' ' : '').$leader->last_name) }} · {{ $leader->membership_number }}
                            </option>
                        @endforeach
                    </select>
                    @error('leader_member_id')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="department-description" class="{{ $labelClass }}">{{ __('departments.description') }}</label>
                    <textarea id="department-description" name="description" rows="4" maxlength="5000" placeholder="{{ __('departments.description_placeholder') }}" @error('description') aria-invalid="true" @enderror class="{{ $inputClass }} {{ $errors->has('description') ? 'border-red-600' : 'border-gray-700' }}">{{ old('description', $department?->description) }}</textarea>
                    @error('description')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label for="department-active" class="inline-flex items-center gap-3 text-sm text-gray-300">
                        <input id="department-active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $department?->is_active ?? true)) class="rounded border-gray-600 bg-slate-900 text-blue-500 focus:ring-blue-500">
                        {{ __('departments.active') }}
                    </label>
                    @error('is_active')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-gray-800 pt-5">
                <a href="{{ route('departments.index') }}" class="rounded-lg border border-gray-700 px-5 py-3 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('departments.cancel') }}</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('departments.save') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
