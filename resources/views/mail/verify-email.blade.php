<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Подтвердите ваш email</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0f;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0f;min-height:100vh;">
    <tr>
        <td align="center" valign="top" style="padding:48px 16px;">

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px;">

                <!-- Logo / Brand -->
                <tr>
                    <td align="center" style="padding-bottom:32px;">
                        <span style="font-size:1.1rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.2);">
                            NO ALONE
                        </span>
                    </td>
                </tr>

                <!-- Card -->
                <tr>
                    <td style="background-color:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.1);border-radius:3px;padding:40px 36px;">

                        <!-- Envelope icon -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="padding-bottom:24px;">
                                    <table cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="width:52px;height:52px;background-color:rgba(190,145,255,0.07);border:1px solid rgba(190,145,255,0.28);border-radius:3px;text-align:center;vertical-align:middle;">
                                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjIiIGhlaWdodD0iMjIiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRkUyOEEyIiBzdHJva2Utd2lkdGg9IjEuNCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHg9IjIiIHk9IjQiIHdpZHRoPSIyMCIgaGVpZ2h0PSIxNiIgcng9IjIiLz48cGF0aCBkPSJNMiA3bDEwIDcgMTAtNyIvPjwvc3ZnPg==" width="22" height="22" alt="" style="display:block;margin:0 auto;">
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Title -->
                            <tr>
                                <td align="center" style="padding-bottom:12px;">
                                    <p style="margin:0;font-size:1.3rem;font-weight:600;color:rgba(255,255,255,0.92);letter-spacing:-0.01em;">
                                        Подтвердите ваш email
                                    </p>
                                </td>
                            </tr>

                            <!-- Greeting -->
                            <tr>
                                <td align="center" style="padding-bottom:20px;">
                                    <p style="margin:0;font-size:0.9rem;color:rgba(255,255,255,0.4);line-height:1.65;">
                                        Привет, {{ $name }}! Для завершения регистрации<br>
                                        нажмите кнопку ниже.
                                    </p>
                                </td>
                            </tr>

                            <!-- Divider -->
                            <tr>
                                <td style="padding-bottom:28px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="border-top:1px solid rgba(255,255,255,0.07);font-size:0;line-height:0;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- CTA Button -->
                            <tr>
                                <td align="center" style="padding-bottom:28px;">
                                    <table cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="border-radius:3px;border:1px solid rgba(190,145,255,0.55);background-color:rgba(190,145,255,0.1);">
                                                <a href="{{ $url }}"
                                                   target="_blank"
                                                   style="display:inline-block;padding:12px 32px;font-size:0.9rem;font-weight:500;color:#BE91FF;text-decoration:none;letter-spacing:0.02em;">
                                                    Подтвердить email
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Divider -->
                            <tr>
                                <td style="padding-bottom:24px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="border-top:1px solid rgba(255,255,255,0.07);font-size:0;line-height:0;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Fallback URL -->
                            <tr>
                                <td align="center" style="padding-bottom:4px;">
                                    <p style="margin:0;font-size:0.75rem;color:rgba(255,255,255,0.2);">
                                        Если кнопка не работает, скопируйте ссылку:
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td align="center">
                                    <p style="margin:0;font-size:0.72rem;color:rgba(190,145,255,0.5);word-break:break-all;line-height:1.5;">
                                        {{ $url }}
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="padding-top:24px;">
                        <p style="margin:0;font-size:0.75rem;color:rgba(255,255,255,0.15);line-height:1.6;">
                            Если вы не регистрировались — просто проигнорируйте это письмо.<br>
                            Ссылка действительна 60 минут.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
