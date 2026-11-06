/**
 * MegaCards.jp - Snkrdunk Auto-Sync
 * 機能: 価格変動検出、OOS検出 (HTTP 404)、在庫復活、メール通知
 *
 * 変更点(認証対応):
 * - ADMIN_PASSWORD の直書きを削除し、スクリプト プロパティ ADMIN_PASSWORD から読む
 * - save_product の応答を確認し、unauthorized などの失敗をエラーとして数える
 * - save_settings を GET(URLに値を載せる)から、パスワード付き POST に変更
 */

// ===== CONFIG =====
const GAS_URL = 'https://script.google.com/macros/s/AKfycbyhZu5LMJPiG5VGmAz9ctJ2O6BHsrA7r3-Ej3WLoh6SqiQKzquWURAYG0BWV4GPEAMi/exec';
const NOTIFY_EMAIL = ''; // 通知メール先（空文字なら送信しない）
const JPY_TO_USD = 150;

// ===== AUTH HELPERS =====
// パスワードは「プロジェクトの設定 > スクリプト プロパティ」の ADMIN_PASSWORD に保存する。
function getAdminPassword_() {
  const pw = PropertiesService.getScriptProperties().getProperty('ADMIN_PASSWORD');
  if (!pw) throw new Error('スクリプト プロパティ ADMIN_PASSWORD が未設定です');
  return pw;
}

// 管理APIへパスワード付きで POST し、結果を確認する。失敗時は例外を投げる。
function adminPost_(payload) {
  const body = Object.assign({}, payload, { password: getAdminPassword_() });
  const res = UrlFetchApp.fetch(GAS_URL, {
    method: 'post',
    contentType: 'text/plain;charset=UTF-8',
    payload: JSON.stringify(body),
    muteHttpExceptions: true,
    followRedirects: true
  });
  const code = res.getResponseCode();
  let data = null;
  try { data = JSON.parse(res.getContentText()); } catch (_) {}
  if (code < 200 || code >= 300) throw new Error('HTTP ' + code);
  if (!data) throw new Error('invalid response');
  if (data.error) throw new Error(String(data.error));
  return data;
}

// ===== ENTRY POINTS =====
function autoSyncStock() { return runSync({ checkStock: true, checkPrice: false }); }
function autoSyncFull()  { return runSync({ checkStock: true, checkPrice: true }); }
function autoSyncSnkrdunk() { return runSync({ checkStock: true, checkPrice: true }); }
function testRun() {
  const result = runSync({ checkStock: true, checkPrice: true });
  Logger.log(JSON.stringify(result, null, 2));
  return result;
}

