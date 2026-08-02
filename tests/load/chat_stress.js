import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 10 }, 
    { duration: '20s', target: 50 }, 
    { duration: '30s', target: 50 }, 
    { duration: '10s', target: 0 },  
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  const BASE_URL = __ENV.BASE_URL || 'http://localhost';
  
  // Мы будем передавать куку через переменную окружения COOKIE
  const SESSION_COOKIE = __ENV.COOKIE || '';

  const params = {
    headers: {
      'Cookie': SESSION_COOKIE,
      // Этот заголовок заставит сервер отдавать лёгкий JSON (Inertia-ответ), а не собирать весь HTML каркас
      'X-Inertia': 'true', 
    },
  };

  // Бьём по странице со списком чатов, которую мы оптимизировали (убрали N+1)
  const res = http.get(`${BASE_URL}/conversations`, params);

  // Проверяем, что запрос прошёл успешно (200 OK) и нас не редиректнуло на логин (302)
  check(res, {
    'is status 200': (r) => r.status === 200,
    'not redirected to login': (r) => r.status !== 302,
  });

  sleep(1);
}
