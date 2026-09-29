<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; max-width: 850px; margin: 40px auto; padding: 0 40px; }
        h1 { color: #111; border-bottom: 2px solid #eee; padding-bottom: 15px; font-size: 2.5em; }
        h2 { color: #222; margin-top: 40px; border-bottom: 1px solid #eee; font-size: 1.8em; }
        h3 { color: #444; margin-top: 30px; font-size: 1.4em; }
        h4 { color: #666; margin-top: 20px; font-size: 1.1em; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20mm; max-width: none; }
            h2 { page-break-before: always; }
        }
        .btn-print {
            background-color: #4f46e5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #fdf6b2; padding: 15px; border: 1px solid #facc15; text-align: center; border-radius: 8px; margin-bottom: 40px;">
        <p style="margin: 0 0 10px 0;">{{ __('Click "Print Now" then select "Save as PDF" in your browser settings.') }}</p>
        <button onclick="window.print()" class="btn-print">{{ __('Print Now') }}</button>
    </div>
    <article class="prose max-w-none">
        {!! $html !!}
    </article>
</body>
</html>
