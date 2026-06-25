<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logs</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        body {
            margin: 0;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(18rem, 22rem) 1fr;
        }

        .sidebar {
            border-right: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 1.25rem;
        }

        .content {
            padding: 1.25rem;
            overflow: auto;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 1.25rem;
            margin: 0;
        }

        .admin-link {
            color: #2563eb;
            font-size: .875rem;
            font-weight: 600;
        }

        .log-list {
            display: grid;
            gap: .5rem;
        }

        .log-link {
            border: 1px solid #e2e8f0;
            border-radius: .5rem;
            display: block;
            padding: .75rem;
        }

        .log-link.active {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .log-date {
            display: block;
            font-weight: 700;
            margin-bottom: .25rem;
        }

        .log-meta {
            color: #64748b;
            display: block;
            font-size: .8rem;
        }

        .viewer {
            background: #020617;
            border-radius: .5rem;
            color: #e2e8f0;
            font-family: "Cascadia Mono", Consolas, "Liberation Mono", monospace;
            font-size: .8125rem;
            line-height: 1.55;
            min-height: calc(100vh - 7rem);
            overflow: auto;
            padding: 1rem;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .empty {
            border: 1px dashed #cbd5e1;
            border-radius: .5rem;
            color: #475569;
            padding: 2rem;
            text-align: center;
        }

        @media (max-width: 900px) {
            .page {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: 0;
                border-bottom: 1px solid #e2e8f0;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <aside class="sidebar">
            <div class="header">
                <h1>Daily Logs</h1>
                <a class="admin-link" href="{{ url('/admin') }}">Admin</a>
            </div>

            @if ($logs->isEmpty())
                <div class="empty">No daily log files found.</div>
            @else
                <nav class="log-list" aria-label="Daily log files">
                    @foreach ($logs as $log)
                        <a
                            class="log-link @if ($selectedFile === $log['file']) active @endif"
                            href="{{ route('logs.index', ['file' => $log['file']]) }}"
                        >
                            <span class="log-date">{{ $log['display_date'] }}</span>
                            <span class="log-meta">{{ $log['file'] }} · {{ $log['size'] }}</span>
                            <span class="log-meta">Updated {{ $log['updated_at']->format('d M Y H:i') }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
        </aside>

        <section class="content">
            <div class="header">
                <h1>{{ $selectedLog['file'] ?? 'Log Viewer' }}</h1>
            </div>

            @if ($selectedFile === null)
                <div class="empty">Laravel will create daily files once the application writes logs.</div>
            @else
                <pre class="viewer">{{ $contents }}</pre>
            @endif
        </section>
    </main>
</body>
</html>
