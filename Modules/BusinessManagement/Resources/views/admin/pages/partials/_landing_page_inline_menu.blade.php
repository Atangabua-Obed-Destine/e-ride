<div class="position-relative nav--tab-wrapper mb-4">
    <ul class="nav d-flex gap-4 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap">
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.look.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/look') ? 'active' : '' }}
            ">{{ translate('Home_page_look') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.customer-app-download.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/customer-app-download') ? 'active' : '' }}
            ">{{ translate('Customer_App_Download') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.footer.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/footer') ? 'active' : '' }}
            ">{{ translate('Footer') }}</a>
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
