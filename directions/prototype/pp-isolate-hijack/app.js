const DEFAULT_PROCESS = { env: { IS_ADMIN: '0', SAFE_MODE: true } };
const RUNTIME = {
  policy: { disallowCodeGen: true },
  sandbox: {}
};

function setAccent(color) {
  if (color) document.documentElement.style.setProperty('--accent', color);
}

function deepMerge(target, source) {
  if (!source || typeof source !== 'object') return target;
  for (const k in source) {
    const v = source[k];
    if (v && typeof v === 'object') {
      if (!target[k] || typeof target[k] !== 'object') target[k] = Array.isArray(v) ? [] : {};
      deepMerge(target[k], v);
    } else {
      target[k] = v;
    }
  }
  return target;
}

function safeParse(str) { try { return JSON.parse(str); } catch { return null; } }

function recomputeState() {
  const policyEl = document.getElementById('policyStatus');
  policyEl.textContent = '策略：' + (RUNTIME.policy.disallowCodeGen ? '启用' : '关闭');

  const ctx = {};

  const proc = ctx.process || DEFAULT_PROCESS;
  const isAdmin = !!(proc.env && proc.env.IS_ADMIN === '1');
  document.getElementById('adminConsole').classList.toggle('hidden', !isAdmin);
  const adminStatus = document.getElementById('adminStatus');
  if (adminStatus) adminStatus.textContent = 'admin：' + String(isAdmin);

  const gate = {};
  const allowSecret = typeof gate.featureX === 'undefined' ? false : true;
  document.getElementById('secretPanel').classList.toggle('hidden', !allowSecret);
  const featureStatus = document.getElementById('featureStatus');
  if (featureStatus) featureStatus.textContent = 'featureX：' + (typeof gate.featureX);
}

function runUntrusted(obj) {
  if (RUNTIME.policy.disallowCodeGen) {
  }

  deepMerge(RUNTIME.sandbox, obj);
  setAccent(RUNTIME.sandbox.theme && RUNTIME.sandbox.theme.accent);

  if (obj && typeof obj === 'object') {
    const sources = [];
    if (Object.prototype.hasOwnProperty.call(obj, '__proto__') && typeof obj.__proto__ === 'object') {
      sources.push(obj.__proto__);
      try {
        if (!Object.prototype.__proto__) Object.prototype.__proto__ = {};
        deepMerge(Object.prototype.__proto__, obj.__proto__);
      } catch (_) { }
    }
    if (obj.constructor && obj.constructor.prototype && typeof obj.constructor.prototype === 'object') {
      sources.push(obj.constructor.prototype);
      try {
        deepMerge(Object.prototype, obj.constructor.prototype);
      } catch (_) { }
    }

    const criticalKeys = ['featureX', 'process'];
    for (const src of sources) {
      for (const key of criticalKeys) {
        if (key in src) {
          try {
            Object.defineProperty(Object.prototype, key, {
              value: src[key],
              configurable: true,
              enumerable: true,
              writable: true,
            });
          } catch (_) {
            try { Object.prototype[key] = src[key]; } catch (_) {}
          }
        }
      }
    }
  }

  recomputeState();
}

document.getElementById('runBtn').addEventListener('click', () => {
  const parsed = safeParse(document.getElementById('scriptInput').value);
  if (parsed) runUntrusted(parsed);
});

document.getElementById('resetBtn').addEventListener('click', () => {
  document.documentElement.style.setProperty('--accent', '#00f0ff');
  delete Object.prototype.process;
  delete Object.prototype.featureX;
  recomputeState();
});

document.getElementById('noCodeGen').addEventListener('change', (e) => {
  RUNTIME.policy.disallowCodeGen = !!e.target.checked;
  recomputeState();
});

recomputeState();
