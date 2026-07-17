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

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

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
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Comprehensive frameworks</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $usesDepartments ? 'Choose the departments this setting actually operates.' : 'Choose the health assessment areas that apply to this setting.' }}
            </p>

            @forelse ($comprehensiveTemplates as $template)
                @php $version = $template->versions->first(); @endphp
                @if ($version)
                    <form method="POST" action="{{ route('assessments.store', $project) }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                        @csrf
                        <input type="hidden" name="creation_path" value="COMPREHENSIVE">
                        <input type="hidden" name="template_version_id" value="{{ $version->template_version_id }}">

                        <h3 class="font-bold text-slate-900 dark:text-white">{{ $template->template_name }}</h3>
                        @if ($template->description)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $template->description }}</p>
                        @endif

                        <div class="mt-4 space-y-2">
                            @foreach ($version->published_payload as $module)
                                <div x-data="{ included: true }" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <label class="flex items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        <input type="checkbox" name="modules[]" value="{{ $module['module_id'] }}" x-model="included" checked class="rounded border-slate-300 text-vytte-600 focus:ring-vytte-500">
                                        {{ $module['area_label'] ?: $module['module_name'] }}
                                    </label>
                                    <input x-show="!included" x-cloak :required="!included" type="text"
                                           name="exclusion_reasons[{{ $module['module_id'] }}]"
                                           placeholder="Why does this area not apply?"
                                           class="mt-3 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700">
                                </div>
                            @endforeach
                        </div>

                        <button class="mt-5 rounded-lg bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800">
                            Start comprehensive assessment
                        </button>
                    </form>
                @endif
            @empty
                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    No approved comprehensive framework is available for this setting yet. Draft or sample content is never shown here.
                </div>
            @endforelse
        </section>

        <section x-show="path === 'FOCUSED'" x-cloak class="mt-7">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">What health domain are you assessing?</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose one approved template. No unrelated departments or programmes will be added.</p>

            <div class="mt-4 space-y-3">
                @forelse ($focusedTemplates as $template)
                    @php $version = $template->versions->first(); @endphp
                    @if ($version)
                        <form method="POST" action="{{ route('assessments.store', $project) }}" class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-800">
                            @csrf
                            <input type="hidden" name="creation_path" value="FOCUSED">
                            <input type="hidden" name="template_version_id" value="{{ $version->template_version_id }}">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400">{{ $template->healthDomain?->domain_name }}</span>
                                <h3 class="mt-1 font-bold text-slate-900 dark:text-white">{{ $template->template_name }}</h3>
                                @if ($template->description)
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $template->description }}</p>
                                @endif
                            </div>
                            <button class="shrink-0 rounded-lg bg-vytte-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-vytte-800">
                                Start assessment
                            </button>
                        </form>
                    @endif
                @empty
                    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        No approved focused templates are available yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
