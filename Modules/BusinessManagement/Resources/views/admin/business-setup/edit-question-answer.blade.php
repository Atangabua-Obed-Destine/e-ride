<form action="{{ route('admin.business.setup.chatting-setup.question-answer.update',  $redefinedQA?->id) }}" method="post" id="updateForm" class="">
    @csrf
    <div class="modal-header border-0">
        <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                aria-label="Close">
        </button>
    </div>
    <div class="modal-body pt-0">
        <div class="mb-20">
            <h5 class="mb-2">{{ translate('Edit Question & Answer') }}</h5>
            <div class="fs-12">
                {{ translate('Here you can set Predefine question that driver when send message  ') }}
            </div>
        </div>
        <div class="p-30 rounded bg-F6F6F6">
            <div class="mb-20">
                <label for="" class="form-label fs-14">{{ translate('Question') }}</label>
                <div class="character-count">
                                    <textarea id="question" name="question" class="form-control character-count-field" cols="30" rows="1"
                                              placeholder="{{ translate('Ex: How to cancel a trip during ongoing trip?') }}" maxlength="150"
                                              data-max-character="150" required>{{ $redefinedQA->question }}</textarea>
                    <span class="d-flex justify-content-end text-muted mt-1">{{ translate('0/150') }}</span>
                </div>
            </div>
            <div class="mb-20">
                <label for="" class="form-label fs-14">{{ translate('Answer') }}</label>
                <div class="character-count">
                                    <textarea id="answer" name="answer" class="form-control character-count-field" cols="30" rows="2"
                                              placeholder="{{ translate('Type answer here') }}" maxlength="250" data-max-character="250" required>{{ $redefinedQA->answer  }}</textarea>
                    <span class="d-flex justify-content-end text-muted mt-1">{{ translate('0/250') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <div class="d-flex gap-3 justify-content-end">
            <button class="btn btn-light h-40px min-w-120 justify-content-center fw-semibold cmn_focus"
                    data-bs-dismiss="modal" type="button">{{ translate('Cancel') }}</button>
            <button type="submit"
                class="btn btn-primary h-40px min-w-120 justify-content-center fw-semibold cmn_focus">{{ translate('Update') }}</button>
        </div>
    </div>
</form>
