<x-admin-layout title="Publishers">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Publishers</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
            A publisher is accountable for an assessment's purpose and method. Verifying its identity does not automatically verify its sources, scoring, field testing, or translations.
        </p>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-3">
            @forelse ($publishers as $publisher)
                <details class="section-card group p-5 dark:border-slate-700 dark:bg-slate-800">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                        <span>
                            <span class="block font-bold text-slate-900 dark:text-white">{{ $publisher->name }}</span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ str($publisher->publisher_type)->replace('_', ' ')->title() }} · {{ str($publisher->visibility)->lower()->title() }} · {{ $publisher->governance_claims_count }} review claims</span>
                        </span>
                        <span @class([
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => $publisher->verification_status === 'VERIFIED',
                            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => $publisher->verification_status === 'UNVERIFIED' || $publisher->verification_status === 'PENDING',
                            'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' => $publisher->verification_status === 'SUSPENDED',
                        ])>{{ str($publisher->verification_status)->replace('_', ' ')->title() }}</span>
                    </summary>

                    <form method="POST" action="{{ route('admin.publishers.update', $publisher) }}" class="mt-5 grid gap-3 border-t border-slate-200 pt-5 dark:border-slate-700 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <input name="publisher_code" value="{{ $publisher->publisher_code }}" required maxlength="80" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <input name="name" value="{{ $publisher->name }}" required maxlength="180" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <select name="publisher_type" required class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            @foreach ($types as $value => $label)<option value="{{ $value }}" @selected($publisher->publisher_type === $value)>{{ $label }}</option>@endforeach
                        </select>
                        <select name="visibility" required class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            @foreach ($visibilities as $value => $label)<option value="{{ $value }}" @selected($publisher->visibility === $value)>{{ $label }}</option>@endforeach
                        </select>
                        <input name="website_url" value="{{ $publisher->website_url }}" type="url" placeholder="Website (optional)" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <input name="contact_email" value="{{ $publisher->contact_email }}" type="email" placeholder="Contact email (optional)" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <textarea name="attribution" rows="2" placeholder="Attribution statement" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white sm:col-span-2">{{ $publisher->attribution }}</textarea>
                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Save details</button>
                        </div>
                    </form>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($publisher->verification_status !== 'VERIFIED')
                            <form method="POST" action="{{ route('admin.publishers.verify', $publisher) }}">@csrf @method('PATCH')<button class="rounded-xl bg-vytte-600 px-4 py-2 text-sm font-semibold text-white">Verify identity</button></form>
                        @endif
                        @if ($publisher->publisher_type !== 'VYTTE' && $publisher->verification_status !== 'SUSPENDED')
                            <form method="POST" action="{{ route('admin.publishers.suspend', $publisher) }}" onsubmit="return confirm('Suspend this publisher? Historical attribution will remain.')">@csrf @method('PATCH')<button class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 dark:border-red-800 dark:text-red-300">Suspend</button></form>
                        @endif
                    </div>
                </details>
            @empty
                <x-empty-state title="No publishers" description="Create the first publisher using the form." />
            @endforelse
            {{ $publishers->links() }}
        </div>

        <form method="POST" action="{{ route('admin.publishers.store') }}" class="section-card h-fit space-y-3 p-5 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            <h2 class="font-bold text-slate-900 dark:text-white">Add publisher</h2>
            <input name="publisher_code" value="{{ old('publisher_code') }}" required maxlength="80" placeholder="Short code, e.g. MOH-NG" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <input name="name" value="{{ old('name') }}" required maxlength="180" placeholder="Publisher name" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <select name="publisher_type" required class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">Publisher type</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('publisher_type') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="visibility" required class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">@foreach ($visibilities as $value => $label)<option value="{{ $value }}" @selected(old('visibility', 'PUBLIC') === $value)>{{ $label }}</option>@endforeach</select>
            <input name="website_url" value="{{ old('website_url') }}" type="url" placeholder="Website (optional)" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <input name="contact_email" value="{{ old('contact_email') }}" type="email" placeholder="Contact email (optional)" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <textarea name="attribution" rows="2" placeholder="Attribution statement (optional)" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('attribution') }}</textarea>
            <button class="w-full rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Create unverified publisher</button>
        </form>
    </div>
</x-admin-layout>
