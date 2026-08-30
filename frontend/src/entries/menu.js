import '../styles/menu.css';
import { getJson, postJson, errorText } from '../lib/apiClient.js';
import { loadCart, saveCart, clearCart, cartCount, cartTotal } from '../lib/cart.js';

const root = document.querySelector('[data-menu-root]');
if (root) init(root);

async function init(root) {
  const listEl = root.querySelector('[data-menu-list]');
  const navEl = root.querySelector('[data-cat-nav]');
  // Панель корзины — сосед <main data-menu-root>, а не потомок: ищем по документу.
  const barEl = document.querySelector('[data-cart-bar]');

  const { ok, data } = await getJson('/api/menu');
  if (!ok || !data?.categories?.length) {
    listEl.innerHTML = '<p class="state-msg">Меню временно недоступно.</p>';
    return;
  }

  const categories = data.categories;
  const priceById = {};
  categories.forEach((c) => c.products.forEach((p) => { priceById[p.id] = p.price; }));

  let cart = loadCart();

  renderNav(navEl, categories);
  renderList(listEl, categories);
  bindSteppers(root, () => cart, (next) => { cart = next; saveCart(cart); syncBar(); });
  syncCards();
  syncBar();

  // клик по категории — скролл к секции
  navEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-cat]');
    if (!btn) return;
    document.getElementById('cat-' + btn.dataset.cat)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // подсветка активной категории при скролле
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (en.isIntersecting) {
        const id = en.target.id.replace('cat-', '');
        navEl.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b.dataset.cat === id));
      }
    });
  }, { rootMargin: '-64px 0px -70% 0px' });
  root.querySelectorAll('.cat-section').forEach((s) => observer.observe(s));

  // оформление заказа
  barEl.querySelector('[data-checkout]').addEventListener('click', () => submitOrder(root, cart, () => {
    cart = {}; clearCart(); syncCards(); syncBar();
  }));

  function syncCards() {
    root.querySelectorAll('[data-product]').forEach((el) => {
      const id = el.dataset.product;
      const qty = cart[id]?.qty || 0;
      el.querySelector('[data-qty]').textContent = qty;
      el.classList.toggle('has-qty', qty > 0);
      el.querySelector('.stepper').dataset.state = qty > 0 ? 'active' : 'idle';
    });
  }

  function syncBar() {
    const count = cartCount(cart);
    const total = cartTotal(cart, priceById);
    barEl.querySelector('[data-count]').textContent = declOfNum(count, ['позиция', 'позиции', 'позиций']);
    barEl.querySelector('[data-total]').textContent = formatRub(total);
    barEl.classList.toggle('show', count > 0);
    barEl.querySelector('[data-checkout]').disabled = count === 0;
    syncCards();
  }

  root._syncBar = syncBar;
}

function renderNav(navEl, categories) {
  navEl.innerHTML = categories
    .map((c, i) => `<button data-cat="${esc(c.id)}" class="${i === 0 ? 'active' : ''}">${esc(c.title)}</button>`)
    .join('');
}

function renderList(listEl, categories) {
  listEl.innerHTML = categories.map((c) => `
    <section class="cat-section" id="cat-${esc(c.id)}">
      <h2><span class="line-dot line-${esc(c.line)}"></span>${esc(c.title)}</h2>
      ${c.products.map((p) => card(p)).join('')}
    </section>
  `).join('');
}

function card(p) {
  return `
    <article class="card" data-product="${esc(p.id)}">
      <div class="info">
        <div class="name">${esc(p.name)}${p.weight ? `<span class="weight">${esc(p.weight)}</span>` : ''}</div>
        ${p.description_short || p.description ? `<div class="desc">${esc(p.description_short || p.description)}</div>` : ''}
        <div class="price">${formatRub(p.price)}</div>
      </div>
      <div class="stepper" data-state="idle">
        <button data-dec aria-label="Убрать">−</button>
        <span class="qty" data-qty>0</span>
        <button data-inc class="add" aria-label="Добавить">＋</button>
      </div>
    </article>`;
}

function bindSteppers(root, getCart, setCart) {
  root.addEventListener('click', (e) => {
    const inc = e.target.closest('[data-inc]');
    const dec = e.target.closest('[data-dec]');
    if (!inc && !dec) return;
    const el = e.target.closest('[data-product]');
    if (!el) return;
    const id = el.dataset.product;
    const cart = { ...getCart() };
    const qty = cart[id]?.qty || 0;
    const next = Math.max(0, Math.min(99, qty + (inc ? 1 : -1)));
    if (next === 0) delete cart[id];
    else cart[id] = { id, qty: next };
    setCart(cart);
  });
}

async function submitOrder(root, cart, onSuccess) {
  const items = Object.values(cart).filter((it) => it.qty > 0).map((it) => ({ id: it.id, qty: it.qty }));
  if (!items.length) return;
  const btn = root.querySelector('[data-checkout]');
  btn.disabled = true;
  const { ok, data } = await postJson('/api/order-submit', { items });
  btn.disabled = false;
  if (!ok) {
    toast(errorText(data?.error), 'err');
    return;
  }
  onSuccess();
  let msg = data.append ? 'Дозаказ отправлен ✓' : 'Заказ отправлен ✓';
  if (data.telegram_ok === false) msg += ' (уведомление в TG не дошло — заказ в панели есть)';
  toast(msg, 'ok');
}

/* ── утилиты ── */
function formatRub(v) {
  return new Intl.NumberFormat('ru-RU').format(v) + ' ₽';
}
function declOfNum(n, forms) {
  const rem10 = n % 10, rem100 = n % 100;
  const f = rem10 === 1 && rem100 !== 11 ? 0 : (rem10 >= 2 && rem10 <= 4 && (rem100 < 10 || rem100 >= 20) ? 1 : 2);
  return `${n} ${forms[f]}`;
}
function esc(s) {
  return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}
let toastTimer;
function toast(text, kind) {
  let el = document.querySelector('.toast');
  if (!el) { el = document.createElement('div'); el.className = 'toast'; document.body.appendChild(el); }
  el.textContent = text;
  el.className = `toast ${kind} show`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}
