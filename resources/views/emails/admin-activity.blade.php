<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;padding:24px 16px;">

        <div style="text-align:center;padding:18px 0;">
            <span style="font-size:22px;font-weight:800;color:#4f46e5;letter-spacing:-0.5px;">Kam<span style="color:#0f172a;">Verify</span></span>
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:2px;">Admin notification</div>
        </div>

        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
            <div style="background:#4f46e5;padding:16px 24px;">
                <div style="color:#ffffff;font-size:17px;font-weight:700;">{{ $heading }}</div>
            </div>

            <div style="padding:20px 24px;">
                @if($note)
                    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#334155;">{{ $note }}</p>
                @endif

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    @foreach($fields as $label => $value)
                        <tr>
                            <td style="padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:12px;color:#64748b;width:42%;vertical-align:top;text-transform:uppercase;letter-spacing:0.4px;">{{ $label }}</td>
                            <td style="padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:14px;color:#0f172a;font-weight:600;text-align:right;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>

                <div style="margin-top:14px;font-size:12px;color:#94a3b8;">{{ now()->toDayDateTimeString() }} UTC</div>
            </div>
        </div>

        <div style="text-align:center;padding:18px 0;font-size:11px;color:#94a3b8;line-height:1.6;">
            This is an automated KamVerify admin alert.<br>
            &copy; {{ date('Y') }} KamVerify
        </div>
    </div>
</body>
</html>
