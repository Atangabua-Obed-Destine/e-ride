{{--
    The review dialog every posting action goes through (paxhitestbed
    supplier-bill/partials/review-modal, on PosInnova's Bootstrap 3).

    Include once per page (inside @section('content')), and put
    @include('ledger.partials.review_script') in @section('javascript'). Then give a trigger button
        data-review-url    where the preview is fetched from (GET), or
        data-review-form   the id of a form whose fields are posted to data-review-url
        data-confirm-form  the id of the form that confirming submits
        data-confirm-label the confirm button's text
        data-confirm-check the wording of the tick box (optional)
        data-confirm-class the confirm button's class (default btn-success)
        data-needs-reason  "1" when a reason must be typed before confirming (posted as "reason")

    Confirming needs: the preview reports no problem, the tick box ticked, and (when asked) a
    reason. Confirming adds reviewed=1 to the form; the server refuses the action without it.
--}}
<div class="modal fade" id="reviewModal" tabindex="-1" role="dialog" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="@lang('ledger.close')"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="reviewModalLabel">@lang('ledger.review_title')</h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="review-body"><div class="ap-muted">@lang('ledger.checking')</div></div>
                <div id="review-reason" style="display:none; margin-top: 12px;">
                    <label for="review-reason-input">@lang('ledger.reason') <span>*</span></label>
                    <textarea id="review-reason-input" class="form-control" maxlength="1000" rows="2"></textarea>
                </div>
                <div class="ap-confirm-check" id="review-check-box" style="margin-top: 12px;">
                    <input type="checkbox" id="review-check" disabled>
                    <label for="review-check" id="review-check-label">@lang('ledger.review_check_default')</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger.go_back')</button>
                <button type="button" class="btn btn-success" id="review-confirm" disabled>@lang('ledger.confirm')</button>
            </div>
        </div>
    </div>
</div>
