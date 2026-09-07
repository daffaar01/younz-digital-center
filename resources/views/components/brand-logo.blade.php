@props(['inverse' => false, 'compact' => false, 'responsive' => false])
<span {{ $attributes->class(['inline-flex items-center gap-3']) }}>
    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-500 font-display text-base font-black text-white" aria-hidden="true">Y</span>
    
    @unless($compact)
        <span class="font-display text-lg font-extrabold tracking-tight {{ $responsive ? 'hidden sm:inline' : '' }} {{ $inverse ? 'text-white' : 'text-[#006c49]' }}">Younz Digital Center</span>
    @endunless
</span>
