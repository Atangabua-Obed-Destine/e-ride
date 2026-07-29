
    @forelse($savedReplies as $key => $savedReply)
        <div class="bg-fafafa p-3 mb-3 rounded-10 saved-answer">
            <div class="q-answer title-color d-flex gap-05 mb-3">
                <div>{{ ++$key  }}.</div>
                <div class="w-100">
                    <div class="fw-medium">{{ translate('Topic') }}</div>
                    <div class="border-bottom border-e2e2e2 opacity-75 mt-2 pb-3">{{ $savedReply->topic }}</div>
                    <div class="fw-medium d-flex align-items-center justify-content-between gap-2 mt-3 mb-2">
                        <span>Answer</span>
                        <button type="button" class="btn copy-btn bg-white box-shadow px-3 text-primary">
                            <i class="tio-copy"></i> Copy
                        </button>
                    </div>
                    <div class="answer-text opacity-75">{{ $savedReply->answer }}</div>
                </div>
            </div>
        </div>
        @empty
        <div class="d-flex justify-content-center mt-3" id="">
            <div class="d-flex justify-content-center align-items-center h-100">
                <div class="d-flex flex-column align-items-center gap-20">
                    <img width="38" src="{{ dynamicAsset('public/assets/admin-module/img/conversation.png') }}"
                         alt="">
                    <p class="fs-12">{{ translate('no save answers') }}</p>
                </div>
            </div>
        </div>
    @endforelse



