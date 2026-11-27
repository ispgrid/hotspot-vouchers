<!DOCTYPE html>
<html>
<body>
    <h1>TEST LAYOUT {{ $sheet->title }}</h1>
    @foreach ($sheet->cards as $card)
        <p>{{ $card['code'] }}</p>
    @endforeach
</body>
</html>
