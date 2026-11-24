{{-- Small avatar for an org chart seat: the holder's photo or initials, or a dashed circle when the seat is empty. --}}
@if ($seat['name'] === null)
    <span class="box-border size-9 shrink-0 rounded-full border-2 border-dashed border-line-input" aria-hidden="true"></span>
@else
    <x-avatar-initials
        :initials="$seat['member']?->initials ?? (new App\Models\Member(['full_name' => $seat['name']]))->initials"
        :photo="$seat['member']?->photoUrl()"
        size="sm"
        :tone="$seat['flag'] === 'inactive' ? 'inactive-soft' : 'active-soft'"
    />
@endif
