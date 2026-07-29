<div class="offcanvas offcanvas-end" id="category-offcanvas" style="--bs-offcanvas-width: 630px;z-index: 1051">
    <div class="offcanvas-header">
        <h6 class="offcanvas-title flex-grow-1">
            {{ translate('Category_Setup') }}
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body scrollbar-thin">
        <div class="card mb-5">
            <div class="card-header">
                <h4 class="mb-0 offcanvas-form-title">{{ translate('Add New Category') }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.blog.category.store') }}" id="category-store-or-update">
                    @csrf
                    <input type="hidden" id="blog_category_id" name="id" value="">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6 mb-4">
                        <label for="blog_category_name" class="mb-2">
                            {{ translate('Category_Name') }}
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="blog_category_name" name="name" class="form-control" placeholder="{{ translate('Ex: General') }}" required>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" class="btn btn-secondary text-capitalize fw-semibold cmn_focus min-w-120">{{ translate('reset') }}</button>
                        <button type="submit" class="btn btn-primary text-capitalize fw-semibold cmn_focus min-w-120">{{ translate('submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="table-top d-flex flex-wrap gap-10 justify-content-between mb-3">
            <h5 class="mb-0">{{ translate('Category List') }}</h5>
            <form action="" class="search-form-blog-category search-form search-form_style-two"
                  method="GET">
                <div class="input-group search-form__input_group">
                            <span class="search-form__icon px-2">
                                <i class="bi bi-search"></i>
                            </span>
                    <input type="search" class="theme-input-style search-form__input"
                           value="{{ request()->get('search') }}" name="search" id="search"
                           placeholder="{{ translate('search') }}">
                </div>
                <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless align-middle table-hover col-mx-w300 text-dark">
                <thead class="table-light align-middle text-capitalize">
                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Category') }}</th>
                    <th class="text-center">{{ translate('Status') }}</th>
                    <th class="text-center">{{ translate('Action') }}</th>
                </tr>
                </thead>
                <tbody id="blog-category-list">
                @include('blogmanagement::admin.blog.category._blog-category-list')
                </tbody>
            </table>
        </div>
    </div>
</div>
