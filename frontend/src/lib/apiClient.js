// Единый JSON-клиент. Заменяет 4 копии fetch-логики старого фронта
// (menu.js / vip.js / amateur-cup.js / orders-page.js).

/**
 * POST JSON. Возвращает { ok, status, data }.
 * ok=false и на сетевой ошибке, и на не-2xx — вызывающий смотрит data.error.
 */
export async function postJson(url, body) {
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    let data = null;
    try { data = await res.json(); } catch { /* пустой/не-JSON ответ */ }
    return { ok: res.ok && data?.ok !== false, status: res.status, data };
  } catch {
    return { ok: false, status: 0, data: { error: 'network' } };
  }
}

/** GET JSON. Возвращает { ok, status, data }. */
export async function getJson(url) {
  try {
    const res = await fetch(url, { credentials: 'same-origin' });
    let data = null;
    try { data = await res.json(); } catch { /* ignore */ }
    return { ok: res.ok, status: res.status, data };
  } catch {
    return { ok: false, status: 0, data: { error: 'network' } };
  }
}

/** Человекочитаемые сообщения по кодам ошибок API. */
export const ERROR_MESSAGES = {
  no_table: 'Сессия стола истекла. Отсканируйте QR ещё раз.',
  no_valid_items: 'Позиции недоступны в меню.',
  empty_items: 'Корзина пуста.',
  too_fast: 'Подождите пару секунд и попробуйте снова.',
  network: 'Нет связи с сервером. Проверьте интернет.',
};

export function errorText(code) {
  return ERROR_MESSAGES[code] || 'Не удалось выполнить запрос. Попробуйте позже.';
}
