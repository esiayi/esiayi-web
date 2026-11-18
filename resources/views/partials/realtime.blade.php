{{-- Pusher settings for resources/js/bootstrap.js; managed in Admin > Settings. --}}
@php
    $pusherCredential = \App\Models\PusherCredentail::first();
    $esiayiRealtime = [
        'key' => $pusherCredential->app_key ?? null,
        'cluster' => $pusherCredential->app_cluster ?? null,
        'authEndpoint' => url('/broadcasting/auth'),
    ];
@endphp
<script>
    window.esiayiRealtime = @json($esiayiRealtime);
</script>
