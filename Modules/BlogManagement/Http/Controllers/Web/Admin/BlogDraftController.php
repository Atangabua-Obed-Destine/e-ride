<?php

namespace Modules\BlogManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Modules\AiModule\Service\Interfaces\AiSettingServiceInterface;
use Modules\BlogManagement\Http\Requests\BlogDraftStoreOrUpdateRequest;
use Modules\BlogManagement\Service\Interfaces\BlogAuthorServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogCategoryServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogDraftServiceInterface;

class BlogDraftController extends Controller
{
    public $blogDraftService;
    public $blogCategoryService;
    public BlogAuthorServiceInterface $blogAuthorService;
    public $aiSettingService;

    public function __construct(BlogDraftServiceInterface $blogDraftService, BlogCategoryServiceInterface $blogCategoryService, BlogAuthorServiceInterface $blogAuthorService, AiSettingServiceInterface $aiSettingService)
    {
        $this->blogDraftService = $blogDraftService;
        $this->blogCategoryService = $blogCategoryService;
        $this->blogAuthorService = $blogAuthorService;
        $this->aiSettingService = $aiSettingService;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $blogCategories = $this->blogCategoryService->getAll(orderBy: ['id' => 'desc']);
        $activeBlogCategories = $this->blogCategoryService->getBy(criteria: ['status' => 1], orderBy: ['id' => 'desc']);
        $isAiSetupEnabled = $this->aiSettingService->findOneBy(criteria: ['status' => 1]);
        $data = $this->blogDraftService->findOne(id: $id, relations: ['blog', 'author']);
        $blogAuthors = $this->blogAuthorService->getAuthorNames();

        return view('blogmanagement::admin.blog.draft.edit', compact('blogCategories', 'activeBlogCategories', 'data', 'isAiSetupEnabled', 'blogAuthors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BlogDraftStoreOrUpdateRequest $request, $id) {
        $saveType = ['is_published' => 1];

        if ($request->filled('draft'))
        {
            $saveType = ['is_drafted' => 1];
        }

        $blogDraft = $this->blogDraftService->findOne(id: $id, relations: ['blog']);
        $data = array_merge($request->validated(), ['blogDraft' => $blogDraft], $saveType);
        $this->blogDraftService->saveBlogDraft(data: $data);

        if ($request->ajax()) {
            return response()->json([
                'successMessage' => BLOG_UPDATE['message'],
                'redirectUrl' => route('admin.blog.index'),
            ]);
        }

        Toastr::success(BLOG_UPDATE['message']);

        return redirect()->route('admin.blog.index');
    }
}
