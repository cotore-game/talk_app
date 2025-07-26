// ユーザーリストとメッセージを定期的に更新する関数
// ユーザーリストを保持する変数 (updateContentで更新される)
let currentUsers = [];

function updateContent() {
    // ユーザーリストの更新
    fetch('get_users.php')
        .then(response => {
            if (!response.ok) {
                // 401 (Unauthorized) または 403 (Forbidden) の場合、ログインが必要と判断
                if (response.status === 401 || response.status === 403) {
                    throw new Error('ログインが必要です。');
                }
                throw new Error('ユーザーリストの取得に失敗しました。ステータス: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // サーバーからエラーが返された場合
            if (data.error) {
                console.error('Server error on get_users:', data.error);
                return;
            }
            document.getElementById('user-count').textContent = data.count;
            const userList = document.querySelector('#user-list ul');
            userList.innerHTML = ''; // 一旦クリア
            currentUsers = []; // ユーザーリストをクリア
            data.users.forEach(user => {
                const li = document.createElement('li');
                li.textContent = user; // 既にPHP側でXSS対策済み
                userList.appendChild(li);
                currentUsers.push(user); // グローバル変数にユーザー名を追加
            });
        })
        .catch(error => {
            console.error('Error fetching users:', error);
            // ログインが必要なエラーの場合、ログインページへリダイレクト
            if (error.message === 'ログインが必要です。') {
                window.location.href = 'login.php';
            }
        });

    // メッセージの更新
    fetch('get_messages.php')
        .then(response => {
            // HTTPステータスが2xx以外の場合
            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    throw new Error('ログインが必要です。');
                }
                throw new Error('メッセージの取得に失敗しました。ステータス: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            const chatBoard = document.getElementById('chat-board');
            // スクロール位置が最下部にあるかを判定
            const isScrolledToBottom = (chatBoard.scrollHeight - chatBoard.clientHeight <= chatBoard.scrollTop + 1);

            chatBoard.innerHTML = ''; // 一旦クリア
            data.forEach(message => {
                const div = document.createElement('div');
                div.className = 'message';

                // ユーザー名とトリップの表示
                let usernameDisplay = `<strong>${message.username}:</strong>`;
                if (message.trip) { // トリップが存在する場合
                    usernameDisplay = `<strong>${message.username}</strong><span class="trip">${message.trip}</span>:`;
                }

                // PHP側でhtmlspecialcharsされているため、ここではそのまま表示
                div.innerHTML = `${usernameDisplay} ${message.message} <span class="timestamp">${message.timestamp}</span>`;
                chatBoard.appendChild(div);
            });

            // メッセージ追加後、スクロール位置を維持または最下部にスクロール
            if (isScrolledToBottom) {
                chatBoard.scrollTop = chatBoard.scrollHeight;
            }
        })
        .catch(error => {
            console.error('Error fetching messages:', error);
            if (error.message === 'ログインが必要です。') {
                window.location.href = 'login.php'; // ログインページへリダイレクト
            }
        });
}

// フォーム送信時の処理
document.getElementById('message-form').addEventListener('submit', function(e) {
    e.preventDefault(); // デフォルトの送信を防止

    const messageInput = document.getElementById('message-input');
    const tripPasswordInput = document.getElementById('trip-password-input'); // トリップパスワード入力フィールドを取得
    const message = messageInput.value.trim();
    const tripPassword = tripPasswordInput.value.trim(); // トリップパスワードの値を取得
    const csrfToken = document.getElementById('csrf-token').value; // CSRFトークンを取得

    if (message) {
        // FormData を使用して、multipart/form-data 形式で送信
        const formData = new URLSearchParams();
        formData.append('message', message);
        formData.append('trip_password', tripPassword); // トリップパスワードを追加
        formData.append('csrf_token', csrfToken); // CSRFトークンを追加

        fetch('post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => response.text()) // レスポンスをテキストとして取得
        .then(result => {
            if (result === 'success') {
                messageInput.value = ''; // メッセージ入力フィールドをクリア
                // tripPasswordInput.value = ''; // 必要に応じてトリップパスワードもクリア
                updateContent(); // メッセージ送信後に即座に更新
            } else {
                alert('メッセージの送信に失敗しました。\n' + result); // サーバーからのエラーメッセージを表示
                console.error('Server response on message post:', result);
            }
        })
        .catch(error => {
            alert('メッセージの送信中にエラーが発生しました。');
            console.error('Error posting message:', error);
        });
    } else {
        alert('メッセージを入力してください。');
    }
});

// メンション機能の追加
const messageInput = document.getElementById('message-input');
const mentionSuggestionsDiv = document.getElementById('mention-suggestions');

messageInput.addEventListener('input', function() {
    const text = messageInput.value;
    const caretPos = messageInput.selectionStart; // カーソル位置を取得
    const textBeforeCaret = text.substring(0, caretPos);

    // 最後の '@' の位置を探す
    const atIndex = textBeforeCaret.lastIndexOf('@');

    if (atIndex !== -1) {
        // '@' からカーソルまでの文字列を取得
        const searchTerm = textBeforeCaret.substring(atIndex + 1);

        // 半角スペースまたは全角スペースで区切られている場合は候補を表示しない
        if (searchTerm.includes(' ') || searchTerm.includes('　')) {
            mentionSuggestionsDiv.style.display = 'none';
            return;
        }

        const filteredUsers = currentUsers.filter(user =>
            user.toLowerCase().startsWith(searchTerm.toLowerCase())
        );

        displaySuggestions(filteredUsers, atIndex);
    } else {
        mentionSuggestionsDiv.style.display = 'none';
    }
});

function displaySuggestions(users, atIndex) {
    mentionSuggestionsDiv.innerHTML = '';
    if (users.length > 0) {
        users.forEach(user => {
            const suggestionItem = document.createElement('div');
            suggestionItem.textContent = user;
            suggestionItem.addEventListener('click', function() {
                // 選択されたユーザー名を挿入
                const currentText = messageInput.value;
                const textBeforeAt = currentText.substring(0, atIndex);
                const textAfterAt = currentText.substring(messageInput.selectionStart); // カーソル以降のテキスト

                // 完全なユーザー名を挿入
                messageInput.value = `${textBeforeAt}@${user} ${textAfterAt}`;
                messageInput.focus();
                mentionSuggestionsDiv.style.display = 'none';
            });
            mentionSuggestionsDiv.appendChild(suggestionItem);
        });
        mentionSuggestionsDiv.style.display = 'block';
    } else {
        mentionSuggestionsDiv.style.display = 'none';
    }
}

// メッセージ入力フィールドからフォーカスが外れたら候補を非表示にする
// ただし、候補クリック時にもイベントが発火するので、少し遅延させる
messageInput.addEventListener('blur', () => {
    setTimeout(() => {
        mentionSuggestionsDiv.style.display = 'none';
    }, 100);
});


// ページロード時と定期的にコンテンツを更新
updateContent();
setInterval(updateContent, 3000);
