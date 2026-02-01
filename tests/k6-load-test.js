// =====================================================
// K6 LOAD TEST SCRIPT
// Movie Booking System - Auto-Scaling Demo
// =====================================================

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');

// Test configuration
export let options = {
  stages: [
    { duration: '1m', target: 50 },   // Ramp up to 50 users
    { duration: '3m', target: 100 },  // Ramp up to 100 users
    { duration: '2m', target: 100 },  // Stay at 100 users
    { duration: '1m', target: 0 },    // Ramp down to 0
  ],
  thresholds: {
    'http_req_duration': ['p(95)<500'], // 95% of requests should be below 500ms
    'errors': ['rate<0.1'],             // Error rate should be less than 10%
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

export default function() {
  // Test 1: Get movies
  let moviesRes = http.get(`${BASE_URL}/api/movies`);
  check(moviesRes, {
    'movies: status is 200': (r) => r.status === 200,
    'movies: response time < 500ms': (r) => r.timings.duration < 500,
  }) || errorRate.add(1);

  sleep(1);

  // Test 2: Get showtimes
  let showtimesRes = http.get(`${BASE_URL}/api/showtimes`);
  check(showtimesRes, {
    'showtimes: status is 200': (r) => r.status === 200,
  }) || errorRate.add(1);

  sleep(1);

  // Test 3: Health checks
  let healthRes = http.get(`${BASE_URL}/api/auth/health`);
  check(healthRes, {
    'health: status is 200': (r) => r.status === 200,
  }) || errorRate.add(1);

  sleep(2);
}

export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
  };
}

function textSummary(data, options) {
  const indent = options.indent || '';
  const enableColors = options.enableColors || false;

  let summary = '\n';
  summary += `${indent}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  summary += `${indent}📊 LOAD TEST SUMMARY\n`;
  summary += `${indent}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  
  // Metrics
  const metrics = data.metrics;
  
  summary += `\n${indent}🔹 HTTP Requests:\n`;
  summary += `${indent}  Total: ${metrics.http_reqs.values.count}\n`;
  summary += `${indent}  Rate: ${metrics.http_reqs.values.rate.toFixed(2)} req/s\n`;
  
  summary += `\n${indent}🔹 Response Time:\n`;
  summary += `${indent}  Avg: ${metrics.http_req_duration.values.avg.toFixed(2)}ms\n`;
  summary += `${indent}  P95: ${metrics.http_req_duration.values['p(95)'].toFixed(2)}ms\n`;
  summary += `${indent}  P99: ${metrics.http_req_duration.values['p(99)'].toFixed(2)}ms\n`;
  
  summary += `\n${indent}🔹 Success Rate:\n`;
  const successRate = ((metrics.http_req_failed.values.rate === 0 ? 1 : 1 - metrics.http_req_failed.values.rate) * 100).toFixed(2);
  summary += `${indent}  ${successRate}%\n`;
  
  summary += `\n${indent}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  
  return summary;
}
