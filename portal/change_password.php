<?php
/**
 * portal/change_password.php
 * บังคับให้แพทย์เปลี่ยน password ก่อนเข้าใช้งานครั้งแรก
 * (และเปิดให้เปลี่ยนเองได้ทุกเมื่อหลังจากนั้นผ่านหน้านี้)
 */

require_once __DIR__ . '/auth.php';

if (empty($_SESSION['portal_user'])) {
    header('Location: login.php');
    exit;
}

$user    = currentUser();
$forced  = !empty($user['must_change_pwd']);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (!password_verify($current, getUserPasswordHash($user['username']))) {
        $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif (strlen($new) < 6) {
        $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($new === $user['username']) {
        $error = 'กรุณาตั้งรหัสผ่านใหม่ที่ไม่ใช่รหัสผ่านเริ่มต้นเดิม';
    } elseif ($new !== $confirm) {
        $error = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        updateUserPassword($user['username'], $new);
        $success = 'เปลี่ยนรหัสผ่านสำเร็จ';
        $forced = false;
        // sync session ให้รู้ว่าไม่ต้องบังคับเปลี่ยนแล้ว
        $_SESSION['portal_user']['must_change_pwd'] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เปลี่ยนรหัสผ่าน — คลินิกทันตกรรม</title>
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
    --success: #1d8a4a;
    --font-th: 'Sarabun', 'Noto Sans Thai', sans-serif;
  }

  @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap');

  html, body { height: 100%; font-family: var(--font-th); background: var(--navy); }

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
    max-width: 420px;
    overflow: hidden;
  }

  .card-header {
    background: linear-gradient(135deg, var(--navy) 0%, #1a3a6b 100%);
    padding: 32px 40px 28px;
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

  .card-header h1 { color: var(--white); font-size: 1.15rem; font-weight: 600; }
  .card-header p  { color: rgba(255,255,255,.6); font-size: .85rem; margin-top: 6px; }

  .forced-banner {
    background: rgba(245, 158, 11, .15);
    border: 1px solid rgba(245, 158, 11, .4);
    color: #fff;
    font-size: .8rem;
    padding: 8px 14px;
    border-radius: 6px;
    margin-top: 12px;
  }

  .card-body { padding: 32px 40px 36px; }

  .form-group { margin-bottom: 18px; }

  label {
    display: block;
    font-size: .82rem;
    font-weight: 500;
    color: var(--slate);
    margin-bottom: 7px;
  }

  input[type="password"] {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid var(--silver);
    border-radius: 8px;
    font-family: var(--font-th);
    font-size: .95rem;
    color: var(--navy);
    background: #f8fafc;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }

  input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(14,140,126,.12);
    background: var(--white);
  }

  .hint { font-size: .76rem; color: var(--slate); opacity: .75; margin-top: 5px; }

  .error-msg, .success-msg {
    border-radius: 6px;
    padding: 10px 14px;
    font-size: .875rem;
    margin-bottom: 18px;
  }

  .error-msg {
    background: #fdf2f2;
    border: 1px solid #f5c6c6;
    border-left: 3px solid var(--error);
    color: var(--error);
  }

  .success-msg {
    background: #f0fbf4;
    border: 1px solid #b7e4c7;
    border-left: 3px solid var(--success);
    color: var(--success);
  }

  .btn-submit {
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
    margin-top: 4px;
    transition: opacity .2s, transform .1s;
  }

  .btn-submit:hover  { opacity: .92; }
  .btn-submit:active { transform: scale(.98); }

  .skip-link {
    display: block;
    text-align: center;
    margin-top: 18px;
    font-size: .82rem;
    color: var(--slate);
    text-decoration: none;
  }
  .skip-link:hover { color: var(--teal); }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <h1>เปลี่ยนรหัสผ่าน</h1>
    <p><?= htmlspecialchars($user['display_name']) ?></p>
    <?php if ($forced): ?>
      <div class="forced-banner">⚠️ กรุณาเปลี่ยนรหัสผ่านก่อนใช้งานครั้งแรก</div>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success-msg"><?= htmlspecialchars($success) ?> — <a href="index.php" style="color:inherit;text-decoration:underline;">ไปหน้าตารางนัด →</a></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="change_password.php">
      <div class="form-group">
        <label for="current_password">รหัสผ่านปัจจุบัน</label>
        <input type="password" id="current_password" name="current_password"
               autocomplete="current-password" required autofocus>
      </div>
      <div class="form-group">
        <label for="new_password">รหัสผ่านใหม่</label>
        <input type="password" id="new_password" name="new_password"
               autocomplete="new-password" required>
        <div class="hint">อย่างน้อย 6 ตัวอักษร และต้องไม่ใช่รหัสผ่านเริ่มต้นเดิม</div>
      </div>
      <div class="form-group">
        <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
        <input type="password" id="confirm_password" name="confirm_password"
               autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn-submit">เปลี่ยนรหัสผ่าน</button>
    </form>
    <?php if (!$forced): ?>
      <a class="skip-link" href="index.php">← กลับไปหน้าตารางนัด</a>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
