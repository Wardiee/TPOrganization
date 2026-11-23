{{-- Small uppercase label, e.g. VACANT or MISSING. Pass the colour classes in. --}}
<span {{ $attributes->class(['shrink-0 rounded-md px-2 py-1 text-[11px] font-bold tracking-[0.06em]']) }}>{{ $slot }}</span>
