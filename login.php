<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

function login_target_for_role($role){
    $targets = [
        "admin" => "admin_dashboard.php",
        "employer" => "employer_dashboard.php",
        "freelancer" => "freelancer_dashboard.php"
    ];

    return $targets[$role] ?? null;
}

function redirect_by_role($role){
    $target = login_target_for_role($role);

    if(!$target){
        return false;
    }

    header("Location: " . $target);
    exit();
}

if(isset($_SESSION['role']) && !redirect_by_role($_SESSION['role'])){
    unset($_SESSION['role'], $_SESSION['user_id'], $_SESSION['username']);
}

$error = '';

if(isset($_POST['login'])){
    define('JOBFIND_ALLOW_DB_FAILURE', true);
    require_once __DIR__ . "/config.php";

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if(!$conn){
        $error = $db_error ?: "Database is not available. Please try again later.";
    } elseif($email === '' || $password === ''){
        $error = "Email and password are required.";
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT user_id, username, role, password
            FROM users
            WHERE email=?
            LIMIT 1
        ");

        if(!$stmt){
            error_log("Login prepare failed: " . mysqli_error($conn));
            $error = "Unable to login right now. Please try again later.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);

            if(!mysqli_stmt_execute($stmt)){
                error_log("Login execute failed: " . mysqli_stmt_error($stmt));
                $error = "Unable to login right now. Please try again later.";
            } else {
                mysqli_stmt_bind_result($stmt, $user_id, $username, $role, $stored_password);
                $found = mysqli_stmt_fetch($stmt);
                $password_ok = $found && (
                    password_verify($password, (string)$stored_password) ||
                    hash_equals((string)$stored_password, (string)$password)
                );

                if($password_ok && login_target_for_role($role)){
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role']     = $role;

                    redirect_by_role($role);
                } elseif($password_ok){
                    $error = "Invalid account role.";
                } else {
                    $error = "Invalid email or password.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=7">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Job_Find</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  *,
  *::before,
  *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    letter-spacing: 0;
  }

  :root {
    --navy: #0b1220;
    --navy2: #111c31;
    --accent: #5b5ff4;
    --cyan: #06b6d4;
    --green: #14b87a;
    --orange: #f97316;
    --light: #eef3f8;
    --white: #ffffff;
    --text: #0f172a;
    --muted: #64748b;
    --border: #dbe4ef;
    --red: #ef4444;
    --radius: 8px;
    --shadow: 0 24px 56px rgba(15, 23, 42, .13);
    --focus: 0 0 0 4px rgba(91, 95, 244, .16);
  }

  html {
    min-height: 100%;
    background: var(--navy);
  }

  body {
    min-height: 100vh;
    font-family: "Noto Sans Thai", "Sora", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    color: var(--text);
    background:
      linear-gradient(120deg, rgba(20, 184, 166, .10) 0%, rgba(20, 184, 166, 0) 34%),
      linear-gradient(240deg, rgba(249, 115, 22, .10) 0%, rgba(249, 115, 22, 0) 32%),
      linear-gradient(180deg, #f8fbff 0%, #edf4fa 360px, #f5f7fb 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 18px;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
  }

  a {
    color: var(--accent);
    text-decoration: none;
  }

  a:hover {
    color: #4548df;
  }

  .auth-shell {
    width: min(1040px, 100%);
    min-height: 620px;
    display: grid;
    grid-template-columns: minmax(320px, .9fr) minmax(360px, 1fr);
    overflow: hidden;
    border: 1px solid rgba(219, 228, 239, .96);
    border-radius: var(--radius);
    background: rgba(255, 255, 255, .96);
    box-shadow: var(--shadow);
  }

  .brand-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 32px;
    padding: 34px 32px;
    overflow: hidden;
    background:
      linear-gradient(145deg, rgba(20, 184, 166, .18) 0%, rgba(91, 95, 244, .12) 42%, transparent 42%),
      linear-gradient(180deg, #0b1220 0%, #101b31 100%);
    color: #ffffff;
  }

  .brand-panel::before {
    content: "";
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    background: linear-gradient(90deg, var(--accent), var(--cyan), var(--green), #f59e0b);
  }

  .brand-lockup {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    text-decoration: none;
  }

  .brand-mark {
    width: 180px;
    height: 180px;
    min-width: 180px;
    max-width: 180px;
    max-height: 180px;
    flex: 0 0 180px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    color: #ffffff;
    font-size: 22px;
    overflow: hidden;
    padding: 0;
    box-shadow: 0 12px 24px rgba(91, 95, 244, .18);
  }

  .brand-mark img {
    width: 100%;
    height: 100%;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
  }

  .brand-name {
    color: #ffffff;
    font-size: 18px;
    line-height: 1.1;
    font-weight: 800;
  }

  .brand-sub {
    margin-top: 3px;
    color: #9fb1c7;
    font-size: 12px;
    line-height: 1.3;
  }

  .brand-lockup > div:not(.brand-mark) {
    display: none;
  }

  .brand-copy {
    position: relative;
    max-width: 360px;
  }

  .brand-copy h1 {
    color: #ffffff;
    font-size: clamp(30px, 4vw, 42px);
    line-height: 1.16;
    font-weight: 800;
  }

  .brand-copy p {
    margin-top: 18px;
    color: #cbd5e1;
    font-size: 15px;
    line-height: 1.8;
  }

  .signal-list {
    position: relative;
    display: grid;
    gap: 12px;
  }

  .signal-item {
    display: grid;
    grid-template-columns: 34px 1fr;
    align-items: center;
    gap: 12px;
    color: #dbe4ff;
    font-size: 14px;
    font-weight: 600;
  }

  .signal-icon {
    width: 34px;
    height: 34px;
    border-radius: var(--radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(219, 228, 255, .18);
    background: rgba(255, 255, 255, .08);
    color: #93c5fd;
    font-size: 13px;
    font-weight: 800;
  }

  .brand-footer {
    position: relative;
    color: #71839d;
    font-size: 12px;
  }

  .form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 44px;
    background: #ffffff;
  }

  .form-inner {
    width: min(420px, 100%);
  }

  .mobile-brand {
    display: none;
    margin-bottom: 28px;
  }

  .form-kicker {
    color: var(--accent);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
  }

  .form-inner h2 {
    margin-top: 8px;
    color: #071327;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 800;
  }

  .subtitle {
    margin-top: 10px;
    margin-bottom: 28px;
    color: #5d6f86;
    font-size: 14px;
    line-height: 1.7;
  }

  .toast-success {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 20;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: min(360px, calc(100vw - 36px));
    padding: 13px 16px;
    border-radius: var(--radius);
    border: 1px solid rgba(34, 197, 94, .24);
    background: #0b1220;
    color: #ffffff;
    box-shadow: 0 18px 36px rgba(15, 23, 42, .20);
    font-size: 14px;
    font-weight: 700;
    transition: opacity .25s ease;
  }

  .toast-success::before {
    content: "";
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--green);
    box-shadow: 0 0 0 4px rgba(20, 184, 122, .18);
    flex: 0 0 auto;
  }

  .alert-err {
    display: grid;
    grid-template-columns: 26px 1fr;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 12px 14px;
    border: 1px solid #fecdd3;
    border-radius: var(--radius);
    background: #fff1f2;
    color: #be123c;
    font-size: 13.5px;
    line-height: 1.5;
  }

  .alert-icon {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffe4e6;
    color: #be123c;
    font-weight: 900;
  }

  .field-group {
    margin-bottom: 16px;
  }

  .field-group label {
    display: block;
    margin-bottom: 7px;
    color: #172033;
    font-size: 13px;
    font-weight: 700;
  }

  .input-wrap {
    position: relative;
  }

  .input-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    width: 22px;
    height: 22px;
    transform: translateY(-50%);
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: var(--accent);
    font-size: 12px;
    font-weight: 800;
    pointer-events: none;
  }

  .form-input {
    width: 100%;
    min-height: 48px;
    padding: 12px 14px 12px 46px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #ffffff;
    color: var(--text);
    font: inherit;
    font-size: 14px;
    outline: none;
    box-shadow: none;
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
  }

  .form-input:focus {
    border-color: var(--accent);
    box-shadow: var(--focus);
  }

  .form-input::placeholder {
    color: #94a3b8;
  }

  .form-input.has-toggle {
    padding-right: 66px;
  }

  .toggle-pw {
    position: absolute;
    right: 8px;
    top: 50%;
    min-width: 52px;
    height: 34px;
    transform: translateY(-50%);
    border: 0;
    border-radius: var(--radius);
    background: #f3f7ff;
    color: #405571;
    cursor: pointer;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    transition: background .16s ease, color .16s ease;
  }

  .toggle-pw:hover {
    background: #eef2ff;
    color: var(--accent);
  }

  .form-actions {
    margin-top: 22px;
  }

  .btn-login {
    width: 100%;
    min-height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--accent) 0%, var(--green) 100%);
    color: #ffffff;
    box-shadow: 0 10px 20px rgba(91, 95, 244, .22);
    cursor: pointer;
    font: inherit;
    font-size: 15px;
    font-weight: 800;
    transition: transform .14s ease, box-shadow .14s ease, filter .14s ease;
  }

  .btn-login:hover {
    filter: saturate(1.05) brightness(.98);
    transform: translateY(-1px);
    box-shadow: 0 14px 26px rgba(91, 95, 244, .26);
  }

  .btn-login:active {
    transform: translateY(0);
  }

  .btn-arrow {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, .16);
    font-size: 14px;
    line-height: 1;
  }

  .register-link {
    margin-top: 22px;
    color: var(--muted);
    font-size: 13.5px;
    line-height: 1.6;
    text-align: center;
  }

  .register-link a {
    font-weight: 800;
  }

  .home-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
  }

  .home-link:hover {
    color: var(--accent);
  }

  @media (max-width: 860px) {
    body {
      align-items: stretch;
      padding: 0;
    }

    .auth-shell {
      min-height: 100vh;
      grid-template-columns: 1fr;
      border: 0;
      border-radius: 0;
    }

    .brand-panel {
      display: none;
    }

    .mobile-brand {
      display: flex;
      align-items: flex-start;
      flex-direction: column;
      gap: 10px;
      text-decoration: none;
    }

    .mobile-brand .brand-mark {
      width: 128px;
      height: 128px;
      min-width: 128px;
      max-width: 128px;
      max-height: 128px;
      flex: 0 0 128px;
    }

    .mobile-brand .brand-name {
      color: #071327;
    }

    .mobile-brand .brand-sub {
      color: #64748b;
    }

    .mobile-brand > div:not(.brand-mark) {
      display: none;
    }

    .form-panel {
      align-items: center;
      padding: 28px 22px;
    }
  }

  @media (max-width: 420px) {
    .form-inner h2 {
      font-size: 25px;
    }

    .toast-success {
      top: 14px;
      right: 14px;
      left: 14px;
      max-width: none;
    }
  }
