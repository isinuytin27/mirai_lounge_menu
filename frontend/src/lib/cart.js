// Корзина в localStorage. Ключ версионирован, чтобы будущие изменения формата
// не ломали старые данные.
const KEY = 'mirai_cart_v2';

/** @returns {Record<string,{id:string,qty:number}>} */
export function loadCart() {
  try {
    const raw = localStorage.getItem(KEY);
    const data = raw ? JSON.parse(raw) : null;
    return data && typeof data === 'object' ? data.items || {} : {};
  } catch {
    return {};
  }
}

export function saveCart(items) {
  try {
    localStorage.setItem(KEY, JSON.stringify({ items }));
  } catch { /* приватный режим / переполнение — тихо игнорируем */ }
}

export function clearCart() {
  saveCart({});
}

export function cartCount(items) {
  return Object.values(items).reduce((n, it) => n + (Number(it.qty) || 0), 0);
}

export function cartTotal(items, priceById) {
  return Object.values(items).reduce(
    (sum, it) => sum + (Number(priceById[it.id]) || 0) * (Number(it.qty) || 0),
    0
  );
}
