<?php
require_once __DIR__ . '/auth.php';

// ถ้า login อยู่แล้ว redirect ไป portal
if (!empty($_SESSION['portal_user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $user = authenticate($username, $password);
            if ($user) {
                $_SESSION['portal_user'] = [
                    'id'              => $user['id'],
                    'username'        => $user['username'],
                    'doctor_code'     => $user['doctor_code'],
                    'display_name'    => $user['display_name'],
                    'must_change_pwd' => (int)$user['must_change_pwd'],
                ];
                header('Location: ' . ($user['must_change_pwd'] ? 'change_password.php' : 'index.php'));
                exit;
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (LockedOutException $e) {
            $error = "เข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารออีก {$e->minutesRemaining} นาทีแล้วลองใหม่";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ — คลินิกทันตกรรม</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:   #0f2447;
    --teal:   #0e8c7e;
    --teal-light: #12a899;
    --slate:  #4a5f7a;
    --silver: #e8edf3;
    --white:  #ffffff;
    --error:  #c0392b;
    --font-th: 'Sarabun', 'Noto Sans Thai', sans-serif;
  }

  @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap');

  html, body {
    height: 100%;
    font-family: var(--font-th);
    background: var(--navy);
  }

  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background:
      radial-gradient(ellipse at 20% 50%, rgba(14,140,126,.15) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 20%, rgba(255,255,255,.04) 0%, transparent 50%),
      var(--navy);
  }

  .card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 24px 64px rgba(0,0,0,.35);
    width: 100%;
    max-width: 400px;
    overflow: hidden;
  }

  .card-header {
    background: linear-gradient(135deg, var(--navy) 0%, #1a3a6b 100%);
    padding: 36px 40px 32px;
    text-align: center;
    position: relative;
  }

  .card-header::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--teal), var(--teal-light), var(--teal));
  }

  .tooth-icon {
    width: 52px;
    height: 52px;
    margin: 0 auto 16px;
    background: rgba(14,140,126,.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid rgba(14,140,126,.3);
  }

  .tooth-icon svg { width: 26px; height: 26px; fill: var(--teal-light); }

  .card-header h1 {
    color: var(--white);
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: .02em;
  }

  .card-header p {
    color: rgba(255,255,255,.55);
    font-size: .85rem;
    margin-top: 4px;
    font-weight: 300;
  }

  .card-body {
    padding: 36px 40px 40px;
  }

  .form-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-size: .82rem;
    font-weight: 500;
    color: var(--slate);
    margin-bottom: 7px;
    letter-spacing: .03em;
  }

  input[type="text"],
  input[type="password"] {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid var(--silver);
    border-radius: 8px;
    font-family: var(--font-th);
    font-size: .95rem;
    color: var(--navy);
    background: #f8fafc;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }

  input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(14,140,126,.12);
    background: var(--white);
  }

  .error-msg {
    background: #fdf2f2;
    border: 1px solid #f5c6c6;
    border-left: 3px solid var(--error);
    border-radius: 6px;
    padding: 10px 14px;
    color: var(--error);
    font-size: .875rem;
    margin-bottom: 20px;
  }

  .btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--teal) 0%, var(--teal-light) 100%);
    color: var(--white);
    border: none;
    border-radius: 8px;
    font-family: var(--font-th);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: .04em;
    transition: opacity .2s, transform .1s;
    margin-top: 4px;
  }

  .btn-login:hover  { opacity: .92; }
  .btn-login:active { transform: scale(.98); }

  .back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    font-size: .82rem;
    color: var(--slate);
    text-decoration: none;
  }

  .back-link:hover { color: var(--teal); }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <div class="tooth-icon">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C9.5 2 7.5 3.5 6.5 5.5C5.5 3.5 3.5 2 2 3C.5 4 1 6.5 1.5 8.5C2 10.5 2 12 2 14C2 16 3 22 5 22C7 22 7 19 8 17C9 15 11 14 12 14C13 14 15 15 16 17C17 19 17 22 19 22C21 22 22 16 22 14C22 12 22 10.5 22.5 8.5C23 6.5 23.5 4 22 3C20.5 2 18.5 3.5 17.5 5.5C16.5 3.5 14.5 2 12 2Z"/>
      </svg>
    </div>
    <h1>คลินิกทันตกรรม</h1>
    <p>ระบบดูข้อมูลนัดหมายสำหรับทันตแพทย์<br><small style="opacity:.6;font-size:.75rem">ใช้รหัสแพทย์เป็น Username และ Password</small></p>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label for="username">ชื่อผู้ใช้</label>
        <input
          type="text"
          id="username"
          name="username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          autocomplete="username"
          placeholder="เช่น 0675"
          required
          autofocus
        >
      </div>
      <div class="form-group">
        <label for="password">รหัสผ่าน</label>
        <input
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          placeholder="รหัสผ่านเริ่มต้น = รหัสแพทย์"
          required
        >
      </div>
      <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
    </form>

    <a class="back-link" href="../index.html">← กลับหน้าหลัก</a>
  </div>
</div>

</body>
</html>
