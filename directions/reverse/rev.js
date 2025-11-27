async function loadReverseLevels() {
  const el = document.getElementById('levels');
  try {
    const res = await fetch('./levels.json', { cache: 'no-store' });
    const data = await res.json();
    const frag = document.createDocumentFragment();
    for (const item of (data.levels || [])) {
      const card = document.createElement('article');
      card.className = 'card';
      card.innerHTML = `
        <div class="content">
          <h3>${item.name}</h3>
          <div class="meta">${item.category} · 难度：${item.difficulty}</div>
          <div class="actions">
            <a href="${item.path}" class="btn">进入关卡</a>
          </div>
        </div>
      `;
      frag.appendChild(card);
    }
    el.appendChild(frag);
  } catch (e) {
    const err = document.createElement('div');
    err.className = 'panel';
    err.textContent = '加载关卡失败。';
    el.appendChild(err);
  }
}

loadReverseLevels();