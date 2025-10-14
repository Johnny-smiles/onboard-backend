<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>On Brand — Portal</title>
    <script> window.__API_BASE_URL__ = "{{ rtrim(config('app.url'), '/') }}/api/v1"; </script>
    @vite('resources/js/portal/main.ts')
  </head>
  <body><div id="app"></div></body>
</html>
