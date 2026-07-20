<x-admin-layout title="Framework">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.framework-versions.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Frameworks</a>
            <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $framework->display_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $framework->module?->module_name }} · {{ $framework->framework_type }} · v{{ $framework->version_number }} · {{ $framework->status }}</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            @if ($framework->status === 'DRAFT')
                <form method="POST" action="{{ route('admin.framework-versions.publish', $framework) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Publish immutable framework</button>
                </form>
            @endif
            @if ($framework->status === 'PUBLISHED')
                <form method="POST" action="{{ route('admin.framework-versions.supersede', $framework) }}">
                    @csrf
                    <button class="rounded-xl border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-700 dark:text-amber-300">Create successor draft</button>
                </form>
            @endif
            @if (in_array($framework->status, ['DRAFT', 'PUBLISHED'], true))
                <form method="POST" action="{{ route('admin.framework-versions.archive', $framework) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 dark:border-red-700 dark:text-red-300">Archive</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-4">
        @foreach ($dependencySummary as $label => $count)
            <div class="section-card p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ str($label)->replace('_', ' ') }}</p>
                <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    @if ($framework->status === 'DRAFT')
        <form method="POST" action="{{ route('admin.framework-versions.update', $framework) }}" class="mb-5 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            @method('PUT')
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Draft metadata</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <input name="display_name" value="{{ $framework->display_name }}" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="source_authority" value="{{ $framework->source_authority }}" placeholder="Source authority" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="license_code" value="{{ $framework->license_code }}" placeholder="License code" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="source_url" value="{{ $framework->source_url }}" placeholder="Source URL" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </div>
            <textarea name="description" rows="2" placeholder="Description" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $framework->description }}</textarea>
            <textarea name="purpose" rows="2" placeholder="Purpose" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $framework->purpose }}</textarea>
            <textarea name="methodology_notes" rows="2" placeholder="Methodology notes" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $framework->methodology_notes }}</textarea>
            <div class="mt-4 text-right"><button class="rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Save metadata</button></div>
        </form>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2 section-card p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Framework sections</h2>
            <div class="mt-4 space-y-4">
                @forelse ($framework->sections as $section)
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $section->display_order }}. {{ $section->section_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $section->section_code }} · {{ $section->purpose }}</p>
                            </div>
                            @if ($framework->status === 'DRAFT')
                                <form method="POST" action="{{ route('admin.framework-versions.sections.destroy', [$framework, $section]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-semibold text-red-600">Remove</button>
                                </form>
                            @endif
                        </div>
                        <div class="mt-3 space-y-2">
                            @foreach ($section->indicators as $indicator)
                                <div class="rounded-lg bg-white p-3 dark:bg-slate-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ $indicator->display_order }}. {{ $indicator->indicator_code }} · {{ $indicator->indicator_name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $indicator->placements->count() }} question placements</p>
                                        </div>
                                        @if ($framework->status === 'DRAFT')
                                            <form method="POST" action="{{ route('admin.framework-versions.indicators.destroy', [$framework, $indicator]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs font-semibold text-red-600">Remove</button>
                                            </form>
                                        @endif
                                    </div>
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
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Governance</h2>
                <dl class="mt-3 space-y-2 text-xs text-slate-500">
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Source authority</dt><dd>{{ $framework->source_authority ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">License</dt><dd>{{ $framework->license_code ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Scoring version</dt><dd>{{ $framework->scoring_version ?? 'Not frozen' }}</dd></div>
                    <div><dt class="font-bold text-slate-700 dark:text-slate-200">Content hash</dt><dd class="break-all">{{ $framework->content_hash ?? 'Not published' }}</dd></div>
                </dl>
            </div>
            <div class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Question placements</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($framework->questionPlacements as $placement)
                        <div class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900">
                            <p class="font-bold text-slate-800 dark:text-slate-100">{{ $placement->display_order }}. {{ $placement->questionVersion?->question?->question_code }}</p>
                            <p class="mt-1 text-slate-500">{{ str($placement->questionVersion?->question_text)->limit(80) }}</p>
                            @if ($framework->status === 'DRAFT')
                                <form method="POST" action="{{ route('admin.framework-versions.placements.destroy', [$framework, $placement]) }}" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-semibold text-red-600">Remove placement</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($framework->status === 'DRAFT')
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <form method="POST" action="{{ route('admin.framework-versions.sections.store', $framework) }}" class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                @csrf
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Add section</h2>
                <input name="section_code" placeholder="Code" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="section_name" placeholder="Name" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="display_order" type="number" value="{{ $framework->sections->count() + 1 }}" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <textarea name="purpose" rows="2" placeholder="Purpose" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                <button class="mt-3 rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Add section</button>
            </form>

            <form method="POST" action="{{ route('admin.framework-versions.indicators.store', $framework) }}" class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                @csrf
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Add indicator</h2>
                <select name="framework_section_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($framework->sections as $section)
                        <option value="{{ $section->framework_section_id }}">{{ $section->section_name }}</option>
                    @endforeach
                </select>
                <input name="indicator_code" placeholder="Code" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="indicator_name" placeholder="Name" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="display_order" type="number" value="{{ $framework->indicators->count() + 1 }}" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <textarea name="description" rows="2" placeholder="Description" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                <button class="mt-3 rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Add indicator</button>
            </form>

            <form method="POST" action="{{ route('admin.framework-versions.placements.store', $framework) }}" class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
                @csrf
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Add question placement</h2>
                <select name="framework_section_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($framework->sections as $section)
                        <option value="{{ $section->framework_section_id }}">{{ $section->section_name }}</option>
                    @endforeach
                </select>
                <select name="framework_indicator_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($framework->indicators as $indicator)
                        <option value="{{ $indicator->framework_indicator_id }}">{{ $indicator->indicator_name }}</option>
                    @endforeach
                </select>
                <select name="question_version_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                    @foreach ($publishedQuestionVersions as $version)
                        <option value="{{ $version->question_version_id }}">{{ $version->question?->question_code }} · {{ str($version->question_text)->limit(70) }}</option>
                    @endforeach
                </select>
                <select name="sub_index_id" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">Unscored / context only</option>
                    @foreach ($subIndices as $subIndex)
                        <option value="{{ $subIndex->sub_index_id }}">{{ $subIndex->full_name }}</option>
                    @endforeach
                </select>
                <input name="display_order" type="number" value="{{ $framework->questionPlacements->count() + 1 }}" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="weight" type="number" step="0.001" value="1" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <input name="criticality" value="STANDARD" class="mt-3 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="is_required" value="1" checked> Required</label>
                <label class="mt-2 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="scoring_contribution" value="1"> Scored</label>
                <button class="mt-3 rounded-xl bg-vytte-700 px-4 py-2 text-sm font-bold text-white">Add placement</button>
            </form>
        </div>
    @endif
</x-admin-layout>
