import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    admin_users: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '5s', target: 10 },    // Ramp up to 10 concurrent admins
        { duration: '5s', target: 30 },    // Ramp up to 30 concurrent admins
        { duration: '5s', target: 30 },    // Stay at 30 concurrent admins
        { duration: '1s', target: 0 },     // Ramp down
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],        // Less than 2% errors
    http_req_duration: ['p(95)<3000'],     // 95% under 3s
    http_req_duration: ['p(99)<6000'],     // 99% under 6s
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://app';

export default function () {
  // Test admin login page
  let res = http.get(`${BASE_URL}/admin/login`);
  check(res, {
    'admin login page loads': (r) => r.status === 200,
    'has login form': (r) => r.body.includes('Sign in') || r.body.includes('Login'),
  });

  sleep(1);

  // Test admin dashboard (redirects to login if not authenticated)
  res = http.get(`${BASE_URL}/admin`, {
    redirects: 0,
  });
  check(res, {
    'admin route accessible': (r) => r.status === 200 || r.status === 302,
  });

  sleep(2);

  // Test resource pages (projects, tickets)
  const resources = ['projects', 'tickets', 'users', 'notifications'];
  const resource = resources[Math.floor(Math.random() * resources.length)];

  res = http.get(`${BASE_URL}/admin/${resource}`, {
    redirects: 0,
  });
  check(res, {
    'resource page accessible': (r) => r.status === 200 || r.status === 302,
  });

  sleep(1);
}
