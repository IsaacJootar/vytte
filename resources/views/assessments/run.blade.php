<x-app-layout :title="$assessment->moduleScope->first()?->module?->module_name ?? 'Assessment'">

    {{-- Locale switcher --}}
    <div class="mb-4 flex justify-end">
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

    @livewire('assessment-runner', ['assessment' => $assessment])

</x-app-layout>
