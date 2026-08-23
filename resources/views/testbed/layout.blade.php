<!DOCTYPE html>
{{--
    Deliberately declares no theme. The switcher below applies each convention
    in turn so the uploader can be seen following the host, and following the
    OS when the host stays silent.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AF Uploader — Testbed</title>

    @livewireStyles
    @afUploaderAssets

    <style>
        /*
            The testbed themes itself from the uploader's own resolved value,
            so the page and the component always agree — which is the point of
            the exercise.
        */
        body {
            margin: 0;
            padding: 32px;
            font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f6f9;
            color: #16181d;
        }

        :root[data-af-theme="dark"] body { background: #0f1115; color: #e6e8ec; }
        :root[data-af-theme="dark"] .panel { background: #171a21; border-color: #262b36; }
        :root[data-af-theme="dark"] .state { background: #0f1115; }
        :root[data-af-theme="dark"] .toolbar button { background: #2b3140; color: #e6e8ec; border-color: #3a4152; }

        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 0 0 10px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; opacity: .6; }
        p.lede { margin: 0 0 28px; opacity: .6; }

        .grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }

        .panel {
            background: #ffffff;
            border: 1px solid #dfe4ec;
            border-radius: 12px;
            padding: 16px;
        }

        .panel .note { margin: 10px 0 0; font-size: 12px; opacity: .55; }

        .state {
            margin-top: 12px;
            padding: 8px 10px;
            background: #eef1f6;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .toolbar { display: flex; gap: 10px; margin: 0 0 24px; flex-wrap: wrap; }

        .toolbar button {
            background: #e7ebf2;
            color: #16181d;
            border: 1px solid #cfd6e2;
            border-radius: 8px;
            padding: 8px 14px;
            font: inherit;
            cursor: pointer;
        }
        .toolbar button:hover { filter: brightness(.96); }

        .theme-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 24px; font-size: 12px; }
        .theme-bar strong { opacity: .6; font-weight: 600; }
        .theme-bar code { padding: 1px 5px; border-radius: 4px; background: rgba(127,127,127,.18); }

        .slots { display: grid; gap: 10px; grid-template-columns: repeat(3, 1fr); }
    </style>
</head>
<body>
    {{ $slot }}

    @livewireScripts
</body>
</html>
