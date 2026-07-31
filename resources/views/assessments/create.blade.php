<x-app-layout title="Create Assessment">
    <div class="max-w-3xl mx-auto" x-data="{ path: '{{ old('creation_path', '') }}', serviceSearch: '' }">
        <a href="{{ route('projects.show', $project) }}" class="text-sm text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
            ← {{ $project->name }}
        </a>

        <div class="mt-5 mb-7">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">What are you assessing?</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Choose the assessment purpose. You will only see choices relevant to that purpose.
            </p>
        </div>

        @php
            $hasComprehensive = $comprehensiveReleases->isNotEmpty();
            $hasFocused = $focusedReleases->isNotEmpty();
        @endphp

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Comprehensive — a whole-facility diagnostic, for health facilities only. --}}
            @if ($hasComprehensive)
                <button type="button" @click="path = 'COMPREHENSIVE'"
                        class="rounded-2xl border p-5 text-left transition"
                        :class="path === 'COMPREHENSIVE' ? 'border-vytte-600 bg-vytte-50 ring-2 ring-vytte-100 dark:bg-vytte-900/20' : 'border-slate-200 bg-white hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800'">
                    <span class="text-base font-bold text-slate-900 dark:text-white">Comprehensive Health Assessment</span>
                    <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400">
                        A full diagnostic across the whole facility and all its services.
                    </span>
                </button>
            @elseif ($isHealthFacility)
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-bold text-slate-400 dark:text-slate-500">Comprehensive Health Assessment</span>
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-400">Coming soon</span>
                    </div>
                    <span class="mt-2 block text-sm text-slate-400 dark:text-slate-500">
                        No comprehensive assessment is published for this facility type yet.
                    </span>
                </div>
            @else
                {{-- Not a facility: comprehensive simply isn't the right tool. Informational, not a limitation. --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="text-base font-bold text-slate-500 dark:text-slate-400">Comprehensive Health Assessment</span>
                    <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400">
                        This is a whole-facility diagnostic — for hospitals, clinics and health facilities. For a {{ strtolower($settingLabel) }}, run a <span class="font-semibold">Focused</span> assessment on a specific health topic →
                    </span>
                </div>
            @endif

            {{-- Focused — a health topic, open to every target. --}}
            @if ($hasFocused)
                <button type="button" @click="path = 'FOCUSED'"
                        class="rounded-2xl border p-5 text-left transition"
                        :class="path === 'FOCUSED' ? 'border-vytte-600 bg-vytte-50 ring-2 ring-vytte-100 dark:bg-vytte-900/20' : 'border-slate-200 bg-white hover:border-vytte-300 dark:border-slate-700 dark:bg-slate-800'">
                    <span class="text-base font-bold text-slate-900 dark:text-white">Focused Health Assessment</span>
                    <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400">
                        Assess one health topic or programme — malaria, TB, WASH, nutrition, and more.
                    </span>
                </button>
            @else
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="text-base font-bold text-slate-400 dark:text-slate-500">Focused Health Assessment</span>
                    <span class="mt-2 block text-sm text-slate-400 dark:text-slate-500">No focused assessments are published yet.</span>
                </div>
            @endif
        </div>

        <section x-show="path === 'COMPREHENSIVE'" x-cloak class="mt-7">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Services included in this assessment</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Select the departments and services currently available in this {{ strtolower($settingLabel) }}. Uncheck any default service that does not operate here.
            </p>

            @forelse ($comprehensiveReleases as $release)
                <form method="POST" action="{{ route('assessments.store', $project) }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    @csrf
                    <input type="hidden" name="creation_path" value="COMPREHENSIVE">
                    <input type="hidden" name="catalogue_release_id" value="{{ $release->catalogue_release_id }}">

                    <div>
                        <span class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $release->facilityProfile?->profile_name }}</span>
                        <h3 class="mt-1 font-bold text-slate-900 dark:text-white">{{ $release->release_name }}</h3>
                        @if ($release->description)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $release->description }}</p>
                        @endif
                    </div>

                    <div class="relative mt-4">
                        <label for="service-search-{{ $release->catalogue_release_id }}" class="sr-only">Search services</label>
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                        </svg>
                        <input id="service-search-{{ $release->catalogue_release_id }}"
                               type="search"
                               x-model.debounce.100ms="serviceSearch"
                               placeholder="Search services"
                               autocomplete="off"
                               class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-10 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <button type="button"
                                x-show="serviceSearch"
                                x-cloak
                                @click="serviceSearch = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white">
                            Clear
                        </button>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($release->departmentFrameworkVersions as $framework)
                            @php
                                $applicability = $framework->pivot->applicability;
                                $isRequired = $applicability === 'REQUIRED';
                                $isDefault = in_array($applicability, ['REQUIRED', 'DEFAULT'], true);
                                $needsExclusionReason = $applicability === 'DEFAULT';
                                $serviceName = $framework->pivot->area_label ?: $framework->module?->module_name;
                            @endphp
                            <div x-data="{ included: {{ $isDefault ? 'true' : 'false' }} }"
                                 data-service-name="{{ Illuminate\Support\Str::lower($serviceName) }}"
                                 x-show="({{ $needsExclusionReason ? 'true' : 'false' }} && !included) || serviceSearch.trim() === '' || $el.dataset.serviceName.includes(serviceSearch.trim().toLowerCase())"
                                 class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
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
                                    <span class="flex-1">{{ $serviceName }}</span>
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ count($framework->published_payload['questions'] ?? []) }} questions</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-400">{{ strtolower($applicability) }}</span>
                                </label>
                                @if ($applicability === 'DEFAULT')
                                    <input x-show="!included" x-cloak :required="!included" type="text"
                                           name="exclusion_reasons[{{ $framework->module_id }}]"
                                           placeholder="Why does this default service not operate here?"
                                           class="mt-3 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-vytte-500 focus:ring-2 focus:ring-vytte-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @php
                        $availableModuleIds = $release->departmentFrameworkVersions->pluck('module_id');
                        $departmentsAwaitingContent = $release->facilityProfile?->departments
                            ?->whereNotIn('module_id', $availableModuleIds)
                            ->reject(fn ($department) => $department->module_code === 'FAC')
                            ->values() ?? collect();
                    @endphp
                    @if ($departmentsAwaitingContent->isNotEmpty())
                        <div class="mt-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Other {{ $release->facilityProfile?->profile_name }} departments</p>
                            <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">These belong to this facility structure, but their governed questions are not published yet.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($departmentsAwaitingContent as $department)
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs text-slate-600 ring-1 ring-amber-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-amber-800">{{ $department->module_name }} · coming soon</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <button class="mt-5 rounded-lg bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800">
                        Start comprehensive assessment
                    </button>
                </form>
            @empty
                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    No comprehensive assessment has been published for {{ strtolower($settingLabel) }} targets yet.
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
