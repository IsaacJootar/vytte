<x-app-layout title="Projects">

    {{-- Page header --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Projects</h1>
            <p class="mt-0.5 text-sm text-slate-500">Each project represents one facility, school, or community you are diagnosing.</p>
        </div>
        <a href="{{ route('projects.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150 flex-shrink-0">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
            </svg>
            New Project
        </a>
    </div>

    {{-- Flash message --}}
    @if (session('success'))
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-800 font-medium">
            <svg class="w-4 h-4 text-green-600 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($projects->isEmpty())
        {{-- Empty state --}}
        <div class="bg-white rounded-2xl border border-slate-200 px-6 py-16 flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-2xl bg-vytte-50 border border-vytte-100 flex items-center justify-center mb-4">
                <x-heroicon-o-folder class="w-6 h-6 text-vytte-600" />
            </div>
            <p class="text-sm font-semibold text-slate-900">No projects yet</p>
            <p class="mt-1.5 text-sm text-slate-500 max-w-xs leading-relaxed">
                Create your first project to start diagnosing a health facility, school, or community.
            </p>
            <a href="{{ route('projects.create') }}"
               class="mt-5 inline-flex items-center gap-1.5 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg hover:bg-vytte-800 transition-colors duration-150">
                Create first project
            </a>
        </div>
    @else
        {{-- Project grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($projects as $project)
                @php
                    $target   = $project->targets->first();
                    $typeName = $target?->targetType?->target_type_name;
                    $catName  = $target?->category?->category_name;
                    $location = collect([$target?->state, $target?->lga])->filter()->implode(' · ');
                    $typeIcon = match ($target?->target_type_code) {
                        'HEALTH_FACILITY' => 'building-office-2',
                        'SCHOOL'          => 'academic-cap',
                        'COMMUNITY'       => 'users',
                        'WATER_POINT'     => 'beaker',
                        default           => 'question-mark-circle',
                    };
                    $typeColor = match ($target?->target_type_code) {
                        'HEALTH_FACILITY' => 'bg-vytte-50 text-vytte-700 border-vytte-100',
                        'SCHOOL'          => 'bg-purple-50 text-purple-700 border-purple-100',
                        'COMMUNITY'       => 'bg-amber-50 text-amber-700 border-amber-100',
                        'WATER_POINT'     => 'bg-teal-50 text-teal-700 border-teal-100',
                        default           => 'bg-slate-50 text-slate-500 border-slate-200',
                    };
                @endphp
                <div class="bg-white rounded-2xl border border-slate-200 flex flex-col hover:border-slate-300 hover:shadow-sm transition-all duration-150">
                    {{-- Card body --}}
                    <a href="{{ route('projects.show', $project) }}" class="block p-5 flex-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 rounded-t-2xl">

                        {{-- Type + score row --}}
                        <div class="flex items-start justify-between gap-2 mb-3">
                            @if ($typeName)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-[11px] font-semibold {{ $typeColor }}">
                                    <x-dynamic-component :component="'heroicon-s-' . $typeIcon" class="w-3 h-3" />
                                    {{ $typeName }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[11px] font-semibold bg-slate-50 text-slate-400 border-slate-200">
                                    No target
                                </span>
                            @endif
                            <x-score-pill :score="null" />
                        </div>

                        {{-- Names --}}
                        @if ($target)
                            <p class="text-[11px] text-slate-400 font-medium leading-none mb-1">{{ $target->name }}</p>
                        @endif
                        <h2 class="text-sm font-bold text-slate-900 leading-snug">{{ $project->name }}</h2>

                        {{-- Category + location --}}
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-400">
                            @if ($catName)
                                <span>{{ $catName }}</span>
                            @endif
                            @if ($location)
                                <span class="flex items-center gap-0.5">
                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $location }}
                                </span>
                            @endif
                        </div>

                    </a>

                    {{-- Card footer --}}
                    <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3 text-[11px]">
                            <span class="text-slate-400">0 assessments</span>
                            @if ($project->isArchived())
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 font-semibold text-[10px]">Archived</span>
                            @endif
                        </div>
                        <a href="{{ route('projects.edit', $project) }}"
                           class="text-[11px] font-semibold text-vytte-700 hover:text-vytte-900 transition-colors">Edit ›</a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($projects->hasPages())
            <div class="mt-6">{{ $projects->links() }}</div>
        @endif
    @endif

</x-app-layout>
