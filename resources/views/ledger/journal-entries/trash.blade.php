@extends('layouts.app')
@section('title', __('ledger_je.deleted_journal_entries'))

@section('content')
@include('ledger.partials.style')
@php($JE = \App\Http\Controllers\Ledger\JournalEntryController::class)
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_je.journal_entries') }}</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-trash"></i> {{ __('ledger_je.deleted_journal_entries') }}</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-arrow-left"></i> {{ __('ledger_je.back_to_journal_entries') }}</a>
            </div>
        </div>
        <div class="box-body">
            @if($entries->count() > 0)
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <strong>{{ __('ledger_je.trash_info') }}:</strong> {{ __('ledger_je.deleted_entries_can_be_restored_or_permanently_deleted') }}
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover ap-table">
                    <thead>
                        <tr>
                            <th width="10%">{{ __('ledger_je.entry_number') }}</th>
                            <th width="10%">{{ __('ledger_je.entry_date') }}</th>
                            <th width="10%">{{ __('ledger_je.journal_type') }}</th>
                            <th width="25%">{{ __('ledger_je.description') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_je.total_debit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_je.total_credit') }}</th>
                            <th width="10%">{{ __('ledger_je.deleted_at') }}</th>
                            <th width="15%">{{ __('ledger_je.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                        <tr>
                            <td><span class="label label-default">{{ $entry->entry_number }}</span></td>
                            <td>{{ $entry->entry_date->format('d/m/Y') }}</td>
                            <td><span class="label label-info">{{ $JE::typeLabel($entry->journal_type) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($entry->description, 40) }}</td>
                            <td class="ap-num">{{ \App\Ledger\Ledger::money($entry->total_debit) }}</td>
                            <td class="ap-num">{{ \App\Ledger\Ledger::money($entry->total_credit) }}</td>
                            <td><small>{{ $entry->deleted_at->format('d/m/Y H:i') }}</small></td>
                            <td style="white-space: nowrap;">
                                <form action="{{ route('ledger.journal-entries.restore', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm({{ json_encode(__('ledger_je.confirm_restore_journal_entry')) }})">
                                        <i class="fa fa-undo"></i> {{ __('ledger_je.restore') }}
                                    </button>
                                </form>
                                <form action="{{ route('ledger.journal-entries.force-delete', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm({{ json_encode(__('ledger_je.confirm_permanent_delete_warning')."\n\n".__('ledger_je.this_action_cannot_be_undone').'!') }})">
                                        <i class="fa fa-trash-alt"></i> {{ __('ledger_je.delete_permanently') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">{{ __('ledger_je.total') }}:</th>
                            <th class="ap-num">{{ \App\Ledger\Ledger::money($entries->sum('total_debit')) }}</th>
                            <th class="ap-num">{{ \App\Ledger\Ledger::money($entries->sum('total_credit')) }}</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div>{{ $entries->links() }}</div>

            <div class="alert alert-warning" style="margin-top: 15px;">
                <h4><i class="fa fa-exclamation-triangle"></i> {{ __('ledger_je.important') }}:</h4>
                <ul style="margin-bottom: 0;">
                    <li><strong>{{ __('ledger_je.restore') }}:</strong> {{ __('ledger_je.recover_entry_to_active_list') }}</li>
                    <li><strong>{{ __('ledger_je.delete_permanently') }}:</strong> {{ __('ledger_je.permanently_remove_cannot_be_undone') }}</li>
                    <li>{{ __('ledger_je.deleted_entries_kept') }}</li>
                </ul>
            </div>
            @else
            <div class="alert alert-success text-center">
                <i class="fa fa-check-circle fa-3x" style="margin-bottom: 10px;"></i>
                <h4>{{ __('ledger_je.trash_is_empty') }}!</h4>
                <p>{{ __('ledger_je.no_deleted_journal_entries_found') }}</p>
                <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-primary"><i class="fa fa-arrow-left"></i> {{ __('ledger_je.back_to_journal_entries') }}</a>
            </div>
            @endif
        </div>
    </div>
</section>
@endsection
