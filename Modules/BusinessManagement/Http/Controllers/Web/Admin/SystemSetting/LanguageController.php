<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\SystemSetting;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\BusinessManagement\Service\Interfaces\LanguageSettingServiceInterface;

class LanguageController extends BaseController
{
    use AuthorizesRequests;

    protected $languageSettingService;

    public function __construct(LanguageSettingServiceInterface $languageSettingService)
    {
        parent::__construct($languageSettingService);
        $this->languageSettingService = $languageSettingService;
    }

    public function index(?Request $request, string $type = null): \Illuminate\View\View|\Illuminate\Database\Eloquent\Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        $language = $this->languageSettingService->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        if (!$language) {
            $this->languageSettingService->create([
                'key_name' => SYSTEM_LANGUAGE,
                'value' => [[
                    "id" => 1,
                    "direction" => "ltr",
                    "code" => "en",
                    "status" => 1,
                    "default" => true
                ]],
                'settings_type' => LANGUAGE_SETTINGS
            ]);
        }
        $allLanguages = collect(LANGUAGES);
        return view('businessmanagement::admin.system-settings.language.index', compact('language','allLanguages'));
    }

    public function store(Request $request)
    {
        $this->authorize('business_edit');
        $language = $this->languageSettingService->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        if ($language) {
            foreach ($language['value'] as $key => $data) {
                if ($data['code'] == $request['code']) {
                    Toastr::warning(DEFAULT_EXISTS_203['message']);
                    return back();
                }

            }
            $this->languageSettingService->storeLanguage($request->all());
            Toastr::success(DEFAULT_STORE_200['message']);
        }
        return back();
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->languageSettingService->updateLanguage($request->all());
        Toastr::success(DEFAULT_UPDATE_200['message']);
        return back();
    }

    public function delete($lang): RedirectResponse
    {
        $this->authorize('business_edit');
        $language = $this->languageSettingService->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        if ($language) {
            foreach ($language['value'] as $data) {
                if ($data['code'] == $lang && array_key_exists('default', $data) && $data['default']) {
                    Toastr::error(LANGUAGE_UPDATE_FAIL_200['message']);
                    return back();
                }
            }
            $this->languageSettingService->deleteLanguage($lang);
            Toastr::success(DEFAULT_DELETE_200['message']);
        }
        return back();
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $this->authorize('business_edit');
        $language = $this->languageSettingService->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        if ($language) {
            foreach ($language['value'] as $key => $data) {
                if ($data['code'] == $request['code']) {
                    if ($data['status'] == 1 && $data['default']) {
                        return response()->json([
                            'message' => LANGUAGE_UPDATE_FAIL_200['message'],
                            'status' => 0
                        ]);
                    }
                }
            }
        }
        $this->languageSettingService->changeLanguageStatus($request->all());
        return response()->json([
            'message' => DEFAULT_UPDATE_200['message'],
            'status' => 1
        ]);
    }

    public function updateDefaultStatus(Request $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->languageSettingService->changeLanguageDefaultStatus($request->all());
        Toastr::success(DEFAULT_UPDATE_200['message']);
        return back();
    }

    public function translate(Request $request,$lang): Factory|View|Application
    {
        $translateData = $this->languageSettingService->translate($lang,$request->all());
        return view('businessmanagement::admin.system-settings.language.translate', compact('lang', 'translateData'));
    }

    public function translateSubmit(Request $request, $lang)
    {
        $this->authorize('business_edit');
        $this->languageSettingService->storeTranslate($request->all(), $lang);
    }

    public function autoTranslate(Request $request, $lang): JsonResponse
    {
        $this->authorize('business_edit');
        $translated = $this->languageSettingService->autoTranslate($request->all(), $lang);
        return response()->json([
            'translated_data' => $translated
        ]);
    }
    public function autoTranslateAll(Request $request, $lang): \Illuminate\Http\JsonResponse
    {
        try {
            if($lang === 'en'){
                return response()->json([
                    'message' => translate('All_datas_are_translated') , 'data' => 'success'
                ]);
            }
            $translated = $this->languageSettingService->autoTranslateAll($request->all(), $lang);
            if ($translated['status']==1){
                return response()->json([
                    'message' =>  translate('translating') , 'data' => 'translating', 'total' => $translated['translatedDataCount'], 'percentage'=> round($translated['percentage'],1), 'hours' => $translated['hours'], 'minutes' => $translated['minutes'], 'seconds' => $translated['seconds'],
                    'status' =>  $translated['renmaining_translated_data_count'] > 0 ? 'pending' : 'done'
                ]);
            }
            if ($translated['status']==2){
                return response()->json([
                    'message' => translate('data_prepared'), 'data' => 'data_prepared', 'total' => count($translated['data_filtered'])
                ]);
            }
            if ($translated['status']==0){
                return response()->json([
                    'message' => translate('All_datas_are_translated'), 'data' => 'success'
                ]);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage() , 'data' => 'error'
            ]);
        }
    }

}
