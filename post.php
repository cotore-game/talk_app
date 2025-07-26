<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/config.php';
startSecureSession();

header('Content-Type: text/plain'); // レスポンスはテキストとして返す

// ログイン状態をチェック
if (!isLoggedIn()) {
    echo 'error: not logged in';
    error_log('Attempt to post message without login from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    exit;
}

/**
 * トリップを生成する。
 * トリップパスワードと秘密のソルトを結合し、SHA-1ハッシュの一部をトリップとして使用する。
 * @param string $tripPassword トリップ生成に使用するパスワード (任意)
 * @param string $salt トリップ生成に使用する秘密のソルト
 * @return string 生成されたトリップ
 */
function generateTrip(string $tripPassword, string $salt): string
{
    if (empty($tripPassword)) {
        return ''; // トリップパスワードがない場合はトリップを生成しない
    }
    // SHA-1ハッシュを使用し、指定されたソルトとトリップパスワードを結合
    $hashed = sha1($tripPassword . $salt);
    // ハッシュ値の先頭8バイト（16文字）をトリップとして使用
    return '◆' . substr($hashed, 0, 8);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンの検証
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo 'error: invalid request (CSRF token mismatch)';
        error_log('CSRF token mismatch on post.php for user: ' . getSessionUsername() . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
        exit;
    }

    $message = trim($_POST['message'] ?? '');
    $tripPassword = trim($_POST['trip_password'] ?? ''); // トリップパスワードを取得
    $username = $_SESSION['username']; // セッションから直接ユーザー名を取得

    // メッセージが空でないか確認
    if (!empty($message)) {
        // メッセージの長さを制限
        if (mb_strlen($message) > 500) {
            echo 'error: message too long (max 500 characters)';
            error_log('User ' . $username . ' attempted to post a message exceeding length limit. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
            exit;
        }

        $messagesFile = MESSAGES_FILE;
        $currentMessages = [];

        // ファイルが存在し、内容が有効なJSONであれば読み込む
        if (file_exists($messagesFile)) {
            $fileContent = file_get_contents($messagesFile);
            if ($fileContent === false) {
                error_log('Failed to read messages.json. File: ' . $messagesFile);
                echo 'error: internal server error (failed to read messages)';
                exit;
            }
            // ファイルが空でないか、有効なJSONであるか確認
            if (trim($fileContent) !== '') {
                $decodedContent = json_decode($fileContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                    $currentMessages = $decodedContent;
                } else {
                    error_log('messages.json contains invalid JSON or is not an array. Resetting messages. File: ' . $messagesFile);
                    // 無効なJSONの場合は、新しい配列から始める
                }
            }
        } else {
             // ファイルが存在しない場合は、初回作成として空配列から始める
             error_log('messages.json does not exist. Creating new file.');
        }

        // トリップを生成
        $trip = generateTrip($tripPassword, TRIP_SECRET_SALT);

        // 新しいメッセージデータを作成
        $newMessage = [
            'username' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            'trip' => htmlspecialchars($trip, ENT_QUOTES, 'UTF-8'),
            'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // メッセージを追加
        $currentMessages[] = $newMessage;

        // 最新の50件に制限
        $currentMessages = array_slice($currentMessages, -50);

        // メッセージをファイルに保存 (JSON_PRETTY_PRINTで整形、JSON_UNESCAPED_UNICODEで日本語エスケープ防止)
        // LOCK_EX を使用して排他ロックをかけ、書き込み中の競合を防ぐ
        if (file_put_contents($messagesFile, json_encode($currentMessages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            error_log('Failed to write messages to ' . $messagesFile . ' for user: ' . $username . '. Check file permissions.');
            echo 'error: file write failed';
        } else {
            echo 'success';
        }
    } else {
        echo 'error: empty message';
        error_log('User ' . $username . ' attempted to post an empty message. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    }
} else {
    echo 'error: invalid request method';
    error_log('Invalid request method to post.php from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
}
