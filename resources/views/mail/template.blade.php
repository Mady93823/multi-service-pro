{{-- The wrapper for an admin-authored email template (M23, ADR D25).

     $content arrives already rendered by MarkdownRenderer (D20) — raw HTML in
     the admin's markdown is stripped there, which is why it can be echoed
     unescaped here. Nothing else on this page takes admin input as HTML. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;padding:32px;">
                    <tr>
                        <td style="padding-bottom:16px;font-size:18px;font-weight:600;">{{ $appName }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:15px;line-height:1.6;">
                            {!! $content !!}
                        </td>
                    </tr>
                    @if ($url !== '')
                        <tr>
                            <td style="padding-top:24px;">
                                <a href="{{ $url }}" style="display:inline-block;background-color:#111827;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;">
                                    {{ __('View details') }}
                                </a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding-top:28px;font-size:12px;color:#6b7280;">
                            {{ __('You are receiving this email because of activity on your :app account.', ['app' => $appName]) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
