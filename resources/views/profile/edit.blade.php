<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="page-header">
            <div>
                <h2 class="page-title">{{ __('profile.title') }}</h2>
                <p class="page-description">{{ __('profile.description') }}</p>
            </div>
        </div>

        <section class="card">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section id="password" class="card scroll-mt-24">
            @include('profile.partials.update-password-form')
        </section>

        <section class="card border-red-500/20">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
