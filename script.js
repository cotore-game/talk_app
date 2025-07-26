// ユーザーリストとメッセージを定期的に更新する関数
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
            data.users.forEach(user => {
                const li = document.createElement('li');
                li.textContent = user; // 既にPHP側でXSS対策済み
                userList.appendChild(li);
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

// ページロード時と定期的にコンテンツを更新
updateContent();
setInterval(updateContent, 3000);
