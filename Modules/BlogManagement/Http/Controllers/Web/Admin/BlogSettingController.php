<?php

namespace Modules\BlogManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\BlogManagement\Http\Requests\BlogAppDownloadStoreOrUpdateRequest;
use Modules\BlogManagement\Http\Requests\BlogPageIntroStoreOrUpdateRequest;
use Modules\BlogManagement\Http\Requests\BlogPrioritySetupStoreOrUpdateRequest;
use Modules\BlogManagement\Service\Interfaces\BlogCategoryServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogSettingServiceInterface;

class BlogSettingController extends Controller
{
    public $blogSettingService;
    public $blogService;
    public $blogCategoryService;

    public function __construct(BlogSettingServiceInterface $blogSettingService, BlogServiceInterface $blogService, BlogCategoryServiceInterface $blogCategoryService)
    {
        $this->blogSettingService = $blogSettingService;
        $this->blogService = $blogService;
        $this->blogCategoryService = $blogCategoryService;
    }

    public function blogPage(Request $request)
    {
        $this->authorize('blog_view');
        $isEnabled = $this->blogSettingService->findOneBy(criteria: ['key_name' => 'is_enabled', 'settings_type' => BLOG_PAGE])?->value;
        $blogPageTitle = $this->blogSettingService->findOneBy(criteria: ['key_name' => 'title', 'settings_type' => BLOG_PAGE])?->value;
        $blogPageSubtitle = $this->blogSettingService->findOneBy(criteria: ['key_name' => 'subtitle', 'settings_type' => BLOG_PAGE])?->value;
        $criteria = $request->all();
        if (array_key_exists('publish_date', $criteria) && !is_null($request->publish_date))
        {
            $date = explode(' - ',$request->publish_date);
            $startDate = Carbon::createFromFormat('m/d/Y', $date[0])->format('Y-m-d');
            $endDate = Carbon::createFromFormat('m/d/Y', $date[1])->format('Y-m-d');
            $criteria['filter_date'] = getDateRange([
                'start' => $startDate,
                'end' => $endDate
            ]);
        }

        $blogList = $this->blogService->index(criteria: $criteria, relations: ['draft.category', 'draft.author', 'category', 'author'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        $blogCategories = $this->blogCategoryService->getBy(criteria: ['status' => 1]);
        return view('blogmanagement::admin.blog.setting.blog-page', compact('isEnabled', 'blogPageTitle', 'blogPageSubtitle', 'blogList', 'blogCategories'));
    }

    public function updateBlogPageIntro(BlogPageIntroStoreOrUpdateRequest $request)
    {
        $this->authorize('blog_edit');
        $data = $request->validated();
        foreach ($data as $key => $value) {
            $info = [
                'key_name' => $key,
                'value' => $value,
                'settings_type' => BLOG_PAGE
            ];
            $blogIntroKey = $this->blogSettingService->findOneBy(criteria: ['key_name' => $key, 'settings_type' => BLOG_PAGE]);

            if ($blogIntroKey) {
                $this->blogSettingService->update(id: $blogIntroKey->id, data: $info);
            } else {
                $this->blogSettingService->create(data: $info);
            }
        }

        Toastr::success(BLOG_INTRO_UPDATE['message']);

        return back();
    }

    public function appDownloadSetup()
    {
        $this->authorize('blog_view');
        $isEnabled = $this->blogSettingService->findOneBy(criteria: ['key_name' => 'is_enabled', 'settings_type' => APP_DOWNLOAD_SETUP])?->value;
        $driverAppContents = $this->blogSettingService->findOneBy(criteria: ['key_name' => DRIVER_APP_CONTENTS, 'settings_type' => APP_DOWNLOAD_SETUP]);
        $customerAppContents = $this->blogSettingService->findOneBy(criteria: ['key_name' => CUSTOMER_APP_CONTENTS, 'settings_type' => APP_DOWNLOAD_SETUP]);

        return view('blogmanagement::admin.blog.setting.app-download-setup', compact('isEnabled', 'driverAppContents', 'customerAppContents'));
    }

    public function updateAppContents(BlogAppDownloadStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('blog_edit');
        $this->blogSettingService->updateAppContents($request->validated());
        Toastr::success(BLOG_APP_DOWNLOAD_SETUP_UPDATE['message']);

        return back();
    }

    public function prioritySetup()
    {
        $this->authorize('blog_view');
        $categorySortingPriority = $this->blogSettingService->findOneBy(criteria: ['key_name' => CATEGORY_SORTING, 'settings_type' => PRIORITY_SETUP])?->value;
        $blogSortingPriority = $this->blogSettingService->findOneBy(criteria: ['key_name' => BLOG_SORTING, 'settings_type' => PRIORITY_SETUP])?->value;

        return view('blogmanagement::admin.blog.setting.priority-setup', compact('categorySortingPriority', 'blogSortingPriority'));
    }

    public function updatePrioritySetup(BlogPrioritySetupStoreOrUpdateRequest $request)
    {
        $this->authorize('blog_edit');
        $data = $request->validated();
        foreach ($data as $key => $value) {
            $info = [
                'key_name' => $key,
                'value' => $value,
                'settings_type' => PRIORITY_SETUP
            ];
            $blogIntroKey = $this->blogSettingService->findOneBy(criteria: ['key_name' => $key, 'settings_type' => PRIORITY_SETUP]);

            if ($blogIntroKey) {
                $this->blogSettingService->update(id: $blogIntroKey->id, data: $info);
            } else {
                $this->blogSettingService->create(data: $info);
            }
        }

        Toastr::success(BLOG_PRIORITY_SETUP_UPDATE['message']);

        return back();
    }


    public function updateSettings(Request $request)
    {
        $this->authorize('blog_edit');

        $blogInfo = $this->blogSettingService->findOneBy(criteria: ['key_name' => $request['name'], 'settings_type' => $request['type']]);
        if ($blogInfo) {
            $data = $this->blogSettingService
                ->update(id: $blogInfo->id, data: ['key_name' => $request['name'], 'settings_type' => $request['type'], 'value' => $request['value']]);
        } else {
            $data = $this->blogSettingService
                ->create(data: ['key_name' => $request['name'], 'settings_type' => $request['type'], 'value' => $request['value']]);
        }

        return response()->json($data);
    }
}
