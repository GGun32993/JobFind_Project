<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

function login_target_for_role($role){
    $targets = [
        "admin" => "admin/dashboard.php",
        "employer" => "employer/dashboard.php",
        "freelancer" => "freelancer/dashboard.php"
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
    require_once __DIR__ . "/config/config.php";
    require_once __DIR__ . "/helpers/password_helpers.php";

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if(!$conn){
        $error = "Unable to login right now. Please try again later.";
    } elseif($email === '' || $password === ''){
        $error = "Email and password are required.";
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT user_id, username, role, password
            FROM Users
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
                mysqli_stmt_store_result($stmt);
                mysqli_stmt_bind_result($stmt, $user_id, $username, $role, $stored_password);
                $found = mysqli_stmt_fetch($stmt);
                $should_rehash_password = false;
                $password_ok = $found && jobfind_verify_password($password, $stored_password, $should_rehash_password);

                if($password_ok && login_target_for_role($role)){
                    if($should_rehash_password){
                        $new_hash = jobfind_hash_password($password);
                        $update_stmt = mysqli_prepare($conn, "UPDATE Users SET password=? WHERE user_id=?");
                        if($update_stmt){
                            mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user_id);
                            if(!mysqli_stmt_execute($update_stmt)){
                                error_log("Login password rehash failed: " . mysqli_stmt_error($update_stmt));
                            }
                            mysqli_stmt_close($update_stmt);
                        } else {
                            error_log("Login password rehash prepare failed: " . mysqli_error($conn));
                        }
                    }

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
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Freelance Matching Online</title>
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
    background: var(--light);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 18px;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
  }

  a {
    color: var(--accent);
    text-decoration: none;
  }

  a:hover {
    color: #4548df;
  }

  .auth-shell {
    width: min(920px, 95vw);
    min-height: 620px;
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    overflow: hidden;
    border: 1px solid rgba(219, 228, 239, .96);
    border-radius: 24px;
    background: var(--white);
    box-shadow: 0 20px 60px rgba(0, 0, 0, .12);
  }

  .brand-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 28px;
    padding: 44px 34px;
    overflow: hidden;
    background: var(--navy);
    color: #ffffff;
    border-right: 0;
  }

  .brand-panel::before {
    content: "";
    position: absolute;
    top: -80px;
    right: -80px;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(99, 102, 241, .15);
  }

  .brand-panel::after {
    content: "";
    position: absolute;
    bottom: -60px;
    left: -60px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(99, 102, 241, .10);
  }

  .brand-lockup {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    text-decoration: none;
  }

  .brand-mark {
    width: 132px;
    height: 120px;
    flex: 0 0 120px;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: #ffffff;
    font-size: 22px;
    overflow: hidden;
    padding: 0;
    box-shadow: none;
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
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.3;
  }

  .brand-lockup > div:not(.brand-mark) {
    display: none;
  }

  .brand-copy {
    position: relative;
    z-index: 1;
    max-width: 100%;
  }

  .brand-copy h1 {
    color: #ffffff;
    font-size: 26px;
    line-height: 1.3;
    font-weight: 800;
  }

  .brand-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 32px;
    margin-bottom: 18px;
    padding: 0 11px;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 999px;
    background: rgba(255, 255, 255, .06);
    color: #c7d2fe;
    font-size: 12px;
    font-weight: 800;
    box-shadow: none;
  }

  .brand-copy p {
    margin-top: 14px;
    color: #94a3b8;
    font-size: 14px;
    line-height: 1.8;
  }

  .signal-list {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 12px;
  }

  .signal-item {
    display: grid;
    grid-template-columns: 38px 1fr;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, .10);
    border-radius: 12px;
    background: rgba(255, 255, 255, .06);
    color: #e2e8f0;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: none;
  }

  .signal-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, .12);
    background: rgba(255, 255, 255, .07);
    color: #a5b4fc;
    font-size: 16px;
    font-weight: 800;
  }

  .signal-item small {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.6;
  }

  .brand-footer {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
  }

  .brand-footer span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
  }

  .form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 44px;
    background: #ffffff;
  }

  .form-inner {
    width: 100%;
    max-width: 420px;
  }

  .mobile-brand {
    display: none;
    margin-bottom: 28px;
  }

  .mobile-brand > div:not(.brand-mark) {
    display: none;
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
      display: grid;
      justify-items: center;
      gap: 8px;
      text-decoration: none;
      text-align: center;
    }

    .mobile-brand .brand-mark {
      width: 128px;
      height: 108px;
      min-width: 0;
      max-width: 128px;
      max-height: 108px;
      padding: 0;
    }

    .mobile-brand .brand-name {
      color: #071327;
    }

    .mobile-brand .brand-sub {
      color: #64748b;
    }

    .form-panel {
      align-items: center;
      width: 100vw;
      max-width: 100vw;
      padding: 28px 22px;
      overflow-x: hidden;
    }

    .form-inner {
      width: calc(100vw - 44px);
      max-width: 420px;
    }

    .form-inner form,
    .field-group,
    .input-wrap,
    .form-input,
    .btn-login {
      max-width: 100%;
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
  <section class="brand-panel" aria-label="Freelance Matching Online">
    <a class="brand-lockup" href="index.php" aria-label="Freelance Matching Online home">
      <div class="brand-mark"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo"></div>
      <div>
        <div class="brand-name">Freelance Matching Online</div>
        <div class="brand-sub">หางานที่ใช่ ได้งานที่ชอบ</div>
      </div>
    </a>

    <div class="brand-copy">
      <div class="brand-kicker"><i class="bi bi-shield-check"></i> Account Workspace</div>
      <h1>กลับเข้าสู่พื้นที่ทำงานของคุณ</h1>
      <p>เข้าสู่ระบบเพื่อจัดการโปรไฟล์ ติดตามงาน สมัครงาน หรือดูผู้สมัครในพื้นที่เดียวของ Freelance Matching Online</p>
    </div>

    <div class="signal-list" aria-label="Account areas">
      <div class="signal-item">
        <span class="signal-icon"><i class="bi bi-briefcase"></i></span>
        <span>งานและการสมัคร<small>ดูงานล่าสุด ติดตามสถานะ และกลับไปสมัครต่อได้เร็ว</small></span>
      </div>
      <div class="signal-item">
        <span class="signal-icon"><i class="bi bi-person-badge"></i></span>
        <span>โปรไฟล์ของคุณ<small>แก้ไขข้อมูล Freelancer หรือ Employer ได้จากบัญชีเดียว</small></span>
      </div>
      <div class="signal-item">
        <span class="signal-icon"><i class="bi bi-star"></i></span>
        <span>รีวิวและเรตติ้ง<small>ดูคะแนน ประวัติการทำงาน และความน่าเชื่อถือ</small></span>
      </div>
    </div>

    <div class="brand-footer">
      <span><i class="bi bi-house-door"></i> กดโลโก้เพื่อกลับหน้าแรก</span>
      <span>© 2026</span>
    </div>
  </section>

  <section class="form-panel">
    <div class="form-inner">
      <a class="mobile-brand" href="index.php" aria-label="Freelance Matching Online home">
        <div class="brand-mark"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo"></div>
        <div>
          <div class="brand-name">Freelance Matching Online</div>
          <div class="brand-sub">หางานที่ใช่ ได้งานที่ชอบ</div>
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
