<x-admin-layout title="Methodology">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Health Methodology</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            The official knowledge model behind every assessment: what people assess, why they assess it,
            and how the results get read.
        </p>
    </div>

    @if (! $version)
        <div class="section-card px-6 py-12 text-center">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No methodology yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                The official knowledge library has not been loaded into this environment.
            </p>
        </div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-stat-card tone="blue" label="Objectives" :value="$counts['objectives']" sub="Reasons to run an assessment"
                         :href="route('admin.methodology.objectives')" />
            <x-stat-card tone="slate" label="Health areas" :value="$counts['areas']" sub="Subdivisions of a health domain"
                         :href="route('admin.methodology.health-areas')" />
            <x-stat-card tone="strong" label="Analysis lenses" :value="$counts['lenses']" sub="Ways of reading the same results"
                         :href="route('admin.methodology.lenses')" />
            <x-stat-card tone="moderate" label="Insight categories" :value="$counts['categories']" sub="Shapes a finding can take"
                         :href="route('admin.methodology.insight-categories')" />
            <x-stat-card tone="blue" label="Templates" :value="$counts['templates']" sub="Official starting points"
                         :href="route('admin.methodology.templates')" />
            <x-stat-card tone="slate" label="Presets" :value="$counts['presets']" sub="Saved starting combinations"
                         :href="route('admin.methodology.presets')" />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="section-card p-5 lg:col-span-2" aria-labelledby="model-heading">
                <h2 id="model-heading" class="text-sm font-bold text-slate-900 dark:text-white">How the model fits together</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    An <strong>objective</strong> says why you are assessing. A <strong>health domain</strong> and its
                    <strong>areas</strong> say what subject you are assessing. Together they suggest a
                    <strong>template</strong> to start from. Once results exist, an <strong>analysis lens</strong>
                    decides how they are read, and each lens produces its own <strong>insights</strong> and
                    recommendations from the same underlying answers.
                </p>
                <p class="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    A lens never changes a score. It changes which findings are surfaced and how they are ordered,
                    which is why the same completed assessment can legitimately produce a risk report and a
                    performance report that read very differently.
                </p>
            </section>

            <section class="section-card p-5" aria-labelledby="version-heading">
                <h2 id="version-heading" class="text-sm font-bold text-slate-900 dark:text-white">Version</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">In force</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">v{{ $version->version_number }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Status</dt>
                        <dd><x-assessment-status-badge :status="$version->status" /></dd>
                    </div>
                    @if ($version->published_at)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">Published</dt>
                            <dd class="text-slate-700 dark:text-slate-200">{{ $version->published_at->format('j M Y') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($version->isEditable())
                    <form method="POST" action="{{ route('admin.methodology.publish', $version) }}" class="mt-4"
                          onsubmit="return confirm('Publish this methodology version? Its contents can never be changed afterwards — future changes need a new version.')">
                        @csrf
                        <button class="btn-primary w-full" data-loading-label="Publishing…">Publish methodology</button>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            Freezes the whole knowledge model so a report can always be traced to the exact
                            methodology in force when it was produced.
                        </p>
                    </form>
                @else
                    <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                        This version is published and frozen. Changes require a new version.
                    </p>
                @endif
            </section>
        </div>

        <section class="mt-4 section-card" aria-labelledby="history-heading">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                <h2 id="history-heading" class="text-sm font-bold text-slate-900 dark:text-white">Version history</h2>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($versions as $entry)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Version {{ $entry->version_number }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $entry->methodology_notes ?: 'No notes recorded.' }}
                            </p>
                            @if ($entry->content_hash)
                                <p class="mt-0.5 break-all text-xs text-slate-400">{{ substr($entry->content_hash, 0, 16) }}…</p>
                            @endif
                        </div>
                        <x-assessment-status-badge :status="$entry->status" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-admin-layout>