// ===== CORE =====
function runSync(opts) {
  const startTime = new Date();
  const checkStock = opts.checkStock !== false;
  const checkPrice = opts.checkPrice !== false;
  Logger.log('Auto-sync starting at ' + startTime.toISOString());

  let products;
  try {
    const res = UrlFetchApp.fetch(GAS_URL + '?action=products', { muteHttpExceptions: true, followRedirects: true });
    products = (JSON.parse(res.getContentText()).products) || [];
  } catch (e) {
    Logger.log('Failed to fetch products: ' + e.message);
    return { error: 'fetch products failed: ' + e.message };
  }

  const snkrProducts = products.filter(p => p && p.snkrdunk_url);
  Logger.log('Snkrdunk-linked products: ' + snkrProducts.length);

  const changes = { checked: 0, priceChanges: 0, oos: 0, restocks: 0, errors: 0 };
  const details = [];

  for (let i = 0; i < snkrProducts.length; i++) {
    const p = snkrProducts[i];
    changes.checked++;

    const m = String(p.snkrdunk_url).match(/apparels\/(\d+)\/used\/(\d+)/);
    if (!m) continue;
    const apparelId = m[1], listingId = m[2];

    let apiCode = 0, item = null;
    try {
      const apiRes = UrlFetchApp.fetch(
        'https://snkrdunk.com/v1/apparels/' + apparelId + '/used/' + listingId,
        { muteHttpExceptions: true, followRedirects: true, headers: { 'Accept': 'application/json' } }
      );
      apiCode = apiRes.getResponseCode();
      if (apiCode === 200) item = (JSON.parse(apiRes.getContentText())).apparelUsedItem;
    } catch (e) {
      changes.errors++;
      Logger.log('API error for ' + p.id + ': ' + e.message);
      continue;
    }

    let changed = false;
    const oldStock = (p.stock !== undefined && p.stock !== null) ? parseInt(p.stock) : 1;
    const oldSnkrPrice = parseInt(p.snkrdunk_price) || 0;
    const oldPriceJpy = parseInt(p.price_jpy) || 0;
    const markup = parseInt(p.markup_jpy) || 0;
    const titleShort = (p.title_en || p.title_jp || p.id).substring(0, 50);

    if (checkStock) {
      if (apiCode === 404 || (apiCode === 200 && item && item.status && item.status !== 'available' && item.isDisplaySold)) {
        if (oldStock > 0) {
          p.stock = 0;
          changes.oos++;
          details.push('✗ OOS: ' + titleShort + ' (' + p.id + ')');
          changed = true;
        }
      } else if (apiCode === 200 && item) {
        if (oldStock <= 0) {
          p.stock = 1;
          changes.restocks++;
          details.push('↻ 在庫復活: ' + titleShort + ' (' + p.id + ')');
          changed = true;
        }
      }
    }

    if (checkPrice && apiCode === 200 && item) {
      const newSnkrPrice = parseInt(item.price) || 0;
      if (newSnkrPrice > 0 && newSnkrPrice !== oldSnkrPrice) {
        p.snkrdunk_price = newSnkrPrice;
        if (markup > 0) {
          p.price_jpy = newSnkrPrice + markup;
          p.price_usd = Math.round((newSnkrPrice + markup) / JPY_TO_USD);
        } else if (oldPriceJpy === oldSnkrPrice) {
          p.price_jpy = newSnkrPrice;
          p.price_usd = Math.round(newSnkrPrice / JPY_TO_USD);
        }
        changes.priceChanges++;
        const delta = newSnkrPrice - oldSnkrPrice;
        const arrow = delta > 0 ? '↑' : '↓';
        details.push(arrow + ' 価格変更: ' + titleShort + ' ¥' + oldSnkrPrice.toLocaleString() + '→¥' + newSnkrPrice.toLocaleString() + ' (' + (delta > 0 ? '+' : '') + delta.toLocaleString() + ')');
        changed = true;
      }
    }

    if (changed) {
      try {
        adminPost_({ action: 'save_product', product: p });
      } catch (e) {
        changes.errors++;
        Logger.log('Save error for ' + p.id + ': ' + e.message);
      }
    }

    Utilities.sleep(200);
  }

  const elapsed = Math.round((new Date() - startTime) / 1000);
  const total = changes.priceChanges + changes.oos + changes.restocks;

  const summary = '実行時間: ' + elapsed + '秒\n'
    + 'チェック数: ' + changes.checked + '\n'
    + '価格変更: ' + changes.priceChanges + '\n'
    + '在庫切れ: ' + changes.oos + '\n'
    + '在庫復活: ' + changes.restocks + '\n'
    + 'エラー: ' + changes.errors + '\n\n'
    + (details.length ? '--- 詳細 ---\n' + details.join('\n') : '変更なし');
  Logger.log(summary);

  if (total > 0 && NOTIFY_EMAIL) {
    try {
      const subject = '[MegaCards Auto-Sync] ' + changes.priceChanges + '価格 / ' + changes.oos + 'OOS / ' + changes.restocks + '復活';
      MailApp.sendEmail(NOTIFY_EMAIL, subject, summary);
    } catch (e) { Logger.log('Email send failed: ' + e.message); }
  }

  return Object.assign({}, changes, { elapsed: elapsed, summary: summary });
}


