<?php
// 共通パスワード
define('COMMON_PASSWORD', 'E_PEN');

// データファイルパスの定義
define('LOGGED_IN_USERS_FILE', __DIR__ . '/logged_in_users.json');
define('MESSAGES_FILE', __DIR__ . '/messages.json');

// トリップ生成用の秘密のソルト
define('TRIP_SECRET_SALT', 'YourSuperSecretRandomStringForTripGeneration');

// セッションIDをURLに含めない
ini_set('session.use_trans_sid', '0');

// HTTP Only属性を有効にし、JavaScriptからのセッションクッキーアクセスを防止
ini_set('session.cookie_httponly', '1');

// セキュア属性を有効にし、HTTPS接続でのみセッションクッキーを送信
// 本番環境がHTTPSでない場合は '0' に設定
ini_set('session.cookie_secure', '0');

// セッションクッキーの有効期限
ini_set('session.gc_maxlifetime', 1800); // 30分

// セッションIDを定期的に再生成
define('SESSION_REGENERATE_INTERVAL', 300); // 5分ごと
?>