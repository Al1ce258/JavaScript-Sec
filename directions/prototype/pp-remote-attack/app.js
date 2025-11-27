const DEFAULT_PROCESS = { env: { IS_ADMIN: '0' } };
const RUNTIME = {
  remote: null,
};

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

function recompute() {
  const ctx = {};
  const proc = ctx.process || DEFAULT_PROCESS;
  const isAdmin = !!(proc.env && proc.env.IS_ADMIN === '1');
  document.getElementById('adminConsole').classList.toggle('hidden', !isAdmin);
  const adminStatus = document.getElementById('adminStatus');
  adminStatus.textContent = 'admin：' + String(isAdmin);

  const gate = {};
  const allowSecret = typeof gate.featureGate === 'undefined' ? false : true;
  document.getElementById('secretPanel').classList.toggle('hidden', !allowSecret);
  const featureStatus = document.getElementById('featureStatus');
  featureStatus.textContent = 'featureGate：' + (typeof gate.featureGate);
}

async function loadRemote(src) {
  try {
    const res = await fetch(src);
    const data = await res.json();
    RUNTIME.remote = data;
    document.getElementById('remoteStatus').textContent = '远程：已加载';
  } catch (e) {
    document.getElementById('remoteStatus').textContent = '远程：加载失败';
  }
}

function applyRemote() {
  if (!RUNTIME.remote || typeof RUNTIME.remote !== 'object') return;
  const sources = [];
  const protoSrc = RUNTIME.remote["__proto__"];
  if (protoSrc && typeof protoSrc === 'object') {
    sources.push(protoSrc);
  }
  const ctorSrc = RUNTIME.remote["constructor"] && RUNTIME.remote["constructor"]["prototype"];
  if (ctorSrc && typeof ctorSrc === 'object') {
    sources.push(ctorSrc);
  }

  const criticalKeys = ['featureGate', 'process'];
  for (const src of sources) {
    for (const key of criticalKeys) {
      if (Object.prototype.hasOwnProperty.call(src, key)) {
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

  recompute();
}

function reset() {
  delete Object.prototype.process;
  delete Object.prototype.featureGate;
  document.getElementById('remoteStatus').textContent = '远程：未加载';
  RUNTIME.remote = null;
  recompute();
}

const params = new URLSearchParams(location.search);
const srcParam = params.get('src');
const srcInput = document.getElementById('srcInput');
if (srcParam) srcInput.value = srcParam;

document.getElementById('loadRemoteBtn').addEventListener('click', async () => {
  const src = srcInput.value.trim() || './poc.json';
  await loadRemote(src);
  applyRemote();
});

document.getElementById('applyBtn').addEventListener('click', () => {
  applyRemote();
});

document.getElementById('resetBtn').addEventListener('click', () => {
  reset();
});

recompute();
