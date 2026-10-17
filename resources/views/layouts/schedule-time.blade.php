@if($startsAt->format('Y-m-d') === $endsAt->format('Y-m-d'))
<span class="schedule-date">{{ $startsAt->translatedFormat('l, j F Y') }}</span>
<span class="schedule-hours"><time datetime="{{ $startsAt->toIso8601String() }}">{{ $startsAt->format('H:i') }}</time>–<time datetime="{{ $endsAt->toIso8601String() }}">{{ $endsAt->format('H:i') }}</time> WIB</span>
@else
<span class="schedule-date">Mulai: {{ $startsAt->translatedFormat('j F Y') }}</span><span class="schedule-hours">{{ $startsAt->format('H:i') }} WIB</span>
<span class="schedule-date">Selesai: {{ $endsAt->translatedFormat('j F Y') }}</span><span class="schedule-hours">{{ $endsAt->format('H:i') }} WIB</span>
@endif
