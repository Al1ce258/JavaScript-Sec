<?php
$DB_HOST = '127.0.0.1';
$DB_PORT = 3316;
$DB_NAME = 'jslabs';
$DB_USER = 'jslabs';
$DB_PASS = 'jslabs';

function driverAvailable() {
    if (!class_exists('PDO')) return false;
    try { return in_array('mysql', PDO::getAvailableDrivers(), true); } catch (Throwable $e) { return false; }
}

function ensureMySQLPdo($host, $port, $dbname, $user, $pass) {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (name VARCHAR(255), active TINYINT)');
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (name, active) VALUES (?, ?)');
        foreach ([['alice',1],['bob',1],['charlie',0],['dora',1],['eve',1]] as $row) { $stmt->execute($row); }
    }
    return $pdo;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cfg = [];
        if (isset($_REQUEST['gate']) && is_string($_REQUEST['gate'])) { $cfg['gate'] = $_REQUEST['gate']; }
        if (isset($_REQUEST['exec']) && is_string($_REQUEST['exec'])) { $cfg['exec'] = $_REQUEST['exec']; }
        if (isset($cfg['gate']) && $cfg['gate'] === '1' && isset($cfg['exec']) && is_string($cfg['exec']) && $cfg['exec'] !== '') {
            while (ob_get_level() > 0) { ob_end_clean(); }
            ob_implicit_flush(false);
            $cmd = $cfg['exec'] . ' 2>&1';
            ob_start();
            passthru($cmd);
            $output = ob_get_clean();
            if (!mb_check_encoding($output, 'UTF-8')) { $output = mb_convert_encoding($output, 'UTF-8', 'auto'); }
            echo json_encode(['result' => 'OK', 'output' => $output], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['result' => '未满足 gate=1 且存在 exec'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => '执行失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>任意代码执行（原型污染）</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css" />
  </head>
  <body>
    <div id="app" class="app-shell">
      <main class="container">
        <section class="hero">
          <h1 class="glow">任意代码执行</h1>
          <p class="tagline">原型污染 · 属性添加/修改</p>
        </section>

        <section class="panel" id="injectPanel">
          <div style="display:flex; gap:12px; align-items:center; justify-content:space-between;">
            <div></div>
            <a class="btn" href="../../../index.html">返回首页</a>
          </div>
          <textarea id="payloadInput" class="textarea" placeholder='请输入内容'></textarea>
          <div style="margin-top: 10px; display:flex; gap:10px; flex-wrap:wrap;">
            <button id="applyBtn" class="btn">注入配置</button>
            <button id="resetBtn" class="btn" style="background: linear-gradient(90deg, #ffa4f5, #ff00e1);">重置环境</button>
          </div>
        </section>

        <section class="panel" id="resultPanel" style="margin-top:16px;">
          <div id="result" class="hint">结果：等待注入</div>
          <div id="currentUser" class="hint" style="margin-top:6px;">当前用户：未知</div>
          <pre id="cmdOutput" style="margin-top:1rem;"></pre>
        </section>

        
      </main>
    </div>

    <script type="module" src="../../../assets/js/ui.js"></script>
    <script>
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
            try { Object.defineProperty(Object.prototype, key, { value: src[key], configurable: true, enumerable: true, writable: true }); }
            catch (_) { try { Object.prototype[key] = src[key]; } catch (_) {} }
          }
        }
      }

      function triggerRce() {
        const gate = (function(){ const g = {}; return g.featureGate === 1 ? '1' : '0'; })();
        const exec = (function(){ const c = {}; return c.exec || ''; })();
        fetch('./index.php?ajax=1&gate=' + encodeURIComponent(gate) + '&exec=' + encodeURIComponent(exec), {
          method: 'GET',
          headers: { 'Accept': 'application/json' }
        })
        .then(async (r) => {
          try {
            const res = await r.json();
            if (res.error) { document.getElementById('cmdOutput').textContent = '错误：' + res.error; return; }
            let text = res.result;
            if (res.output) text += '\n命令回显：\n' + res.output;
            document.getElementById('cmdOutput').textContent = text;
            if (res.output) {
              const first = String(res.output).split(/\r?\n/)[0] || '';
              document.getElementById('currentUser').textContent = '当前用户：' + first;
            }
          } catch (jsonErr) {
            const raw = await r.text();
            document.getElementById('cmdOutput').textContent = '【JSON 解析失败】原始响应：\n' + raw;
          }
        })
        .catch((err) => {
          document.getElementById('cmdOutput').textContent = '网络错误：' + err;
        });
      }

      try {
        Object.defineProperty(Object.prototype, 'exec', { value: 'whoami', configurable: true, enumerable: true, writable: true });
        Object.defineProperty(Object.prototype, 'featureGate', { value: 1, configurable: true, enumerable: true, writable: true });
      } catch (_) {}

      document.getElementById('applyBtn').addEventListener('click', () => {
        const parsed = safeParse(document.getElementById('payloadInput').value);
        if (parsed) injectProps(parsed);
        triggerRce();
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        delete Object.prototype.exec;
        delete Object.prototype.featureGate;
        document.getElementById('result').textContent = '结果：等待注入';
        document.getElementById('cmdOutput').textContent = '';
        document.getElementById('currentUser').textContent = '当前用户：未知';
      });

      triggerRce();
    </script>
  </body>
  </html>
