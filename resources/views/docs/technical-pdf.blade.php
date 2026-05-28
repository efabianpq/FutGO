<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Técnico — Soy Pachón Mundial</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        @page { size: letter landscape; margin: 12mm 10mm; }
        body { color: #1a1a1a; font-size: 10px; line-height: 1.45; }

        /* Header con brand */
        .doc-header {
            border-bottom: 2px solid #0a3d2e;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .doc-header .brand {
            display: inline-block;
            font-size: 18px;
            font-weight: 900;
            color: #0a3d2e;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .doc-header .brand .at { color: #d4a82a; }
        .doc-header .meta {
            float: right;
            font-size: 8px;
            color: #4a4a48;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding-top: 6px;
        }
        .doc-header::after { content: ""; display: block; clear: both; }

        /* Tipografía */
        h1 {
            font-size: 20px;
            color: #0a3d2e;
            text-transform: uppercase;
            letter-spacing: -.01em;
            margin: 18px 0 8px;
            border-bottom: 1.5px solid #0a3d2e;
            padding-bottom: 4px;
            page-break-before: always;
        }
        h1:first-of-type { page-break-before: avoid; }
        h2 {
            font-size: 14px;
            color: #14593f;
            text-transform: uppercase;
            letter-spacing: -.005em;
            margin: 14px 0 6px;
        }
        h3 {
            font-size: 12px;
            color: #0a3d2e;
            text-transform: uppercase;
            margin: 10px 0 4px;
        }
        h4 {
            font-size: 10.5px;
            color: #14593f;
            margin: 8px 0 3px;
        }

        p { margin: 5px 0; }
        ul, ol { margin: 5px 0; padding-left: 20px; }
        li { margin: 2px 0; }

        /* Code blocks */
        pre {
            background: #faf7ef;
            border: 1px solid #e5dfd1;
            border-left: 3px solid #0a3d2e;
            padding: 8px 10px;
            margin: 6px 0;
            font-family: 'Courier New', monospace;
            font-size: 8.5px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            page-break-inside: avoid;
        }
        code {
            font-family: 'Courier New', monospace;
            font-size: 9px;
            background: #f5f1e8;
            padding: 1px 4px;
            border-radius: 2px;
            color: #0a3d2e;
        }
        pre code {
            background: transparent;
            padding: 0;
            border: none;
        }

        /* Tablas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
            font-size: 9px;
            page-break-inside: avoid;
        }
        thead {
            background: #0a3d2e;
            color: #f5f1e8;
        }
        thead th {
            padding: 5px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        tbody td {
            padding: 4px 6px;
            border-bottom: 0.5px solid #efeadc;
            vertical-align: top;
        }
        tbody tr:nth-child(even) { background: #faf7ef; }

        /* Quotes */
        blockquote {
            margin: 8px 0;
            padding: 6px 12px;
            border-left: 3px solid #d4a82a;
            background: #faf7ef;
            color: #4a4a48;
            font-style: italic;
        }

        /* Horizontal rule */
        hr {
            border: none;
            border-top: 1px solid #e5dfd1;
            margin: 12px 0;
        }

        /* Links */
        a {
            color: #14593f;
            text-decoration: none;
        }

        /* Strong */
        strong { font-weight: 700; color: #0a3d2e; }

        /* Footer */
        .doc-footer {
            position: fixed;
            bottom: -8mm;
            left: 0; right: 0;
            text-align: center;
            font-size: 8px;
            color: #8a8884;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="doc-header">
    <span class="brand"><span class="at">@</span>SoyPachon</span>
    <span class="meta">Informe Técnico · {{ $generatedAt }}</span>
</div>

{!! $content !!}

<div class="doc-footer">
    soypachonmundial.online · Informe técnico v1.0
</div>

</body>
</html>
