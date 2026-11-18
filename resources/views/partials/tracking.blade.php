{{--
    Google Analytics and the Facebook Pixel set non-essential cookies, so they only load
    once the visitor has accepted cookies (UK GDPR / PECR). See partials.cookie_consent.
--}}
<script>
    window.esiayiLoadTrackers = function () {
        if (window.esiayiTrackersLoaded) { return; }
        window.esiayiTrackersLoaded = true;

        @if ($googleAnalytic && $googleAnalytic->status == 1 && $googleAnalytic->analytic_id)
        (function () {
            var id = @json($googleAnalytic->analytic_id);
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { dataLayer.push(arguments); };
            gtag('js', new Date());
            gtag('config', id);
        })();
        @endif

        @if ($facebookPixel && $facebookPixel->status == 1 && $facebookPixel->app_id)
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @json($facebookPixel->app_id));
        fbq('track', 'PageView');
        @endif
    };

    if (document.cookie.split('; ').indexOf('esiayi_consent=accepted') !== -1) {
        window.esiayiLoadTrackers();
    }
</script>
