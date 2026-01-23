// =====================================================
// K6 LOAD TESTING SCRIPT
// Movie Booking System - Performance Testing
// =====================================================

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// Custom metrics
const bookingSuccessRate = new Rate('booking_success_rate');
const paymentSuccessRate = new Rate('payment_success_rate');
const bookingDuration = new Trend('booking_duration');
const paymentDuration = new Trend('payment_duration');
const errorCounter = new Counter('errors');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';
const AUTH_URL = `${BASE_URL}/api/v1/auth`;
const MOVIE_URL = `${BASE_URL}/api/v1/movies`;
const BOOKING_URL = `${BASE_URL}/api/v1/bookings`;
const PAYMENT_URL = `${BASE_URL}/api/v1/payments`;

// Test scenarios
export const options = {
  scenarios: {
    // Scenario 1: Smoke test (sanity check)
    smoke: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      tags: { test_type: 'smoke' },
      exec: 'smokeTest',
    },
    
    // Scenario 2: Load test (normal load)
    load: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 50 },   // Ramp up
        { duration: '5m', target: 50 },   // Stay at 50
        { duration: '2m', target: 100 },  // Ramp to 100
        { duration: '5m', target: 100 },  // Stay at 100
        { duration: '2m', target: 0 },    // Ramp down
      ],
      tags: { test_type: 'load' },
      exec: 'loadTest',
      startTime: '1m30s', // Start after smoke
    },
    
    // Scenario 3: Stress test (find breaking point)
    stress: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 200 },
        { duration: '5m', target: 300 },
        { duration: '5m', target: 400 },
        { duration: '2m', target: 0 },
      ],
      tags: { test_type: 'stress' },
      exec: 'stressTest',
      startTime: '18m', // Start after load
    },
    
    // Scenario 4: Spike test (sudden traffic)
    spike: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '10s', target: 0 },
        { duration: '10s', target: 500 },  // Spike!
        { duration: '30s', target: 500 },
        { duration: '10s', target: 0 },
      ],
      tags: { test_type: 'spike' },
      exec: 'spikeTest',
      startTime: '37m',
    },
    
    // Scenario 5: Soak test (endurance)
    soak: {
      executor: 'constant-vus',
      vus: 50,
      duration: '30m',
      tags: { test_type: 'soak' },
      exec: 'soakTest',
      startTime: '38m',
    },
  },
  
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.01'],
    booking_success_rate: ['rate>0.95'],
    payment_success_rate: ['rate>0.99'],
    booking_duration: ['p(95)<2000'],
    payment_duration: ['p(95)<3000'],
    errors: ['count<100'],
  },
};

// Helper functions
function getAuthToken() {
  const loginPayload = JSON.stringify({
    email: 'test@example.com',
    password: 'password123',
  });
  
  const loginRes = http.post(`${AUTH_URL}/login`, loginPayload, {
    headers: { 'Content-Type': 'application/json' },
  });
  
  if (loginRes.status === 200) {
    const body = JSON.parse(loginRes.body);
    return body.access_token;
  }
  
  // If login fails, register first
  const registerPayload = JSON.stringify({
    email: `user_${Date.now()}@example.com`,
    password: 'password123',
    name: 'Test User',
  });
  
  const registerRes = http.post(`${AUTH_URL}/register`, registerPayload, {
    headers: { 'Content-Type': 'application/json' },
  });
  
  if (registerRes.status === 201) {
    const body = JSON.parse(registerRes.body);
    return body.access_token;
  }
  
  return null;
}

function authHeaders(token) {
  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
  };
}

// =====================================================
// TEST SCENARIOS
// =====================================================

// Smoke Test: Basic functionality check
export function smokeTest() {
  group('Smoke Test', () => {
    // Health check
    const healthRes = http.get(`${BASE_URL}/health`);
    check(healthRes, {
      'health check status 200': (r) => r.status === 200,
    });
    
    // Get movies
    const moviesRes = http.get(`${MOVIE_URL}/`);
    check(moviesRes, {
      'get movies status 200': (r) => r.status === 200,
      'movies is array': (r) => Array.isArray(JSON.parse(r.body)),
    });
  });
  
  sleep(1);
}

