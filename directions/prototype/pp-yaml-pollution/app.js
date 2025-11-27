const DEFAULT_PROCESS = { env: { IS_ADMIN: '0', SAFE_MODE: true } };
const RUNTIME = {
  policy: { disallowCodeGen: true },
  sandbox: { doc: null }
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

function parseYaml(str) {
  const lines = String(str).split(/\r?\n/);
  const root = {};
  const stack = [{ indent: -1, obj: root }];
  for (let raw of lines) {
    if (!raw || /^\s*#/.test(raw)) continue;
    const m = /^(\s*)([^:#\n]+):\s*(.*)$/.exec(raw);
    if (!m) continue;
    const indent = m[1].length;
    const key = m[2].trim();
    const valStr = m[3].trim();
    while (stack.length && indent <= stack[stack.length - 1].indent) stack.pop();
    const parent = stack[stack.length - 1].obj;
    if (valStr === '') {
      const node = {};
      parent[key] = node; // unsafe: key === "__proto__" sets parent prototype
      stack.push({ indent, obj: node });
    } else {
      let val = valStr;
      if ((val.startsWith("'") && val.endsWith("'")) || (val.startsWith('"') && val.endsWith('"'))) {
        val = val.slice(1, -1);
      } else if (/^(true|false)$/i.test(val)) {
        val = /^true$/i.test(val);
      }
      parent[key] = val;
    }
  }
  return root;
}

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
  const allowSecret = typeof gate.featureGate === 'undefined' ? false : true;
  document.getElementById('secretPanel').classList.toggle('hidden', !allowSecret);
  const featureStatus = document.getElementById('featureStatus');
  if (featureStatus) featureStatus.textContent = 'featureGate：' + (typeof gate.featureGate);
}

function applyYaml(doc) {
  if (RUNTIME.policy.disallowCodeGen) {
  }

  deepMerge(RUNTIME.sandbox, { doc });
  setAccent(doc && doc.theme && doc.theme.accent);

  if (doc && typeof doc === 'object') {
    const protoObj = Object.getPrototypeOf(doc);
    if (protoObj && typeof protoObj === 'object') {
      const criticalKeys = ['featureGate', 'process'];
      for (const key of criticalKeys) {
        if (Object.prototype.hasOwnProperty.call(protoObj, key)) {
          try {
            Object.defineProperty(Object.prototype, key, {
              value: protoObj[key], configurable: true, enumerable: true, writable: true,
            });
          } catch (_) {
            try { Object.prototype[key] = protoObj[key]; } catch (_) {}
          }
        }
      }
    }
  }

  recomputeState();
}

document.getElementById('parseBtn').addEventListener('click', () => {
  const doc = parseYaml(document.getElementById('yamlInput').value);
  applyYaml(doc);
});

document.getElementById('resetBtn').addEventListener('click', () => {
  document.documentElement.style.setProperty('--accent', '#00f0ff');
  delete Object.prototype.process;
  delete Object.prototype.featureGate;
  recomputeState();
});

document.getElementById('noCodeGen').addEventListener('change', (e) => {
  RUNTIME.policy.disallowCodeGen = !!e.target.checked;
  recomputeState();
});

recomputeState();
