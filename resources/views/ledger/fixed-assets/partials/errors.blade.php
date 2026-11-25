@if ($errors->any())
<div class="alert alert-danger" style="margin: 15px 15px 0;">
    <h4 style="margin-top: 0;"><i class="icon fa fa-ban"></i> @lang('ledger_assets.validation_errors')</h4>
    <ul style="margin-bottom: 0;">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
