@forelse($blogCategories as $key => $blogCategory)
    <tr>
        <td>{{ ++$key }}</td>
        <td>{{ $blogCategory->name }}</td>
        <td class="text-center">
            <label class="switcher mx-auto">
                <input class="switcher_input status-change-blog-category"
                       type="checkbox"
                       {{ $blogCategory->status == 1 ? 'checked' : '' }}
                       data-url="{{ route('admin.blog.category.status', $blogCategory->id) }}"
                       data-icon="{{ dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                       data-title="{{$blogCategory->status == 0 ? translate('Are you sure to turn on the category') : translate('Are you sure to turn off the category') }}?"
                       data-sub-title="{{$blogCategory->status == 0 ? translate('Once you turn on, it will be accessed when selecting the category') : translate('When you turn off this category, It can not be accessed and visible for selection')}}"
                       id="{{ $blogCategory->id }}"
                       data-confirm-btn="{{ $blogCategory->status == 0 ? translate('Yes, On') : translate('Yes, Off') }}"
                       data-cancel-btn="{{ translate('Not Now') }}"
                       data-search="{{ request()->get('search') }}"
                       data-cancel-btn-class="btn-secondary"
                >
                    <span class="switcher_control" ></span>
            </label>
        </td>
        <td>
            <div class="d-flex justify-content-center gap-2 align-items-center">
                <a
                    href="#"
                    data-id="{{ $blogCategory->id }}"
                    data-name="{{ $blogCategory->name }}"
                    class="btn btn-outline-info btn-action edit-blog-category" title="{{ translate('Edit Category') }}">
                    <i class="bi bi-pencil-fill"></i>
                </a>
                <a
                    href="#"
                    data-url="{{ route('admin.blog.category.destroy', $blogCategory) }}"
                    data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                    data-title="{{ translate('Are you sure to delete this Category')."?" }}"
                    data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed it from the category list and cannot be accessed.') }}"
                    data-confirm-btn="{{translate("Yes, Delete")}}"
                    data-cancel-btn="{{translate("Not Now")}}"
                    class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center delete-blog-category-button"
                    data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                    <i class="bi bi-trash-fill"></i>
                </a>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="14">
            <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                <img src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt=""
                     width="100">
                <p class="text-center">{{translate('no_data_available')}}</p>
            </div>
        </td>
    </tr>
@endforelse
