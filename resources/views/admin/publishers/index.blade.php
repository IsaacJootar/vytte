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

                    @php($fieldClasses = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white')
                    <form method="POST" action="{{ route('admin.publishers.update', $publisher) }}" class="mt-5 grid gap-3 border-t border-slate-200 pt-5 dark:border-slate-700 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Publisher code
                            <input name="publisher_code" value="{{ $publisher->publisher_code }}" required maxlength="80" class="{{ $fieldClasses }}">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Name
                            <input name="name" value="{{ $publisher->name }}" required maxlength="180" class="{{ $fieldClasses }}">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Publisher type
                            <select name="publisher_type" required class="{{ $fieldClasses }}">
                                @foreach ($types as $value => $label)<option value="{{ $value }}" @selected($publisher->publisher_type === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Visibility
                            <select name="visibility" required class="{{ $fieldClasses }}">
                                @foreach ($visibilities as $value => $label)<option value="{{ $value }}" @selected($publisher->visibility === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Website <span class="font-normal text-slate-400">(optional)</span>
                            <input name="website_url" value="{{ $publisher->website_url }}" type="url" class="{{ $fieldClasses }}">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Contact email <span class="font-normal text-slate-400">(optional)</span>
                            <input name="contact_email" value="{{ $publisher->contact_email }}" type="email" class="{{ $fieldClasses }}">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 sm:col-span-2">Attribution statement
                            <textarea name="attribution" rows="2" class="{{ $fieldClasses }}">{{ $publisher->attribution }}</textarea>
                        </label>
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

        <form method="POST" action="{{ route('admin.publishers.store') }}" class="section-card h-fit space-y-4 p-5 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            <h2 class="font-bold text-slate-900 dark:text-white">Add publisher</h2>
            <x-form-field label="Publisher code" name="publisher_code">
                <x-text-input id="publisher_code" name="publisher_code" type="text" class="block w-full" :value="old('publisher_code')" required maxlength="80" placeholder="Short code, e.g. MOH-NG" />
            </x-form-field>
            <x-form-field label="Publisher name" name="name">
                <x-text-input id="name" name="name" type="text" class="block w-full" :value="old('name')" required maxlength="180" placeholder="Publisher name" />
            </x-form-field>
            <x-form-field label="Publisher type" name="publisher_type">
                <select id="publisher_type" name="publisher_type" required class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="">Choose a type</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('publisher_type') === $value)>{{ $label }}</option>@endforeach</select>
            </x-form-field>
            <x-form-field label="Visibility" name="visibility">
                <select id="visibility" name="visibility" required class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">@foreach ($visibilities as $value => $label)<option value="{{ $value }}" @selected(old('visibility', 'PUBLIC') === $value)>{{ $label }}</option>@endforeach</select>
            </x-form-field>
            <x-form-field label="Website" name="website_url" optional>
                <x-text-input id="website_url" name="website_url" type="url" class="block w-full" :value="old('website_url')" placeholder="https://" />
            </x-form-field>
            <x-form-field label="Contact email" name="contact_email" optional>
                <x-text-input id="contact_email" name="contact_email" type="email" class="block w-full" :value="old('contact_email')" />
            </x-form-field>
            <x-form-field label="Attribution statement" name="attribution" optional>
                <textarea id="attribution" name="attribution" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('attribution') }}</textarea>
            </x-form-field>
            <button class="w-full rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Create unverified publisher</button>
        </form>
    </div>
</x-admin-layout>
