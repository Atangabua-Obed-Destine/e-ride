{{--
    Menu editor shared by the admin panel and the partner portal.
    Needs: $store (the brand: categories, items), $routePrefix ('admin.store.' or 'partner.'),
    $routeBase (route params, e.g. ['store' => $store] or []).
    Optional: $kitchens (kitchens this page may switch), $kitchen (the one shown),
    $canEditMenu (false for a branch login), $hasBranches.
    Two views (?view=): "today" (the morning check at one kitchen: what is served
    today, with its switch) and "full" (edit the menu, serving times included).
--}}
@php
    use Modules\StoreManagement\Entities\Store;
    use Modules\StoreManagement\Entities\StoreItem;

    $r = fn(string $name, array $params = []) => route($routePrefix . $name, array_merge($routeBase, $params));
    $store->linkMenu();
    $now = now();
    $kitchens = $kitchens ?? collect([$store]);
    $kitchen = $kitchen ?? $store;
    $canEditMenu = $canEditMenu ?? true;
    $canSetPrices = $canSetPrices ?? true;
    $hasBranches = $hasBranches ?? false;
    $kitchenStates = $kitchen->stateMap();
    $view = !$canEditMenu ? 'today'
        : (request('view') === 'full' || $store->items->isEmpty() ? 'full' : (request('view') === 'today' ? 'today' : ($defaultView ?? 'today')));
    $pageUrl = fn(array $q) => request()->url() . '?' . http_build_query($q);
    $dayNames = ['mon' => translate('Mon'), 'tue' => translate('Tue'), 'wed' => translate('Wed'), 'thu' => translate('Thu'), 'fri' => translate('Fri'), 'sat' => translate('Sat'), 'sun' => translate('Sun')];
    // "Mon, Thu · 18:00 to 22:00", "Every day", or "" when nothing is set.
    $when = function (?array $days, ?array $window, bool $sayEveryDay = false) use ($dayNames) {
        $parts = [];
        if ($days !== null) {
            $parts[] = $days ? implode(', ', array_map(fn($d) => $dayNames[$d], $days)) : translate('No_day');
        } elseif ($window || $sayEveryDay) {
            $parts[] = translate('Every_day');
        }
        if ($window) {
            $parts[] = $window[0] . '–' . $window[1];
        }
        return implode(' · ', $parts);
    };
    // The switch: at one kitchen (Today, or a store without branches), or the whole menu (Full, with branches).
    $kitchenLabels = $hasBranches
        ? [StoreItem::AVAILABLE => translate('Available'), StoreItem::SOLD_OUT => translate('Sold_out_today'), StoreItem::NOT_HERE => translate('Not_at_this_branch')]
        : [StoreItem::AVAILABLE => translate('Available'), StoreItem::SOLD_OUT => translate('Sold_out_today'), StoreItem::HIDDEN => translate('Hidden')];
    $menuLabels = ['on_menu' => translate('On_the_menu'), StoreItem::HIDDEN => translate('Hidden_at_all_branches')];
    $brandSwitch = $hasBranches && $view === 'full';
    $stateLabels = $brandSwitch ? $menuLabels : $kitchenLabels;
    $stateOf = fn(StoreItem $item) => $brandSwitch
        ? ($item->is_available ? 'on_menu' : StoreItem::HIDDEN)
        : $item->switchState($kitchenStates->get($item->id), $now);

    $sections = $store->categories->map(fn($c) => ['category' => $c, 'items' => $store->items->where('store_menu_category_id', $c->id)]);
    $known = $store->categories->pluck('id')->all();
    $loose = $store->items->filter(fn($i) => !$i->store_menu_category_id || !in_array($i->store_menu_category_id, $known));
    if ($loose->isNotEmpty() || $store->categories->isEmpty()) {
        $sections->push(['category' => null, 'items' => $loose]);
    }
    if ($view === 'today') {
        // Served today (day of the week), in sections that are not hidden.
        // With branches, a dish hidden from the whole menu is not a branch's to switch.
        $sections = $sections->filter(fn($s) => !$s['category'] || $s['category']->is_active)
            ->map(fn($s) => ['category' => $s['category'], 'items' => $s['items']->filter(fn($i) => $i->servedOn($now) && (!$hasBranches || $i->is_available))])
            ->filter(fn($s) => $s['items']->isNotEmpty());
        $todayIds = $sections->flatMap(fn($s) => $s['items']->pluck('id'));
        $notToday = $store->items->count() - $todayIds->count();
    }
