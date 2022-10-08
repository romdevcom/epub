<!DOCTYPE html>
<html lang="{{$lang}}">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    {{--<link rel="shortcut icon" href="{{url('favicon.ico')}}" type="image/x-icon" />--}}

    @if(isset($seo) && isset($seo['title']))
        <title>{{$seo['title']}}</title>
    @else
        <title>{{trans('translation.title')}}</title>
    @endif

    @if(isset($seo) && isset($seo['description']))
        <meta name="description" content="{{$seo['description']}}">
    @else
        <meta name="description" content="{{trans('translation.title')}}">
    @endif

    <link rel="stylesheet" href="{{url('assets/css/main.css?ver=' . date('Hsi'))}}">

    @yield('header-scripts')
</head>

<body>
    <header>

    </header>

    <main>
        @yield('content')
    </main>

    <footer>

    </footer>
</body>
@yield('footer-scripts')
</html>
