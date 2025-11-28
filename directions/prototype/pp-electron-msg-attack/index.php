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
        $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $color  = isset($_REQUEST['color']) && is_string($_REQUEST['color']) ? $_REQUEST['color'] : '';
        if ($action === 'deleteAllFiles') {
            echo json_encode(['result' => 'deleted_all_files'], JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'changeColor' && $color !== '') {
            echo json_encode(['result' => 'color_changed', 'color' => $color], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['result' => 'no_action'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => '消息处理失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Electron 消息原型污染</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css" />
  </head>
  <body>
    <div id="app" class="app-shell">
      <main class="container">
        <section class="hero">
          <h1 class="glow">Electron 消息原型污染</h1>
          <p class="tagline">属性添加/修改</p>
        </section>

        <section class="panel" id="injectPanel">
          <div style="display:flex; gap:12px; align-items:center; justify-content:space-between;">
            <div></div>
            <a class="btn" href="../../../index.html">返回首页</a>
          </div>
          <textarea id="payloadInput" class="textarea" placeholder='请输入内容'></textarea>
          <div style="margin-top: 10px; display:flex; gap:10px; flex-wrap:wrap;">
            <button id="applyBtn" class="btn">注入指令字典</button>
            <button id="resetBtn" class="btn" style="background: linear-gradient(90deg, #ffa4f5, #ff00e1);">重置环境</button>
          </div>
        </section>

        <section class="panel" id="sandboxPanel" style="margin-top:16px;">
          <div class="hint">沙箱 · 低权限</div>
          <div style="margin-top:8px; display:flex; gap:10px; align-items:center;">
            <input id="colorInput" class="textarea" style="min-height:0; height:40px;" placeholder="blue" value="blue" />
            <button id="runSandbox" class="btn">运行沙箱程序（安全）</button>
          </div>
          <div class="hint" style="margin-top:6px;">说明：沙箱程序只会发送颜色请求，不设置 action。</div>
        </section>

        <section class="panel" id="operatorPanel" style="margin-top:16px;">
          <div class="hint">系统操作员 · 高权限</div>
          <div style="margin-top:8px; display:flex; gap:16px; flex-wrap:wrap;">
            <div id="opStatus" class="hint">状态：待机</div>
            <div id="fileStatus" class="hint">文件：5 项</div>
          </div>
          <div style="margin-top:8px; display:flex; gap:10px;">
            <button id="receiveBtn" class="btn">执行任务（接收指令）</button>
          </div>
        </section>

        <section class="panel" id="resultPanel" style="margin-top:16px;">
          <div id="result" class="hint">结果：无</div>
        </section>

        <section id="secretPanel" class="panel hidden" style="margin-top:16px;">
          <div>受保护的内容</div>
          <div style="margin-top:8px;">CTF Flag：<span style="color: var(--accent)">FLAG{PP_ELECTRON_MSG_SUCCESS}</span></div>
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

      const FILES = ['/etc/hosts','/var/log/app.log','/home/user/docs.txt','/tmp/cache.bin','/opt/app/config.json'];
      function updateFiles(count) { document.getElementById('fileStatus').textContent = '文件：' + String(count) + ' 项'; }
      updateFiles(FILES.length);

      let pendingMessage = null;
      function sandboxProgram() {
        const color = String(document.getElementById('colorInput').value || 'blue');
        const msg = { color }; // 不设置 action，由操作员使用默认值
        pendingMessage = msg;
        document.getElementById('opStatus').textContent = '状态：收到请求（待执行）';
      }

      function operatorHandle(msg) {
        const action = msg.action || 'changeColor'; // 读取原型链属性（漏洞点）
        const color = msg.color || '';
        fetch('./index.php?ajax=1&action=' + encodeURIComponent(action) + '&color=' + encodeURIComponent(color), {
          method: 'GET', headers: { 'Accept': 'application/json' }
        })
        .then(async (res) => {
          try {
            const data = await res.json();
            if (data.result === 'deleted_all_files') {
              FILES.length = 0; updateFiles(FILES.length);
              document.getElementById('result').textContent = '结果：高权限执行 deleteAllFiles';
              document.getElementById('secretPanel').classList.remove('hidden');
            } else if (data.result === 'color_changed') {
              document.documentElement.style.setProperty('--accent', data.color || '#00f0ff');
              document.getElementById('result').textContent = '结果：颜色已更改为 ' + String(data.color);
              document.getElementById('secretPanel').classList.add('hidden');
            } else {
              document.getElementById('result').textContent = '结果：无操作';
              document.getElementById('secretPanel').classList.add('hidden');
            }
            document.getElementById('opStatus').textContent = '状态：完成';
          } catch (_) {
            document.getElementById('result').textContent = '结果：请求错误';
            document.getElementById('opStatus').textContent = '状态：失败';
            document.getElementById('secretPanel').classList.add('hidden');
          }
        })
        .catch(() => {
          document.getElementById('result').textContent = '结果：网络错误';
          document.getElementById('opStatus').textContent = '状态：失败';
          document.getElementById('secretPanel').classList.add('hidden');
        });
      }

      document.getElementById('applyBtn').addEventListener('click', () => {
        const parsed = safeParse(document.getElementById('payloadInput').value);
        if (parsed) injectProps(parsed);
      });

      document.getElementById('runSandbox').addEventListener('click', () => {
        sandboxProgram();
      });

      document.getElementById('receiveBtn').addEventListener('click', () => {
        const msg = pendingMessage || {};
        operatorHandle(msg);
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        delete Object.prototype.action;
        pendingMessage = null;
        document.getElementById('result').textContent = '结果：无';
        document.getElementById('opStatus').textContent = '状态：待机';
        FILES.splice(0, FILES.length, '/etc/hosts','/var/log/app.log','/home/user/docs.txt','/tmp/cache.bin','/opt/app/config.json');
        updateFiles(FILES.length);
        document.getElementById('secretPanel').classList.add('hidden');
      });
    </script>
  </body>
  </html>