@endphp

<style>
    .menu-state { width: auto; min-width: 150px; font-weight: 600; }
    .menu-state[data-state="available"] { color: #12804a; border-color: #9fd8b8; background-color: #f0faf4; }
    .menu-state[data-state="sold_out"] { color: #b45309; border-color: #f3c98b; background-color: #fff8ec; }
    .menu-state[data-state="hidden"], .menu-state[data-state="not_here"] { color: #6b7280; background-color: #f3f4f6; }
    .menu-state[data-state="on_menu"] { color: #12804a; border-color: #9fd8b8; background-color: #f0faf4; }
    .menu-kitchens { display: flex; flex-wrap: wrap; gap: 6px; }
    .menu-kitchens a { border: 1px solid #d1d5db; border-radius: 999px; padding: 5px 14px; font-size: 14px; font-weight: 600; color: #374151; text-decoration: none; background: #fff; }
    .menu-kitchens a.active { background: #111827; color: #fff; border-color: #111827; }
    .menu-row.is-off { opacity: .6; }
    .menu-when { font-size: 12px; color: #6b7280; }
    .menu-when i { font-size: 11px; }
    .menu-days { display: flex; flex-wrap: wrap; gap: 6px; }
    .menu-days label { border: 1px solid #d1d5db; border-radius: 999px; padding: 4px 12px; cursor: pointer; font-size: 13px; user-select: none; margin: 0; }
    .menu-days input { display: none; }
    .menu-days input:checked + span { font-weight: 700; }
    .menu-days label:has(input:checked) { background: #111827; color: #fff; border-color: #111827; }
</style>

<div class="store-menu">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link {{ $view === 'today' ? 'active' : '' }} {{ $store->items->isEmpty() ? 'disabled' : '' }}" href="{{ $pageUrl(['view' => 'today', 'kitchen' => $kitchen->id]) }}">
                    <i class="bi bi-calendar-check"></i> {{ translate('Today') }}
                </a>
            </li>
            @if($canEditMenu)
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'full' ? 'active' : '' }}" href="{{ $pageUrl(['view' => 'full']) }}">
                        <i class="bi bi-list-ul"></i> {{ translate('Full_menu') }}
                    </a>
                </li>
            @endif
        </ul>
        @if($view === 'full')
            @if($canSetPrices)<button type="button" class="btn btn-primary" data-menu-item-new><i class="bi bi-plus-lg"></i> {{ translate('Add_item') }}</button>@endif
        @endif
    </div>

    @if($view === 'today')
        <div class="mb-3">
            @if($kitchens->count() > 1)
                <div class="menu-kitchens mb-2" aria-label="{{ translate('Branch') }}">
                    @foreach($kitchens as $k)
                        <a href="{{ $pageUrl(['view' => 'today', 'kitchen' => $k->id]) }}" class="{{ $k->id === $kitchen->id ? 'active' : '' }}">
                            <i class="bi bi-shop"></i> {{ $k->branch_name ?: $k->brand()->name }}
                        </a>
                    @endforeach
                </div>
            @endif
            <div class="fw-bold fs-5">
                {{ $dayNames[Store::DAYS[$now->dayOfWeekIso - 1]] }} {{ $now->format('d/m') }}
                @if($hasBranches) · {{ $kitchen->branch_name ?: $kitchen->brand()->name }}@endif
            </div>
            <div class="text-muted fs-14">
                @if($hasBranches)
                    {{ translate('What customers can order from this branch today. Sold out today comes back by itself tomorrow. Not at this branch: customers see which other branch has it.') }}
                @else
                    {{ translate('What customers can order today. Set a dish to Sold out today when it runs out: it comes back by itself tomorrow.') }}
                @endif
            </div>
        </div>
    @elseif($hasBranches)
        <div class="alert alert-light border fs-14 mb-3">
            <i class="bi bi-diagram-3"></i> {{ translate('This menu is shared by all branches. Each branch marks its own dishes Sold out today or Not at this branch on its Today page.') }}
        </div>
        <form action="{{ $r('menu.category.save') }}" method="POST" class="d-flex gap-2 mb-3" style="max-width: 420px">
            @csrf
            <input type="text" name="name" class="form-control" required maxlength="80" placeholder="{{ translate('New_section,_e.g._Rice_dishes') }}">
            <button type="submit" class="btn btn-outline-primary text-nowrap">{{ translate('Add_section') }}</button>
        </form>
    @else
        <form action="{{ $r('menu.category.save') }}" method="POST" class="d-flex gap-2 mb-3" style="max-width: 420px">
            @csrf
            <input type="text" name="name" class="form-control" required maxlength="80" placeholder="{{ translate('New_section,_e.g._Rice_dishes') }}">
            <button type="submit" class="btn btn-outline-primary text-nowrap">{{ translate('Add_section') }}</button>
        </form>
    @endif

    @if($store->items->isEmpty())
        <div class="text-center text-muted py-5 border rounded bg-white mb-3">
            <i class="bi bi-egg-fried fs-1 d-block mb-2"></i>
            {{ translate('No items yet. Add your best sellers first: a clear photo, a short description and the price.') }}
        </div>
    @elseif($view === 'today' && $sections->isEmpty())
        <div class="text-center text-muted py-5 border rounded bg-white mb-3">
            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
            {{ translate('Nothing on the menu today.') }}
        </div>
    @endif

    @foreach($sections as $section)
        @php($category = $section['category'])
        <div class="card mb-3 {{ $category && !$category->is_active ? 'border-dashed' : '' }}">
            <div class="card-header d-flex flex-wrap align-items-center gap-2 py-2">
                @if($category && $view === 'full')
                    <form action="{{ $r('menu.category.save', ['category' => $category->id]) }}" method="POST" class="d-flex gap-2 align-items-center flex-grow-1">
                        @csrf
                        <input type="text" name="name" value="{{ $category->name }}" class="form-control form-control-sm fw-semibold border-0 bg-transparent px-0" style="max-width: 280px" maxlength="80" aria-label="{{ translate('Section_name') }}" onchange="this.form.submit()">
                    </form>
                    @if(!$category->is_active)<span class="badge bg-secondary">{{ translate('Hidden') }}</span>@endif
                    @if($label = $when($category->servedDayList(), $category->servedWindow()))
                        <span class="menu-when"><i class="bi bi-clock"></i> {{ $label }}</span>
                    @endif
                    <span class="badge bg-light text-dark">{{ $section['items']->count() }}</span>
                    <button type="button" class="btn btn-sm btn-light" title="{{ translate('When_it_is_served') }}" aria-label="{{ translate('When_it_is_served') }}: {{ $category->name }}"
                            data-menu-section-edit data-action="{{ $r('menu.category.save', ['category' => $category->id]) }}"
                            data-section="{{ json_encode(['name' => $category->name, 'days' => $category->servedDayList(), 'from' => $category->served_from, 'until' => $category->served_until]) }}">
                        <i class="bi bi-clock"></i>
                    </button>
                    <form action="{{ $r('menu.category.save', ['category' => $category->id]) }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="name" value="{{ $category->name }}">
                        <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                        <button type="submit" class="btn btn-sm btn-light" title="{{ $category->is_active ? translate('Hide_section') : translate('Show_section') }}">
                            <i class="bi {{ $category->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                        </button>
                    </form>
                    <form action="{{ $r('menu.category.delete', ['category' => $category->id]) }}" method="POST" class="m-0" onsubmit='return confirm(@json(translate("Remove this section? Its items stay, under Other.")))'>
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger" title="{{ translate('Remove_section') }}"><i class="bi bi-trash"></i></button>
                    </form>
                @else
                    <span class="fw-semibold flex-grow-1">{{ $category ? $category->name : ($store->categories->isEmpty() ? translate('Menu') : translate('Other')) }}</span>
                    @if($category && $category->servedWindow())
                        @php($label = implode('–', $category->servedWindow()))
                        <span class="menu-when"><i class="bi bi-clock"></i> {{ $label }}</span>
                    @endif
                @endif
            </div>
            <div class="list-group list-group-flush">
                @forelse($section['items'] as $item)
                    @php($state = $stateOf($item))
                    <div class="list-group-item menu-row d-flex align-items-center gap-3 py-3 {{ in_array($state, [StoreItem::AVAILABLE, 'on_menu'], true) ? '' : 'is-off' }}">
                        @if($item->imageUrl())
                            <img src="{{ $item->imageUrl() }}" width="56" height="56" class="rounded object-fit-cover flex-shrink-0" alt="">
                        @else
                            <span class="rounded bg-light d-inline-flex align-items-center justify-content-center flex-shrink-0 text-muted" style="width:56px;height:56px"><i class="bi bi-image fs-4"></i></span>
                        @endif
                        <div class="flex-grow-1" style="min-width: 0">
                            <div class="fw-semibold text-truncate">{{ $item->name }}</div>
                            @if($item->description)
                                <div class="fs-12 text-muted text-truncate">{{ $item->description }}</div>
                            @elseif($view === 'full')
                                <button type="button" class="btn btn-link p-0 fs-12 text-decoration-none" data-menu-add-desc="{{ $item->id }}">+ {{ translate('Add a description') }}</button>
                            @endif
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                <span class="fw-bold">{{ getCurrencyFormat($item->price) }}</span>
                                {{-- Today: only hours that differ from the section's, already shown above. --}}
                                @php($label = $view === 'today'
                                    ? ($item->effectiveWindow() && $item->effectiveWindow() !== $item->category?->servedWindow() ? implode('–', $item->effectiveWindow()) : '')
                                    : $when($item->servedDayList(), $item->servedWindow()))
                                @if($label)<span class="menu-when"><i class="bi bi-clock"></i> {{ $label }}</span>@endif
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                            <select class="form-select form-select-sm menu-state" data-state="{{ $state }}" data-kitchen="{{ $brandSwitch ? '' : $kitchen->id }}" data-menu-state="{{ $r('menu.item.availability', ['item' => $item->id]) }}" aria-label="{{ translate('Availability') }}: {{ $item->name }}">
                                @foreach($stateLabels as $value => $text)
                                    <option value="{{ $value }}" {{ $state === $value ? 'selected' : '' }}>{{ $text }}</option>
                                @endforeach
                            </select>
                            @if($view === 'today' && $canEditMenu)
                                <a class="btn btn-sm btn-light" href="{{ $pageUrl(['view' => 'full', 'edit' => $item->id]) }}" title="{{ translate('edit') }}" aria-label="{{ translate('edit') }} {{ $item->name }}"><i class="bi bi-pencil"></i></a>
                            @endif
                            @if($view === 'full')
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-light" title="{{ translate('edit') }}" aria-label="{{ translate('edit') }} {{ $item->name }}"
                                            data-menu-item-edit="{{ $item->id }}"
                                            data-action="{{ $r('menu.item.save', ['item' => $item->id]) }}"
                                            data-item="{{ json_encode(['name' => $item->name, 'description' => $item->description, 'price' => $item->price, 'store_menu_category_id' => $item->store_menu_category_id, 'days' => $item->servedDayList(), 'from' => $item->served_from, 'until' => $item->served_until]) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ $r('menu.item.delete', ['item' => $item->id]) }}" method="POST" class="m-0" onsubmit='return confirm(@json(translate("Remove this item from the menu?")))'>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light text-danger" title="{{ translate('delete') }}" aria-label="{{ translate('delete') }} {{ $item->name }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted fs-12 py-3">{{ translate('No items in this section yet.') }}</div>
                @endforelse
            </div>
        </div>
    @endforeach

    @if($view === 'today' && $notToday > 0)
        @if($canEditMenu)
            <a href="{{ $pageUrl(['view' => 'full']) }}" class="d-block text-center text-muted fs-14 py-2">
                {{ translate('Not_served_today') }}: {{ $notToday }} · {{ translate('See_the_full_menu') }}
            </a>
        @else
            <div class="text-center text-muted fs-14 py-2">{{ translate('Not_served_today') }}: {{ $notToday }}</div>
        @endif
    @endif
</div>

@php($dayPicker = function () use ($dayNames) {
    $html = '<div class="menu-days">';
    foreach ($dayNames as $key => $name) {
        $html .= '<label><input type="checkbox" name="served_days[]" value="' . $key . '" checked><span>' . e($name) . '</span></label>';
    }
    return $html . '</div>';
})

@if($view === 'full')
<div class="modal fade" id="menu-item-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data" data-new-action="{{ $r('menu.item.save') }}">
            @csrf
            <input type="hidden" name="schedule" value="1">
            <div class="modal-header">
                <h5 class="modal-title" data-modal-title data-new="{{ translate('Add_item') }}" data-edit="{{ translate('Edit_item') }}">{{ translate('Add_item') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ translate('Name') }} *</label>
                    <input type="text" name="name" class="form-control" required maxlength="120">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">{{ translate('Price_(FCFA)') }} *</label>
                        <input type="number" name="price" class="form-control" required min="1" step="1" @unless($canSetPrices) readonly title="{{ translate('Only the owner changes prices.') }}" @endunless>
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ translate('Section') }}</label>
                        <select name="store_menu_category_id" class="form-select">
                            <option value="">{{ translate('Other') }}</option>
                            @foreach($store->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ translate('Description') }}</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="500" placeholder="{{ translate('What is in it, portion size') }}"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ translate('Photo') }}</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="border rounded p-3">
                    <div class="fw-semibold mb-1">{{ translate('When_it_is_served') }}</div>
                    <div class="fs-12 text-muted mb-2">{{ translate('Customers only see it on these days. Leave the times empty to serve it all day.') }}</div>
                    {!! $dayPicker() !!}
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <input type="time" name="served_from" class="form-control form-control-sm" style="max-width: 130px" aria-label="{{ translate('From') }}">
                        <span class="text-muted">{{ translate('to') }}</span>
                        <input type="time" name="served_until" class="form-control form-control-sm" style="max-width: 130px" aria-label="{{ translate('Until') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="menu-section-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST">
            @csrf
            <input type="hidden" name="schedule" value="1">
            <input type="hidden" name="name">
            <div class="modal-header">
                <h5 class="modal-title" data-section-title></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="fw-semibold mb-1">{{ translate('When_this_section_is_served') }}</div>
                <div class="fs-12 text-muted mb-2">{{ translate('For example Breakfast, 07:00 to 11:00, or a dish of the day on Fridays. Leave the times empty to serve it all day.') }}</div>
                {!! $dayPicker() !!}
                <div class="d-flex align-items-center gap-2 mt-2">
                    <input type="time" name="served_from" class="form-control form-control-sm" style="max-width: 130px" aria-label="{{ translate('From') }}">
                    <span class="text-muted">{{ translate('to') }}</span>
                    <input type="time" name="served_until" class="form-control form-control-sm" style="max-width: 130px" aria-label="{{ translate('Until') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('script')
<script>
(function () {
    var token = @json(csrf_token());
    function setDays(form, days) {
        form.querySelectorAll('input[name="served_days[]"]').forEach(function (c) { c.checked = !days || days.indexOf(c.value) !== -1; });
    }

    var modalEl = document.getElementById('menu-item-modal');
    if (modalEl) {
        var form = modalEl.querySelector('form');
        var title = modalEl.querySelector('[data-modal-title]');
        var modal = new bootstrap.Modal(modalEl);
        var open = function (action, item) {
            form.reset();
            form.action = action;
            title.textContent = item ? title.dataset.edit : title.dataset.new;
            setDays(form, item ? item.days : null);
            if (item) {
                form.name.value = item.name || '';
                form.price.value = Math.round(item.price || 0);
                form.description.value = item.description || '';
                form.store_menu_category_id.value = item.store_menu_category_id || '';
                form.served_from.value = item.from || '';
                form.served_until.value = item.until || '';
            }
            modal.show();
        };
        // Staff do not add dishes (they come with a price): no button then.
        var addItem = document.querySelector('[data-menu-item-new]');
        if (addItem) addItem.addEventListener('click', function () { open(form.dataset.newAction, null); });
        // "+ Add a description" and ?edit=<dish> open that dish's form.
        document.querySelectorAll('[data-menu-add-desc]').forEach(function (b) {
            b.addEventListener('click', function () {
                const edit = document.querySelector('[data-menu-item-edit="' + b.dataset.menuAddDesc + '"]');
                if (edit) { edit.click(); setTimeout(function () { const d = document.querySelector('#menu-item-modal [name=description]'); if (d) d.focus(); }, 400); }
            });
        });
        setTimeout(function () {
            const want = new URLSearchParams(location.search).get('edit');
            const edit = want && document.querySelector('[data-menu-item-edit="' + want + '"]');
            if (edit) edit.click();
        }, 0);
        document.querySelectorAll('[data-menu-item-edit]').forEach(function (b) {
            b.addEventListener('click', function () { open(b.dataset.action, JSON.parse(b.dataset.item)); });
        });

        var sectionEl = document.getElementById('menu-section-modal');
        var sectionForm = sectionEl.querySelector('form');
        var sectionModal = new bootstrap.Modal(sectionEl);
        document.querySelectorAll('[data-menu-section-edit]').forEach(function (b) {
            b.addEventListener('click', function () {
                var s = JSON.parse(b.dataset.section);
                sectionForm.reset();
                sectionForm.action = b.dataset.action;
                sectionForm.name.value = s.name;
                sectionEl.querySelector('[data-section-title]').textContent = s.name;
                setDays(sectionForm, s.days);
                sectionForm.served_from.value = s.from || '';
                sectionForm.served_until.value = s.until || '';
                sectionModal.show();
            });
        });
    }

    // Available / Sold out today / Hidden, saved at once.
    document.querySelectorAll('[data-menu-state]').forEach(function (select) {
        select.addEventListener('change', function () {
            var before = select.dataset.state;
            select.disabled = true;
            fetch(select.dataset.menuState, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                body: JSON.stringify({state: select.value, kitchen: select.dataset.kitchen || null})
            }).then(function (r) { if (!r.ok) throw r; return r.json(); })
              .then(function (d) {
                  select.dataset.state = d.state;
                  select.value = d.state;
                  select.closest('.menu-row').classList.toggle('is-off', d.state !== 'available' && d.state !== 'on_menu');
              })
              .catch(function () { select.value = before; alert(@json(translate('Could not save. Check your connection and try again.'))); })
              .finally(function () { select.disabled = false; });
        });
    });
})();
</script>
@endpush
