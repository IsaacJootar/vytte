@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 bg-white border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:border-vytte-700 focus:ring-2 focus:ring-vytte-700/[0.12] disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed transition-shadow duration-150']) }}>
