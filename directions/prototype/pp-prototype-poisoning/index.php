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
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new Exception('JSON 不是对象/数组');

        $hasPwn = false;
        $scan = function($obj) use (&$scan, &$hasPwn) {
            if (!is_array($obj) && !is_object($obj)) return;
            foreach ($obj as $k => $v) {
                if ($k === '__proto__' && (is_array($v) || is_object($v))) {
                    if (isset($v['polluted']) && $v['polluted'] === 'yes') {
                        $hasPwn = true; return;
                    }
                }
                if (is_array($v) || is_object($v)) $scan($v);
            }
        };
        $scan($data);

        if ($hasPwn) {
            echo json_encode([
                'result' => '检测到 __proto__.polluted === "yes"',
                'flag' => 'FLAG{PP_SIMPLE_POLLUTION}'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'result' => '未检测到 __proto__.polluted === "yes"',
                'flag' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        echo json_encode(['error' => 'JSON 解析失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>极简原型投毒</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css" />
  </head>
  <body>
    <div id="app" class="app-shell">
      <main class="container">
        <section class="hero">
          <h1 class="glow">极简原型投毒</h1>
          <p class="tagline">一步式 JSON 原型链污染</p>
        </section>

        <section class="panel" id="injectPanel">
          <div style="display:flex; gap:12px; align-items:center; justify-content:space-between;">
            <div></div>
            <a class="btn" href="../../../index.html">返回首页</a>
          </div>
          <textarea id="payloadInput" class="textarea" placeholder='请输入内容'></textarea>
          <div style="margin-top: 10px; display:flex; gap:10px; flex-wrap:wrap;">
            <button id="injectBtn" class="btn">注入配置</button>
            <button id="resetBtn" class="btn" style="background: linear-gradient(90deg, #ffa4f5, #ff00e1);">重置环境</button>
          </div>
        </section>

        <section class="panel" id="resultPanel" style="margin-top:16px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div class="hint">系统状态</div>
            <div id="result" class="hint">结果：等待注入</div>
          </div>
          <div style="margin-top:8px; display:flex; gap:16px; flex-wrap:wrap;">
            <div id="pollutedStatus" class="hint">polluted：undefined</div>
          </div>
        </section>

        <section id="secretPanel" class="panel hidden" style="margin-top:16px;">
          <div class="hint">受保护的内容</div>
          <div style="margin-top:8px;">CTF Flag：<span style="color: var(--accent)">FLAG{PP_SIMPLE_POLLUTION}</span></div>
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
        const o = {};
        const val = o.polluted;
        const pollutedStatus = document.getElementById('pollutedStatus');
        pollutedStatus.textContent = 'polluted：' + (typeof val) + (val !== undefined ? (' = ' + String(val)) : '');
        const passed = (val === 'yes');
        document.getElementById('secretPanel').classList.toggle('hidden', !passed);
      }

      function uploadAndParseIni() {
        const fileInput = document.getElementById('iniFile');
        if (!fileInput.files.length) {
          document.getElementById('result').textContent = '结果：请选择文件';
          return;
        }
        const formData = new FormData();
        formData.append('iniFile', fileInput.files[0]);
        fetch('./index.php?ajax=1', {
          method: 'POST',
          body: formData
        })
          .then(async (res) => {
            try {
              const data = await res.json();
              document.getElementById('result').textContent = '结果：' + String(data.result ?? '无输出');
              const hasFlag = typeof data.flag === 'string' && data.flag.includes('PP_INI_POLLUTION_SUCCESS');
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

      document.getElementById('injectBtn').addEventListener('click', () => {
        const payload = document.getElementById('payloadInput').value.trim();
        if (!payload) { alert('请输入 JSON'); return; }
        const obj = safeParse(payload);
        if (obj) {
          injectProps(obj);
          document.getElementById('result').textContent = '结果：已注入';
          recompute();
        } else {
          alert('JSON 解析失败');
        }
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        delete Object.prototype.polluted;
        document.getElementById('result').textContent = '结果：等待注入';
        document.getElementById('secretPanel').classList.add('hidden');
        recompute();
      });

      recompute();
    </script>
  </body>
  </html>
