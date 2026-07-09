<?php
session_start();
if (isset($_SESSION['tgj_admin'])) { header('Location: index.php'); exit(); }
require_once '../includes/connection.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $row  = $conn->query("SELECT * FROM admin WHERE username = '" . $conn->real_escape_string($user) . "' LIMIT 1")->fetch_assoc();
    if ($row && password_verify($pass, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['tgj_admin'] = true;
        header('Location: index.php'); exit();
    } else {
        $error = 'Verkeerde gebruikersnaam of wachtwoord.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Admin Login — TGJ</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg); }
    .login-card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:40px; width:100%; max-width:380px; }
    .login-title { font-size:1.4rem; font-weight:900; color:var(--gold); margin-bottom:28px; text-align:center; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-title">⚔ TGJ Admin</div>
    <?php if ($error): ?><div class="flash flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Gebruikersnaam</label>
            <input type="text" name="username" class="form-control" autofocus>
        </div>
        <div class="form-group">
            <label>Wachtwoord</label>
            <input type="password" name="password" class="form-control">
        </div>
        <button class="btn btn-gold" style="width:100%;justify-content:center;">Inloggen</button>
    </form>
</div>
</body>
</html>
