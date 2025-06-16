document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function() {
        window.location.href = 'https://trello.com/b/fMuVe5zn/yellowduckcoderstask';
    }, 2000);
    const hash = window.location.hash.substring(1); // #token=...
    const query = new URLSearchParams(window.location.search); // ?telegram_user_id=...

    const params = new URLSearchParams(hash);
    const token = params.get('token');
    const user_id = query.get('telegram_user_id'); // исправлено

    if (token && user_id) {
        fetch('/api/trello/store-user-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ token: token, user_id: user_id })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('result').innerText = 'Integration completed successfully!';
                    setTimeout(() => {
                        window.location.href = 'https://trello.com/';
                    }, 2000);
                } else {
                    document.getElementById('result').innerText = 'Error: ' + data.error;
                }
            })
            .catch(error => {
                document.getElementById('result').innerText = 'Error sending data: ' + error;
            });
    } else {
        document.getElementById('result').innerText = 'Token or user ID not found';
    }
});
