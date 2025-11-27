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
        $csv = file_get_contents('php://input');
        if ($csv==='') throw new Exception('CSV 内容为空');
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        if (count($lines)<2) throw new Exception('CSV 至少需要标题行和数据行');
        $headers = array_shift($lines);
        $headers = array_map('trim', $headers);
        $json = [];
        foreach ($lines as $row) {
            $obj = [];
            foreach ($headers as $i=>$h) {
                $obj[$h] = $row[$i] ?? '';
            }
            $json[] = $obj;
        }

        $hasPollution = false;
        foreach ($json as $row) {
            if (array_key_exists('__proto__', $row) && array_key_exists('polluted', $row) && $row['polluted'] === 'yes') {
                $hasPollution = true;
                break;
            }
        }

        $cleanJson = array_map(function($row){
            return array_filter($row,function($_,$k){ return $k !== '__proto__'; },ARRAY_FILTER_USE_BOTH);
        }, $json);

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        if ($hasPollution) {
            echo json_encode(['result'=>'OK','data'=>$cleanJson,'flag'=>'FLAG{PP_CSV_POLLUTION}'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['result'=>'转换成功','data'=>$cleanJson], JSON_UNESCAPED_UNICODE);
        }
        exit;
    } catch (Throwable $e) {
        echo json_encode(['error' => 'CSV 解析失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CSV 原型链污染</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css" />
  </head>
  <body>
    <div id="app" class="app-shell">
      <main class="container">
        <section class="hero">
          <h1 class="glow">CSV 原型链污染</h1>
          <p class="tagline">CSV→JSON 转换工具</p>
        </section>

        <section class="panel" id="injectPanel">
          <div style="display:flex; gap:12px; align-items:center; justify-content:space-between;">
            <div></div>
            <a class="btn" href="../../../index.html">返回首页</a>
          </div>
          <textarea id="payloadInput" class="textarea" placeholder='__proto__,polluted
,yes'></textarea>
          <div style="margin-top: 10px; display:flex; gap:10px; flex-wrap:wrap;">
            <button id="injectBtn" class="btn">转换</button>
            <button id="resetBtn" class="btn" style="background: linear-gradient(90deg, #ffa4f5, #ff00e1);">重置</button>
          </div>
        </section>

        <section class="panel" id="resultPanel" style="margin-top:16px;">
          <div id="result" class="hint">结果：等待转换</div>
          <pre id="csvResult" style="margin-top:1rem;"></pre>
        </section>

        <section id="secretPanel" class="panel hidden" style="margin-top:16px;">
          <div>受保护的内容</div>
          <div style="margin-top:8px;">CTF<div>CTF Flag：<span style="color: var(--accent)">FLAG{PP_CSV_POLLUTION}</span></div>
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
              const hasFlag = typeof data.flag === 'string' && data.flag.includes('PP_CSV_POLLUTION');
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
        const csv = document.getElementById('payloadInput').value;
        if (!csv.trim()) { alert('请输入 CSV'); return; }
        fetch('?ajax=1', {
          method: 'POST',
          headers: {'Content-Type': 'text/plain'},
          body: csv
        })
        .then(async r => {
          try {
            const res = await r.json();
            if (res.error) { document.getElementById('csvResult').textContent = '错误：' + res.error; return; }
            let text = res.result;
            if (res.data) text += '<br>' + JSON.stringify(res.data,null,2);
            if (res.flag) text += '<br><strong>Flag：</strong>' + res.flag;
            document.getElementById('csvResult').innerHTML = text;
          } catch (jsonErr) {
            const raw = await r.text();
            document.getElementById('csvResult').textContent = '【JSON 解析失败】原始响应：\n' + raw;
            console.error('[JSON 失败] 原始响应体：', raw);
          }
        })
        .catch(async (err) => {
          const raw = await err.response?.text?.() ?? '无 response 对象';
          console.error('[fetch 失败] 原始响应体：', raw);
          document.getElementById('csvResult').textContent = '网络错误：' + err;
        });
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        delete Object.prototype.polluted;
        document.getElementById('result').textContent = '结果：等待转换';
        document.getElementById('csvResult').textContent = '';
        document.getElementById('secretPanel').classList.add('hidden');
      });
    </script>
  </body>
</html>
