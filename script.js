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
            if (data.error) {
                console.error('Server error on get_users:', data.error);
                // alert('エラー: ' + data.error); // ユーザーに直接通知する代わりにconsole.errorを使用
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
            if (error.message === 'ログインが必要です。') {
                window.location.href = 'login.php'; // ログインページへリダイレクト
            }
        });

    // メッセージの更新
    fetch('get_messages.php')
        .then(response => {
            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    throw new Error('ログインが必要です。');
                }
                throw new Error('メッセージの取得に失敗しました。ステータス: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Server error on get_messages:', data.error);
                // alert('エラー: ' + data.error); // ユーザーに直接通知する代わりにconsole.errorを使用
                return;
            }
            const chatBoard = document.getElementById('chat-board');
            const isScrolledToBottom = (chatBoard.scrollHeight - chatBoard.clientHeight <= chatBoard.scrollTop + 1);

            chatBoard.innerHTML = ''; // 一旦クリア
            data.forEach(message => {
                const div = document.createElement('div');
                div.className = 'message';
                // PHP側でhtmlspecialcharsされているため、ここではそのまま表示
                div.innerHTML = `<strong>${message.username}:</strong> ${message.message} <span class="timestamp">${message.timestamp}</span>`;
                chatBoard.appendChild(div);
            });

            // スクロール位置を維持または最下部にスクロール
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
    const message = messageInput.value.trim();
    const csrfToken = document.getElementById('csrf-token').value; // CSRFトークンを取得

    if (message) {
        fetch('post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message) + '&csrf_token=' + encodeURIComponent(csrfToken) // CSRFトークンを追加
        })
        .then(response => response.text()) // レスポンスをテキストとして取得
        .then(result => {
            if (result === 'success') {
                messageInput.value = ''; // 入力フィールドをクリア
                updateContent(); // メッセージ送信後に即座に更新
            } else {
                console.error('Server response on message post:', result);
                alert('メッセージの送信に失敗しました。\n' + result); // サーバーからのエラーメッセージを表示
            }
        })
        .catch(error => {
            console.error('Error posting message:', error);
            alert('メッセージの送信中にエラーが発生しました。');
        });
    } else {
        alert('メッセージが空です。');
        messageInput.value = ''; // 空のメッセージの場合はクリア
    }
});

// 初回ロード時と、3秒ごとに更新
updateContent();
setInterval(updateContent, 3000);
