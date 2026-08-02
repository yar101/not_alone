import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 10 }, // Плавно доводим до 10 виртуальных пользователей (VUs) за 10 сек
    { duration: '20s', target: 50 }, // Плавно повышаем до 50 VUs за 20 сек
    { duration: '30s', target: 50 }, // Держим нагрузку в 50 VUs в течение 30 сек
    { duration: '10s', target: 0 },  // Плавно снижаем до 0 за 10 сек
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'], // 95% запросов должны выполняться быстрее 1 секунды
    http_req_failed: ['rate<0.01'],    // Уровень ошибок (500/502/404) должен быть меньше 1%
  },
};

export default function () {
  // Вы можете передавать базовый URL через переменную BASE_URL, по умолчанию бьёт в localhost
  const BASE_URL = __ENV.BASE_URL || 'http://localhost';

  // Имитируем заход на страницу каталога, которую мы только что оптимизировали
  const res = http.get(`${BASE_URL}/search`);

  check(res, {
    'is status 200': (r) => r.status === 200,
    'page loaded successfully': (r) => !r.error,
  });

  // Задержка в 1 секунду между запросами каждого "пользователя", чтобы имитировать чтение страницы
  sleep(1);
}
