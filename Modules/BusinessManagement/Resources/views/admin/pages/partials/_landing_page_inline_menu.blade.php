<div class="position-relative nav--tab-wrapper mb-4">
    <ul class="nav d-flex gap-4 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap">
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.intro-section.index') }}" class="text-capitalize nav-link active-rounded-20
                {{ Request::is('admin/business/pages-media/landing-page/intro-section') ? 'active' : '' }}
            ">{{ translate('intro_section') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.business-statistics.index') }}" class="text-capitalize nav-link active-rounded-20
                {{ Request::is('admin/business/pages-media/landing-page/business-statistics') ? 'active' : '' }}
            ">{{ translate('business_statistics') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.our-solutions.index') }}" class="text-capitalize nav-link active-rounded-20
                {{ Request::is('admin/business/pages-media/landing-page/our-solutions*') ? 'active' : '' }}
            ">{{ translate('Our_Solutions') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.our-services.index') }}" class="text-capitalize nav-link active-rounded-20
             {{ Request::is('admin/business/pages-media/landing-page/our-services') ? 'active' : '' }}
            ">{{ translate('Our_Services') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.gallery.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/gallery') ? 'active' : '' }}
            ">{{ translate('Gallery') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.customer-app-download.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/customer-app-download') ? 'active' : '' }}
            ">{{ translate('Customer_App_Download') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.earn-money.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/earn-money') ? 'active' : '' }}
            ">{{ translate('earn_money') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.testimonial.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/testimonial*') ? 'active' : '' }}
            ">{{ translate('testimonial') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.landing-page.newsletter.index') }}" class="text-capitalize nav-link active-rounded-20
            {{ Request::is('admin/business/pages-media/landing-page/newsletter') ? 'active' : '' }}
            ">{{ translate('Newsletter') }}</a>
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
