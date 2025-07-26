<?php
require_once __DIR__ . '/config.php';

/**
 * 安全なセッションを開始または再開する。
 * セッションハイジャック対策として、一定時間ごとにセッションIDを再生成する。
 * また、最終アクティビティ時間を更新し、アイドル状態のセッションを追跡する。
 */
function startSecureSession()
{
    // セッションが既に開始されている場合は何もしない
    if (session_status() === PHP_SESSION_NONE) {
        session_start();

        // セッションIDの再生成
        if (!isset($_SESSION['last_session_regenerate_time']) ||
            time() - $_SESSION['last_session_regenerate_time'] > SESSION_REGENERATE_INTERVAL
        ) {
            session_regenerate_id(true); // 古いセッションIDを削除し、新しいものを生成
            $_SESSION['last_session_regenerate_time'] = time();
        }
    }
    // アクティビティ時間を更新
    $_SESSION['last_activity'] = time();
}

/**
 * ユーザーがログインしているか確認する。
 * セッションに'loggedin'フラグがtrueで、かつ'username'が設定されていることを確認する。
 * @return bool ログインしていればtrue、そうでなければfalse
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['username']);
}

/**
 * セッションからユーザー名を取得し、XSS対策を施して返す。
 * @return string XSS対策済みのユーザー名、または空文字列
 */
function getSessionUsername(): string
{
    return isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') : '';
}

/**
 * ログイン中のユーザー情報をJSONファイルから読み込む。
 * @return array ログイン中のユーザー情報の配列
 */
function getLoggedInUsers(): array
{
    $users = [];
    if (file_exists(LOGGED_IN_USERS_FILE)) {
        $fileContent = file_get_contents(LOGGED_IN_USERS_FILE);
        if ($fileContent === false) {
            error_log('Failed to read logged_in_users.json. Check file permissions.');
            return [];
        }
        $decodedContent = json_decode($fileContent, true);
        // JSONデコードに成功し、かつ配列であるかを確認
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
            $users = $decodedContent;
        } else {
            error_log('logged_in_users.json contains invalid JSON or is not an array. JSON Error: ' . json_last_error_msg());
            // 無効なJSONの場合は、エラーログを出力し、空の配列を返す
        }
    }
    return $users;
}

/**
 * ログイン中のユーザー情報をJSONファイルに保存する。
 * @param array $users 保存するユーザー情報の配列
 * @return bool 保存に成功すればtrue、失敗すればfalse
 */
function saveLoggedInUsers(array $users): bool
{
    // JSON_PRETTY_PRINTで整形し、JSON_UNESCAPED_UNICODEで日本語がエスケープされないようにする
    // LOCK_EXで排他ロックをかけ、書き込み中の競合を防ぐ
    if (file_put_contents(LOGGED_IN_USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        error_log('Failed to write to logged_in_users.json. Check file permissions.');
        return false;
    }
    return true;
}

/**
 * ログイン中のユーザーリストに新しいユーザーを追加する。
 * 既に存在する場合は情報を更新する。
 * @param string $username 追加するユーザー名
 * @return bool 成功すればtrue、失敗すればfalse
 */
function addLoggedInUser(string $username): bool
{
    $users = getLoggedInUsers();
    $found = false;
    $currentTime = time();
    $sessionId = session_id();

    foreach ($users as &$userEntry) {
        // 同じユーザー名かつ同じセッションIDのエントリがあれば更新
        if ($userEntry['username'] === $username && $userEntry['session_id'] === $sessionId) {
            $userEntry['last_activity'] = $currentTime;
            $found = true;
            break;
        }
    }
    unset($userEntry); // 参照を解除

    if (!$found) {
        // 新しいユーザーとして追加
        $users[] = [
            'username' => $username,
            'session_id' => $sessionId,
            'login_time' => $currentTime,
            'last_activity' => $currentTime,
        ];
    }

    return saveLoggedInUsers($users);
}

/**
 * ログイン中のユーザーリストから指定されたユーザーを削除する。
 * セッションIDも考慮して、正確に特定ユーザーのセッションを削除する。
 * @param string $usernameToRemove 削除するユーザー名
 * @return bool 成功すればtrue、失敗すればfalse
 */
function removeLoggedInUser(string $usernameToRemove): bool
{
    $users = getLoggedInUsers();
    $newUsers = [];
    $removed = false;
    $sessionId = session_id();

    foreach ($users as $userEntry) {
        // ログアウトするユーザー名かつ現在のセッションIDに紐づくエントリのみを削除
        if ($userEntry['username'] === $usernameToRemove && $userEntry['session_id'] === $sessionId) {
            $removed = true;
            continue; // このユーザーエントリはスキップ
        }
        $newUsers[] = $userEntry;
    }
    
    // 変更があった場合のみ保存
    if ($removed) {
        return saveLoggedInUsers($newUsers);
    }
    return true; // 変更がなければtrue
}

/**
 * タイムアウトしたセッションのユーザーをリストから削除する。
 * @param int $timeout ユーザーが非アクティブとみなされるまでの秒数
 * @return bool 変更があった場合はtrue、なければtrue（保存失敗時のみfalse）
 */
function cleanupExpiredSessions(int $timeout = SESSION_REGENERATE_INTERVAL): bool
{
    $users = getLoggedInUsers();
    $activeUsers = [];
    $currentTime = time();
    $changed = false;

    foreach ($users as $userEntry) {
        // 最終アクティビティからタイムアウト時間が経過していないユーザーのみをアクティブとみなす
        if (isset($userEntry['last_activity']) && ($currentTime - $userEntry['last_activity']) < $timeout) {
            $activeUsers[] = $userEntry;
        } else {
            $changed = true; // 期限切れユーザーが見つかった
            error_log('Cleaned up expired session for user: ' . ($userEntry['username'] ?? 'UNKNOWN') . ' (Session ID: ' . ($userEntry['session_id'] ?? 'UNKNOWN') . ')');
        }
    }
    
    // 変更があった場合のみ保存
    if ($changed) {
        return saveLoggedInUsers($activeUsers);
    }
    return true; // 変更がなければtrue
}

/**
 * CSRFトークンを生成し、セッションに保存する。
 * @return string 生成されたCSRFトークン
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // bin2hexとrandom_bytesで強力な乱数を生成
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 送信されたCSRFトークンを検証する。
 * @param string $token 検証するトークン
 * @return bool トークンが有効であればtrue、そうでなければfalse
 */
function verifyCsrfToken(string $token): bool
{
    // hash_equalsを使用してタイミング攻撃を防ぐ
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
