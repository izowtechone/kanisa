<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ $community->code }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ $community->name }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('communities.details_title') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('edit communities')
                    <a href="{{ route('communities.edit', $community) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('communities.edit') }}</a>
                    <form method="POST" action="{{ route('communities.status', $community) }}">
                        @csrf
                        @method('PATCH')
                        <button class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-300">{{ $community->status === 'active' ? __('communities.inactive') : __('communities.active') }}</button>
                    </form>
                @endcan
                @can('delete communities')
                    <form method="POST" action="{{ route('communities.destroy', $community) }}" onsubmit="return window.confirm(@js(__('communities.confirm_delete')))">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-sm font-semibold text-rose-300">{{ __('communities.delete') }}</button>
                    </form>
                @endcan
                <a href="{{ route('communities.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800">{{ __('communities.back_to_list') }}</a>
            </div>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                [__('communities.status'), __('communities.'.$community->status)],
                [__('communities.location'), $community->location ?: '—'],
                [__('communities.member_count'), number_format($members->total())],
                [__('communities.contact_phone'), $community->contact_phone ?: '—'],
                [__('communities.contact_email'), $community->contact_email ?: '—'],
            ] as [$label, $value])
                <div class="border-b border-slate-800 pb-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-1 break-words font-medium text-slate-100">{{ $value }}</p>
                </div>
            @endforeach
            <div class="border-b border-slate-800 pb-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('communities.leader') }}</p>
                @if ($community->leader)
                    <p class="mt-1 font-medium text-slate-100">{{ trim(collect([$community->leader->first_name, $community->leader->middle_name, $community->leader->last_name])->filter()->implode(' ')) }}</p>
                    <p class="mt-1 font-mono text-xs text-blue-300">{{ $community->leader->membership_number }}</p>
                @else
                    <p class="mt-1 text-slate-400">{{ __('communities.leader_not_selected') }}</p>
                @endif
            </div>
            @if ($community->description)
                <div class="border-b border-slate-800 pb-3 sm:col-span-2 lg:col-span-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('communities.description_field') }}</p>
                    <p class="mt-1 whitespace-pre-line text-slate-200">{{ $community->description }}</p>
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#111a2e] shadow-xl">
            <div class="border-b border-slate-800 px-5 py-4">
                <h3 class="font-semibold text-white">{{ __('communities.members') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-900/50 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('communities.membership_number') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.member_name') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.phone') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.membership_status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($members as $member)
                            <tr class="text-slate-300">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-blue-300">{{ $member->membership_number }}</td>
                                <td class="px-5 py-4 font-medium text-white">{{ trim(collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' ')) }}</td>
                                <td class="px-5 py-4">{{ $member->phone ?: '—' }}</td>
                                <td class="px-5 py-4">{{ __('members.'.$member->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">{{ __('communities.no_members') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($members->hasPages())
                <div class="border-t border-slate-800 px-5 py-4">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
