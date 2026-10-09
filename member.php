<?php
declare(strict_types=1);

const MEGACARDS_MEMBER_PAGE_TTL = 2592000;
ini_set('session.gc_maxlifetime', (string) MEGACARDS_MEMBER_PAGE_TTL);
session_set_cookie_params([
    'lifetime' => MEGACARDS_MEMBER_PAGE_TTL,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
$member = $_SESSION['mc_member'] ?? null;
if (!is_array($member) || empty($member['id']) || empty($member['email'])) {
    header('Location: /?member_login=1', true, 302);
    exit;
}
$membersFile = __DIR__ . '/data/members.json';
$storedMembers = is_file($membersFile) ? json_decode((string) file_get_contents($membersFile), true) : [];
if (is_array($storedMembers)) {
    foreach ($storedMembers as $storedMember) {
        if (!is_array($storedMember) || (string) ($storedMember['id'] ?? '') !== (string) $member['id']) continue;
        $member['name'] = (string) ($storedMember['name'] ?? $member['name'] ?? '');
        $member['email'] = (string) ($storedMember['email'] ?? $member['email']);
        $member['state'] = (string) ($storedMember['state'] ?? $member['state'] ?? '');
        $member['address'] = is_array($storedMember['address'] ?? null) ? $storedMember['address'] : [];
        $_SESSION['mc_member'] = array_merge($_SESSION['mc_member'], $member);
        break;
    }
}
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
function member_page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$name = member_page_escape((string) ($member['name'] ?? ''));
$email = member_page_escape((string) $member['email']);
$address = is_array($member['address'] ?? null) ? $member['address'] : [];
$addressLine1 = member_page_escape((string) ($address['line1'] ?? ''));
$addressLine2 = member_page_escape((string) ($address['line2'] ?? ''));
$suburb = member_page_escape((string) ($address['suburb'] ?? ''));
$state = (string) ($address['state'] ?? $member['state'] ?? '');
$postcode = member_page_escape((string) ($address['postcode'] ?? ''));
$hasAddress = trim((string) ($address['line1'] ?? '')) !== '' && trim((string) ($address['postcode'] ?? '')) !== '';
$addressSummary = $hasAddress ? implode(', ', array_filter([(string) ($address['line1'] ?? ''), (string) ($address['line2'] ?? ''), (string) ($address['suburb'] ?? ''), trim($state . ' ' . (string) ($address['postcode'] ?? '')), 'Australia'])) : '';
$states = [
    'NSW' => 'New South Wales', 'VIC' => 'Victoria', 'QLD' => 'Queensland', 'WA' => 'Western Australia',
    'SA' => 'South Australia', 'TAS' => 'Tasmania', 'ACT' => 'Australian Capital Territory', 'NT' => 'Northern Territory',
];
?>
<!doctype html>
<html lang="en-AU">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My account | MegaCards.jp</title>
  <meta name="robots" content="noindex,nofollow">
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-SPWWT45MZF"></script>
  <script src="/ga4_attribution.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--navy:#0b1428;--blue:#1459e9;--blue-dark:#0b3cac;--yellow:#ffd447;--ink:#101522;--muted:#626b7d;--line:#dce2eb;--paper:#f3f6fa;--green:#0d7a50;}
    *{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--paper);font-family:"DM Sans",Arial,sans-serif;line-height:1.55}.shell{width:min(calc(100% - 40px),1080px);margin-inline:auto}button,input,select{font:inherit}a{color:inherit;text-decoration:none}
    .topbar{color:#fff;background:var(--navy);border-bottom:1px solid rgba(255,255,255,.1)}.topbar__inner{min-height:76px;display:flex;align-items:center;gap:24px}.brand{display:inline-flex;align-items:center;gap:10px;margin-right:auto;font:800 1.25rem "Manrope",sans-serif;letter-spacing:-.04em}.brand__mark{display:grid;place-items:center;width:34px;height:34px;color:var(--navy);background:var(--yellow);border-radius:10px;transform:rotate(-4deg)}.brand__suffix{color:#67a0ff}.topbar__link{padding:9px 13px;color:#dbe5fa;border:1px solid #34425f;border-radius:999px;font-size:.78rem;font-weight:800}.topbar__link:hover{background:#172746}.topbar__logout{padding:9px 13px;color:var(--navy);background:var(--yellow);border:0;border-radius:999px;cursor:pointer;font-size:.78rem;font-weight:900}
    main{padding:58px 0 80px}.account-heading{display:grid;grid-template-columns:1fr auto;align-items:end;gap:24px;margin-bottom:26px}.eyebrow{margin:0 0 8px;color:var(--blue);font-size:.72rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.account-heading h1{margin:0;font:800 clamp(2.1rem,5vw,3.5rem)/1 "Manrope",sans-serif;letter-spacing:-.055em}.account-heading p:last-child{margin:14px 0 0;color:var(--muted)}.signed-pill{display:flex;align-items:center;gap:9px;padding:10px 14px;color:#255333;background:#eef9f1;border:1px solid #b8dfc2;border-radius:999px;font-size:.75rem;font-weight:800}.signed-pill::before{content:"";width:8px;height:8px;background:#16a34a;border-radius:50%;box-shadow:0 0 0 4px rgba(22,163,74,.12)}
    .account-grid{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:22px}.panel{padding:30px;background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 18px 50px rgba(11,20,40,.07)}.panel h2{margin:0 0 6px;font:800 1.35rem "Manrope",sans-serif;letter-spacing:-.025em}.panel__intro{margin:0 0 24px;color:var(--muted);font-size:.84rem}.profile-form{display:grid;grid-template-columns:1fr 1fr;gap:18px}.field{display:block}.field--wide{grid-column:1/-1}.field span{display:block;margin-bottom:6px;color:#465066;font-size:.72rem;font-weight:800;letter-spacing:.045em;text-transform:uppercase}.field small{display:block;margin-top:5px;color:#7b8495;font-size:.7rem}.field input,.field select{width:100%;min-height:48px;padding:10px 12px;color:var(--ink);background:#fff;border:1px solid #cfd7e3;border-radius:10px;outline:none}.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(20,89,233,.1)}.field input[readonly]{color:#4f596b;background:#f5f7fa}.form-section{grid-column:1/-1;margin-top:4px;padding-top:22px;border-top:1px solid var(--line)}.form-section h3{margin:0 0 4px;font-size:1rem}.form-section p{margin:0;color:var(--muted);font-size:.78rem}.form-status{grid-column:1/-1;min-height:22px;margin:0;font-size:.8rem;font-weight:700}.form-status.error{color:#b42318}.form-status.success{color:var(--green)}.save-button{grid-column:1/-1;min-height:50px;color:#fff;background:var(--blue);border:0;border-radius:11px;cursor:pointer;font-weight:900}.save-button:hover{background:var(--blue-dark)}.save-button:disabled{cursor:not-allowed;opacity:.58}
    .account-aside{display:grid;align-content:start;gap:14px}.info-card{padding:22px;background:var(--navy);border-radius:18px;color:#fff}.info-card h2{margin:0 0 12px;font:800 1.05rem "Manrope",sans-serif}.info-card p{margin:0;color:#b9c4d9;font-size:.78rem;overflow-wrap:anywhere}.info-card strong{display:block;margin-top:5px;color:#fff;font-size:.88rem}.security-card{padding:20px;background:#fff;border:1px solid var(--line);border-radius:16px}.security-card strong{display:block;margin-bottom:7px;font-size:.84rem}.security-card p{margin:0;color:var(--muted);font-size:.75rem}.back-link{display:block;padding:13px;color:var(--blue);background:#fff;border:1px solid var(--line);border-radius:12px;text-align:center;font-size:.8rem;font-weight:900}
    .orders-panel{margin-top:22px;padding:30px;background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 18px 50px rgba(11,20,40,.07)}.orders-heading{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:20px}.orders-heading h2{margin:0;font:800 1.35rem "Manrope",sans-serif}.orders-heading p{margin:5px 0 0;color:var(--muted);font-size:.8rem}.orders-count{padding:7px 11px;color:#294265;background:#edf3fd;border-radius:999px;font-size:.7rem;font-weight:800}.orders-list{display:grid;gap:14px}.orders-empty{padding:28px;color:var(--muted);background:#f7f9fc;border:1px dashed #cbd5e1;border-radius:14px;text-align:center;font-size:.82rem}.order-card{padding:20px;border:1px solid var(--line);border-radius:15px}.order-card__head{display:flex;justify-content:space-between;gap:14px;align-items:start}.order-card__number{font:800 1rem "Manrope",sans-serif}.order-card__date{margin-top:3px;color:var(--muted);font-size:.72rem}.order-status{padding:6px 10px;color:#7a5200;background:#fff5d6;border-radius:999px;font-size:.68rem;font-weight:900;text-transform:capitalize}.order-card__meta{display:flex;flex-wrap:wrap;gap:8px 18px;margin:15px 0;color:#4d586b;font-size:.75rem}.order-items{display:grid;gap:7px;padding:13px 0;border-top:1px solid #edf0f4;border-bottom:1px solid #edf0f4}.order-item{display:flex;justify-content:space-between;gap:16px;font-size:.76rem}.order-item span{color:#3f495a}.order-item strong{white-space:nowrap}.order-card__foot{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:15px}.order-total small{display:block;color:var(--muted);font-size:.66rem}.order-total strong{font-size:1.05rem}.receipt-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;color:#fff;background:var(--blue);border-radius:9px;font-size:.73rem;font-weight:900}.receipt-button:hover{background:var(--blue-dark)}
    @media(max-width:760px){.shell{width:min(calc(100% - 28px),1080px)}.topbar__inner{min-height:66px;gap:8px}.brand{font-size:1.05rem}.brand__mark{width:30px;height:30px}.topbar__link,.topbar__logout{padding:8px 9px;font-size:.68rem}main{padding:38px 0 56px}.account-heading{grid-template-columns:1fr;gap:14px}.signed-pill{justify-self:start}.account-grid{grid-template-columns:1fr}.panel,.orders-panel{padding:22px}.profile-form{grid-template-columns:1fr}.field--wide,.form-section,.form-status,.save-button{grid-column:auto}.account-aside{grid-row:1}.info-card{padding:18px}.orders-heading,.order-card__head,.order-card__foot{align-items:stretch;flex-direction:column}.receipt-button{width:100%}}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="shell topbar__inner">
      <a class="brand" href="/"><span class="brand__mark">M</span><span>MegaCards<span class="brand__suffix">.jp</span></span></a>
      <a class="topbar__link" href="/#shop">Shop boxes</a>
      <button class="topbar__logout" id="memberPageLogout" type="button">Log out</button>
    </div>
  </header>
  <main>
    <div class="shell">
      <div class="account-heading">
        <div><p class="eyebrow">MegaCards membership</p><h1>My account</h1><p>Review and update your member information.</p></div>
        <div class="signed-pill">Signed in as <span id="headingMemberName"><?= $name ?></span></div>
      </div>
      <div class="account-grid">
        <section class="panel" aria-labelledby="profileTitle">
          <h2 id="profileTitle">Member information</h2>
          <p class="panel__intro">Update your personal details and shipping &amp; billing address. Your email address is your login ID.</p>
          <form class="profile-form" id="memberProfileForm">
            <label class="field"><span>Full name</span><input name="name" type="text" autocomplete="name" maxlength="80" value="<?= $name ?>" required></label>
            <label class="field"><span>Email address / Login ID</span><input name="email" type="email" autocomplete="email" maxlength="190" value="<?= $email ?>" required></label>
            <div class="form-section"><h3>Shipping &amp; billing address</h3><p>This single address is used for both delivery and billing.</p></div>
            <label class="field field--wide"><span>Street address</span><input name="addressLine1" type="text" autocomplete="address-line1" maxlength="120" value="<?= $addressLine1 ?>" required></label>
            <label class="field field--wide"><span>Apartment, suite, etc. (optional)</span><input name="addressLine2" type="text" autocomplete="address-line2" maxlength="120" value="<?= $addressLine2 ?>"></label>
            <label class="field field--wide"><span>Suburb or city</span><input name="suburb" type="text" autocomplete="address-level2" maxlength="80" value="<?= $suburb ?>" required></label>
            <label class="field"><span>State or territory</span><select name="state" autocomplete="address-level1" required><?php foreach ($states as $code => $label): ?><option value="<?= $code ?>"<?= $state === $code ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Postcode</span><input name="postcode" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="4" pattern="[0-9]{4}" value="<?= $postcode ?>" required></label>
            <label class="field field--wide"><span>Country</span><input name="country" type="text" autocomplete="country-name" value="Australia" readonly></label>
            <div class="form-section"><h3>Password</h3><p>Leave the new password fields blank to keep your current password.</p></div>
            <label class="field"><span>New password</span><input name="newPassword" type="password" autocomplete="new-password" minlength="8" maxlength="128" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,128}" title="Use at least 8 characters with an uppercase letter and a number."><small>At least 8 characters with an uppercase letter and a number.</small></label>
            <label class="field"><span>Confirm new password</span><input name="confirmPassword" type="password" autocomplete="new-password" minlength="8" maxlength="128"></label>
            <label class="field field--wide"><span>Current password</span><input name="currentPassword" type="password" autocomplete="current-password" maxlength="128" required><small>Required to confirm and save any changes.</small></label>
            <p class="form-status" id="memberProfileStatus" role="status" aria-live="polite"></p>
            <button class="save-button" id="memberProfileSubmit" type="submit">Save changes</button>
          </form>
        </section>
        <aside class="account-aside">
          <div class="info-card"><h2>Login ID</h2><p>Email address<strong id="accountEmail"><?= $email ?></strong></p></div>
          <div class="info-card"><h2>Shipping &amp; billing</h2><p>Saved address<strong id="accountAddress"><?= member_page_escape($addressSummary !== '' ? $addressSummary : 'Add your address') ?></strong></p></div>
          <div class="security-card"><strong>Your password stays private</strong><p>Passwords are stored as secure hashes and are never displayed or returned by the member API.</p></div>
          <a class="back-link" href="/">← Back to MegaCards</a>
        </aside>
      </div>
      <section class="orders-panel" aria-labelledby="ordersTitle">
        <div class="orders-heading"><div><h2 id="ordersTitle">Order history</h2><p>View orders placed with this account and download a PDF receipt.</p></div><span class="orders-count" id="ordersCount">Loading…</span></div>
        <div class="orders-list" id="ordersList"><div class="orders-empty">Loading your orders…</div></div>
      </section>
    </div>
  </main>
  <script>
    const profileForm = document.getElementById('memberProfileForm');
    const profileStatus = document.getElementById('memberProfileStatus');
    const profileSubmit = document.getElementById('memberProfileSubmit');
    const ordersList = document.getElementById('ordersList');
    const ordersCount = document.getElementById('ordersCount');
    const accountEscape = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
    const orderMoney = value => `JPY ${Math.max(0, Number(value) || 0).toLocaleString('en-AU')}`;
    async function loadOrderHistory() {
      try {
        const response = await fetch('/member_orders.php?v=' + Date.now(), { cache: 'no-store' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok || !Array.isArray(data.orders)) throw new Error(data.error || 'Order history could not be loaded.');
        ordersCount.textContent = `${data.orders.length} ${data.orders.length === 1 ? 'order' : 'orders'}`;
        if (!data.orders.length) {
          ordersList.innerHTML = '<div class="orders-empty">No orders have been placed with this account yet.</div>';
          return;
        }
        ordersList.innerHTML = data.orders.map(order => {
          const date = order.created_at ? new Date(order.created_at).toLocaleString('en-AU', { day:'numeric', month:'short', year:'numeric', hour:'numeric', minute:'2-digit' }) : 'Date unavailable';
          const items = Array.isArray(order.items) ? order.items : [];
          const itemRows = items.map(item => `<div class="order-item"><span>${accountEscape(item.title)} × ${Math.max(1, Number(item.qty) || 1)}</span><strong>${orderMoney((Number(item.price_jpy) || 0) * Math.max(1, Number(item.qty) || 1))}</strong></div>`).join('');
          const shipping = [order.shipping_carrier, order.shipping_service].filter(Boolean).join(' · ');
          return `<article class="order-card">
            <div class="order-card__head"><div><div class="order-card__number">Order ${accountEscape(order.order_number)}</div><div class="order-card__date">${accountEscape(date)}</div></div><span class="order-status">${accountEscape(order.status || 'Pending payment')}</span></div>
            <div class="order-card__meta"><span>Payment: <strong>${accountEscape(String(order.payment_method || '').toUpperCase() || '—')}</strong></span>${shipping ? `<span>Delivery: <strong>${accountEscape(shipping)}</strong></span>` : ''}${order.tracking_number ? `<span>Tracking: <strong>${accountEscape(order.tracking_number)}</strong></span>` : ''}</div>
            <div class="order-items">${itemRows || '<div class="order-item"><span>Order details</span><strong>—</strong></div>'}</div>
            <div class="order-card__foot"><div class="order-total"><small>Order total</small><strong>${orderMoney(order.total_jpy)}</strong></div><a class="receipt-button" href="/member_orders.php?receipt=${encodeURIComponent(order.order_number)}" download>Download PDF receipt</a></div>
          </article>`;
        }).join('');
      } catch (error) {
        ordersCount.textContent = 'Unavailable';
        ordersList.innerHTML = `<div class="orders-empty">${accountEscape(error.message || 'Order history could not be loaded.')}</div>`;
      }
    }
    loadOrderHistory();
    profileForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      profileStatus.textContent = '';
      profileStatus.className = 'form-status';
      if (!profileForm.reportValidity()) return;
      const form = new FormData(profileForm);
      const newPassword = String(form.get('newPassword') || '');
      const confirmPassword = String(form.get('confirmPassword') || '');
      if (newPassword !== confirmPassword) {
        profileStatus.textContent = 'New passwords do not match.';
        profileStatus.classList.add('error');
        return;
      }
      if (newPassword && (newPassword.length < 8 || !/[A-Z]/.test(newPassword) || !/\d/.test(newPassword))) {
        profileStatus.textContent = 'Use at least 8 characters with an uppercase letter and a number.';
        profileStatus.classList.add('error');
        return;
      }
      profileSubmit.disabled = true;
      profileSubmit.textContent = 'Saving…';
      try {
        const response = await fetch('/member_auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_profile',
            name: String(form.get('name') || '').trim(),
            email: String(form.get('email') || '').trim(),
            address_line1: String(form.get('addressLine1') || '').trim(),
            address_line2: String(form.get('addressLine2') || '').trim(),
            suburb: String(form.get('suburb') || '').trim(),
            state: String(form.get('state') || ''),
            postcode: String(form.get('postcode') || '').trim(),
            current_password: String(form.get('currentPassword') || ''),
            new_password: newPassword
          })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok || !data.member) throw new Error(data.error || 'Your changes could not be saved.');
        document.getElementById('headingMemberName').textContent = data.member.name || data.member.email;
        document.getElementById('accountEmail').textContent = data.member.email;
        const address = data.member.address || {};
        document.getElementById('accountAddress').textContent = [address.line1, address.line2, address.suburb, [address.state, address.postcode].filter(Boolean).join(' '), address.country || 'Australia'].filter(Boolean).join(', ');
        profileForm.elements.currentPassword.value = '';
        profileForm.elements.newPassword.value = '';
        profileForm.elements.confirmPassword.value = '';
        profileStatus.textContent = 'Your member information has been updated.';
        profileStatus.classList.add('success');
      } catch (error) {
        profileStatus.textContent = error.message || 'Your changes could not be saved.';
        profileStatus.classList.add('error');
      } finally {
        profileSubmit.disabled = false;
        profileSubmit.textContent = 'Save changes';
      }
    });
    document.getElementById('memberPageLogout').addEventListener('click', async () => {
      try {
        await fetch('/member_auth.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'logout' }) });
      } finally {
        window.location.href = '/';
      }
    });
  </script>
</body>
</html>
