<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\SupportSavedReplyStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\QuestionAnswerServiceInterface;
use Modules\BusinessManagement\Http\Requests\QuestionAnswerStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\SupportSavedReplyServiceInterface;

class ChattingSetupController extends BaseController
{
    use AuthorizesRequests;
    protected $businessSettingService;

    protected $questionAnswerService;

    protected $supportSavedReplyService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService, QuestionAnswerServiceInterface $questionAnswerService, SupportSavedReplyServiceInterface $supportSavedReplyService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
        $this->questionAnswerService = $questionAnswerService;
        $this->supportSavedReplyService = $supportSavedReplyService;

    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        if (in_array($type, [DRIVER, SUPPORT])) {
            $settings = $this->businessSettingService->getBy(criteria: ['settings_type' => CHATTING_SETTINGS]);
            $redefinedQAs = $this->questionAnswerService->getBy(orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request?->page ?? 1);
            $savedReplies = $this->supportSavedReplyService->getBy(orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request?->page ?? 1);
            return view('businessmanagement::admin.business-setup.chatting-setup', compact('settings', 'redefinedQAs', 'savedReplies'));
        }
        abort(404);

    }

    public function storeQuestionAnswer(QuestionAnswerStoreOrUpdateRequest $request)
    {
        $this->authorize('business_view');
        $this->questionAnswerService->create(data: $request->validated());
        Toastr::success(translate('Redefined Q&A stored successfully'));
        return redirect()->back();
    }

    public function statusQuestionAnswer(Request $request): JsonResponse
    {
        $this->authorize('business_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->questionAnswerService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

    public function editQuestionAnswer($id): View
    {
        $this->authorize('business_edit');
        $redefinedQA = $this->questionAnswerService->findOne(id: $id);
        if (!$redefinedQA) {
            Toastr::error(translate('Q&A not found'));
            return redirect()->back();
        }
        return view('businessmanagement::admin.business-setup.edit-question-answer', compact('redefinedQA'));
    }

    public function updateQuestionAnswer($id, QuestionAnswerStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->questionAnswerService->update(id: $id, data: $request->validated());
        Toastr::success(translate('Redefined Q&A updated successfully'));
        return redirect()->back();
    }

    public function destroyQuestionAnswer(string $id): RedirectResponse
    {
        $this->authorize('business_delete');
        $this->questionAnswerService->delete(id: $id);
        Toastr::success(translate('Redefined Q&A deleted successfully.'));
        return redirect()->route('admin.business.setup.chatting-setup.index',DRIVER);
    }


    public function storeSupportSavedReply(SupportSavedReplyStoreOrUpdateRequest $request)
    {
        $this->authorize('business_view');
        $this->supportSavedReplyService->create(data: $request->validated());
        Toastr::success(translate('Support Saved Reply stored successfully'));
        return redirect()->back();
    }

    public function statusSupportSavedReply(Request $request): JsonResponse
    {
        $this->authorize('business_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->supportSavedReplyService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

    public function editSupportSavedReply($id): View
    {
        $this->authorize('business_edit');
        $savedReply = $this->supportSavedReplyService->findOne(id: $id);
        if (!$savedReply) {
            Toastr::error(translate('Support Saved Reply not found'));
            return redirect()->back();
        }
        return view('businessmanagement::admin.business-setup.edit-support-saved-reply', compact('savedReply'));
    }

    public function updateSupportSavedReply($id, SupportSavedReplyStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->supportSavedReplyService->update(id: $id, data: $request->validated());
        Toastr::success(translate('Support Saved Reply updated successfully'));
        return redirect()->back();
    }

    public function destroySupportSavedReply(string $id): RedirectResponse
    {
        $this->authorize('business_delete');
        $this->supportSavedReplyService->delete(id: $id);
        Toastr::success(translate('Support Saved Reply deleted successfully.'));
        return redirect()->route('admin.business.setup.chatting-setup.index',SUPPORT);
    }

}
