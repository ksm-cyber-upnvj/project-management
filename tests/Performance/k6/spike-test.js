import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    spike: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },    // Normal load
        { duration: '10s', target: 1000 },  // Spike to 1000 users
        { duration: '1m', target: 1000 },   // Stay at spike
        { duration: '30s', target: 10 },    // Recovery
        { duration: '30s', target: 0 },     // Ramp down
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.10'],        // Less than 10% errors during spike
    http_req_duration: ['p(95)<10000'],    // 95% under 10s
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://app';

export default function () {
  // Simulate various user actions
  const scenarios = [
    () => {
      // Visit homepage
      const res = http.get(`${BASE_URL}/`);
      check(res, { 'homepage available': (r) => r.status === 200 });
    },
    () => {
      // Visit admin login
      const res = http.get(`${BASE_URL}/admin/login`);
      check(res, { 'login available': (r) => r.status === 200 });
    },
    () => {
      // Visit external dashboard
      const res = http.get(`${BASE_URL}/external/test-token`);
      check(res, { 'external accessible': (r) => r.status === 200 || r.status === 404 });
    },
  ];

  // Randomly execute one scenario
  const scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
  scenario();

  sleep(0.5);
}
