<?php

namespace Modules\BlogManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\AiModule\Service\Interfaces\AiSettingServiceInterface;
use Modules\BlogManagement\Http\Requests\BlogStoreOrUpdateRequest;
use Modules\BlogManagement\Http\Requests\BlogSummernoteFileStoreRequest;
use Modules\BlogManagement\Service\Interfaces\BlogAuthorServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogCategoryServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogDraftServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogServiceInterface;
use App\Exports\StyledReport\ColumnFormat;

class BlogController extends Controller
{
    public $blogCategoryService;
    public $blogService;
    public $blogDraftService;
    public BlogAuthorServiceInterface $blogAuthorService;

    public $aiSettingService;

    public function __construct(BlogCategoryServiceInterface $blogCategoryService, BlogServiceInterface $blogService, BlogDraftServiceInterface $blogDraftService, BlogAuthorServiceInterface $blogAuthorService, AiSettingServiceInterface $aiSettingService)
    {
        $this->blogCategoryService = $blogCategoryService;
        $this->blogService = $blogService;
        $this->blogDraftService = $blogDraftService;
        $this->blogAuthorService = $blogAuthorService;
        $this->aiSettingService = $aiSettingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('blogmanagement::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $blogCategories = $this->blogCategoryService->getAll(orderBy: ['id' => 'desc']);
        $activeBlogCategories = $this->blogCategoryService->getBy(criteria: ['status' => 1], orderBy: ['id' => 'desc']);
        $isAiSetupEnabled = $this->aiSettingService->findOneBy(criteria: ['status' => 1]);
        $blogAuthors = $this->blogAuthorService->getAuthorNames();

        return view('blogmanagement::admin.blog.create', compact('blogCategories', 'activeBlogCategories', 'isAiSetupEnabled', 'blogAuthors'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BlogStoreOrUpdateRequest $request) {
        $saveType = ['is_published' => 1];

        if ($request->filled('draft'))
        {
            $saveType = ['is_drafted' => 1];
        }

        $data = array_merge($request->validated(), $saveType);
        $this->blogService->saveBlog(data: $data);

        if ($request->ajax()) {
            return response()->json([
                'successMessage' => BLOG_STORE['message'],
                'redirectUrl' => route('admin.blog.index'),
            ]);
        }

        Toastr::success(BLOG_STORE['message']);

        return redirect()->route('admin.blog.index');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('blogmanagement::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id)
    {
        $blogCategories = $this->blogCategoryService->getAll(orderBy: ['id' => 'desc']);
        $activeBlogCategories = $this->blogCategoryService->getBy(criteria: ['status' => 1], orderBy: ['id' => 'desc']);
        $isAiSetupEnabled = $this->aiSettingService->findOneBy(criteria: ['status' => 1]);
        $data = $this->blogService->findOne(id: $id, relations: ['author']);
        $blogAuthors = $this->blogAuthorService->getAuthorNames();

        return view('blogmanagement::admin.blog.edit', compact('blogCategories', 'activeBlogCategories', 'data', 'isAiSetupEnabled', 'blogAuthors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BlogStoreOrUpdateRequest $request, $id) {
        $saveType = ['is_published' => 1];

        if ($request->filled('draft'))
        {
            $saveType = ['is_drafted' => 1];
        }
        $blog = $this->blogService->findOne(id: $id, relations: ['draft']);
        $data = array_merge($request->validated(), ['blog' => $blog], $saveType);
        $this->blogService->saveBlog(data: $data);

        if ($request->ajax()) {
            return response()->json([
                'successMessage' => BLOG_UPDATE['message'],
                'redirectUrl' => route('admin.blog.index'),
            ]);
        }

        Toastr::success(BLOG_UPDATE['message']);

        return redirect()->route('admin.blog.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {
        $this->authorize('blog_delete');
        $this->blogService->delete(id: $id);

        Toastr::success(BLOG_DELETE['message']);

        return redirect()->route('admin.blog.index');

    }

    public function export(Request $request)
    {
        $this->authorize('blog_export');
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
        $blogData = $this->blogService->index(criteria: $criteria, relations: ['draft.category', 'draft.author', 'category', 'author'], orderBy: ['created_at' => 'desc']);
        $exportData = $this->blogService->export($blogData);

        $categoryName = $request->blog_category_id ? $this->blogCategoryService->findOne(id: $request->blog_category_id)?->name : null;

        $config = styledExportConfig(
            $exportData,
            title: 'Blog List',
            summary: ['Total Blogs' => $exportData->count()],
            filters: [
                'Category'      => $categoryName           ?? translate('All'),
                'Publish Date'  => $request->publish_date  ?? translate('N/A'),
                'Search'        => $request->search        ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'blogs-' . time() . '.xlsx',
            headings: ['Id', 'Category', 'Title', 'Writer', 'Publish Date', 'Status'],
        );
        return exportData($exportData, $request['file'], '', $config);
    }

    public function status(Request $request)
    {
        $this->authorize('blog_edit');
        $blogInfo = $this->blogService->findOneBy(criteria: ['id' => $request->id])->toArray();
        $blogInfo['status'] = $request->status;
        $blog = $this->blogService->update(id: $request->id, data: $blogInfo);

        return response()->json($blog);
    }

    public function uploadSummernoteImage(BlogSummernoteFileStoreRequest $request)
    {
        $fileName = fileUploader('blog/summernote/', image: $request->image);

        return dynamicStorage('storage/app/public/blog/summernote/' . $fileName);
    }

    public function deleteSummernoteImage(Request $request)
    {
        $image = $request->get('image');

        if ($image && str_contains(parse_url($image, PHP_URL_PATH) ?? '', '/blog/summernote/')) {
            fileRemover('blog/summernote/', basename(parse_url($image, PHP_URL_PATH)));
        }

        return response()->json(['success' => true]);
    }
}
