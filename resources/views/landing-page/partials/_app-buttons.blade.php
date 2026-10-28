{{--
    App store buttons. Each renders ONLY when its store URL is set in
    Business Management > System Settings > App Version Setup, so the page
    never shows a dead button.

    Usage:
      @include('landing-page.partials._app-buttons', ['android' => $x, 'ios' => $y])
--}}
@php($android = $android ?? null)
@php($ios = $ios ?? null)

@if($android && ($android['app_url'] ?? null))
    <a href="{{ $android['app_url'] }}" target="_blank" rel="noopener" class="er-btn er-btn-outline">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M3.6 2.3 13.4 12 3.6 21.7a1.6 1.6 0 0 1-.6-1.3V3.6c0-.5.2-1 .6-1.3Zm11.1 10.8 2.7 2.7-10 5.7 7.3-8.4Zm0-2.2L7.4 2.5l10 5.7-2.7 2.7Zm1.4 1.1 3.3-1.9c.8-.4.8-1.6 0-2.1l-.6-.3-2.7 2.7v1.6Z"></path>
        </svg>
        <span style="display: flex; flex-direction: column; align-items: flex-start; line-height: 1.15">
            <span style="font-size: 11px; font-weight: 500; opacity: .75">{{ translate('Get it on') }}</span>
            <span style="font-size: 15px">{{ translate('Google Play') }}</span>
        </span>
    </a>
@endif

@if($ios && ($ios['app_url'] ?? null))
    <a href="{{ $ios['app_url'] }}" target="_blank" rel="noopener" class="er-btn er-btn-outline">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M16.4 12.7c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9s-1.8-.9-3-.8c-1.5 0-2.9.9-3.7 2.3-1.6 2.7-.4 6.8 1.1 9 .8 1.1 1.7 2.3 2.9 2.2 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.2.9-1.2 1.3-2.5 1.3-2.5s-2.5-1-2.5-3.6ZM14.2 5.4c.6-.8 1-1.9.9-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.8-1.3Z"></path>
        </svg>
        <span style="display: flex; flex-direction: column; align-items: flex-start; line-height: 1.15">
            <span style="font-size: 11px; font-weight: 500; opacity: .75">{{ translate('Download on the') }}</span>
            <span style="font-size: 15px">{{ translate('App Store') }}</span>
        </span>
    </a>
@endif
