<?php
require_once __DIR__ . '/auth_helper.php';
startSecureSession();

header('Content-Type: text/plain'); // レスポンスはテキストとして返す

// ログインチェック
if (!isLoggedIn()) {
    echo 'error: not logged in';
    error_log('Attempt to post message without login from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンの検証
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo 'error: invalid request (CSRF token mismatch)';
        error_log('CSRF token mismatch on post.php for user: ' . getSessionUsername() . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
        exit;
    }

    $message = trim($_POST['message'] ?? '');
    $username = $_SESSION['username']; // セッションから直接取得

    if (!empty($message)) {
        // メッセージの長さを制限
        if (mb_strlen($message) > 500) {
            echo 'error: message too long (max 500 characters)';
            error_log('User ' . $username . ' attempted to post a message exceeding length limit. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
            exit;
        }

        $messagesFile = MESSAGES_FILE; // 定義されたパスを使用
        $currentMessages = [];

        // ファイルが存在し、内容が有効なJSONであれば読み込む
        if (file_exists($messagesFile) && filesize($messagesFile) > 0) {
            $fileContent = file_get_contents($messagesFile);
            if ($fileContent !== false) {
                $decodedContent = json_decode($fileContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                    $currentMessages = $decodedContent;
                } else {
                    error_log('messages.json contains invalid JSON or is not an array. Resetting messages. File: ' . $messagesFile . '. JSON Error: ' . json_last_error_msg());
                    // 無効なJSONの場合は、新しい配列から始める
                    $currentMessages = []; // 安全のために既存の内容を破棄
                }
            } else {
                error_log('Failed to read messages.json. File: ' . $messagesFile . '. Check file permissions.');
                echo 'error: internal server error (failed to read messages)';
                exit;
            }
        } else if (!file_exists($messagesFile)) {
             // ファイルが存在しない場合は、初回作成として空配列から始める
             error_log('messages.json does not exist. Creating new file.');
        }


        $newMessage = [
            'username' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'), // ここでXSS対策
            'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),   // ここでXSS対策
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $currentMessages[] = $newMessage;

        // 最新の50件に制限
        $currentMessages = array_slice($currentMessages, -50);

        // JSON_UNESCAPED_UNICODE と JSON_PRETTY_PRINT を使用
        // LOCK_EX を使用して排他ロックをかける
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
    error_log('Invalid request method on post.php from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
}
?>
