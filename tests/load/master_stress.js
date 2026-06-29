import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 20 },  // Быстрый разгон до 20
    { duration: '30s', target: 100 }, // Жесткий разгон до 100 одновременных пользователей!
    { duration: '30s', target: 100 }, // Держим пиковую нагрузку
    { duration: '10s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'], // Допускаем до 5% ошибок, так как это сложный флоу
  },
};

export default function () {
  const BASE_URL = __ENV.BASE_URL;
  const IDOL_ID = __ENV.IDOL_ID;
  const SERVICE_ID = __ENV.SERVICE_ID;
  const CONV_ID = __ENV.CONV_ID;

  // 1. Инициализация (заходим на страницу логина, чтобы получить CSRF токен)
  let initRes = http.get(`${BASE_URL}/login`, {
      headers: { 'Accept': 'application/json' }
  });
  
  if (!initRes.cookies['XSRF-TOKEN']) {
      console.error("XSRF-TOKEN не получен с сервера!");
      return;
  }
  
  // Laravel присылает токен в куке, мы должны раскодировать его для отправки в заголовке
  let csrfToken = decodeURIComponent(initRes.cookies['XSRF-TOKEN'][0].value);

  const baseHeaders = {
      'Accept': 'application/json',
  };

  // 2. Авторизация
  let loginRes = http.post(`${BASE_URL}/login`, {
      email: 'k6_bot@noalone.test',
      password: 'password',
  }, {
      headers: Object.assign({}, baseHeaders, { 'X-XSRF-TOKEN': csrfToken })
  });

  if (loginRes.status >= 400) {
      console.log(`[LOGIN ERROR] Status: ${loginRes.status} Body: ${loginRes.body}`);
  }

  check(loginRes, { 'Успешная авторизация': (r) => r.status < 400 });

  // При успешной авторизации Laravel ротирует сессию и выдаёт НОВЫЙ CSRF токен
  if (loginRes.cookies['XSRF-TOKEN']) {
      csrfToken = decodeURIComponent(loginRes.cookies['XSRF-TOKEN'][0].value);
  }
  
  // Обновляем базовые заголовки новым токеном для всех последующих POST-запросов
  const authHeaders = Object.assign({}, baseHeaders, { 'X-XSRF-TOKEN': csrfToken });
  const jsonHeaders = Object.assign({}, authHeaders, { 'Content-Type': 'application/json' });

  sleep(1); // Имитируем, что юзер грузит главную

  // 3. Просмотр каталога
  let searchRes = http.get(`${BASE_URL}/search`, { headers: authHeaders });
  check(searchRes, { 'Каталог загружен': (r) => r.status === 200 });

  sleep(1); // Юзер выбирает айдола

  // 4. Оформление заказа (добавление в корзину и покупка)
  const orderPayload = JSON.stringify({
      idol_id: parseInt(IDOL_ID),
      services: [{ id: parseInt(SERVICE_ID), quantity: 1 }]
  });
  
  let orderRes = http.post(`${BASE_URL}/orders`, orderPayload, { headers: jsonHeaders });
  check(orderRes, { 'Заказ успешно создан': (r) => r.status < 400 });

  sleep(1); // Юзер переходит в чат

  // 5. Загрузка чатов и отправка сообщения
  let chatListRes = http.get(`${BASE_URL}/conversations`, { headers: authHeaders });
  check(chatListRes, { 'Список чатов загружен': (r) => r.status === 200 });

  const msgPayload = JSON.stringify({
      body: `Load test message from VU ${__VU} at ${new Date().toISOString()}`,
  });
  
  let msgRes = http.post(`${BASE_URL}/conversations/${CONV_ID}/messages`, msgPayload, { headers: jsonHeaders });
  check(msgRes, { 'Сообщение в чат отправлено': (r) => r.status < 400 });

  sleep(1);
}
