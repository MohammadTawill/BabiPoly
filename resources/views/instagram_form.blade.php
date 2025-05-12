<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <title>Instagram Link to PDF</title>
</head>
<body>
    

    <!-- Display success or error messages -->
    @if (session('success'))
        <p style="color: green;">{!! session('success') !!}</p>
    @endif
    @if (session('error'))
        <p style="color: red;">{{ session('error') }}</p>
    @endif

    <form action="/generate-pdf" method="POST">
        @csrf
        <label for="instagram_link" class="form-label">Enter Instagram Link:</label>
        <input type="url" id="instagram_link" name="instagram_link" placeholder="https://instagram.com/username" required>
        <button type="submit">Generate PDF</button>
    </form>
</body>
</html>