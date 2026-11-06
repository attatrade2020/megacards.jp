# GAS 管理APIの認証サンプル

GAS側の既存 `doGet` / `doPost` に以下の認証・振り分けを統合してください。
このリポジトリの変更だけではサーバーの認証は有効になりません。統合後はWebアプリを新バージョンで再デプロイしてください。

スクリプト設定の「スクリプト プロパティ」に `ADMIN_PASSWORD` を登録し、十分に長い新しいパスワードを設定してください。公開済みの旧パスワードは再利用しないでください。値をソースコードやログに書かないでください。

管理画面はJSON文字列を `Content-Type: text/plain;charset=UTF-8` のPOSTボディで送ります（GASへのクロスオリジンのプリフライトを避けるため）。`e.postData.contents` をJSONとして読み取ります。認証成功は `{ "success": true }`、失敗は `{ "error": "unauthorized" }` です。ContentServiceの応答は通常HTTP 200となるため、画面側はJSONのエラーも判定します。

```javascript
function jsonResponse_(value) {
  return ContentService.createTextOutput(JSON.stringify(value))
    .setMimeType(ContentService.MimeType.JSON);
}

// 固定長のSHA-256ダイジェストを全バイト比較し、途中でreturnしない。
// JavaScript/GASの実行基盤には厳密な定数時間の保証はないが、
// 平文の文字列比較や不一致位置での早期終了は避ける。
function constantTimePasswordEqual_(provided, expected) {
  const left = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256, provided, Utilities.Charset.UTF_8);
  const right = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256, expected, Utilities.Charset.UTF_8);
  let difference = 0;
  for (let i = 0; i < left.length; i++) {
    difference |= left[i] ^ right[i];
  }
  return difference === 0;
}

function isAuthorized_(password) {
  const expected = PropertiesService.getScriptProperties()
    .getProperty('ADMIN_PASSWORD');
  // 未設定・空のプロパティでは認証させない。
  if (!expected || typeof password !== 'string' || !password) return false;
  return constantTimePasswordEqual_(password, expected);
}

function doPost(e) {
  let body;
  try {
    body = JSON.parse(e.postData.contents);
  } catch (_) {
    return jsonResponse_({ error: 'invalid_request' });
  }
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return jsonResponse_({ error: 'invalid_request' });
  }

  const adminActions = [
    'verify', 'orders', 'save_product', 'delete_product',
    'update_order_status', 'fetch_snkrdunk'
  ];
  if (adminActions.indexOf(body.action) !== -1) {
    // 注文読取・副作用を含む既存処理より先に認証する。
    if (!isAuthorized_(body.password)) {
      return jsonResponse_({ error: 'unauthorized' });
    }
    if (body.action === 'verify') {
      return jsonResponse_({ success: true });
    }
    try {
      return jsonResponse_(dispatchAdminAction_(body));
    } catch (_) {
      // リクエスト全体やパスワードをログ・応答に出さない。
      return jsonResponse_({ error: 'operation_failed' });
    }
  }

  // 公開の注文作成等がある場合は、既存の公開actionだけを明示的に
  // 分岐してここに移植する。管理処理への汎用フォールバックは禁止。
  return jsonResponse_({ error: 'unknown_action' });
}

function doGet(e) {
  const action = e && e.parameter && e.parameter.action;
  if (action === 'products') {
    // 下記の関数名を既存の公開商品一覧処理に合わせる。
    return jsonResponse_({ products: listPublicProducts_() });
  }
  // 旧 GET ?action=orders を必ず無効にする。
  // GETではパスワードも受け付けず、管理処理を実行しない。
  return jsonResponse_({ error: 'unauthorized' });
}

// 以下の処理名は接続例。実際のシート操作等の既存処理に置き換える。
// 各処理はJSON化できる値を返し、失敗時は例外を投げること。
function dispatchAdminAction_(body) {
  switch (body.action) {
    case 'orders':
      return { orders: listOrders_() };
    case 'save_product':
      saveProduct_(body.product);
      return { success: true };
    case 'delete_product':
      deleteProduct_(body.id);
      return { success: true };
    case 'update_order_status':
      updateOrderStatus_(body.id, body.status);
      return { success: true };
    case 'fetch_snkrdunk':
      return fetchSnkrdunk_(body.url);
    default:
      throw new Error('unknown_action');
  }
}
```

既存エンドポイントに認証なしで注文を返す分岐や、保存・削除・注文更新を実行する経路を残さないでください。公開の商品一覧・注文作成の仕様は維持し、上記の接続例を既存実装に合わせて統合します。

管理画面は入力パスワードを `sessionStorage` に保持し、再読み込み時にも `verify` で再検証します。ログアウト・`unauthorized` 応答で削除します。URLクエリには含めません。

動作確認は、誤ったパスワードでverifyと各管理actionが `unauthorized` になること、正しいパスワードでのみ成功すること、GETのordersが拒否されること、Script Propertiesでパスワード変更後に旧セッションの次の管理リクエストでログアウトすることを確認してください。
