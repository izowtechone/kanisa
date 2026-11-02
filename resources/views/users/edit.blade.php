<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">{{ __('users.edit_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __('users.edit_description') }}</p>
        </div>
        @include('users.form', ['isEdit' => true])
    </div>
</x-app-layout>
