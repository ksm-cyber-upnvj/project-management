import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    stress: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 50 },     // Ramp to 50 users
        { duration: '1m', target: 100 },    // Ramp to 100 users
        { duration: '1m', target: 200 },    // Ramp to 200 users
        { duration: '1m', target: 300 },    // Ramp to 300 users
        { duration: '1m', target: 400 },    // Ramp to 400 users
        { duration: '2m', target: 400 },    // Stay at 400 users
        { duration: '1m', target: 0 },      // Ramp down
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.10'],        // Less than 10% errors
    http_req_duration: ['p(95)<5000'],     // 95% under 5s
    http_req_duration: ['p(99)<10000'],    // 99% under 10s
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://app';

export default function () {
  // Test critical paths
  const tests = [
    () => {
      const res = http.get(`${BASE_URL}/`);
      check(res, { 'homepage status 200': (r) => r.status === 200 });
      return 1;
    },
    () => {
      const res = http.get(`${BASE_URL}/admin/login`);
      check(res, { 'login status 200': (r) => r.status === 200 });
      return 1;
    },
    () => {
      const res = http.get(`${BASE_URL}/admin`);
      check(res, { 'admin accessible': (r) => r.status === 200 || r.status === 302 });
      return 2;
    },
  ];

  // Execute random test
  const test = tests[Math.floor(Math.random() * tests.length)];
  const sleepDuration = test();

  sleep(sleepDuration);
}
