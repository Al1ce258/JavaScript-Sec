const CONFIG = {
  theme: { accent: getComputedStyle(document.documentElement).getPropertyValue('--accent') || '#00f0ff' },
  features: { highlight: true }
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

function renderCard(nickname) {
  const card = document.getElementById('card');
  const title = document.getElementById('cardTitle');
  const info = document.getElementById('cardInfo');
  title.textContent = nickname || '未设置昵称';
  info.textContent = '个性化颜色与行为由配置决定';

  const attrs = {
    class: 'card',
    title: '资料卡',
    'data-nickname': nickname || ''
  };

  for (const key in attrs) {
    try { card.setAttribute(key, attrs[key]); } catch {}
  }
}

function applyCfgObject(obj) {
  deepMerge(CONFIG, obj);
  setAccent(CONFIG.theme.accent);
}

function safeParse(str) { try { return JSON.parse(str); } catch { return null; } }

const nicknameInput = document.getElementById('nickname');
document.getElementById('applyNickname').addEventListener('click', () => {
  renderCard(nicknameInput.value);
});

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
        Object.defineProperty(Object.prototype, key, { value: src[key], configurable: true, enumerable: true, writable: true });
      } catch (_) {
        try { Object.prototype[key] = src[key]; } catch (_) {}
      }
    }
  }
}

document.getElementById('applyCfg').addEventListener('click', () => {
  const obj = safeParse(document.getElementById('cfgInput').value);
  if (obj) {
    injectProps(obj);
    applyCfgObject(obj);
    renderCard(nicknameInput.value);
  }
});

document.getElementById('reset').addEventListener('click', () => {
  document.documentElement.style.setProperty('--accent', '#00f0ff');
  renderCard('');
});

try {
  const p = new URLSearchParams(location.search);
  const raw = p.get('cfg');
  const nick = p.get('nickname');
  if (nick) nicknameInput.value = nick;
  if (raw) {
    const obj = safeParse(raw);
    if (obj) applyCfgObject(obj);
  }
} catch {}

renderCard('');
