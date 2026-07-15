<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center gap-2 px-4 py-2 bg-white text-slate-700 text-sm font-semibold rounded-lg border border-slate-300 shadow-sm hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 focus-visible:ring-offset-2 disabled:opacity-65 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{ $slot }}
</button>
