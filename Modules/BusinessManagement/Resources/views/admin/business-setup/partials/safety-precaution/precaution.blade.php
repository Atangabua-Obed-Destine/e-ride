<div class="tab-pane fade {{Request::is('admin/business/setup/safety-precaution/precaution') ? 'show active' : ''}}">
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-2">{{ translate('Safety Precautions') }}</h6>
            <p class="fs-12 mb-0">{{ translate('Here you can set the title & description which will displayed in the precaution section as informative content  for the users') }}
                .</p>
        </div>
        <div class="card-body">
            <form action="{{route('admin.business.setup.safety-precaution.precaution.store')}}" method="POST">
                @csrf
                <div class="row align-items-center g-3">
                    <div class="col-md-12">
                        <div class="p-4 rounded bg-F6F6F6">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="">
                                        <label class="form-label">
                                            {{ translate('title') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="character-count">
                                                        <textarea name="title" id="safety_title" tabindex="1"
                                                                  class="form-control character-count-field"
                                                                  rows="1"
                                                                  placeholder="{{ translate('Type here safety precaution title') }}"
                                                                  maxlength="80" data-max-character="80"
                                                                  required></textarea>
                                            <span
                                                class="d-flex justify-content-end mt-1 text-muted">{{ translate('0/80') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="">
                                        <label class="form-label">
                                            {{ translate('Description') }}
                                            <span class="text-danger">*</span>
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('description.') }}"></i>
                                        </label>
                                        <div class="character-count">
                                                        <textarea name="description" id="safety_Description"
                                                                  tabindex="2"
                                                                  class="form-control character-count-field" rows="1"
                                                                  placeholder="{{ translate('Description') }}"
                                                                  maxlength="250" data-max-character="250"
                                                                  required></textarea>
                                            <span
                                                class="d-flex justify-content-end mt-1 text-muted">{{ translate('0/250') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="">
                                        <label class="form-label">
                                            {{ translate('User type') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="for_whom[]" class="bg-white js-select-multiple multiple-select2 js-select-2" id=""  multiple="multiple" data-placeholder="{{ translate('Select user type') }}" required>
                                            <option value="{{ CUSTOMER }}">{{ translate('For Customer') }}</option>
                                            <option value="{{ DRIVER }}">{{ translate('For Driver') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4 gap-3">
                    <button type="reset" class="btn text-uppercase btn-secondary">
                        {{ translate('Reset') }}
                    </button>
                    <button type="submit"
                            class="btn btn-primary text-uppercase cmn_focus"
                            tabindex="5">{{ translate('submit') }}</button>
                </div>
            </form>
            <div class="border-top pt-4 mt-4">
                <div class="card-body p-0">
                    <h3 class="mb-4">{{ translate('Precautions List') }}</h3>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th class="sl">{{ translate('SL') }}</th>
                                <th class="text-capitalize">{{ translate('For Whom') }}</th>
                                <th class="text-capitalize">{{ translate('Title') }}</th>
                                <th class="text-capitalize">{{ translate('Description') }}</th>
                                <th class="text-capitalize">{{ translate('Status') }}</th>
                                <th class="text-center action">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($safetyPrecautions as $key => $safetyPrecaution)
                                <tr>
                                    <td class="sl">{{ $key + $safetyPrecautions->firstItem() }}</td>
                                    <td>
                                            <span
                                                class="text-capitalize min-w30">{{ is_array($safetyPrecaution->for_whom) ? implode(', ', $safetyPrecaution->for_whom) : $safetyPrecaution->for_whom }}</span>
                                    </td>
                                    <td>
                                        <div class="max-i-s-30rem line--limit-2 targetToolTip"
                                             data-reason="{{ $safetyPrecaution->title }}"
                                             data-bs-custom-class="des-tooltip"
                                             data-bs-html="true"
                                             data-bs-placement="bottom"
                                        >
                                            {{ $safetyPrecaution->title }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="max-i-s-30rem line--limit-2 targetToolTip"
                                             data-reason="{{ $safetyPrecaution->description }}"
                                             data-bs-custom-class="des-tooltip"
                                             data-bs-html="true"
                                             data-bs-placement="bottom"
                                        >
                                            {{ $safetyPrecaution->description }}
                                        </div>
                                    </td>
                                    <td>
                                        <label class="switcher">
                                            <input class="switcher_input custom_status_change"
                                                   type="checkbox"
                                                   id="{{ $safetyPrecaution->id }}"
                                                   data-url="{{ route('admin.business.setup.safety-precaution.precaution.status') }}"
                                                   data-title="{{$safetyPrecaution->is_active == 1 ? translate('Are you sure to turn off this Safety Precaution') : translate('Are you sure to turn On this Safety Precaution')}}?"
                                                   data-sub-title="{{$safetyPrecaution->is_active == 1 ? translate('Once you turn off this Safety Precaution') . ', ' .translate('drivers will no longer see this Safety Precaution.') : translate('Once you turn On this Safety Precaution') . ', ' . translate('drivers will see this Safety Precaution.')}}"
                                                   data-confirm-btn="{{$safetyPrecaution->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                                {{ $safetyPrecaution->is_active == 1 ? "checked": ""  }}
                                            >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div
                                            class="d-flex justify-content-center gap-2 align-items-center">
                                            <button
                                                class="btn btn-outline-primary btn-action editSafetyPrecautionData"
                                                data-id="{{$safetyPrecaution->id}}">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button data-url="{{ route('admin.business.setup.safety-precaution.precaution.delete', ['id' => $safetyPrecaution?->id]) }}"
                                                    data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                    data-title="{{ translate('Are you sure to delete this Safety Precaution') }}?"
                                                    data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                    data-confirm-btn="{{ translate('Yes, Delete') }}"
                                                    data-cancel-btn="{{ translate('Not Now') }}"
                                                    type="button"
                                                    class="btn btn-outline-danger btn-action delete-button">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div
                                            class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                            <img
                                                src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                alt="" width="100">
                                            <p class="text-center">{{translate('no_data_available')}}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="table-bottom d-flex flex-column flex-sm-row justify-content-sm-between align-items-center gap-2">
                        <p class="mb-0"></p>

                        <div
                            class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-end gap-3 gap-sm-4">
                            <nav>
                                {!! $safetyPrecautions->links() !!}
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editSafetyPrecautionModal">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
        </div>
    </div>
</div>
