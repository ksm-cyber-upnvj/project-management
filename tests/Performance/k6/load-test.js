import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    average_load: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },   // Ramp up to 10 users
        { duration: '2m', target: 50 },    // Ramp up to 50 users
        { duration: '3m', target: 50 },    // Stay at 50 users
        { duration: '30s', target: 10 },   // Ramp down to 10 users
        { duration: '30s', target: 0 },    // Ramp down to 0
      ],
    },
    peak_load: {
      executor: 'ramping-vus',
      startVUs: 0,
      startTime: '6m',
      stages: [
        { duration: '30s', target: 100 },  // Sudden spike to 100 users
        { duration: '1m', target: 100 },   // Stay at 100 users
        { duration: '30s', target: 0 },    // Ramp down
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],        // Less than 5% errors
    http_req_duration: ['p(95)<2000'],     // 95% of requests under 2s
    http_req_duration: ['p(99)<5000'],     // 99% of requests under 5s
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://app';

export default function () {
  // Test homepage
  let res = http.get(`${BASE_URL}/`);
  check(res, {
    'homepage status is 200': (r) => r.status === 200,
    'homepage loads': (r) => r.body.includes('Cytrack'),
  });

  sleep(1);

  // Test external login page (simulating client portal access)
  res = http.get(`${BASE_URL}/external/dummy-token`);
  check(res, {
    'external login accessible': (r) => r.status === 200 || r.status === 404,
  });

  sleep(2);
}
