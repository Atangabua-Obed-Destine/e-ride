{{-- Draft / Posted / Reversed, in words. --}}
@if($entry->is_reversed)
<span class="ap-chip void">@lang('ledger_je.reversed')</span>
@elseif($entry->is_posted)
<span class="ap-chip posted">@lang('ledger_je.posted')</span>
@else
<span class="ap-chip draft">@lang('ledger_je.draft')</span>
@endif
