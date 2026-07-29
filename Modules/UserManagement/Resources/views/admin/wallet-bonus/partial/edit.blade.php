<form id="edit-wallet-bonus-form" data-route="{{ route('admin.wallet-bonus.update', $walletBonus->id) }}">
    @csrf
    <div class="row g-lg-4 g-3">
        @include('usermanagement::admin.wallet-bonus.partial._form', ['walletBonus' => $walletBonus])
        <div class="col-12 d-flex justify-content-end align-items-end gap-2 mt-3">
            <button type="reset" class="btn btn-secondary"
                    tabindex="9">{{ translate('Reset') }}</button>
            <button type="submit" class="btn btn-primary"
                    tabindex="10">{{ translate('Submit') }}</button>
        </div>
    </div>
</form>

