# Performance Testing with K6

Performance tests for Cytrack Project Management System using Grafana K6.

## Prerequisites

Ensure all Docker services are running:
```bash
docker compose up -d
```

## Available Tests

### 1. Load Test (`load-test.js`)
Tests average and peak load scenarios with concurrent users.

**Scenarios:**
- **Average Load**: 10 → 50 users over 2.5 minutes, maintain for 3 minutes
- **Peak Load**: Spike to 100 users for 1 minute

**Run:**
```bash
docker compose run --rm k6 run load-test.js
```

**What it tests:**
- Homepage performance
- External dashboard access
- Response times under normal load

---

### 2. Admin Test (`admin-test.js`)
Simulates concurrent admin users accessing the panel.

**Scenarios:**
- Ramp up: 10 → 30 concurrent admins
- Duration: 7 minutes total

**Run:**
```bash
docker compose run --rm k6 run admin-test.js
```

**What it tests:**
- Admin login page performance
- Dashboard access
- Resource pages (projects, tickets, users)

---

### 3. Spike Test (`spike-test.js`)
Tests system resilience under sudden traffic spikes.

**Scenarios:**
- Normal: 10 users
- Spike: 1000 users for 1 minute
- Recovery: Back to 10 users

**Run:**
```bash
docker compose run --rm k6 run spike-test.js
```

**What it tests:**
- System stability during traffic spikes
- Recovery capability
- Error rate during extreme load

---

### 4. Stress Test (`stress-test.js`)
Gradually increases load to find system limits.

**Scenarios:**
- Progressive ramp: 50 → 100 → 200 → 300 → 400 users
- Each stage: 1-2 minutes
- Total duration: 8 minutes

**Run:**
```bash
docker compose run --rm k6 run stress-test.js
```

**What it tests:**
- Maximum capacity
- Performance degradation patterns
- Breaking point identification

---

## Performance Thresholds

### Load Test
- Error rate: < 5%
- 95th percentile: < 2 seconds
- 99th percentile: < 5 seconds

### Admin Test
- Error rate: < 2%
- 95th percentile: < 3 seconds
- 99th percentile: < 6 seconds

### Spike Test
- Error rate: < 10%
- 95th percentile: < 10 seconds

### Stress Test
- Error rate: < 10%
- 95th percentile: < 5 seconds
- 99th percentile: < 10 seconds

---

## Running All Tests

Run all performance tests sequentially:
```bash
docker compose run --rm k6 run load-test.js
docker compose run --rm k6 run admin-test.js
docker compose run --rm k6 run spike-test.js
docker compose run --rm k6 run stress-test.js
```

---

## Understanding Results

K6 outputs metrics including:
- **http_req_duration**: Response time statistics (avg, min, max, p95, p99)
- **http_req_failed**: Percentage of failed requests
- **vus**: Number of active virtual users
- **iterations**: Total number of test iterations

**Example output:**
```
http_req_duration..............: avg=245ms  min=12ms  max=1.2s  p(95)=850ms
http_req_failed................: 0.23%
vus............................: 50
```

---

## Customization

Modify test parameters by editing the respective `.js` files:

**Change user count:**
```javascript
stages: [
  { duration: '1m', target: 20 },  // Change to desired user count
]
```

**Adjust thresholds:**
```javascript
thresholds: {
  http_req_duration: ['p(95)<3000'],  // Change threshold
}
```

---

## Troubleshooting

**Tests fail to connect:**
```bash
# Verify services are running
docker compose ps

# Check nginx is accessible
docker compose exec k6 wget -O- http://nginx
```

**High error rates:**
- Check application logs: `docker compose logs app`
- Verify database connection: `docker compose logs db`
- Review nginx logs: `docker compose logs nginx`

---

## Best Practices

1. **Run tests incrementally** - Start with load test, then progress to stress/spike
2. **Monitor system resources** - Use `docker stats` during tests
3. **Test during off-peak hours** - Avoid impacting production
4. **Baseline first** - Establish baseline metrics before optimization
5. **One test at a time** - Don't run multiple performance tests simultaneously

---

## Notes

- Tests use the `performance` profile in docker-compose.yml
- All tests target the nginx service (not external URLs)
- Tests don't require authentication (testing public endpoints)
- For authenticated testing, modify scripts to include login flow
