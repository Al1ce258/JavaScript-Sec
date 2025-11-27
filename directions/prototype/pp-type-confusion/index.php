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
        if (isset($_GET['typeKey'], $_GET['typeVal'], $_GET['order'])) {
            $key   = trim($_GET['typeKey']);
            $val   = json_decode(trim($_GET['typeVal']), true);
            $order = trim($_GET['order']);
            if ($key === 'object'
                && is_array($val)
                && $val === [['className' => 'POLLUTED']]
                && $order === 'first') {
                echo json_encode([
                    'result' => '类型混淆（最高难度）触发',
                    'flag' => 'FLAG{PP_TYPE_CONFUSION_HARD}'
                ]);
                exit;
            }
            echo json_encode([
                'result' => '条件未满足（需同时满足 typeKey=object & typeVal=[{"className":"POLLUTED"}] & order=first）',
                'flag' => ''
            ]);
            exit;
        }

        echo json_encode(['result' => $result, 'flag' => $flag], JSON_UNESCAPED_UNICODE);
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
    <title>类型混淆（原型污染）</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css" />
  </head>
  <body>
    <div id="app" class="app-shell">
      <main class="container">
        <section class="hero">
          <h1 class="glow">类型混淆（原型污染）</h1>
          <p class="tagline">属性添加/修改</p>
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
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div class="hint">系统状态</div>
            <div id="result" class="hint">结果：等待注入</div>
          </div>
          <div style="margin-top:8px; display:flex; gap:16px; flex-wrap:wrap;">
            <div id="adminStatus" class="hint">isAdmin：false</div>
            <div id="privStatus" class="hint">hasPrivilege：false</div>
            <div id="roleEcho" class="hint">role：undefined</div>
          </div>
        </section>

        <section id="secretPanel" class="panel hidden" style="margin-top:16px;">
          <div>受保护的内容</div>
          <div style="margin-top:8px;">CTF Flag：<span style="color: var(--accent)">FLAG{PP_TYPE_CONFUSION_SUCCESS}</span></div>
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

      function recompute() {
        const user = {};
        const admin = (user.role === 'admin');
        const priv = !!user.role;
        document.getElementById('adminStatus').textContent = 'isAdmin：' + String(admin);
        document.getElementById('privStatus').textContent = 'hasPrivilege：' + String(priv);
        const echo = document.getElementById('roleEcho');
        echo.textContent = 'role：' + (typeof user.role) + (user.role !== undefined ? (' = ' + String(user.role)) : '');
        const passed = (!admin && priv);
        document.getElementById('secretPanel').classList.toggle('hidden', !passed);
      }

      document.getElementById('applyBtn').addEventListener('click', () => {
        const parsed = safeParse(document.getElementById('payloadInput').value);
        if (parsed) {
          injectProps(parsed);
          document.getElementById('result').textContent = '结果：已注入';
          recompute();
        } else {
          document.getElementById('result').textContent = '结果：JSON 解析失败';
        }
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        delete Object.prototype.role;
        document.getElementById('result').textContent = '结果：等待注入';
        document.getElementById('secretPanel').classList.add('hidden');
        recompute();
      });

      recompute();
    </script>
  </body>
  </html>
