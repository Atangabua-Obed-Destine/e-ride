{{-- A utilisation bar with the percentage written on it. Expects $pct and $color. --}}
<div class="progress" style="height: 20px; margin-bottom: 2px;">
    <div class="progress-bar progress-bar-{{ $color }}" role="progressbar" style="width: {{ min(100, max(0, (float) $pct)) }}%; min-width: 2.5em; color: {{ (float) $pct > 0 ? '#fff' : '#333' }};"
         aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">{{ number_format((float) $pct, 1) }}%</div>
</div>
