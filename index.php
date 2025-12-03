<?php
session_start();

$API_BASE = 'https://crp.is:8182';
$LOGIN_URL   = $API_BASE . '/user/login';
$LOGOUT_URL  = $API_BASE . '/user/logout';
$BALANCE_URL = $API_BASE . '/user/balance';
$CURLIST_URL = $API_BASE . '/market/curlist';
$TICKER_URL  = $API_BASE . '/market/ticker';

// === LOGOUT ===
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    if (isset($_SESSION['auth_token'])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $LOGOUT_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: auth_token=' . $_SESSION['auth_token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// === LOGIN MANUAL ===
$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['PublicKey'])) {
    $data = [
        'PublicKey' => trim($_POST['PublicKey']),
        'password'  => $_POST['password']
    ];
    if (!empty($_POST['2fa_pin'])) {
        $data['2fa_pin'] = $_POST['2fa_pin'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $LOGIN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        $loginError = 'Koneksi gagal: ' . htmlspecialchars($err);
    } else {
        $json = json_decode($res, true);
        if ($json && $json['success'] && isset($json['result']['auth_token'])) {
            $_SESSION['auth_token'] = $json['result']['auth_token'];
            $_SESSION['public_key'] = $data['PublicKey'];
        } else {
            $loginError = 'Login gagal — periksa Public Key, password, atau 2FA PIN.';
        }
    }
}

// === FUNGSI API ===
function apiGet($url, $authToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: auth_token=' . $authToken]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$balances = $allCurrencies = $usdtTickers = null;

if (isset($_SESSION['auth_token'])) {
    $auth = $_SESSION['auth_token'];

    // 1. Saldo & daftar aset
    $balance = apiGet($BALANCE_URL, $auth);
    $balances = [];
    if ($balance['success'] ?? false) {
        foreach ($balance['result']['allbalance'] as $item) {
            $name = $item['currency']['name'];
            $balances[$name] = $item['balance'];
        }
    }

    $curlist = apiGet($CURLIST_URL, $auth);
    if ($curlist['success'] ?? false) {
        $allCurrencies = $curlist['result'];
    }

    // 2. Harga semua pair yang quote-nya USDT
    $ticker = apiGet($TICKER_URL, $auth);
    if ($ticker['success'] ?? false) {
        foreach ($ticker['result'] as $p) {
            if ($p['ecur'] === 'usdt' && $p['enable']) {
                $usdtTickers[$p['cur']] = [
                    'last' => $p['last_price'],
                    'ask'  => $p['ack'],
                    'bid'  => $p['bid'],
                    'pair' => $p['pair']
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Utopiahub — Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { background: #000; color: #00FF41; font-family: monospace; padding: 1.2rem; }
    h1,h2 { margin: 1.2rem 0 0.6rem; }
    .section { margin-bottom: 1.5rem; }
    .highlight { color: #00FFAA; }
    .zero { opacity: 0.4; }
    .logout-btn {
      display: inline-block;
      margin-top: 0.5rem;
      padding: 0.4rem 1rem;
      background: #222;
      color: #ff4141;
      text-decoration: none;
      border: 1px solid #ff414150;
      border-radius: 4px;
    }
    .logout-btn:hover { background: #333; }
    form label { display: inline-block; width: 100px; }
    input { background: #111; color: #00FF41; border: 1px solid #00FF4150; padding: 0.4rem; margin: 0.3rem 0; width: 250px; }
    .error { color: #ff4141; }
    .address { 
      word-break: break-all; 
      background: #0a0a0a; 
      padding: 0.4rem; 
      font-size: 0.85em; 
      margin-top: 0.3rem; 
    }
  </style>
</head>
<body>
  <h1>🔐 Utopiahub Dashboard</h1>

  <?php if (!isset($_SESSION['auth_token'])): ?>
    <?php if ($loginError): ?>
      <p class="error">⚠️ <?= htmlspecialchars($loginError) ?></p>
    <?php endif; ?>
    <form method="POST">
      <div><label>Public Key:</label> <input type="text" name="PublicKey" required autocomplete="off"></div>
      <div><label>Password:</label> <input type="password" name="password" required></div>
      <div><label>2FA PIN:</label> <input type="text" name="2fa_pin" placeholder="(opsional)" maxlength="6"></div>
      <div><button type="submit">🔓 Login ke crp.is</button></div>
    </form>

  <?php else: ?>
    <!-- ADDRESS LENGKAP -->
    <div class="section">
      <h2>🆔 Public Key</h2>
      <div class="address"><?= htmlspecialchars($_SESSION['public_key']) ?></div>
    </div>

    <!-- HARGA ASSET vs USDT -->
    <?php if ($usdtTickers): ?>
      <div class="section">
        <h2>📈 Harga vs USDT</h2>
        <?php foreach ($usdtTickers as $cur => $t): ?>
          <div>
            <strong><?= strtoupper($cur) ?>:</strong>
            Last: <span class="highlight"><?= number_format($t['last'], 4) ?></span>,
            Ask: <?= number_format($t['ask'], 4) ?>,
            Bid: <?= number_format($t['bid'], 4) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- SALDO SEMUA ASET -->
    <?php if ($allCurrencies): ?>
      <div class="section">
        <h2>💳 Saldo Semua Aset</h2>
        <?php foreach ($allCurrencies as $name => $cur): 
            $bal = $balances[$name] ?? 0;
            $class = $bal == 0 ? 'zero' : 'highlight';
        ?>
          <div>
            <strong><?= strtoupper($name) ?>:</strong> 
            <span class="<?= $class ?>"><?= number_format($bal, $cur['round'] ?? 4) ?></span>
            <small>(<?= htmlspecialchars($cur['fullname']) ?>)</small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- LOGOUT -->
    <a href="?logout=1" class="logout-btn">🚪 Logout</a>

  <?php endif; ?>

  <hr>
  <small class="note">Data dari crp.is API — refresh halaman untuk update.</small>
</body>
</html>
