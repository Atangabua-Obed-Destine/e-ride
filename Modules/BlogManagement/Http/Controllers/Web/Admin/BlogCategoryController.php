<?php

namespace Modules\BlogManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\BlogManagement\Http\Requests\BlogCategoryStoreOrUpdateRequest;
use Modules\BlogManagement\Service\Interfaces\BlogCategoryServiceInterface;

class BlogCategoryController extends Controller
{
    public $blogCategoryService;

    public function __construct(BlogCategoryServiceInterface $blogCategoryService)
    {
        $this->blogCategoryService = $blogCategoryService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
       $this->authorize('blog_view');
       $blogCategories = $this->blogCategoryService->index(criteria: $request->all(), orderBy: ['id' => 'desc']);
       $activeBlogCategories = $this->blogCategoryService->getBy(criteria: ['status' => 1], orderBy: ['id' => 'desc']);

       return response()->json([
           'success' => true,
           'view' => view('blogmanagement::admin.blog.category._blog-category-list', compact('blogCategories'))->render(),
           'create_blade_category_view' => view('blogmanagement::admin.blog.partials._active-categories', compact('activeBlogCategories'))->render()
       ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(BlogCategoryStoreOrUpdateRequest $request) {
        $this->authorize('blog_edit');
        if ($request->filled('id'))
        {
            $this->blogCategoryService->update(id: $request->id, data: ['name' => $request->name]);
        } else{
            $this->blogCategoryService->create(data: ['name' => $request->name]);
        }

        return response()->json([
            'success' => true,
            'message' => $request->filled('id') ? translate('Blog category updated successfully!') : translate('Blog category stored successfully!')
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {
        $this->blogCategoryService->delete(id: $id);

        return response()->json([
            'success' => true,
            'message' => translate('Blog category deleted successfully!')
        ]);
    }

    public function status($id, Request $request)
    {
        $this->authorize('blog_edit');

        $blogCategoryInfo = $this->blogCategoryService->findOneBy(criteria: ['id' => $id])->toArray();
        $blogCategoryInfo['status'] = $request->value;
        $this->blogCategoryService->update(id: $id, data: $blogCategoryInfo);
        $blogCategories = $this->blogCategoryService->index(criteria: $request->all(), orderBy: ['id' => 'desc']);

        return response()->json([
            'success' => true,
            'view' => view('blogmanagement::admin.blog.category._blog-category-list', compact('blogCategories'))->render(),
        ]);
    }
}
