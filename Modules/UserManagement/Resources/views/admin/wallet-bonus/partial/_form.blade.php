@php
    $decimalPoint = (int)businessConfig('currency_decimal_point', BUSINESS_INFORMATION)?->value ?? 2;
@endphp
<div class="col-md-6 col-lg-4 d-flex flex-column">
    <div class="">
        <label for="bonus_title" class="mb-2">{{ translate('bonus_title') }} <span
                class="text-danger">*</span>
        </label>
        <div class="character-count">
            <input type="text" id="bonus_title" value="{{old('bonus_title', isset($walletBonus) ? $walletBonus->name : '')}}"
                   name="bonus_title" maxlength="80" data-max-character="80"
                   class="form-control character-count-field"
                   placeholder="{{ translate('Ex: Eid Dhamaka') }}"
                   required tabindex="1">
            <span>{{translate('0/80')}}</span>
        </div>
    </div>
</div>
<div class="col-md-6 col-lg-4 d-flex flex-column">
    <div class="">
        <label for="short_desc" class="mb-2">{{ translate('short_description') }} <span
                class="text-danger">*</span>
        </label>
        <div class="character-count">
            <input id="short_desc" name="short_desc"
                   class="form-control character-count-field"
                   maxlength="255" data-max-character="255"
                   placeholder="{{ translate('type_here') }}..."
                   value="{{old('short_desc', isset($walletBonus) ? $walletBonus->description : '')}}" tabindex="2" required/>
            <span>{{translate('0/255')}}</span>
        </div>
    </div>
</div>
<div class="col-md-6 col-lg-4 d-flex flex-column">
    <div class="">
        <label for="bonus_amount" class="mb-2"><span
                id="bonus_amount_label">{{ translate('bonus_amount') }}</span> <span
                class="text-danger">*</span></label>
        <div class="input-group input--group wallet-bonus-amount">
            <input type="text" id="bonus_amount"
                   value="{{old('bonus_amount', isset($walletBonus) ? $walletBonus->bonus_amount : '')}}"
                   name="bonus_amount" class="form-control"
                   placeholder="{{ translate('Ex: 5') }}" step="0.01" required tabindex="3"
                   data-decimal="{{ $decimalPoint }}"
            >
            <select class="form-select border-start-0 cmn_focus currency-type-select"
                    id="amount_type" name="amount_type" required tabindex="4">
                <option value="amount"
                    @selected(old('amount_type', isset($walletBonus) ? $walletBonus->amount_type : 'amount') === 'amount')>
                    {{ session()->get('currency_symbol') ?? '$' }}
                </option>
                <option value="percentage"
                    @selected(old('amount_type', isset($walletBonus) ? $walletBonus->amount_type : '') === 'percentage')>
                    %
                </option>
            </select>
        </div>
    </div>
</div>
<div class="col-md-6 col-lg-4">
    <div class="">
        <label for="minimum_add_amount"
               class="mb-2">{{ translate('minimum_add_amount') }}
            ({{session()->get('currency_symbol') ?? '$'}}) <span
                class="text-danger">*</span>
        </label>
        <input type="text" id="minimum_add_amount" name="minimum_add_amount"
               class="form-control"
               placeholder="{{ translate('Ex: 100') }}" step="0.01"
               value="{{old('minimum_add_amount', isset($walletBonus) ? $walletBonus->min_add_amount : '')}}" required tabindex="5"
               data-decimal="{{ $decimalPoint }}"
        >
    </div>
</div>
<div class="col-md-6 col-lg-4">
    <div class="">
        <label for="maximum_bonus"
               class="mb-2">{{ translate('maximum_bonus') }}
            ({{session()->get('currency_symbol') ?? '$'}})
        </label>
        <input type="text" id="maximum_bonus" name="maximum_bonus"
               class="form-control"
               placeholder="{{ translate('Ex: 5000') }}" value="{{old('maximum_bonus', isset($walletBonus) ? $walletBonus->max_bonus_amount : '')}}"
               step="0.01" tabindex="6"
               data-decimal="{{ $decimalPoint }}"
        >
    </div>
</div>
<div class="col-md-6 col-lg-4">
    <div class="">
        <label for="start_date"
               class="mb-2">{{ translate('start_date') }} <span class="text-danger">*</span></label>
        <input type="date" value="{{ old('start_date', isset($walletBonus) ? $walletBonus?->start_date->format('Y-m-d') : 'dd/mm/yyyy') }}" id="start_date"
               min="{{date('Y-m-d',strtotime(now()))}}"
               name="start_date" class="form-control" required tabindex="7">
    </div>
</div>
<div class="col-md-6 col-lg-4">
    <div class="">
        <label for="end_date" class="mb-2">{{ translate('end_date') }} <span
                class="text-danger">*</span></label>
        <input type="date" id="end_date" value="{{ old('end_date', isset($walletBonus) ? $walletBonus?->end_date->format('Y-m-d') : 'dd/mm/yyyy') }}" name="end_date"
               min="{{date('Y-m-d',strtotime(now()))}}"
               class="form-control" required tabindex="8">
    </div>
</div>
