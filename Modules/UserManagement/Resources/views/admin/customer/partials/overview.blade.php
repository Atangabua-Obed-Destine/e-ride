@php use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;use Modules\UserManagement\Lib\AdditionalDataForm; @endphp
<div class="tab-pane fade active show" id="overview-pane" role="tabpanel">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="d-flex align-items-center gap-2 text-dark">
                        <i class="bi bi-person-fill-gear text-primary"></i>
                        {{translate('customer_details')}}
                    </h5>

                    <div class=" my-4">
                        <ul class="nav nav--tabs justify-content-start gap-3 bg-white" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link border active" data-bs-toggle="tab"
                                        data-bs-target="#trip-tab-pane" aria-selected="true"
                                        role="tab">{{translate('trip')}}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link border" data-bs-toggle="tab"
                                        data-bs-target="#duty_review-tab-pane" aria-selected="false"
                                        role="tab"
                                        tabindex="-1">{{translate('duty_&_review')}}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link border" data-bs-toggle="tab"
                                        data-bs-target="#wallet-tab-pane" aria-selected="false"
                                        role="tab"
                                        tabindex="-1">{{translate('wallet')}}</button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="trip-tab-pane" role="tabpanel">
                            <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div
                                            class="text-capitalize">{{translate('total_completed_trip')}}</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{$commonData['total_success_request']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div
                                            class="text-capitalize">{{translate('total_cancel_trip')}}</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{$commonData['total_cancel_request']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div
                                            class="text-capitalize">{{translate('lowest_price_trip')}}</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{getCurrencyFormat($commonData['customer_lowest_fare']??0)}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div
                                            class="text-capitalize">{{translate('highest_price_trip')}}</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{getCurrencyFormat($commonData['customer_highest_fare'] ?? 0)}}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="duty_review-tab-pane" role="tabpanel">
                            <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div
                                            class="text-capitalize">{{translate('total_review_given')}}</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{$commonData['customer_total_review_count']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div
                                            class="text-capitalize">{{translate('total_review_received')}}</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{$commonData['customer_total_received_review_count']}}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="wallet-tab-pane" role="tabpanel">
                            <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">Total Level Point <span
                                                class="text-muted">( {{$commonData['customer']?->level?->name}} - {{$otherData['targeted_review_point'] + $otherData['targeted_cancel_point'] + $otherData['targeted_amount_point'] + $otherData['targeted_ride_point']}}/{{$otherData['customer_level_point_goal'] ?? 0}} )</span>
                                        </div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{$otherData['targeted_review_point'] + $otherData['targeted_cancel_point'] + $otherData['targeted_amount_point'] + $otherData['targeted_ride_point']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">Wallet Money</div>
                                        <span
                                            class="badge bg-info-5 fs-14 text-dark">{{getCurrencyFormat($commonData['customer']->userAccount()->value('wallet_balance') ?? 0)}}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="text-capitalize text-dark mb-3 d-flex align-items-center gap-2"><i
                            class="bi bi-paperclip text-primary"></i> {{translate('attached_documents')}}
                    </h5>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        @forelse($customer->other_documents ?? [] as $doc)
                            <div class="mb-2">
                                <a href="{{ dynamicStorage('storage/app/public/customer/document/') }}/{{ $doc }}"
                                   download="{{ $doc }}"
                                   class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="w-30px aspect-1"
                                             src="{{ getExtensionIcon($doc) }}"
                                             alt="">
                                        <h6 class="fs-12">{{ $doc }}</h6>
                                    </div>
                                    <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                </a>
                            </div>
                        @empty
                            <p class="text-capitalize">{{translate('no_documents_found')}}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @php
            $additionalConfig = AdditionalDataForm::fields(CUSTOMER);
            $additionalData = $customer->additionalInfo?->additional_data ?? [];
            $textFields = [];
            $fileFields = [];
            foreach ($additionalConfig as $configField) {
                $title = $configField['title'] ?? null;
                if (!is_string($title) || trim($title) === '') {
                    continue;
                }
                if (!array_key_exists($title, $additionalData)) {
                    continue;
                }
                if (($configField['type'] ?? null) === 'file') {
                    $fileFields[$title] = $additionalData[$title];
                } else {
                    $value = $additionalData[$title];
                    if (!is_null($value) && !(is_string($value) && trim($value) === '') && !(is_array($value) && empty($value))) {
                        $textFields[$title] = [
                            'type' => $configField['type'] ?? null,
                            'value' => $value,
                            ];
                    }
                }
            }
            $additionalFileBaseUrl = dynamicStorage('storage/app/public/user/additional-data');
        @endphp
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="d-flex align-items-center gap-2 text-dark mb-20">
                        <i class="bi bi-plus-circle-fill text-primary"></i>
                        {{translate('Additional Info')}}
                    </h5>
                    <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                        @forelse($textFields as $title => $field)
                            @php
                                $value = $field['value'] ?? null;
                                $isEmailField = ($field['type'] ?? null) === 'email';
                                if ($isEmailField) {
                                    $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;
                                } elseif (is_array($value)) {
                                    $displayValue = ucwords(AdditionalDataFieldNormalizer::humanizeFieldKey(implode(', ', $value)));
                                } else {
                                    $displayValue = ucwords(AdditionalDataFieldNormalizer::humanizeFieldKey((string) $value));
                                }
                                $isLongValue = mb_strlen($displayValue) > 40;
                                $shortValue = $isLongValue ? mb_substr($displayValue, 0, 40) . '...' : $displayValue;
                            @endphp
                            <li>
                                <div class="d-flex gap-3 justify-content-between additional-info-row">
                                    <div
                                        class="text-capitalize additional-info-title">{{ AdditionalDataFieldNormalizer::humanizeFieldKey($title) }}</div>
                                    <div class="additional-info-value-wrap">
                                        <span class="badge bg-info-5 fs-14 text-dark text-wrap additional-info-value {{ $isLongValue ? 'additional-info-value-justify' : 'text-end' }}">
                                            <span class="js-additional-info-short">{{ $shortValue }}</span>
                                            @if($isLongValue)
                                                <span class="js-additional-info-full d-none">{{ $displayValue }}</span>
                                                <button type="button"
                                                        class="additional-info-toggle js-additional-info-toggle"
                                                        aria-expanded="false"
                                                        data-see-more="{{ translate('See More') }}"
                                                        data-see-less="{{ translate('See Less') }}">
                                                    {{ translate('See More') }}
                                                </button>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li>
                                <p class="text-capitalize mb-0">{{ translate('no_additional_info_found') }}</p>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="text-capitalize text-dark mb-20 d-flex align-items-center gap-2"><i
                            class="bi bi-paperclip text-primary"></i> {{translate('Additional Documents')}}
                    </h5>
                    @if(!empty($fileFields))
                        @foreach($fileFields as $title => $files)
                            <div class="mb-3">
                                <h6 class="fs-14 text-capitalize mb-2">{{ AdditionalDataFieldNormalizer::humanizeFieldKey($title) }}</h6>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    @forelse((array) $files as $doc)
                                        <div class="mb-0">
                                            <a href="{{ $additionalFileBaseUrl }}/{{ $doc }}"
                                               download="{{ $doc }}"
                                               target="_blank"
                                               class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img class="w-30px aspect-1"
                                                         src="{{ getExtensionIcon($doc) }}"
                                                         alt="">
                                                    <h6 class="fs-12">{{ $doc }}</h6>
                                                </div>
                                                <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                            </a>
                                        </div>
                                    @empty
                                        <p class="text-capitalize">{{ translate('no_documents_found') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-capitalize mb-0">{{ translate('no_documents_found') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