// ===================================================================
// AUTO-IMPORT: New listings from registered Snkrdunk search URLs
// Runs every 1 hour via Time Trigger (autoImportSnkrdunkUrls)
// For URLs with mode='auto', fetches page 1 listings and imports new products
// ===================================================================
function autoImportSnkrdunkUrls() {
  const startTime = new Date();
  Logger.log('Auto-import starting at ' + startTime.toISOString());

  // 1. Load settings to get URL list
  let entries;
  try {
    const sRes = UrlFetchApp.fetch(GAS_URL + '?action=settings', { muteHttpExceptions: true, followRedirects: true });
    const sData = JSON.parse(sRes.getContentText());
    let raw = sData.snkrdunk_search_urls;
    if (typeof raw === 'string') raw = JSON.parse(raw);
    if (!Array.isArray(raw)) raw = [];
    entries = raw.map(function(it) {
      if (typeof it === 'string') return { url: it, mode: 'manual', last_run_at: null, last_count: null };
      return { url: it.url || '', mode: it.mode === 'auto' ? 'auto' : 'manual', last_run_at: it.last_run_at || null, last_count: (it.last_count !== undefined && it.last_count !== null) ? it.last_count : null };
    }).filter(function(e) { return !!e.url; });
  } catch (e) {
    Logger.log('Settings fetch failed: ' + e.message);
    return { error: e.message };
  }

  const autoEntries = entries.filter(function(e) { return e.mode === 'auto'; });
  Logger.log('Auto entries: ' + autoEntries.length + ' / total: ' + entries.length);
  if (autoEntries.length === 0) return { skipped: true, total: 0 };

  // 2. Fetch existing products to build dedup set
  let existingSnkrUrls = new Set();
  try {
    const pRes = UrlFetchApp.fetch(GAS_URL + '?action=products', { muteHttpExceptions: true, followRedirects: true });
    const pData = JSON.parse(pRes.getContentText());
    (pData.products || []).forEach(function(p) {
      if (p && p.snkrdunk_url) existingSnkrUrls.add(p.snkrdunk_url);
    });
  } catch (e) {
    Logger.log('Products fetch failed: ' + e.message);
  }

  let totalImported = 0;
  const summary = [];

  for (let i = 0; i < entries.length; i++) {
    const entry = entries[i];
    if (entry.mode !== 'auto') continue;

    let newCount = 0;
    try {
      // 3a. Fetch search page via our PHP scraper
      const fetchUrl = 'https://megacards.jp/snkrdunk_import.php?action=fetch&url=' + encodeURIComponent(entry.url);
      const fRes = UrlFetchApp.fetch(fetchUrl, { muteHttpExceptions: true, followRedirects: true });
      const fData = JSON.parse(fRes.getContentText());
      if (!fData.ok) {
        Logger.log('Fetch failed for ' + entry.url + ': ' + (fData.error || 'unknown'));
        continue;
      }
      const products = fData.products || [];

      // 3b. Filter new products
      const newProducts = products.filter(function(sp) { return sp.snkrdunk_url && !existingSnkrUrls.has(sp.snkrdunk_url); });

      // 3c. For each new product, fetch multi-image then create
      for (let j = 0; j < newProducts.length; j++) {
        const sp = newProducts[j];

        // Get all images via API
        let imgs = sp.image_url ? [sp.image_url] : [];
        try {
          const imgApi = 'https://megacards.jp/snkrdunk_import.php?action=images&apparel_id=' + encodeURIComponent(sp.apparel_id) + '&listing_id=' + encodeURIComponent(sp.listing_id);
          const iRes = UrlFetchApp.fetch(imgApi, { muteHttpExceptions: true, followRedirects: true });
          const iData = JSON.parse(iRes.getContentText());
          if (iData.ok && iData.image_urls && iData.image_urls.length) imgs = iData.image_urls;
        } catch (_) {}

        const imgsJson = JSON.stringify(imgs);
        const product = {
          id: sp.snkr_id,
          title_en: sp.title,
          title_jp: sp.title,
          category: 'pokemon',
          icon: '',
          price_jpy: sp.price_jpy,
          price_usd: Math.round(sp.price_jpy / JPY_TO_USD),
          weight_g: 50,
          shrinkwrap: false,
          status: 'hidden',
          stock: 1,
          sort: 0,
          release_date: '',
          image_url: sp.image_url,
          notes: '[' + (sp.condition || 'PSA10') + '] Auto-imported (auto-mode) ' + (imgs.length > 1 ? '| imgs:' + imgsJson : ''),
          snkrdunk_url: sp.snkrdunk_url,
          snkrdunk_source_url: entry.url,
          snkrdunk_price: sp.price_jpy,
          condition: sp.condition || ''
        };

        try {
          adminPost_({ action: 'save_product', product: product });
          existingSnkrUrls.add(sp.snkrdunk_url);
          newCount++;
          totalImported++;
        } catch (e) {
          Logger.log('Save failed for ' + sp.snkr_id + ': ' + e.message);
        }

        Utilities.sleep(150);
      }

      Logger.log('URL ' + (i + 1) + ': new=' + newCount + ' (' + entry.url + ')');
      summary.push('#' + (i + 1) + ': ' + newCount + ' new');

      // 4. Update entry's last_run_at and last_count
      entry.last_run_at = new Date().toISOString();
      entry.last_count = newCount;

    } catch (e) {
      Logger.log('Error for ' + entry.url + ': ' + e.message);
    }
  }

  // 5. Save updated entries back to settings(パスワード付き POST)
  try {
    adminPost_({ action: 'save_settings', snkrdunk_search_urls: JSON.stringify(entries) });
  } catch (e) {
    Logger.log('Settings save failed: ' + e.message);
  }

  const elapsed = Math.round((new Date() - startTime) / 1000);
  Logger.log('Auto-import done: ' + totalImported + ' new products in ' + elapsed + 's');
  Logger.log(summary.join('\n'));

  if (totalImported > 0 && NOTIFY_EMAIL) {
    try {
      MailApp.sendEmail(NOTIFY_EMAIL, '[MegaCards Auto-Import] ' + totalImported + '件 新規取込', summary.join('\n'));
    } catch (_) {}
  }

  return { ok: true, imported: totalImported, autoUrls: autoEntries.length, elapsed: elapsed };
}
