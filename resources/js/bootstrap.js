import 'bootstrap';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

/**
 * Realtime chat settings come from the page (window.esiayiRealtime, filled from
 * Admin > Settings > Pusher) instead of being baked in at build time, so the same
 * build works on every environment. Without a key, Echo is not started.
 */
const realtime = window.esiayiRealtime || {};

if (realtime.key) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: realtime.key,
        cluster: realtime.cluster,
        forceTLS: true,
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios.post(realtime.authEndpoint, {
                        socket_id: socketId,
                        channel_name: channel.name
                    })
                    .then(response => callback(false, response.data))
                    .catch(error => callback(true, error));
            }
        }),
    });
}
