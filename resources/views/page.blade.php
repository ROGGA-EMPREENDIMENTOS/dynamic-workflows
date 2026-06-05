<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dynamic Workflows</title>
    @filamentStyles
    @livewireStyles
</head>
<body>
    <div style="max-width: 1200px; margin: 2rem auto; padding: 0 1rem;">
        @livewire('dynamic-workflows.workflow-rule-list')
    </div>

    @livewireScripts
    @filamentScripts
</body>
</html>
