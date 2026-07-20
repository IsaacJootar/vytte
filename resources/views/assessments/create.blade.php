<x-app-layout title="Create Assessment">
    <div class="max-w-3xl mx-auto" x-data="{ path: '{{ old('creation_path', '') }}' }">
        <a href="{{ route('projects.show', $project) }}" class="text-sm text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
            ← {{ $project->name }}
        </a>

        <div class="mt-5 mb-7">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">What are you assessing?</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Choose the assessment purpose. You will only see choices relevant to that purpose.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <button type="button" @click="path = 'COMPREHENSIVE'"
                    class="rounded-2xl border p-5 text-left transition"
                    :class="path === 'COMPREHENSIVE' ? 'border-vytte-600 bg-vytte-50 ring-2 ring-vytte-100 dark:bg-vytte-900/20' : 'border-slate-200 bg-white hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800'">
                <span class="text-base font-bold text-slate-900 dark:text-white">Comprehensive Health Assessment</span>
                <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400">
                    Assess health across the entire {{ strtolower($target?->targetType?->target_type_name ?? 'setting') }}.
                </span>
            </button>

            <button type="button" @click="path = 'FOCUSED'"
                    class="rounded-2xl border p-5 text-left transition"
                    :class="path === 'FOCUSED' ? 'border-vytte-600 bg-vytte-50 ring-2 ring-vytte-100 dark:bg-vytte-900/20' : 'border-slate-200 bg-white hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800'">
                <span class="text-base font-bold text-slate-900 dark:text-white">Focused Health Assessment</span>
                <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400">
                    Assess one health domain, programme, topic, or intervention.
                </span>
            </button>
        </div>

        <section x-show="path === 'COMPREHENSIVE'" x-cloak class="mt-7">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Services included in this assessment</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Select the departments and services currently available in this facility. Uncheck any default service that does not operate here.
            </p>

            @forelse ($comprehensiveReleases as $release)
                <form method="POST" action="{{ route('assessments.store', $project) }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    @csrf
                    <input type="hidden" name="creation_path" value="COMPREHENSIVE">
                    <input type="hidden" name="catalogue_release_id" value="{{ $release->catalogue_release_id }}">

                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $release->facilityProfile?->profile_name }}</span>
                            <h3 class="mt-1 font-bold text-slate-900 dark:text-white">{{ $release->release_name }}</h3>
                            @if ($release->description)
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $release->description }}</p>
                            @endif
                        </div>
                        <span class="mt-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300 sm:mt-0">
                            {{ $release->departmentFrameworkVersions->count() }} services
                        </span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($release->departmentFrameworkVersions as $framework)
                            @php
                                $applicability = $framework->pivot->applicability;
                                $isRequired = $applicability === 'REQUIRED';
                                $isDefault = in_array($applicability, ['REQUIRED', 'DEFAULT'], true);
                            @endphp
                            <div x-data="{ included: {{ $isDefault ? 'true' : 'false' }} }" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                @if ($isRequired)
                                    <input type="hidden" name="departments[]" value="{{ $framework->module_id }}">
                                @endif
                                <label class="flex items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    <input type="checkbox"
                                           @if (! $isRequired) name="departments[]" @endif
                                           value="{{ $framework->module_id }}"
                                           x-model="included"
                                           @checked($isDefault)
                                           @disabled($isRequired)
                                           class="rounded border-slate-300 text-vytte-600 focus:ring-vytte-500 disabled:opacity-50">
                                    <span class="flex-1">{{ $framework->pivot->area_label ?: $framework->module?->module_name }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-400">{{ strtolower($applicability) }}</span>
                                </label>
                                @if ($applicability === 'DEFAULT')
                                    <input x-show="!included" x-cloak :required="!included" type="text"
                                           name="exclusion_reasons[{{ $framework->module_id }}]"
                                           placeholder="Why does this default service not operate here?"
                                           class="mt-3 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700">
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <button class="mt-5 rounded-lg bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800">
                        Start comprehensive assessment
                    </button>
                </form>
            @empty
                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    No published Vytte catalogue release is available for this facility profile yet.
                </div>
            @endforelse
        </section>

        <section x-show="path === 'FOCUSED'" x-cloak class="mt-7">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">What health domain are you assessing?</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose one approved template. No unrelated departments or programmes will be added.</p>

            <div class="mt-4 space-y-3">
                @forelse ($focusedReleases as $release)
                    <form method="POST" action="{{ route('assessments.store', $project) }}" class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-800">
                        @csrf
                        <input type="hidden" name="creation_path" value="FOCUSED">
                        <input type="hidden" name="catalogue_release_id" value="{{ $release->catalogue_release_id }}">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $release->healthDomain?->domain_name }}</span>
                            <h3 class="mt-1 font-bold text-slate-900 dark:text-white">{{ $release->release_name }}</h3>
                            @if ($release->description)
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $release->description }}</p>
                            @endif
                        </div>
                        <button class="shrink-0 rounded-lg bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800">
                            Start assessment
                        </button>
                    </form>
                @empty
                    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        No approved focused templates are available yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
