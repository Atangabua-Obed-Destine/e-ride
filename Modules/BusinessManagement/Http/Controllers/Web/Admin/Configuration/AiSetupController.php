<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Modules\AiModule\Http\Requests\AiSettingStoreOrUpdateRequest;
use Modules\AiModule\Service\Interfaces\AiSettingServiceInterface;

class AiSetupController extends Controller
{
    protected $aiSettingService;

    public function __construct(AiSettingServiceInterface $aiSettingService)
    {
        $this->aiSettingService = $aiSettingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $aiSetting = $this->aiSettingService->findOneBy(criteria: ['ai_name' => 'OpenAI']);

        return view('businessmanagement::admin.configuration.ai-setup', compact('aiSetting'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AiSettingStoreOrUpdateRequest $request)
    {
        $ai = $this->aiSettingService->findOneBy(criteria: ['ai_name' => 'OpenAI']);
        $data = $request->validated();
        $data['status'] = array_key_exists('status', $data) ? 1 : 0;
        if ($ai) {
            $this->aiSettingService->update(id: $ai->id, data: $data);
        } else {
            $this->aiSettingService->create(data: $data);
        }

        Toastr::success(AI_SETUP_UPDATE['message']);

        return back();
    }
}
