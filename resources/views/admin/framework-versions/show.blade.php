<x-admin-layout title="Framework">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.framework-versions.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Frameworks</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $framework->display_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $framework->module?->module_name }} · {{ $framework->framework_type }} · v{{ $framework->version_number }} · {{ $framework->status }}</p>
        </div>
        @if ($framework->status === 'DRAFT')
            <form method="POST" action="{{ route('admin.framework-versions.publish', $framework) }}">
                @csrf
                @method('PATCH')
                <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Publish immutable framework</button>
            </form>
        @endif
    </div>
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Framework sections</h2>
            <div class="mt-4 space-y-4">
                @forelse ($framework->sections as $section)
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $section->display_order }}. {{ $section->section_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $section->purpose }}</p>
                        <div class="mt-3 space-y-2">
                            @foreach ($section->indicators as $indicator)
                                <div class="rounded-lg bg-white p-3 dark:bg-slate-800">
                                    <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ $indicator->indicator_code }} · {{ $indicator->indicator_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $indicator->placements->count() }} question placements</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No sections have been added yet.</p>
                @endforelse
            </div>
        </div>
        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Governance</h2>
                <dl class="mt-3 space-y-2 text-xs text-slate-500">
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Source authority</dt><dd>{{ $framework->source_authority ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">License</dt><dd>{{ $framework->license_code ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Scoring version</dt><dd>{{ $framework->scoring_version ?? 'Not frozen' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Content hash</dt><dd class="break-all">{{ $framework->content_hash ?? 'Not published' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Question placements</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $framework->questionPlacements->count() }} placements pin exact question versions.</p>
            </div>
        </div>
    </div>
</x-admin-layout>
