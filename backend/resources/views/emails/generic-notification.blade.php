<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Notification' }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <p>{!! nl2br(e($body)) !!}</p>

        @if (!empty($data))
            <hr>
            <ul>
                @foreach ($data as $key => $value)
                    <li><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                @endforeach
            </ul>
        @endif

        <div class="footer">
            <p>This is an automated notification from the Hospital Health Management System.</p>
        </div>
    </div>
</body>
</html>
