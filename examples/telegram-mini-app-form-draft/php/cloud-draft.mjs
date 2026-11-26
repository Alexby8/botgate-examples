export function cloudStore(tg, key = 'consultation_draft_v1') {
  if (!tg?.isVersionAtLeast('6.9') || !tg.CloudStorage) {
    throw new Error('CloudStorage недоступен: обновите Telegram');
  }
  let uncertain = false;
  function call(method, ...args) {
    if (uncertain) return Promise.reject(new Error('Перезапустите форму после сбоя хранения'));
    return new Promise((resolve, reject) => {
      let settled = false;
      const timer = setTimeout(() => {
        settled = true; uncertain = true;
        reject(new Error('Нет подтверждения хранения. Не закрывайте форму'));
      }, 10000);
      const done = (error, value) => {
        if (settled) return;
        settled = true; clearTimeout(timer);
        if (error || (method !== 'getItem' && value !== true)) {
          reject(new Error('Хранилище не подтвердило операцию'));
        } else resolve(value);
      };
      try { tg.CloudStorage[method](key, ...args, done); }
      catch (error) { clearTimeout(timer); settled = true; reject(error); }
    });
  }
  return {
    read: () => call('getItem'),
    write: value => {
      if (value.length > 4096) return Promise.reject(new Error('Черновик слишком большой'));
      return call('setItem', value);
    },
    remove: () => call('removeItem')
  };
}
