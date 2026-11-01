<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            {{ __($member ? 'members.form_edit_title' : 'members.form_create_title') }}
        </h2>
    </x-slot>

    @php
        $baseInput = 'w-full rounded-lg border bg-[#0b1120] px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 [color-scheme:dark]';
        $inputBorder = 'border-gray-700';
        $inputError = 'border-red-600';
        $labelClass = 'mb-2 block text-sm text-gray-300';
        $fields = [
            ['name' => 'first_name', 'required' => true],
            ['name' => 'middle_name', 'required' => false],
            ['name' => 'last_name', 'required' => true],
        ];
    @endphp

    <div class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8">
        <div class="mx-auto max-w-5xl space-y-7">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">
                        {{ __('members.eyebrow') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ __($member ? 'members.form_edit_description' : 'members.form_create_description') }}
                    </p>
                </div>

                <a href="{{ route('members.index') }}"
                   class="rounded-lg border border-gray-700 px-5 py-3 text-sm text-gray-300 transition hover:bg-gray-800">
                    {{ __('members.back_to_list') }}
                </a>
            </div>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-5 text-red-300">
                    <p class="mb-2 font-semibold">{{ __('members.fix_errors') }}</p>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $member && ! $membershipNumberPreview)
                <div role="alert" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-700/60 bg-amber-950/40 p-5 text-sm text-amber-200">
                    <p>{{ __('members.configure_church_short_name') }}</p>
                    @can('manage settings')
                        <a href="{{ route('settings.edit') }}"
                           class="font-semibold text-blue-300 underline underline-offset-4">
                            {{ __('members.open_settings') }}
                        </a>
                    @endcan
                </div>
            @endif

            <form
                x-data="{ submitting: false }"
                @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }"
                action="{{ $member ? route('members.update', $member) : route('members.store') }}"
                method="POST"
                class="space-y-8 rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl sm:p-8"
            >
                @csrf
                @if ($member)
                    @method('PUT')
                @endif

                <section class="space-y-5">
                    <h3 class="border-b border-gray-800 pb-3 text-lg font-semibold">
                        {{ __('members.membership_details') }}
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="membership_number_preview" class="{{ $labelClass }}">
                                {{ __('members.membership_number_preview') }}
                            </label>
                            @if ($member || $membershipNumberPreview)
                                <input
                                    id="membership_number_preview"
                                    type="text"
                                    value="{{ $member?->membership_number ?? $membershipNumberPreview }}"
                                    readonly
                                    class="{{ $baseInput }} border-gray-700 bg-slate-900/80 font-semibold text-blue-300"
                                >
                            @else
                                <p class="rounded-lg border border-amber-700/50 bg-amber-950/30 px-4 py-3 text-sm text-amber-200">
                                    {{ __('members.membership_number_will_be_generated') }}
                                </p>
                            @endif
                            @if (! $member && $membershipNumberPreview)
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ __('members.membership_number_preview_note') }}
                                </p>
                            @endif
                        </div>

                        <div>
                            <label for="envelope_number" class="{{ $labelClass }}">
                                {{ __('members.envelope_number') }}
                                <span class="text-red-400" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="envelope_number"
                                type="text"
                                name="envelope_number"
                                value="{{ old('envelope_number', $member?->envelope_number) }}"
                                placeholder="{{ __('members.envelope_placeholder') }}"
                                autocomplete="off"
                                maxlength="255"
                                required
                                @error('envelope_number') aria-invalid="true" @enderror
                                class="{{ $baseInput }} {{ $errors->has('envelope_number') ? $inputError : $inputBorder }}"
                            >
                            @error('envelope_number')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="membership_date" class="{{ $labelClass }}">
                                {{ __('members.membership_date') }}
                            </label>
                            <input
                                id="membership_date"
                                type="date"
                                name="membership_date"
                                value="{{ old('membership_date', $member?->membership_date?->toDateString()) }}"
                                max="{{ now()->toDateString() }}"
                                @error('membership_date') aria-invalid="true" @enderror
                                class="{{ $baseInput }} {{ $errors->has('membership_date') ? $inputError : $inputBorder }}"
                            >
                            @error('membership_date')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="status" class="{{ $labelClass }}">{{ __('members.status') }}</label>
                            <select
                                id="status"
                                name="status"
                                required
                                class="{{ $baseInput }} {{ $errors->has('status') ? $inputError : $inputBorder }}"
                            >
                                <option value="active" @selected(old('status', $member?->status ?? 'active') === 'active')>
                                    {{ __('members.active') }}
                                </option>
                                <option value="inactive" @selected(old('status', $member?->status) === 'inactive')>
                                    {{ __('members.inactive') }}
                                </option>
                            </select>
                            @error('status')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="space-y-5">
                    <h3 class="border-b border-gray-800 pb-3 text-lg font-semibold">
                        {{ __('members.personal_details') }}
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach ($fields as $field)
                            <div>
                                <label for="{{ $field['name'] }}" class="{{ $labelClass }}">
                                    {{ __('members.'.$field['name']) }}
                                    @if ($field['required'])
                                        <span class="text-red-400" aria-hidden="true">*</span>
                                    @endif
                                </label>
                                <input
                                    id="{{ $field['name'] }}"
                                    type="text"
                                    name="{{ $field['name'] }}"
                                    value="{{ old($field['name'], $member?->{$field['name']}) }}"
                                    maxlength="255"
                                    @if ($field['required']) required @endif
                                    autocomplete="{{ $field['name'] === 'first_name' ? 'given-name' : ($field['name'] === 'last_name' ? 'family-name' : 'additional-name') }}"
                                    @error($field['name']) aria-invalid="true" @enderror
                                    class="{{ $baseInput }} {{ $errors->has($field['name']) ? $inputError : $inputBorder }}"
                                >
                                @error($field['name'])
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach

                        <div>
                            <label for="gender" class="{{ $labelClass }}">
                                {{ __('members.gender') }}
                                <span class="text-red-400" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="gender"
                                name="gender"
                                required
                                class="{{ $baseInput }} {{ $errors->has('gender') ? $inputError : $inputBorder }}"
                            >
                                <option value="">{{ __('members.choose_gender') }}</option>
                                <option value="Male" @selected(old('gender', $member?->gender) === 'Male')>
                                    {{ __('members.male') }}
                                </option>
                                <option value="Female" @selected(old('gender', $member?->gender) === 'Female')>
                                    {{ __('members.female') }}
                                </option>
                            </select>
                            @error('gender')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="date_of_birth" class="{{ $labelClass }}">
                                {{ __('members.date_of_birth') }}
                            </label>
                            <input
                                id="date_of_birth"
                                type="date"
                                name="date_of_birth"
                                value="{{ old('date_of_birth', $member?->date_of_birth?->toDateString()) }}"
                                max="{{ now()->toDateString() }}"
                                @error('date_of_birth') aria-invalid="true" @enderror
                                class="{{ $baseInput }} {{ $errors->has('date_of_birth') ? $inputError : $inputBorder }}"
                            >
                            @error('date_of_birth')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="{{ $labelClass }}">{{ __('members.phone') }}</label>
                            <input
                                id="phone"
                                type="tel"
                                name="phone"
                                value="{{ old('phone', $member?->phone) }}"
                                placeholder="{{ __('members.phone_placeholder') }}"
                                maxlength="30"
                                autocomplete="tel"
                                class="{{ $baseInput }} {{ $errors->has('phone') ? $inputError : $inputBorder }}"
                            >
                            @error('phone')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="{{ $labelClass }}">{{ __('members.email') }}</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email', $member?->email) }}"
                                maxlength="255"
                                autocomplete="email"
                                class="{{ $baseInput }} {{ $errors->has('email') ? $inputError : $inputBorder }}"
                            >
                            @error('email')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="{{ $labelClass }}">{{ __('members.address') }}</label>
                            <textarea
                                id="address"
                                name="address"
                                rows="3"
                                maxlength="2000"
                                placeholder="{{ __('members.address_placeholder') }}"
                                class="{{ $baseInput }} {{ $errors->has('address') ? $inputError : $inputBorder }}"
                            >{{ old('address', $member?->address) }}</textarea>
                            @error('address')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-800 pt-6">
                    <a href="{{ route('members.index') }}"
                       class="rounded-lg border border-gray-700 px-6 py-3 text-gray-300 transition hover:bg-gray-800">
                        {{ __('members.cancel') }}
                    </a>
                    <button
                        type="submit"
                        x-bind:disabled="submitting"
                        class="rounded-lg bg-blue-600 px-7 py-3 font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="!submitting">
                            {{ __($member ? 'members.update_member' : 'members.save_member') }}
                        </span>
                        <span x-cloak x-show="submitting" class="inline-flex items-center gap-2">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            {{ __('members.saving') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
