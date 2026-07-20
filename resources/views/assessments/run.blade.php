<x-app-layout :title="$assessment->moduleScope->first()?->module?->module_name ?? 'Assessment'">

    {{-- Back to the project this assessment belongs to. Leaving mid-assessment does not
         discard anything — answers are saved as they are given. --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $assessment->project ? route('projects.show', $assessment->project) : route('projects.index') }}"
           class="link-nav inline-flex items-center gap-1 text-sm">
            <span aria-hidden="true">&larr;</span>
            {{ $assessment->project?->name ? 'Back to '.\Illuminate\Support\Str::limit($assessment->project->name, 40) : 'Back to projects' }}
        </a>

        {{-- Locale switcher --}}
        <div class="flex justify-end">
        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            @foreach (['en' => 'EN', 'fr' => 'FR'] as $code => $label)
                @php $active = app()->getLocale() === $code; @endphp
                <form method="POST" action="{{ route('locale.store') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit"
                            class="px-3 py-1.5 text-xs font-bold transition-colors duration-150
                                {{ $active
                                    ? 'bg-vytte-700 text-white'
                                    : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                        {{ $label }}
                    </button>
                </form>
            @endforeach
            </div>
        </div>
    </div>

    @livewire('assessment-runner', ['assessment' => $assessment])

</x-app-layout>
