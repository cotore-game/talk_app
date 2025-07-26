<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/config.php';
startSecureSession();

header('Content-Type: application/json');

// ログイン状態をチェック
if (!isLoggedIn()) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

$messagesFile = MESSAGES_FILE;
$messages = [];

// メッセージファイルが存在する場合
if (file_exists($messagesFile)) {
    $fileContent = file_get_contents($messagesFile);
    if ($fileContent === false) {
        // ファイルの読み込みに失敗した場合
        error_log('Failed to read messages.json. File: ' . $messagesFile . '. Check file permissions.');
        echo json_encode(['error' => 'failed to read messages file']);
        exit;
    }
    // ファイルが空でないか、または空白文字のみでないか確認
    if (trim($fileContent) !== '') {
        $decodedContent = json_decode($fileContent, true);
        // JSONデコードに成功し、かつ配列であるかを確認
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
            $messages = $decodedContent;
        } else {
            // 無効なJSONの場合、または配列でない場合
            error_log('messages.json contains invalid JSON or is not an array. File: ' . $messagesFile . '. JSON Error: ' . json_last_error_msg());
            // 無効なJSONであっても、ここでは空のメッセージリストを返すことで、クライアント側での表示は継続可能にする
        }
    }
} else {
    // ファイルが存在しない場合は初回であるとみなし、空のメッセージリストを返す
    error_log('messages.json does not exist. Returning empty messages.');
}

// JSON形式でメッセージを返す
echo json_encode($messages, JSON_UNESCAPED_UNICODE);
