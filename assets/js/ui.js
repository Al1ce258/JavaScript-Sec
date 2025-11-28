export function renderShell() {
  const app = document.getElementById('app');
  const nav = document.createElement('nav');
  nav.className = 'nav';
  nav.innerHTML = `
    <div class="brand">
      <div class="logo"></div>
      <div class="name">JS-Sec Labs</div>
    </div>
    <div class="links">
      <a href="/">首页</a>
      <a href="#" onclick="location.reload()">刷新</a>
    </div>
  `;
  const bg = document.createElement('div');
  bg.className = 'particles';
  app.prepend(nav);
  app.prepend(bg);
}

// Init immediately if on any page
renderShell();