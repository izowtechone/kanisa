@props(['id', 'name', 'label', 'autocomplete' => 'current-password', 'hint' => null, 'required' => true])

<div x-data="{ visible: false }">
    <label for="{{ $id }}" class="field-label">
        {{ $label }}@if ($required)<span class="text-red-400" aria-hidden="true"> *</span>@endif
    </label>
    <div class="relative">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            :type="visible ? 'text' : 'password'"
            type="password"
            autocomplete="{{ $autocomplete }}"
            @required($required)
            @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->merge(['class' => 'field-input pr-12']) }}
        >
        <button
            type="button"
            @click="visible = ! visible"
            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-control text-slate-400 hover:text-white"
            :aria-label="visible ? @js(__('auth_pages.hide_password')) : @js(__('auth_pages.show_password'))"
            :aria-pressed="visible"
        >
            <span x-show="! visible"><x-icon name="eye" class="h-5 w-5"/></span>
            <span x-show="visible" x-cloak><x-icon name="eye-off" class="h-5 w-5"/></span>
        </button>
    </div>
    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="field-error" role="alert">{{ $message }}</p>
    @enderror
</div>
