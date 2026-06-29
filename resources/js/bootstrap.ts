import axios from 'axios';

if (typeof window !== 'undefined') {
    (window as any).axios = axios;
    (window as any).axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    (window as any).axios.defaults.baseURL = window.location.origin;
}
