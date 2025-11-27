const DEFAULT_ATTEMPTS = 3;

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

function heavyCompute(ms) {
  const start = performance.now();
  while (performance.now() - start < ms) {
    Math.sqrt(1234567);
  }
}

function recomputeStatus(attempts, overloaded) {
  document.getElementById('attemptsStatus').textContent = 'attempts：' + (attempts === '默认' ? '默认' : String(attempts));
  document.getElementById('overloadStatus').textContent = 'overload：' + String(!!overloaded);
  document.getElementById('jobInfo').textContent = '任务：attempts=' + (attempts === '默认' ? '默认' : String(attempts));
  document.getElementById('secretPanel').classList.toggle('hidden', !overloaded);
}

document.getElementById('applyBtn').addEventListener('click', () => {
  const parsed = safeParse(document.getElementById('payloadInput').value);
  if (parsed) injectProps(parsed);
  recomputeStatus('默认', false);
});

document.getElementById('resetBtn').addEventListener('click', () => {
  delete Object.prototype.attempts;
  recomputeStatus('默认', false);
});

document.getElementById('runBtn').addEventListener('click', async () => {
  const job = {};
  let attempts = job.attempts;
  if (attempts == null) attempts = DEFAULT_ATTEMPTS;
  const n = Number(attempts);
  const overloaded = Number.isFinite(n) && n > 10000;
  const ms = Math.min(Math.max(n / 20, 50), 800);
  document.getElementById('progress').textContent = '进度：执行中...';
  await new Promise(resolve => setTimeout(resolve, 0));
  heavyCompute(ms);
  document.getElementById('progress').textContent = '进度：100%';
  recomputeStatus(n, overloaded);
});

recomputeStatus('默认', false);
