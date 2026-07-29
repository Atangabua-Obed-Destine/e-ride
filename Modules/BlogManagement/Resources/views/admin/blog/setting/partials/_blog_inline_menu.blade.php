<div class="position-relative nav--tab-wrapper mb-4">
    <ul class="nav d-flex gap-4 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap">
        <li class="nav-item">
            <a href="{{ route('admin.blog.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ request()->routeIs('admin.blog.index') ? 'active' : '' }}
            ">{{ translate('Blog_Page') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.blog.app-download-setup.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/blog/app-download-setup') ? 'active' : '' }}
            ">{{ translate('App_Download_Setup') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.blog.priority-setup.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/blog/priority-setup') ? 'active' : '' }}
            ">{{ translate('Priority_Setup') }}</a>
        </li>
    </ul>
    <div class="nav--tab__prev">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <div class="nav--tab__next">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>
