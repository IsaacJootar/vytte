@props(['href' => '#', 'icon' => 'home', 'label' => '', 'active' => false])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex-1 flex flex-col items-center gap-0.5 pt-2 pb-1 focus:outline-none focus-visible:bg-slate-50 dark:focus-visible:bg-slate-700']) }}>
    @if($active)
        <span class="w-1 h-1 rounded-full bg-vytte-700 mb-0.5"></span>
    @else
        <span class="w-1 h-1 mb-0.5"></span>
    @endif
    <x-dynamic-component
        :component="'heroicon-o-' . $icon"
        class="{{ $active ? 'text-vytte-700' : 'text-slate-400 dark:text-slate-500' }} w-5 h-5"
    />
    <span class="text-[9px] font-{{ $active ? '700' : '500' }} {{ $active ? 'text-vytte-700' : 'text-slate-400 dark:text-slate-500' }}">
        {{ $label }}
    </span>
</a>
