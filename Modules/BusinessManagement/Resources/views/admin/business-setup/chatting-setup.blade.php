@extends('adminmodule::layouts.master')

@section('title', translate('Chatting Setup'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('Business_Setup') }}</h2>

            <div class="col-12 mb-3">
                <div class="">
                    @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                </div>
            </div>

            @include('businessmanagement::admin.business-setup.partials._chatting-setup-inline')

            <div class="tab-content">
                <div
                    class="tab-pane fade {{Request::is('admin/business/setup/chatting-setup/driver') ? 'show active' : ''}}"
                    id="driver">
                    <div class="card">
                        <div class="collapsible-card-body">
                            <div class="card-header flex-md-nowrap flex-wrap d-flex align-items-center justify-content-between gap-2">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2 fs-16 text-capitalize">{{ translate('Predefined Q & A') }}</h5>
                                    <div class="fs-14">
                                        {{ translate('Driver will see some pre-defined messages with answer in the chatting pages') }}
                                    </div>
                                </div>
                                <div class="card-head-group d-flex align-items-center gap-2">
                                    <div class="view-btn cursor-pointer text-link fs-12 fw-semibold d-flex align-items-center gap-0">
                                        View 
                                        <i class="tio-arrow-upward"></i>
                                    </div>
                                    <label class="switcher cmn_focus rounded-pill">
                                        <input class="switcher_input collapsible-card-switcher update-business-setting"
                                               id="driverQuestionAnswerStatus" type="checkbox" tabindex="2"
                                               name="driver_question_answer_status"
                                               data-name="driver_question_answer_status" data-type="{{ CHATTING_SETTINGS }}"
                                               data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                               data-icon="{{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/question-answer-off.png') : dynamicAsset('public/assets/admin-module/img/question-answer-on.png') }}"
                                               data-title="{{ translate('Are you sure') }}?"
                                               data-sub-title="{{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? translate('Do you want to turn OFF predefined Q & A for the driver') . '? ' . translate('When it’s off the Driver will not be able to see any predefined Q & A.') : translate('Do you want to turn ON predefined Q & A for the driver') . '? ' . translate('When it’s on the Driver will be able to see any predefined Q & A.') }}"
                                               data-confirm-btn="{{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                            {{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body collapsible-card-content">
                                <div class="row align-items-center g-3 mb-20">
                                    <!-- <div class="col-md-4">
                                        <h5 class="mb-2">{{ translate('Setup Question & Answer') }}</h5>
                                        <div class="fs-12">
                                            {{ translate('Here you can set Predefine question that driver when send message  ') }}
                                        </div>
                                    </div> -->
                                    <div class="col-md-12">
                                        <form
                                            action="{{ route('admin.business.setup.chatting-setup.question-answer.store') }}"
                                            method="post">
                                            @csrf
                                            <div class="col-md-12">
                                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="mb-0">
                                                                <label for=""
                                                                    class="form-label fs-14">{{ translate('Question') }}
                                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                    data-bs-toggle="tooltip"
                                                                    data-bs-title="{{ translate('You can ask Question here') }}"></i>
                                                                </label>
                                                                <div class="character-count">
                                                                    <textarea id="question" name="question" tabindex="3"
                                                                            class="form-control character-count-field"
                                                                            cols="30" rows="1"
                                                                            placeholder="{{ translate('Ex: How to cancel a trip during ongoing trip?') }}"
                                                                            maxlength="150"
                                                                            data-max-character="150" required></textarea>
                                                                    <span
                                                                        class="d-flex justify-content-end mt-1 text-muted">{{ translate('0/150') }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-0">
                                                                <label for="" class="form-label fs-14">{{ translate('Answer') }}
                                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                    data-bs-toggle="tooltip"
                                                                    data-bs-title="{{ translate('Type answer here') }}"></i>
                                                                </label>
                                                                <div class="character-count">
                                                                    <textarea id="answer" name="answer" rows="1" tabindex="4"
                                                                            class="form-control character-count-field"
                                                                            cols="30" rows="2"
                                                                            placeholder="{{ translate('Type answer here') }}"
                                                                            maxlength="250" data-max-character="250"
                                                                            required></textarea>
                                                                    <span
                                                                        class="d-flex justify-content-end mt-1 text-muted">{{ translate('0/250') }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-3 justify-content-end mt-4">
                                                    <button class="btn min-w--120 btn-light h-40px justify-content-center fw-semibold cmn_focus"
                                                        type="reset" tabindex="5">{{ translate('Reset') }}</button>
                                                    <button
                                                        class="btn min-w--120 btn-primary h-40px justify-content-center fw-semibold cmn_focus" tabindex="6">{{ translate('Submit') }}</button>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <div class="border-top pt-4 mt-4">
                                    <h5 class="text-capitalize mb-20"> {{ translate('Question & Answer List') }}
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-borderless align-middle mb-0">
                                            <thead class="table-light align-middle">
                                            <tr>
                                                <th>{{ translate('SL') }}</th>
                                                <th>
                                                    {{ translate('Question') }}
                                                </th>
                                                <th>
                                                    {{ translate('Answer') }}
                                                </th>
                                                <th>{{ translate('status') }}</th>
                                                <th class="text-center">{{ translate('action') }}</th>
                                            </tr>
                                            </thead>
                                            <tbody>

                                            @forelse($redefinedQAs as $key => $redefinedQA)
                                                <tr>
                                                    <td class="sl">{{ $key + $redefinedQAs->firstItem() }}</td>
                                                    <td>
                                                        <div class="min-w300 max-w300 line--limit-2"
                                                             data-bs-custom-class="des-tooltip" data-bs-toggle="tooltip"
                                                             data-bs-html="true" data-bs-placement="bottom"
                                                             data-bs-title="{{ $redefinedQA->question }}">
                                                            {{ $redefinedQA->question }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="min-w300 max-w-450 line--limit-2"
                                                             data-bs-custom-class="des-tooltip" data-bs-toggle="tooltip"
                                                             data-bs-html="true" data-bs-placement="bottom"
                                                             data-bs-title="{{ $redefinedQA->answer}}">
                                                            {{ $redefinedQA->answer }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="switcher">
                                                            <input class="switcher_input custom_status_change"
                                                                   type="checkbox"
                                                                   id="{{ $redefinedQA->id }}"
                                                                   data-url="{{ route('admin.business.setup.chatting-setup.question-answer.status') }}"
                                                                   data-title="{{$redefinedQA->is_active == 1 ? translate('Are you sure to turn off this Q & A') : translate('Are you sure to turn On this Q & A')}}?"
                                                                   data-sub-title="{{$redefinedQA->is_active == 1 ? translate('Once you turn off this Q & A') . ', ' .translate('drivers will no longer see this Q & A.') : translate('Once you turn On this Q & A') . ', ' . translate('drivers will see this Q & A.')}}"
                                                                   data-confirm-btn="{{$redefinedQA->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                                                {{ $redefinedQA->is_active == 1 ? "checked": ""  }}
                                                            >
                                                            <span class="switcher_control"></span>
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <div
                                                            class="d-flex justify-content-center gap-2 align-items-center">
                                                            <button class="btn btn-outline-primary btn-action editData"
                                                                    data-id="{{$redefinedQA->id}}">
                                                                <i class="bi bi-pencil-fill"></i>
                                                            </button>
                                                            <button data-url="{{ route('admin.business.setup.chatting-setup.question-answer.delete', ['id' => $redefinedQA?->id]) }}"
                                                                    data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                                    data-title="{{ translate('Are you sure to delete this Q&A') }}?"
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
                                                 {!! $redefinedQAs->links() !!}
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="tab-pane fade {{Request::is('admin/business/setup/chatting-setup/support') ? 'show active' : ''}}"
                    id="support">
                    <div class="card collapsible-card-body">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <div class="w-0 flex-grow-1">
                                <h5 class="mb-2 fs-16 text-capitalize">{{ translate('Support Saved Replies') }}</h5>
                                <div class="fs-14">
                                    {{ translate('Here admin can save some predefined replies for common questions asked by driver to reply in chat') }}
                                </div>
                            </div>
                            <div class="card-head-group d-flex align-items-center gap-2">
                                <div class="view-btn cursor-pointer text-link fs-12 fw-semibold d-flex align-items-center gap-0">
                                    View 
                                    <i class="tio-arrow-upward"></i>
                                </div>
                                <label class="switcher cmn_focus rounded-pill">
                                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                                            id="driverQuestionAnswerStatus" type="checkbox" tabindex="2"
                                            name="driver_question_answer_status"
                                            data-name="driver_question_answer_status" data-type="{{ CHATTING_SETTINGS }}"
                                            data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                            data-icon="{{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/question-answer-off.png') : dynamicAsset('public/assets/admin-module/img/question-answer-on.png') }}"
                                            data-title="{{ translate('Are you sure') }}?"
                                            data-sub-title="{{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? translate('Do you want to turn OFF predefined Q & A for the driver') . '? ' . translate('When it’s off the Driver will not be able to see any predefined Q & A.') : translate('Do you want to turn ON predefined Q & A for the driver') . '? ' . translate('When it’s on the Driver will be able to see any predefined Q & A.') }}"
                                            data-confirm-btn="{{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                        {{ ($settings->firstWhere('key_name', 'driver_question_answer_status')->value ?? 0) == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body collapsible-card-content">
                            <div class="row align-items-center g-3 mb-20">
                                <!-- <div class="col-md-4">
                                    <h5 class="mb-2">{{ translate('Setup Answer & Topics') }}</h5>
                                    <div class="fs-12">
                                        {{ translate('Here you can set predefine answer & the topics for the answer that will help when anyone reply any common topics.') }}
                                    </div>
                                </div> -->
                                <div class="col-md-12">
                                    <form action="{{ route('admin.business.setup.chatting-setup.support-saved-reply.store') }}" method="post">
                                        @csrf
                                        <div class="col-md-12">
                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="">
                                                            <label for="" class="form-label fs-14">{{ translate('Topic') }}
                                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-title="{{ translate('You can ask about Topic here') }}"></i>
                                                            </label>
                                                            <div class="character-count">
                                                                <textarea id="topic" name="topic"
                                                                          class="form-control character-count-field" cols="30"
                                                                          rows="1"
                                                                          placeholder="{{ translate('Ex: When driver want to cancel a ongoing trip') }}"
                                                                          maxlength="150"
                                                                          data-max-character="150" required></textarea>
                                                                <span
                                                                    class="d-flex justify-content-end text-muted mt-1">{{ translate('0/150') }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="">
                                                            <label for="" class="form-label fs-14">{{ translate('Answer') }}
                                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-title="{{ translate('Type answer here') }}"></i>
                                                            </label>
                                                            <div class="character-count">
                                                                <textarea id="answer" rows="1" name="answer"
                                                                          class="form-control character-count-field" cols="30"
                                                                          rows="2"
                                                                          placeholder="{{ translate('Type answer here') }}"
                                                                          maxlength="250" data-max-character="250"
                                                                          required></textarea>
                                                                <span
                                                                    class="d-flex justify-content-end text-muted mt-1">{{ translate('0/250') }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 justify-content-end mt-4">
                                                <button
                                                    class="btn btn-light h-40px min-w-120 justify-content-center fw-semibold cmn_focus"
                                                    type="reset">{{ translate('Reset') }}</button>
                                                <button
                                                    class="btn btn-primary h-40px min-w-120 justify-content-center fw-semibold cmn_focus">{{ translate('Submit') }}</button>
                                            </div>
                                        </div>
                                        <div class="col-12">

                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="border-top pt-4 mt-4">
                                <h5 class="text-capitalize mb-20"> {{ translate('Topics & Answer List') }}
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-borderless align-middle mb-0">
                                        <thead class="table-light align-middle">
                                        <tr>
                                            <th>{{ translate('SL') }}</th>
                                            <th>
                                                {{ translate('Topic') }}
                                            </th>
                                            <th>
                                                {{ translate('Answer') }}
                                            </th>
                                            <th>{{ translate('status') }}</th>
                                            <th class="text-center">{{ translate('action') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($savedReplies as $key => $savedReply)
                                            <tr>
                                                <td class="sl">{{ $key + $savedReplies->firstItem() }}</td>
                                                <td>
                                                    <div class="min-w300 max-w300 line--limit-2"
                                                         data-bs-custom-class="des-tooltip" data-bs-toggle="tooltip"
                                                         data-bs-html="true" data-bs-placement="bottom"
                                                         data-bs-title="{{ $savedReply->topic }}">
                                                        {{ $savedReply->topic }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="min-w300 max-w-450 line--limit-2"
                                                         data-bs-custom-class="des-tooltip" data-bs-toggle="tooltip"
                                                         data-bs-html="true" data-bs-placement="bottom"
                                                         data-bs-title="{{ $savedReply->answer}}">
                                                        {{ $savedReply->answer }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <label class="switcher">
                                                        <input class="switcher_input custom_status_change"
                                                               type="checkbox"
                                                               id="{{ $savedReply->id }}"
                                                               data-url="{{ route('admin.business.setup.chatting-setup.support-saved-reply.status') }}"
                                                               data-title="{{$savedReply->is_active == 1 ? translate('Are you sure to turn off this Topic & Answer') : translate('Are you sure to turn on this Topic & Answer') }} ?"
                                                               data-sub-title="{{$savedReply->is_active == 1 ? translate('Once you turn off this Topic & Answer') . ', ' . translate(', the support section will no longer see this Topic & Answer.'): translate('Once you turn On this Topic & Answer') . ', ' . translate(', the support section will see this Topic & Answer.')}}"
                                                               data-confirm-btn="{{$savedReply->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                                            {{ $savedReply->is_active == 1 ? "checked": ""  }}
                                                        >
                                                        <span class="switcher_control"></span>
                                                    </label>

{{--                                                    <label class="switcher">--}}
{{--                                                        <input class="switcher_input status-change"--}}
{{--                                                               data-url="{{ route('admin.business.setup.chatting-setup.support-saved-reply.status') }}"--}}
{{--                                                               id="{{ $savedReply->id }}"--}}
{{--                                                               type="checkbox"--}}
{{--                                                               name="status" {{ $savedReply->is_active == 1 ? "checked": ""  }} >--}}
{{--                                                        <span class="switcher_control"></span>--}}
{{--                                                    </label>--}}
                                                </td>
                                                <td>
                                                    <div
                                                        class="d-flex justify-content-center gap-2 align-items-center">
                                                        <button class="btn btn-outline-primary btn-action editTopic"
                                                                data-id="{{$savedReply->id}}">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </button>
                                                        <button data-url="{{ route('admin.business.setup.chatting-setup.support-saved-reply.delete', ['id' => $savedReply?->id]) }}"
                                                                data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                                data-title="{{ translate('Are you sure to delete this Topic & Answer') }}?"
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
                                             {!! $savedReplies->links() !!}
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
    <!-- End Main Content -->


    <div class="modal fade" id="editDataModal">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
            </div>
        </div>
    </div>

    <div class="modal fade" id="editTopicModal">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#trips_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
        $(document).ready(function () {
            $('.editData').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.chatting-setup.question-answer.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function (data) {
                        $('#editDataModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editDataModal').modal('show');
                        $('.character-count-field').on('keyup change', function () {
                            initialCharacterCount($(this));
                        });
                        $('.character-count-field').each(function () {
                            initialCharacterCount($(this));
                        });
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });

            $('.editTopic').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.chatting-setup.support-saved-reply.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function (data) {
                        $('#editTopicModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editTopicModal').modal('show');
                        $('.character-count-field').on('keyup change', function () {
                            initialCharacterCount($(this));
                        });
                        $('.character-count-field').each(function () {
                            initialCharacterCount($(this));
                        });
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });

            function initialCharacterCount(item) {
                let str = item.val();
                let maxCharacterCount = item.data('max-character');
                let characterCount = str.length;
                if (characterCount > maxCharacterCount) {
                    item.val(str.substring(0, maxCharacterCount));
                    characterCount = maxCharacterCount;
                }
                item.closest('.character-count').find('span').text(characterCount + '/' + maxCharacterCount);
            }
        });
    </script>
@endpush
