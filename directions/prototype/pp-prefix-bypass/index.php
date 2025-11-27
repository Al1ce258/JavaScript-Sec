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
    $flag = null;
    try {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $data = @json_decode($raw, true);
            if (is_array($data)) {
                $hasBypass = false;
                $scan = function($obj) use (&$scan, &$hasBypass) {
                    if (!is_array($obj) && !is_object($obj)) return;
                    foreach ($obj as $k => $v) {
                        if ($k === 'bypassKey' && $v === 'pwn') { $hasBypass = true; return; }
                        if (is_array($v) || is_object($v)) $scan($v);
                    }
                };
                $scan($data);
                if ($hasBypass) {
                    echo json_encode([
                        'result' => '前缀绕过触发，检测到 bypassKey === "pwn"',
                        'flag' => 'FLAG{PP_PREFIX_BYPASS_SUCCESS}'
                    ]);
                    exit;
                }
            }
        }

        echo json_encode(['result' => '未检测到 bypassKey === "pwn"', 'flag' => null], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => '类型检测失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>前缀绕过原型污染</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css" />
  </head>
  <body>
    <div id="app" class="app-shell">
      <main class="container">
        <section class="hero">
          <h1 class="glow">前缀绕过原型污染</h1>
          <p class="tagline">绕过前缀检查造成原型链污染</p>
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
        </section>

        <section id="secretPanel" class="panel hidden" style="margin-top:16px;">
          <div>受保护的内容</div>
          <div style="margin-top:8px;">CTF<div>CTF Flag：<span style="color: var(--accent)">FLAG{PP_PREFIX_BYPASS_SUCCESS}</span></div>
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

      function triggerBypass() {
        const payload = document.getElementById('payloadInput').value;
        fetch('./index.php?ajax=1', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: payload
        })
          .then(async (res) => {
            try {
              const data = await res.json();
              document.getElementById('result').textContent = '结果：' + String(data.result ?? '无输出');
              const hasFlag = typeof data.flag === 'string' && data.flag.includes('PP_PREFIX_BYPASS_SUCCESS');
              document.getElementById('secretPanel').classList.toggle('hidden', !hasFlag);
            } catch (_) {
              document.getElementById('result').textContent = '结果：请求错误';
              document.getElementById('secretPanel').classList.add('hidden');
            }
          })
          .catch(() => {
            document.getElementById('result').textContent = '结果：网络错误';
            document.getElementById('secretPanel').classList.add('hidden');
          });
      }

      document.getElementById('applyBtn').addEventListener('click', () => {
        const parsed = safeParse(document.getElementById('payloadInput').value);
        if (parsed) injectProps(parsed);
        triggerBypass();
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        delete Object.prototype.bypassKey;
        document.getElementById('result').textContent = '结果：等待注入';
        document.getElementById('secretPanel').classList.add('hidden');
      });
    </script>
  </body>
  </html>
