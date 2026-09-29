<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: #374151;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }
        .wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            padding: 30px;
            text-align: center;
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .logo {
            max-height: 50px;
            width: auto;
        }
        .brand {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            text-decoration: none;
        }
        .content {
            padding: 30px;
            background-color: #ffffff;
            font-size: 16px;
            line-height: 1.6;
        }
        .content p {
            margin-top: 0;
            margin-bottom: 20px;
        }
        .content p:last-child {
            margin-bottom: 0;
        }
        .content h1, .content h2, .content h3, .content h4 {
            color: #111827;
            font-weight: 700;
            margin-top: 32px;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        .content h1 { font-size: 24px; }
        .content h2 { font-size: 20px; }
        .content h3 { font-size: 18px; }
        .content h4 { font-size: 16px; }

        .content strong, .content b {
            font-weight: 700;
            color: #111827;
        }
        .content a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .content ul, .content ol {
            padding-left: 20px;
            margin-bottom: 20px;
        }
        .content li {
            margin-bottom: 12px;
        }
        .content blockquote {
            margin: 0 0 20px 0;
            padding-left: 20px;
            border-left: 4px solid #e5e7eb;
            font-style: italic;
            color: #4b5563;
        }
        .content code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            background-color: #f3f4f6;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .content pre {
            background-color: #f3f4f6;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        .content pre code {
            background-color: transparent;
            padding: 0;
            border-radius: 0;
            font-size: 14px;
        }
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }
        table th, table td {
            padding: 12px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        table th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #111827;
        }
    </style>
</head>
<body>
    @php
        $logo = \Agentoom\Core\Models\Configuration::getValue('app_logo');
        $footer = \Agentoom\Core\Models\Configuration::getValue('email_footer_content');
        $currentYear = date('Y');
    @endphp
    <div class="wrapper">
        <div class="container">
            <div class="header">
                @if($logo)
                    <img src="{{ url($logo) }}" alt="Logo" class="logo">
                @else
                    <div class="brand">Agentoom</div>
                @endif
            </div>
            <div class="content">
                @if(isset($body))
                    {!! $body !!}
                @else
                    @yield('content')
                @endif
            </div>
            <div class="footer">
                @if($footer)
                    {!! $footer !!}
                @else
                    <p>{{ $currentYear }} &copy; Agentoom</p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
