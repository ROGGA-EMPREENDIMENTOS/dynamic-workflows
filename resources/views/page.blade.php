@php($workflowVersion = \Rogga\DynamicWorkflows\DynamicWorkflows::version())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dynamic Workflows v{{ $workflowVersion }}</title>
    @filamentStyles
    @livewireStyles
</head>
<body>
    <div style="max-width: 1200px; margin: 2rem auto; padding: 0 1rem;">
        <h1 style="display: flex; align-items: baseline; gap: 0.5rem; font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">
            Dynamic Workflows
            <span style="font-size: 0.8rem; font-weight: 500; color: #6b7280;">v{{ $workflowVersion }}</span>
        </h1>
        @livewire('dynamic-workflows.workflow-rule-list')
    </div>

    @livewireScripts
    @filamentScripts
</body>
</html>
