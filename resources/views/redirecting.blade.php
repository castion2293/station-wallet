<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{config('station_wallet.login_redirecting.web_title')}}</title>
</head>
<body>

    @if ($method === 'redirect')
        {{--<h1>{{$web_url}}</h1>--}}
        <a id="link" href="{{$web_url}}"></a>

        <script language="JavaScript">
            document.getElementById('link').click()
        </script>
    @else
        <form id="form" method="{{ $method }}" action="{{ $web_url }}">
            @foreach($params as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
        </form>

        <script language="JavaScript">
            document.getElementById('form').submit()
        </script>
    @endif

</body>
</html>