<?php
require_once __DIR__ . '/auth_helper.php';
startSecureSession();

header('Content-Type: application/json');

// ログインチェック
if (!isLoggedIn()) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

$messagesFile = MESSAGES_FILE;
$messages = [];

if (file_exists($messagesFile)) { // filesizeチェックはcontentsの前に
    $fileContent = file_get_contents($messagesFile);
    if ($fileContent === false) {
        error_log('Failed to read messages.json. File: ' . $messagesFile . '. Check file permissions.');
        echo json_encode(['error' => 'failed to read messages file']);
        exit;
    }
    // ファイルが空でないか、有効なJSONであるか確認
    if (trim($fileContent) !== '') {
        $decodedContent = json_decode($fileContent, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
            $messages = $decodedContent;
        } else {
            error_log('messages.json contains invalid JSON or is not an array. File: ' . $messagesFile . '. JSON Error: ' . json_last_error_msg()); // エラーログに出力
            // 無効なJSONの場合は、空のメッセージリストを返す
        }
    }
} else {
    // ファイルが存在しない場合は初回であるとみなし、空のメッセージリストを返す
    error_log('messages.json does not exist. Returning empty messages.');
}

echo json_encode($messages, JSON_UNESCAPED_UNICODE);
?>