</style>
</head>
<body>

<?php if(isset($_GET['registered'])): ?>
<div class="toast-success" id="reg-toast" role="status" aria-live="polite">
  สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ
</div>
<script>
  setTimeout(() => {
    const toast = document.getElementById('reg-toast');
    if(toast) toast.style.opacity = '0';
  }, 4000);
</script>
<?php endif; ?>

<main class="auth-shell">
  <section class="brand-panel" aria-label="Job_Find">
    <a class="brand-lockup" href="index.php" aria-label="Job_Find home">
      <div class="brand-mark"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=7" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="brand-name">Job_Find</div>
        <div class="brand-sub">แพลตฟอร์มหางาน Freelance</div>
      </div>
    </a>

    <div class="brand-copy">
      <h1>กลับเข้าสู่พื้นที่ทำงานของคุณ</h1>
      <p>ติดตามงาน โปรไฟล์ การสมัคร และรีวิวได้จากบัญชีเดียว</p>
    </div>

    <div class="signal-list" aria-label="Account areas">
      <div class="signal-item">
        <span class="signal-icon">J</span>
        <span>งานล่าสุดและสถานะการสมัคร</span>
      </div>
      <div class="signal-item">
        <span class="signal-icon">P</span>
        <span>โปรไฟล์ Freelancer และ Employer</span>
      </div>
      <div class="signal-item">
        <span class="signal-icon">R</span>
        <span>รีวิว เรตติ้ง และข้อความสนับสนุน</span>
      </div>
    </div>

    <div class="brand-footer">© 2026 Job_Find</div>
  </section>

  <section class="form-panel">
    <div class="form-inner">
      <a class="mobile-brand" href="index.php" aria-label="Job_Find home">
        <div class="brand-mark"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=7" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
        <div>
          <div class="brand-name">Job_Find</div>
          <div class="brand-sub">แพลตฟอร์มหางาน Freelance</div>
        </div>
      </a>

      <div class="form-kicker">Account Login</div>
      <h2>เข้าสู่ระบบ</h2>
      <p class="subtitle">กรอกอีเมลและรหัสผ่านเพื่อไปยังแดชบอร์ดของคุณ</p>

      <?php if($error): ?>
      <div class="alert-err" role="alert">
        <span class="alert-icon">!</span>
        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="on">
        <div class="field-group">
          <label for="email">อีเมล</label>
          <div class="input-wrap">
            <span class="input-icon">@</span>
            <input id="email" type="email" name="email" class="form-input"
                   placeholder="email@example.com"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   required autocomplete="email">
          </div>
        </div>

        <div class="field-group">
          <label for="pw-input">รหัสผ่าน</label>
          <div class="input-wrap">
            <span class="input-icon">#</span>
            <input id="pw-input" type="password" name="password"
                   class="form-input has-toggle"
                   placeholder="รหัสผ่านของคุณ"
                   required autocomplete="current-password">
            <button type="button" class="toggle-pw" id="toggle-pw" aria-controls="pw-input" aria-label="แสดงรหัสผ่าน">
              แสดง
            </button>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" name="login" class="btn-login">
            เข้าสู่ระบบ <span class="btn-arrow">›</span>
          </button>
        </div>
      </form>

      <div class="register-link">
        ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a>
      </div>

      <a class="home-link" href="index.php">← กลับหน้าแรก</a>
    </div>
  </section>
</main>

<script>
  const toggleButton = document.getElementById('toggle-pw');
  const passwordInput = document.getElementById('pw-input');

  if(toggleButton && passwordInput){
    toggleButton.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      toggleButton.textContent = isPassword ? 'ซ่อน' : 'แสดง';
      toggleButton.setAttribute('aria-label', isPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
    });
  }
</script>
</body>
</html>
