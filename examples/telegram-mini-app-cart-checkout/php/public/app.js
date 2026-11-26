
const telegram = window.Telegram?.WebApp;
const el = id => document.getElementById(id);
const rubles = new Intl.NumberFormat('ru-RU', {style: 'currency', currency: 'RUB'});
const pendingKey = 'tea-shop-pending-order';
let state, quote, pending, working = false;
const money = minor => rubles.format(minor / 100);

async function api(body) {
  const response = await fetch('api.php', {
    method: body ? 'POST' : 'GET',
    headers: {'Content-Type': 'application/json', 'X-Telegram-Init-Data': telegram?.initData || ''},
    body: body ? JSON.stringify(body) : undefined
  });
  const data = await response.json();
  if (!response.ok) {
    throw Object.assign(new Error(data.error || 'Запрос не выполнен'), {status: response.status});
  }
  return data;
}

function render() {
  if (!state) return;
  const controls = state.products.map(product => {
    const quantity = state.cart.find(item => item.id === product.id)?.quantity || 0;
    const line = document.createElement('div');
    line.className = 'row';
    const name = document.createElement('span');
    name.textContent = product.name + ' · ' + money(product.price_minor) + ' × ' + quantity;
    line.append(name);
    for (const [symbol, step, name] of [['−', -1, 'Уменьшить'], ['+', 1, 'Увеличить']]) {
      const btn = document.createElement('button');
      btn.textContent = symbol;
      btn.setAttribute('aria-label', name + ': ' + product.name);
      btn.disabled = working || !!pending || (step < 0 ? quantity === 0 : quantity >= Math.min(product.stock, 20));
      btn.onclick = () => interact(async () => {
        quote = null;
        state = await api({action: 'quantity', product: product.id, quantity: quantity + step, revision: state.revision});
      });
      line.append(btn);
    }
    return line;
  });
  el('products').replaceChildren(...controls);
  el('total').textContent = 'Текущий итог: ' + money(state.cart.reduce((sum, row) => sum + row.quantity * row.price_minor, 0));
}

function refreshControls() {
  render();
  el('calculate').disabled = working || !state?.cart.length || !!pending;
  el('preview').hidden = !quote || !!pending;
  el('preview').textContent = quote ? 'Расчёт: ' + quote.items.map(item =>
    item.name + ' × ' + item.quantity + ' по ' + money(item.price_minor)).join(', ') + '. К оформлению ' + money(quote.total) : '';
  el('place').hidden = !quote || !!pending;
  el('place').disabled = working;
  el('place').textContent = quote ? 'Оформить за ' + money(quote.total) : 'Оформить';
  el('retry').hidden = !pending;
  el('retry').disabled = working;
}

async function interact(action) {
  if (working) return;
  working = true;
  el('status').textContent = '';
  refreshControls();
  try {
    await action();
  } catch (err) {
    el('status').textContent = err.status ? err.message : 'Связь прервалась. Повторите проверку.';
    if (err.status === 409 || err.status === 422) {
      quote = null;
      if (pending) {
        localStorage.removeItem(pendingKey);
        pending = null;
      }
      try { state = await api(); } catch { /* Keep the last available cart until reloaded. */ }
    } else if (pending) {
      el('status').textContent += ' Проверьте заказ с тем же ключом.';
    }
  } finally {
    working = false;
    refreshControls();
  }
}

async function checkOrder() {
  const order = await api({action: 'place', ...pending});
  localStorage.removeItem(pendingKey);
  pending = null;
  quote = null;
  el('status').textContent = 'Заказ №' + order.order_id + ' сохранён. Сумма ' + money(order.total) + '.';
  try { state = await api(); } catch { state = null; el('products').replaceChildren(); el('total').textContent = ''; }
}

el('calculate').onclick = () => interact(async () => {
  quote = await api({action: 'preview', revision: state.revision});
});
el('place').onclick = () => interact(async () => {
  const operation = {quote: quote.quote, operation: crypto.randomUUID()};
  localStorage.setItem(pendingKey, JSON.stringify(operation));
  pending = operation;
  await checkOrder();
});
el('retry').onclick = () => interact(checkOrder);
telegram?.ready();
try {
  pending = JSON.parse(localStorage.getItem(pendingKey) || 'null');
} catch {
  pending = null;
}
interact(async () => {
  state = await api();
  if (pending) el('status').textContent = 'Осталось проверить результат предыдущего оформления.';
});
