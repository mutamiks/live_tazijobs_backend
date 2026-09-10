<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'TaziJobs') }}</title>
</head>
<body>
    <p>Hello,</p>

    <p>{!! nl2br(e($body)) !!}</p>

    <p>Regards,<br>{{ $fromName ?? config('mail.from.name', config('app.name', 'TaziJobs')) }}</p>
</body>
</html>
