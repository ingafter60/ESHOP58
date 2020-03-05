<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Document</title>
</head>
<body>
   @include('partials.header')
   <div>
      @yield('main')
   </div>
   @include('partials.footer')
</body>
</html>
