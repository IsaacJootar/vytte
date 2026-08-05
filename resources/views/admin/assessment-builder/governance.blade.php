<x-admin-layout :title="$assessment->display_name">
    <div class="mb-5">
        <a href="{{ route('admin.assessments.edit', $assessment) }}" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Back to purpose</a>
        <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Publisher and source</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Say who is accountable for this assessment and where its guidance comes from. Publisher identity and content review are shown separately.
        </p>
    </div>

    <x-assessment-wizard-steps :steps="$steps" :current-step="$currentStep" :assessment="$assessment" />

    @unless ($isEditable)
        <div class="mb-4 max-w-2xl rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
            This published version is locked. Its attribution remains exactly as it was when published.
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.assessments.provenance', $assessment) }}" class="section-card max-w-2xl space-y-6 p-6 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        @method('PUT')

        <div>
            <label for="content_publisher_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Who publishes this assessment?</label>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">The publisher accepts responsibility for its purpose, question selection, and scoring method.</p>
            <select id="content_publisher_id" name="content_publisher_id" required @disabled(! $isEditable)
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @foreach ($publishers as $publisher)
                    <option value="{{ $publisher->content_publisher_id }}" @selected(old('content_publisher_id', $assessment->content_publisher_id) === $publisher->content_publisher_id)>
                        {{ $publisher->name }}{{ $publisher->verification_status === 'VERIFIED' ? ' · identity verified' : ' · identity not verified' }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Need another publisher? Add and verify it from <a href="{{ route('admin.publishers.index') }}" class="font-semibold text-vytte-700 hover:underline dark:text-vytte-300">Publishers</a>.</p>
        </div>

        <div>
            <label for="distribution_level" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Who may discover it?</label>
            <select id="distribution_level" name="distribution_level" required @disabled(! $isEditable)
                    class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @foreach (['PRIVATE' => 'Private draft or workspace use', 'ORGANIZATION' => 'This organization', 'PARTNER' => 'Selected partners', 'PUBLIC' => 'Public catalogue'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('distribution_level', $assessment->distribution_level) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="border-t border-slate-200 pt-5 dark:border-slate-700">
            <label for="source_authority" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Underlying source or authority</label>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">This may differ from the publisher—for example, a hospital may adapt national guidance.</p>
            <input id="source_authority" name="source_authority" value="{{ old('source_authority', $assessment->source_authority) }}" required maxlength="180" @disabled(! $isEditable)
                   placeholder="e.g. Federal Ministry of Health guideline"
                   class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="license_code" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Usage terms or licence</label>
                <input id="license_code" name="license_code" value="{{ old('license_code', $assessment->license_code) }}" required maxlength="80" @disabled(! $isEditable)
                       placeholder="e.g. CC-BY-4.0 or permission held"
                       class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
            <div>
                <label for="source_url" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Source link <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="source_url" name="source_url" value="{{ old('source_url', $assessment->source_url) }}" type="url" maxlength="2000" @disabled(! $isEditable)
                       placeholder="https://…"
                       class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-vytte-500 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
        </div>

        @if ($isEditable)
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                <button class="rounded-xl bg-vytte-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700">Save and continue to structure</button>
                <a href="{{ route('admin.assessments.show', $assessment) }}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">Save later</a>
            </div>
        @endif
    </form>
</x-admin-layout>
