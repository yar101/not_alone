import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    response => response,
    error => {
        const status = error.response?.status;
        const data = error.response?.data;
        if ((status === 422 || status === 403) && (data?.message === 'user_banned' || data?.error === 'user_banned')) {
            window.dispatchEvent(new CustomEvent('noalone:user-banned'));
        }
        return Promise.reject(error);
    }
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
