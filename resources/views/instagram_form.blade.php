<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Link to PDF</title>
</head>
<body>
    <h1>Enter Instagram Link</h1>

    <!-- Display success or error messages -->
    @if (session('success'))
        <p style="color: green;">{!! session('success') !!}</p>
    @endif
    @if (session('error'))
        <p style="color: red;">{{ session('error') }}</p>
    @endif

    <form action="/generate-pdf" method="POST">
        @csrf
        <label for="instagram_link">Instagram Link:</label>
        <input type="url" id="instagram_link" name="instagram_link" required>
        <button type="submit">Generate PDF</button>
    </form>
</body>
</html>