@extends('adminmodule::layouts.master')

@section('title', translate('Ask E-Ride'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 1180px">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#E02020"><i class="bi bi-stars text-white fs-4"></i></span>
                <div class="flex-grow-1">
                    <h2 class="fs-22 mb-0">{{ translate('Ask E-Ride') }}</h2>
                    <div class="text-muted fs-14">{{ translate('The assistant in the customer app. It answers from the knowledge pack below and from the orders of the customer, and only offers buttons into the app. It never pays, books or cancels.') }}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-light text-dark fs-12">{{ translate('Today') }}: {{ $stats['today'] }}</span>
                    <span class="badge bg-light text-dark fs-12">{{ translate('Answered') }}: {{ $stats['answered'] }}</span>
                    <span class="badge bg-light text-dark fs-12">{{ translate('Not helpful') }}: {{ $stats['unhelpful'] }}</span>
                    @foreach($stats['by_provider'] as $name => $n)
                        <span class="badge bg-light text-dark fs-12">{{ \Modules\AssistantManagement\Lib\AssistantSettings::PROVIDERS[$name]['label'] ?? $name }}: {{ $n }}</span>
                    @endforeach
                </div>
            </div>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form action="{{ route('admin.assistant.settings') }}" method="POST" class="row g-3 mb-3">
                @csrf
                <div class="col-lg-8">
                    <div class="card h-100"><div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <h5 class="mb-0">{{ translate('AI providers, in order') }}</h5>
                            <label class="d-flex align-items-center gap-2 mb-0 fw-semibold">
                                {{ translate('On for customers') }}
                                <span class="switcher"><input type="hidden" name="enabled" value="0"><input class="switcher_input" type="checkbox" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}><span class="switcher_control"></span></span>
                            </label>
                        </div>
                        <p class="fs-12 text-muted mb-0">{{ translate('When a provider says its free quota or credits are used up, or fails, Ask E-Ride moves to the next one by itself, and tries it again once it is back (the next day for a used-up quota).') }}</p>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label mb-0 fw-semibold" for="first">{{ translate('Ask first') }}</label>
                            <select name="first" id="first" class="form-select form-select-sm" style="max-width: 220px">
                                @foreach($settings['providers'] as $p)
                                    <option value="{{ $p['name'] }}" {{ $loop->first ? 'selected' : '' }}>{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        @foreach($settings['providers'] as $i => $p)
                            <div class="border rounded-3 p-3 {{ $i === 0 ? 'border-danger' : '' }}">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge rounded-pill {{ $i === 0 ? 'bg-danger' : 'bg-dark' }}">{{ $i + 1 }}</span>
                                    <span class="fw-bold">{{ $p['label'] }}</span>
                                    @if(!$p['has_key'])
                                        <span class="badge bg-secondary">{{ translate('No key yet') }}</span>
                                    @elseif($p['skipped'])
                                        <span class="badge bg-warning text-dark" title="{{ $p['skipped'] }}">{{ translate('Resting') }}: {{ $p['skipped'] }}</span>
                                    @else
                                        <span class="badge bg-success">{{ translate('Ready') }}</span>
                                    @endif
                                    <span class="flex-grow-1"></span>
                                    <label class="d-flex align-items-center gap-2 mb-0 fs-12">{{ translate('Use it') }}
                                        <input type="hidden" name="on[{{ $p['name'] }}]" value="0"><input type="checkbox" name="on[{{ $p['name'] }}]" value="1" {{ $p['enabled'] ? 'checked' : '' }}>
                                    </label>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-7">
                                        <label class="form-label fs-12 mb-1" for="key-{{ $p['name'] }}">{{ translate('API key') }}</label>
                                        <input type="password" id="key-{{ $p['name'] }}" name="key[{{ $p['name'] }}]" class="form-control" autocomplete="new-password" placeholder="{{ $p['has_key'] ? translate('Saved. Leave blank to keep it') : translate('Paste the key') }}">
                                        @if($p['has_key'])
                                            <label class="fs-12 mt-1 d-flex align-items-center gap-1"><input type="checkbox" name="clear[{{ $p['name'] }}]" value="1"> {{ translate('Remove key') }}</label>
                                        @endif
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fs-12 mb-1" for="model-{{ $p['name'] }}">{{ translate('Model') }}</label>
                                        <input type="text" id="model-{{ $p['name'] }}" name="model[{{ $p['name'] }}]" class="form-control" value="{{ $p['model'] }}" maxlength="80">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div></div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100"><div class="card-body d-flex flex-column gap-3">
                        <h5 class="mb-0">{{ translate('Limits') }}</h5>
                        <div>
                            <label class="form-label" for="per_user_daily">{{ translate('Questions per customer per day') }}</label>
                            <input type="number" id="per_user_daily" name="per_user_daily" class="form-control" min="1" max="200" value="{{ $settings['per_user_daily'] }}">
                        </div>
                        <div>
                            <label class="form-label" for="app_daily">{{ translate('Questions for the whole app per day') }}</label>
                            <input type="number" id="app_daily" name="app_daily" class="form-control" min="10" max="100000" value="{{ $settings['app_daily'] }}">
                        </div>
                        <div class="bg-light rounded-3 p-3 fs-12">
                            <i class="bi bi-shield-check text-success"></i>
                            {{ translate('Phone numbers and emails are removed from questions before they reach the AI, and names are never sent. Refunds, complaints and safety always go to WhatsApp support.') }}
                        </div>
                        <button type="submit" class="btn btn-primary mt-auto">{{ translate('Save') }}</button>
                    </div></div>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2 mb-4">
                @foreach($settings['providers'] as $p)
                    <form action="{{ route('admin.assistant.test', $p['name']) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm" {{ $p['has_key'] ? '' : 'disabled' }}><i class="bi bi-lightning-charge"></i> {{ translate('Test') }} {{ $p['label'] }}</button>
                    </form>
                @endforeach
            </div>

            <div class="card mb-4"><div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h5 class="mb-0 flex-grow-1">{{ translate('Knowledge pack') }}</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-entry-new><i class="bi bi-plus-lg"></i> {{ translate('Add an entry') }}</button>
                </div>
                <p class="fs-12 text-muted">{{ translate('Short, plain facts about how E-Ride works. Ask E-Ride answers only from these. An entry with a help key also shows as the info sheet on that screen of the app; each line of its text becomes a step.') }}</p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light"><tr><th>{{ translate('Entry') }}</th><th>{{ translate('Service') }}</th><th>{{ translate('Help key') }}</th><th class="text-center">{{ translate('Active') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($entries as $e)
                            <tr>
                                <td><div class="fw-semibold">{{ $e->title_en }}</div><div class="fs-12 text-muted">{{ $e->title_fr }}</div></td>
                                <td>{{ $e->service }}</td>
                                <td>@if($e->help_key)<code>{{ $e->help_key }}</code>@else<span class="text-muted">-</span>@endif</td>
                                <td class="text-center">{!! $e->is_active ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>' !!}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-light" data-entry-edit data-action="{{ route('admin.assistant.entry.save', $e->id) }}"
                                            data-entry="{{ json_encode($e->only(['help_key', 'service', 'title_en', 'title_fr', 'body_en', 'body_fr', 'sort', 'is_active'])) }}"><i class="bi bi-pencil"></i></button>
                                    <form action="{{ route('admin.assistant.entry.delete', $e->id) }}" method="POST" class="d-inline" onsubmit='return confirm(@json(translate("Remove this entry?")))'>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div></div>

            <div class="card mb-4"><div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h5 class="mb-0 flex-grow-1">{{ translate('What people ask') }}</h5>
                    <ul class="nav nav-pills">
                        @foreach(['all' => translate('All'), 'unhelpful' => translate('Not helpful'), 'unanswered' => translate('No answer')] as $key => $label)
                            <li class="nav-item"><a class="nav-link py-1 {{ $filter === $key ? 'active' : '' }}" href="{{ route('admin.assistant.index', ['show' => $key]) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <p class="fs-12 text-muted">{{ translate('Questions marked not helpful, or with no answer, show what confuses customers: add or fix a knowledge entry for them.') }}</p>
                <div class="table-responsive">
                    <table class="table align-middle fs-14">
                        <thead class="table-light"><tr><th>{{ translate('Question') }}</th><th>{{ translate('Answer') }}</th><th>{{ translate('Screen') }}</th><th>{{ translate('By') }}</th><th>{{ translate('Helped?') }}</th><th>{{ translate('When') }}</th></tr></thead>
                        <tbody>
                        @forelse($questions as $q)
                            <tr>
                                <td style="max-width: 280px">{{ $q->question }}</td>
                                <td style="max-width: 320px" class="fs-12">{{ $q->reply }}
                                    @if($q->actions)<div class="mt-1">@foreach($q->actions as $a)<span class="badge bg-light text-dark me-1">{{ $a['label'] ?? $a['type'] }}</span>@endforeach</div>@endif
                                </td>
                                <td class="fs-12">{{ $q->screen ?: '-' }}</td>
                                <td class="fs-12">{{ $q->provider ? (\Modules\AssistantManagement\Lib\AssistantSettings::PROVIDERS[$q->provider]['label'] ?? $q->provider) : translate($q->status) }}</td>
                                <td>@if($q->helped === true)<span class="text-success fw-bold">{{ translate('Yes') }}</span>@elseif($q->helped === false)<span class="text-danger fw-bold">{{ translate('No') }}</span>@else<span class="text-muted">-</span>@endif</td>
                                <td class="fs-12 text-nowrap">{{ $q->created_at->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('No questions yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end">{!! $questions->links() !!}</div>
            </div></div>
        </div>
    </div>

    <div class="modal fade" id="entry-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" data-new-action="{{ route('admin.assistant.entry.save') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">{{ translate('Knowledge entry') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">{{ translate('Title (English)') }} *</label><input name="title_en" class="form-control" required maxlength="120"></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('Title (French)') }} *</label><input name="title_fr" class="form-control" required maxlength="120"></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('Text (English)') }} *</label><textarea name="body_en" class="form-control" rows="5" required maxlength="2000"></textarea><div class="fs-12 text-muted">{{ translate('One fact or step per line.') }}</div></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('Text (French)') }} *</label><textarea name="body_fr" class="form-control" rows="5" required maxlength="2000"></textarea></div>
                        <div class="col-md-4"><label class="form-label">{{ translate('Service') }}</label><input name="service" class="form-control" maxlength="40" placeholder="food, bus, rides…"></div>
                        <div class="col-md-4"><label class="form-label">{{ translate('Help key') }}</label><input name="help_key" class="form-control" maxlength="40" placeholder="food_pay"><div class="fs-12 text-muted">{{ translate('Only for an info sheet on an app screen.') }}</div></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('Order') }}</label><input name="sort" type="number" class="form-control"></div>
                        <div class="col-md-2 d-flex align-items-end"><label class="d-flex align-items-center gap-2 mb-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> {{ translate('Active') }}</label></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ translate('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ translate('Save') }}</button></div>
            </form>
        </div>
    </div>
@endsection

@push('script')
<script>
(function () {
    var el = document.getElementById('entry-modal');
    var form = el.querySelector('form');
    var modal = new bootstrap.Modal(el);
    function open(action, e) {
        form.reset();
        form.action = action;
        if (e) {
            ['title_en', 'title_fr', 'body_en', 'body_fr', 'service', 'help_key', 'sort'].forEach(function (k) { form[k].value = e[k] == null ? '' : e[k]; });
            form.querySelector('input[type=checkbox][name=is_active]').checked = !!e.is_active;
        }
        modal.show();
    }
    document.querySelector('[data-entry-new]').addEventListener('click', function () { open(form.dataset.newAction, null); });
    document.querySelectorAll('[data-entry-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset.action, JSON.parse(b.dataset.entry)); }); });
})();
</script>
@endpush
