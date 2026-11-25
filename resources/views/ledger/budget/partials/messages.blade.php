{{-- Validation errors and the period warnings a budget screen raises (paxhitestbed showed them as Flasher warnings). --}}
@if($errors->any())
    <div class="ap-note is-bad" style="margin-bottom: 12px;">
        <ul style="margin: 0; padding-left: 18px;">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
@foreach((array) session('budget_warnings', []) as $w)
    <div class="ap-note is-warn" style="margin-bottom: 8px;"><i class="fa fa-calendar"></i> {{ $w }}</div>
@endforeach
