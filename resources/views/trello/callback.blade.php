<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Trello Callback</title>
    <script src="{{ asset('js/trello-callback.js') }}" defer></script>
</head>
<body>
<h1>Trello Callback</h1>
<p>Processing Trello callback...</p>
<footer>
    <script src="{{ asset('js/trello-callback.js') }}" defer></script>
</footer>
</body>
</html>
