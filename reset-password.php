<?php
declare(strict_types=1);
$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$validFormat = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
?>
<!doctype html>
<html lang="en-AU">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Reset password | MegaCards.jp</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:#101b33;background:linear-gradient(145deg,#071631,#123d80);font-family:"DM Sans",sans-serif}.card{width:min(480px,100%);padding:38px;background:#fff;border-radius:24px;box-shadow:0 28px 80px rgba(0,0,0,.3)}.brand{display:inline-flex;align-items:center;gap:10px;margin-bottom:30px;color:#101b33;text-decoration:none;font:800 1.1rem "Manrope",sans-serif}.mark{display:grid;place-items:center;width:34px;height:34px;color:#fff;background:#1459e9;border-radius:9px}.eyebrow{margin:0 0 8px;color:#1459e9;font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{margin:0;font:800 2rem/1.1 "Manrope",sans-serif;letter-spacing:-.04em}.intro{margin:14px 0 24px;color:#58647a;font-size:.9rem;line-height:1.6}form{display:grid;gap:14px}label span{display:block;margin-bottom:5px;color:#58647a;font-size:.7rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}input{width:100%;min-height:48px;padding:10px 12px;border:1px solid #d9dfeb;border-radius:10px;font:inherit;outline:none}input:focus{border-color:#1459e9;box-shadow:0 0 0 3px rgba(20,89,233,.1)}small{display:block;margin-top:5px;color:#6b7280;font-size:.72rem}.status{min-height:22px;margin:0;color:#a52a2a;font-size:.8rem;text-align:center}.button{min-height:50px;color:#fff;background:#1459e9;border:0;border-radius:12px;cursor:pointer;font:800 .9rem "DM Sans",sans-serif}.button:disabled{opacity:.6}.back{display:block;margin-top:20px;color:#1459e9;font-size:.8rem;font-weight:700;text-align:center}.invalid{padding:16px;color:#8b1e1e;background:#fff1f1;border-radius:12px;line-height:1.5}
  </style>
</head>
<body>
  <main class="card">
    <a class="brand" href="/"><span class="mark">M</span><span>MegaCards.jp</span></a>
    <p class="eyebrow">Member security</p>
    <h1>Choose a new password.</h1>
    <?php if ($validFormat): ?>
      <p class="intro">Enter a new password for your account. Reset links expire after 60 minutes and can only be used once.</p>
      <form id="resetForm">
        <label><span>New password</span><input name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,128}" required><small>At least 8 characters, including an uppercase letter and a number.</small></label>
        <label><span>Confirm new password</span><input name="confirmPassword" type="password" autocomplete="new-password" minlength="8" maxlength="128" required></label>
        <p class="status" id="resetStatus" role="status" aria-live="polite"></p>
        <button class="button" id="resetSubmit" type="submit">Update password</button>
      </form>
    <?php else: ?>
      <p class="invalid">This reset link is invalid. Return to the login window and request a new link.</p>
    <?php endif; ?>
    <a class="back" href="/?member_login=1">Back to member login</a>
  </main>
<?php if ($validFormat): ?>
<script>
const form=document.getElementById('resetForm');const status=document.getElementById('resetStatus');const submit=document.getElementById('resetSubmit');
form.addEventListener('submit',async(event)=>{event.preventDefault();status.textContent='';const data=new FormData(form);const password=String(data.get('password')||'');if(password!==String(data.get('confirmPassword')||'')){status.textContent='Passwords do not match.';return}if(!form.reportValidity())return;submit.disabled=true;submit.textContent='Updating…';try{const response=await fetch('/password_reset.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'reset',token:<?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,password})});const result=await response.json().catch(()=>({}));if(!response.ok||!result.ok)throw new Error(result.error||'Your password could not be updated.');form.reset();status.style.color='#16784a';status.textContent=result.message;submit.textContent='Password updated';window.setTimeout(()=>{window.location.href='/?member_login=1'},1800)}catch(error){status.textContent=error.message||'Your password could not be updated.';submit.disabled=false;submit.textContent='Update password'}});
</script>
<?php endif; ?>
</body>
</html>
