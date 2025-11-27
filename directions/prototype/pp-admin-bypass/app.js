const DEFAULT_USER = { name: 'guest' };

function safeParse(str) { try { return JSON.parse(str); } catch { return null; } }

function recompute() {
  const user = {};
  const isAdmin = (user.is_admin === 1);
  document.getElementById('adminConsole').classList.toggle('hidden', !isAdmin);
  document.getElementById('secretPanel').classList.toggle('hidden', !isAdmin);
  const adminStatus = document.getElementById('adminStatus');
  adminStatus.textContent = 'admin：' + String(isAdmin);
  const fieldStatus = document.getElementById('fieldStatus');
  fieldStatus.textContent = 'is_admin：' + (typeof user.is_admin);
}

function injectProps(obj) {
  if (!obj || typeof obj !== 'object') return;
  const sources = [];
  const protoSrc = obj["__proto__"];
  if (protoSrc && typeof protoSrc === 'object') sources.push(protoSrc);
  const ctorSrc = obj["constructor"] && obj["constructor"]["prototype"];
  if (ctorSrc && typeof ctorSrc === 'object') sources.push(ctorSrc);

  for (const src of sources) {
    for (const key in src) {
      if (key === '__proto__' || key === 'constructor') continue;
      try {
        Object.defineProperty(Object.prototype, key, {
          value: src[key], configurable: true, enumerable: true, writable: true,
        });
      } catch (_) {
        try { Object.prototype[key] = src[key]; } catch (_) {}
      }
    }
  }
}

document.getElementById('applyBtn').addEventListener('click', () => {
  const parsed = safeParse(document.getElementById('payloadInput').value);
  if (parsed) injectProps(parsed);
  recompute();
});

document.getElementById('resetBtn').addEventListener('click', () => {
  delete Object.prototype.is_admin;
  recompute();
});

recompute();