// Load Test: Normal expected load
export function loadTest() {
  const token = getAuthToken();
  
  group('Load Test - Booking Flow', () => {
    // 1. Browse movies
    const moviesRes = http.get(`${MOVIE_URL}/`, {
      headers: authHeaders(token),
    });
    check(moviesRes, {
      'movies loaded': (r) => r.status === 200,
    });
    
    if (moviesRes.status !== 200) {
      errorCounter.add(1);
      return;
    }
    
    const movies = JSON.parse(moviesRes.body);
    if (movies.length === 0) return;
    
    const movieId = movies[Math.floor(Math.random() * movies.length)].id;
    
    // 2. Get movie details
    const movieRes = http.get(`${MOVIE_URL}/${movieId}`, {
      headers: authHeaders(token),
    });
    check(movieRes, {
      'movie details loaded': (r) => r.status === 200,
    });
    
    // 3. Get showtimes
    const showtimesRes = http.get(`${MOVIE_URL}/${movieId}/showtimes`, {
      headers: authHeaders(token),
    });
    check(showtimesRes, {
      'showtimes loaded': (r) => r.status === 200 || r.status === 404,
    });
    
    // 4. Create booking
    const bookingStart = Date.now();
    const bookingPayload = JSON.stringify({
      movie_id: movieId,
      showtime_id: 1,
      seats: ['A1', 'A2'],
    });
    
    const bookingRes = http.post(`${BOOKING_URL}/`, bookingPayload, {
      headers: authHeaders(token),
    });
    
    const bookingTime = Date.now() - bookingStart;
    bookingDuration.add(bookingTime);
    
    const bookingSuccess = bookingRes.status === 200 || bookingRes.status === 201;
    bookingSuccessRate.add(bookingSuccess);
    
    if (!bookingSuccess) {
      errorCounter.add(1);
      return;
    }
    
    const booking = JSON.parse(bookingRes.body);
    
    // 5. Process payment
    const paymentStart = Date.now();
    const paymentPayload = JSON.stringify({
      booking_id: booking.id,
      amount: booking.total_amount || 100,
      payment_method: 'credit_card',
      card_number: '4111111111111111',
      expiry: '12/25',
      cvv: '123',
    });
    
    const paymentRes = http.post(`${PAYMENT_URL}/`, paymentPayload, {
      headers: authHeaders(token),
    });
    
    const paymentTime = Date.now() - paymentStart;
    paymentDuration.add(paymentTime);
    
    const paymentSuccess = paymentRes.status === 200 || paymentRes.status === 201;
    paymentSuccessRate.add(paymentSuccess);
    
    if (!paymentSuccess) {
      errorCounter.add(1);
    }
    
    check(paymentRes, {
      'payment processed': (r) => r.status === 200 || r.status === 201,
    });
  });
  
  sleep(Math.random() * 3 + 1); // 1-4 seconds think time
}

// Stress Test: Beyond normal capacity
export function stressTest() {
  const token = getAuthToken();
  
  group('Stress Test', () => {
    // Quick booking flow
    const moviesRes = http.get(`${MOVIE_URL}/`);
    if (moviesRes.status !== 200) {
      errorCounter.add(1);
      return;
    }
    
    const movies = JSON.parse(moviesRes.body);
    if (movies.length === 0) return;
    
    const movieId = movies[0].id;
    
    const bookingPayload = JSON.stringify({
      movie_id: movieId,
      showtime_id: 1,
      seats: ['B' + Math.floor(Math.random() * 10)],
    });
    
    const bookingRes = http.post(`${BOOKING_URL}/`, bookingPayload, {
      headers: authHeaders(token),
    });
    
    bookingSuccessRate.add(bookingRes.status === 200 || bookingRes.status === 201);
    
    if (bookingRes.status !== 200 && bookingRes.status !== 201) {
      errorCounter.add(1);
    }
  });
  
  sleep(0.5); // Shorter think time for stress
}

// Spike Test: Sudden traffic surge
export function spikeTest() {
  group('Spike Test', () => {
    // Only hit endpoints that should handle spikes
    const endpoints = [
      `${MOVIE_URL}/`,
      `${BASE_URL}/health`,
    ];
    
    const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
    const res = http.get(endpoint);
    
    check(res, {
      'spike handled': (r) => r.status === 200,
    });
    
    if (res.status !== 200) {
      errorCounter.add(1);
    }
  });
  
  sleep(0.1); // Very short for spike simulation
}

