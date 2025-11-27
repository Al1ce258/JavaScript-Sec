async function loadDirections() {
  const el = document.getElementById('directionsText');
  try {
    const res = await fetch('directions/directions.json', { cache: 'no-store' });
    const data = await res.json();
    const frag = document.createDocumentFragment();
    for (const item of (data.directions || [])) {
      const sec = document.createElement('section');
      sec.className = 'text-section';
      sec.innerHTML = `
        <div class="text-heading">${item.name}</div>
        <div class="rule"></div>
        <div class="levels-grid" aria-label="levels"></div>
      `;
      frag.appendChild(sec);

      // Derive levels.json path from direction entry path
      const levelsPath = item.path.replace(/index\.html$/, 'levels.json');
      try {
        const r = await fetch(levelsPath, { cache: 'no-store' });
        const d = await r.json();
        const grid = sec.querySelector('.levels-grid');
        const subFrag = document.createDocumentFragment();
        for (const lv of (d.levels || [])) {
          const card = document.createElement('article');
          card.className = 'card';
          card.innerHTML = `
            <div class="content">
              <h3>${lv.name}</h3>
              <div class="meta">${lv.category} · 难度：${lv.difficulty}</div>
              <div class="actions">
                <a href="${lv.path}" class="btn">进入关卡</a>
              </div>
            </div>
          `;
          subFrag.appendChild(card);
        }
        grid.appendChild(subFrag);
      } catch {}
    }
    el.appendChild(frag);
  } catch (e) {
    const err = document.createElement('div');
    err.className = 'panel';
    err.textContent = '加载方向失败。';
    el.appendChild(err);
  }
}

loadDirections();