{{--
    Cookie banner with an equal Accept / Reject choice. The choice is kept for 180 days in
    the esiayi_consent cookie; trackers (partials.tracking) only load after "accepted".
    Shown when the admin enables it, or whenever analytics or the pixel are switched on.
--}}
@php
    $consentNeeded = ($cookie_consent && $cookie_consent->status == 1)
        || ($googleAnalytic && $googleAnalytic->status == 1)
        || ($facebookPixel && $facebookPixel->status == 1);
@endphp
@if ($consentNeeded)
<div id="esiayi-consent" role="dialog" aria-live="polite" aria-label="{{ __('Cookie consent') }}" hidden
     style="position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:14px 16px;
            background:{{ $cookie_consent->background_color ?? '#ffffff' }};color:{{ $cookie_consent->text_color ?? '#1a1a1a' }};
            border-top:1px solid {{ $cookie_consent->border_color ?? '#e5e5e5' }};box-shadow:0 -2px 12px rgba(0,0,0,.08);">
    <div style="max-width:1140px;margin:0 auto;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
        <p style="margin:0;flex:1 1 320px;font-size:14px;line-height:1.5;color:inherit;">
            {{ $cookie_consent->message ?? __('We use cookies for analytics and advertising. You can accept or reject them.') }}
            <a href="{{ route('privacy-policy') }}" style="color:inherit;text-decoration:underline;">{{ $cookie_consent->link_text ?? __('More info') }}</a>
        </p>
        <div style="display:flex;gap:8px;flex:0 0 auto;">
            <button type="button" data-consent="rejected"
                    style="padding:8px 18px;border-radius:6px;border:1px solid {{ $cookie_consent->btn_bg_color ?? '#0ba3fa' }};background:transparent;color:inherit;font-size:14px;">
                {{ __('Reject') }}
            </button>
            <button type="button" data-consent="accepted"
                    style="padding:8px 18px;border-radius:6px;border:1px solid {{ $cookie_consent->btn_bg_color ?? '#0ba3fa' }};background:{{ $cookie_consent->btn_bg_color ?? '#0ba3fa' }};color:{{ $cookie_consent->btn_text_color ?? '#ffffff' }};font-size:14px;">
                {{ $cookie_consent->btn_text ?? __('Accept') }}
            </button>
        </div>
    </div>
</div>
<script>
    (function () {
        var banner = document.getElementById('esiayi-consent');
        if (!banner || /(?:^|; )esiayi_consent=/.test(document.cookie)) { return; }

        banner.hidden = false;
        // Keep the banner from covering page content such as the search button.
        document.body.style.paddingBottom = banner.offsetHeight + 'px';

        banner.addEventListener('click', function (e) {
            var choice = e.target.getAttribute('data-consent');
            if (!choice) { return; }
            document.cookie = 'esiayi_consent=' + choice + '; path=/; max-age=' + (180 * 24 * 60 * 60) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            banner.hidden = true;
            document.body.style.paddingBottom = '';
            if (choice === 'accepted' && window.esiayiLoadTrackers) { window.esiayiLoadTrackers(); }
        });
    })();
</script>
@endif
