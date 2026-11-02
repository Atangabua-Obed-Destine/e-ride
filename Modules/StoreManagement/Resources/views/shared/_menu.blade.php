{{--
    Menu editor shared by the admin panel and the partner portal.
    Needs: $store (with categories, items), $routePrefix ('admin.store.' or 'partner.'),
    $routeBase (route params, e.g. ['store' => $store] or []).
--}}
@php
    $r = fn(string $name, array $params = []) => route($routePrefix . $name, array_merge($routeBase, $params));
    $sections = $store->categories->map(fn($c) => ['category' => $c, 'items' => $store->items->where('store_menu_category_id', $c->id)]);
    $loose = $store->items->whereNull('store_menu_category_id');
    $known = $store->categories->pluck('id')->all();
    $loose = $loose->merge($store->items->filter(fn($i) => $i->store_menu_category_id && !in_array($i->store_menu_category_id, $known)));
    if ($loose->isNotEmpty() || $store->categories->isEmpty()) {
        $sections->push(['category' => null, 'items' => $loose]);
    }
@endphp

<div class="store-menu">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <form action="{{ $r('menu.category.save') }}" method="POST" class="d-flex gap-2 flex-grow-1" style="max-width: 420px">
            @csrf
            <input type="text" name="name" class="form-control" required maxlength="80" placeholder="{{ translate('New_section,_e.g._Rice_dishes') }}">
            <button type="submit" class="btn btn-outline-primary text-nowrap">{{ translate('Add_section') }}</button>
        </form>
        <button type="button" class="btn btn-primary" data-menu-item-new><i class="bi bi-plus-lg"></i> {{ translate('Add_item') }}</button>
    </div>

    @if($store->items->isEmpty())
        <div class="text-center text-muted py-5 border rounded bg-white mb-3">
            <i class="bi bi-egg-fried fs-1 d-block mb-2"></i>
            {{ translate('No items yet. Add your best sellers first: a clear photo, a short description and the price.') }}
        </div>
    @endif

    @foreach($sections as $section)
        @php($category = $section['category'])
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 py-2">
                @if($category)
                    <form action="{{ $r('menu.category.save', ['category' => $category->id]) }}" method="POST" class="d-flex gap-2 align-items-center flex-grow-1">
                        @csrf
                        <input type="text" name="name" value="{{ $category->name }}" class="form-control form-control-sm fw-semibold border-0 bg-transparent px-0" style="max-width: 280px" maxlength="80" aria-label="{{ translate('Section_name') }}" onchange="this.form.submit()">
                    </form>
                    <span class="badge bg-light text-dark">{{ $section['items']->count() }}</span>
                    <form action="{{ $r('menu.category.delete', ['category' => $category->id]) }}" method="POST" onsubmit='return confirm(@json(translate("Remove this section? Its items stay, under Other.")))'>
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger" title="{{ translate('Remove_section') }}"><i class="bi bi-trash"></i></button>
                    </form>
                @else
                    <span class="fw-semibold">{{ $store->categories->isEmpty() ? translate('Menu') : translate('Other') }}</span>
                @endif
            </div>
            <div class="list-group list-group-flush">
                @forelse($section['items'] as $item)
                    <div class="list-group-item d-flex align-items-center gap-3 py-3 {{ $item->is_available ? '' : 'opacity-75' }}">
                        @if($item->imageUrl())
                            <img src="{{ $item->imageUrl() }}" width="56" height="56" class="rounded object-fit-cover flex-shrink-0" alt="">
                        @else
                            <span class="rounded bg-light d-inline-flex align-items-center justify-content-center flex-shrink-0 text-muted" style="width:56px;height:56px"><i class="bi bi-image fs-4"></i></span>
                        @endif
                        <div class="flex-grow-1" style="min-width: 0">
                            <div class="fw-semibold text-truncate">{{ $item->name }}</div>
                            @if($item->description)<div class="fs-12 text-muted text-truncate">{{ $item->description }}</div>@endif
                            <div class="fw-bold mt-1">{{ getCurrencyFormat($item->price) }}</div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                            <label class="d-flex align-items-center gap-2 fs-12 text-muted mb-0" title="{{ translate('Available') }}">
                                <span data-availability-label>{{ $item->is_available ? translate('Available') : translate('Sold_out') }}</span>
                                <span class="switcher">
                                    <input class="switcher_input" type="checkbox" data-menu-availability="{{ $r('menu.item.availability', ['item' => $item->id]) }}" {{ $item->is_available ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </span>
                            </label>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light" title="{{ translate('edit') }}" aria-label="{{ translate('edit') }} {{ $item->name }}"
                                        data-menu-item-edit
                                        data-action="{{ $r('menu.item.save', ['item' => $item->id]) }}"
                                        data-item="{{ json_encode(['name' => $item->name, 'description' => $item->description, 'price' => $item->price, 'store_menu_category_id' => $item->store_menu_category_id, 'is_available' => $item->is_available]) }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ $r('menu.item.delete', ['item' => $item->id]) }}" method="POST" class="m-0" onsubmit='return confirm(@json(translate("Remove this item from the menu?")))'>
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="{{ translate('delete') }}" aria-label="{{ translate('delete') }} {{ $item->name }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted fs-12 py-3">{{ translate('No items in this section yet.') }}</div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>

<div class="modal fade" id="menu-item-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data" data-new-action="{{ $r('menu.item.save') }}">
            @csrf
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
                        <input type="number" name="price" class="form-control" required min="1" step="1">
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
                <label class="d-flex align-items-center gap-2">
                    <input type="hidden" name="is_available" value="0">
                    <input type="checkbox" name="is_available" value="1" checked>
                    {{ translate('Available_now') }}
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@push('script')
<script>
(function () {
    var modalEl = document.getElementById('menu-item-modal');
    var form = modalEl.querySelector('form');
    var title = modalEl.querySelector('[data-modal-title]');
    var modal = new bootstrap.Modal(modalEl);
    function open(action, item) {
        form.reset();
        form.action = action;
        title.textContent = item ? title.dataset.edit : title.dataset.new;
        if (item) {
            form.name.value = item.name || '';
            form.price.value = Math.round(item.price || 0);
            form.description.value = item.description || '';
            form.store_menu_category_id.value = item.store_menu_category_id || '';
            form.querySelector('input[type=checkbox][name=is_available]').checked = !!item.is_available;
        }
        modal.show();
    }
    document.querySelector('[data-menu-item-new]').addEventListener('click', function () { open(form.dataset.newAction, null); });
    document.querySelectorAll('[data-menu-item-edit]').forEach(function (b) {
        b.addEventListener('click', function () { open(b.dataset.action, JSON.parse(b.dataset.item)); });
    });
    var token = form.querySelector('input[name=_token]').value;
    document.querySelectorAll('[data-menu-availability]').forEach(function (input) {
        input.addEventListener('change', function () {
            var label = input.closest('label').querySelector('[data-availability-label]');
            fetch(input.dataset.menuAvailability, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                body: JSON.stringify({is_available: input.checked ? 1 : 0})
            }).then(function (r) { if (!r.ok) throw r; return r.json(); })
              .then(function (d) {
                  label.textContent = d.is_available ? @json(translate('Available')) : @json(translate('Sold_out'));
                  input.closest('.list-group-item').classList.toggle('opacity-75', !d.is_available);
              })
              .catch(function () { input.checked = !input.checked; alert(@json(translate('Could not save. Check your connection and try again.'))); });
        });
    });
})();
</script>
@endpush
