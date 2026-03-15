import http from 'k6/http';
import { sleep, check } from 'k6';
import { BASE_URL, DEFAULT_HEADERS, HIGH_LOAD_OPTIONS } from '../lib/config.js';

export const options = HIGH_LOAD_OPTIONS;

export default function () {
    const res = http.get(`${BASE_URL}/__missing__`, { headers: DEFAULT_HEADERS });

    check(res, {
        'status is 404': (r) => r.status === 404,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
    });

    // Уменьшаем задержку между запросами
    sleep(0.01); // 100ms вместо 1s
}
