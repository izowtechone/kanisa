<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">{{ __('users.create_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __('users.create_description') }}</p>
        </div>
        @include('users.form', ['managedUser' => null, 'isEdit' => false])
    </div>
</x-app-layout>