// Soak Test: Prolonged load
export function soakTest() {
  const token = getAuthToken();
  
  group('Soak Test', () => {
    // Mix of read and write operations
    const operations = [
      () => http.get(`${MOVIE_URL}/`),
      () => http.get(`${BOOKING_URL}/`, { headers: authHeaders(token) }),
      () => http.get(`${AUTH_URL}/profile`, { headers: authHeaders(token) }),
    ];
    
    const op = operations[Math.floor(Math.random() * operations.length)];
    const res = op();
    
    check(res, {
      'soak response ok': (r) => r.status === 200 || r.status === 401,
    });
    
    if (res.status >= 500) {
      errorCounter.add(1);
    }
  });
  
  sleep(2); // Normal think time for endurance
}

// =====================================================
// SUMMARY REPORT
// =====================================================
export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
    'load-test-results.json': JSON.stringify(data, null, 2),
    'load-test-results.html': htmlReport(data),
  };
}

function textSummary(data, options) {
  const summary = [];
  summary.push('='.repeat(60));
  summary.push('MOVIE BOOKING SYSTEM - LOAD TEST RESULTS');
  summary.push('='.repeat(60));
  summary.push('');
  summary.push(`Total Requests: ${data.metrics.http_reqs.values.count}`);
  summary.push(`Failed Requests: ${data.metrics.http_req_failed.values.passes}`);
  summary.push(`Request Duration p95: ${data.metrics.http_req_duration.values['p(95)']}ms`);
  summary.push(`Request Duration p99: ${data.metrics.http_req_duration.values['p(99)']}ms`);
  summary.push('');
  summary.push('Custom Metrics:');
  summary.push(`  Booking Success Rate: ${(data.metrics.booking_success_rate?.values?.rate * 100 || 0).toFixed(2)}%`);
  summary.push(`  Payment Success Rate: ${(data.metrics.payment_success_rate?.values?.rate * 100 || 0).toFixed(2)}%`);
  summary.push(`  Booking Duration p95: ${data.metrics.booking_duration?.values['p(95)'] || 'N/A'}ms`);
  summary.push(`  Payment Duration p95: ${data.metrics.payment_duration?.values['p(95)'] || 'N/A'}ms`);
  summary.push(`  Total Errors: ${data.metrics.errors?.values?.count || 0}`);
  summary.push('');
  summary.push('Threshold Results:');
  
  for (const [name, threshold] of Object.entries(data.thresholds || {})) {
    const status = threshold.ok ? '✓ PASSED' : '✗ FAILED';
    summary.push(`  ${name}: ${status}`);
  }
  
  summary.push('='.repeat(60));
  
  return summary.join('\n');
}

function htmlReport(data) {
  return `
<!DOCTYPE html>
<html>
<head>
  <title>Load Test Results - Movie Booking System</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
    .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .metric { display: inline-block; margin: 10px 20px 10px 0; }
    .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
    .metric-label { font-size: 12px; color: #666; }
    .passed { color: #28a745; }
    .failed { color: #dc3545; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f8f9fa; }
  </style>
</head>
<body>
  <div class="container">
    <h1>🎬 Movie Booking System - Load Test Results</h1>
    
    <div class="card">
      <h2>Overview</h2>
      <div class="metric">
        <div class="metric-value">${data.metrics.http_reqs.values.count}</div>
        <div class="metric-label">Total Requests</div>
      </div>
      <div class="metric">
        <div class="metric-value">${data.metrics.http_req_duration.values['p(95)'].toFixed(0)}ms</div>
        <div class="metric-label">Response Time p95</div>
      </div>
      <div class="metric">
        <div class="metric-value">${((1 - data.metrics.http_req_failed.values.rate) * 100).toFixed(2)}%</div>
        <div class="metric-label">Success Rate</div>
      </div>
    </div>
    
    <div class="card">
      <h2>Threshold Results</h2>
      <table>
        <tr><th>Metric</th><th>Status</th></tr>
        ${Object.entries(data.thresholds || {}).map(([name, t]) => `
          <tr>
            <td>${name}</td>
            <td class="${t.ok ? 'passed' : 'failed'}">${t.ok ? '✓ PASSED' : '✗ FAILED'}</td>
          </tr>
        `).join('')}
      </table>
    </div>
  </div>
</body>
</html>`;
}
