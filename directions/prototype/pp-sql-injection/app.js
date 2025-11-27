const DEFAULT_RESULT_COUNT = 0;
function safeParse(str) { try { return JSON.parse(str); } catch { return null; } }

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


function runQuery() {
  const cfg = {};
  const useParam = cfg.useParam !== false; // 默认参数化，若原型被设置为 false 则改为拼接
  const input = String(document.getElementById('queryInput').value || '');
  const mode = useParam ? 'param' : 'concat';

  fetch(`./query.php?name=${encodeURIComponent(input)}&mode=${encodeURIComponent(mode)}`, {
    method: 'GET', headers: { 'Accept': 'application/json' }
  }).then(async (res) => {
    try {
      const data = await res.json();
      const count = Number(data.count ?? 0);
      document.getElementById('result').textContent = '结果：' + String(count) + ' 行';
      const hasFlag = typeof data.flag === 'string' && data.flag.includes('PP_SQLI_ATTACK_SUCCESS');
      document.getElementById('secretPanel').classList.toggle('hidden', !hasFlag);
    } catch (_) {
      document.getElementById('result').textContent = '结果：请求错误';
      document.getElementById('secretPanel').classList.add('hidden');
    }
  }).catch(() => {
    document.getElementById('result').textContent = '结果：网络错误';
    document.getElementById('secretPanel').classList.add('hidden');
  });
}

document.getElementById('applyBtn').addEventListener('click', () => {
  const parsed = safeParse(document.getElementById('payloadInput').value);
  if (parsed) injectProps(parsed);
  runQuery();
});

document.getElementById('resetBtn').addEventListener('click', () => {
  delete Object.prototype.useParam;
  runQuery();
});

document.getElementById('runBtn').addEventListener('click', () => {
  runQuery();
});

runQuery();
