<!doctype html>
<html lang="en">
<body>
    <p>Hello,</p>
    <p>You have been invited to complete <strong>{{ $form->title }}</strong>.</p>
    <p><a href="{{ $url }}">Open the secure form</a></p>
    <p>This personal link expires {{ $expiresAt?->toDayDateTimeString() }} and can be used once.</p>
    <p>If you did not expect this message, you can ignore it.</p>
</body>
</html>
