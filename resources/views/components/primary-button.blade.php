<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 focus-visible:ring-offset-2 active:bg-vytte-900 disabled:opacity-65 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{-- The submit spinner is applied globally by resources/js/submit-state.js on submit, so
         every button behaves identically. No per-button spinner here — it double-rendered. --}}
    <span>{{ $slot }}</span>
</button>
