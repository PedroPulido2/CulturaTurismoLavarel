<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activa tu cuenta</title>
</head>

<body
    style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased; color: #333333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
        style="background-color: #f4f6f9; padding: 20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="600" cellspacing="0" cellpadding="0" border="0"
                    style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">

                    <tr>
                        <td style="padding: 40px 30px; line-height: 1.6; font-size: 15px; color: #334155;">
                            <p style="margin-top: 0; font-size: 18px; font-weight: bold; color: #0f172a;">Hola,
                                {{ $nombre }}
                            </p>

                            <p style="margin-bottom: 20px;">
                                Se ha creado una cuenta de administrador para ti en la plataforma oficial de la
                                <strong>Secretaría de Cultura, Turismo y Patrimonio de Sogamoso</strong>.
                            </p>

                            <p style="margin-bottom: 25px;">
                                Para activar tu cuenta y definir tu contraseña de acceso, haz clic en el siguiente
                                botón. Ten en cuenta que este enlace será válido únicamente durante las próximas
                                <strong>24 horas</strong>:
                            </p>

                            <!-- Botón CTA -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                style="margin: 30px auto;">
                                <tr>
                                    <td align="center" style="background-color: #0284c7; border-radius: 6px;">
                                        <a href="{{ $url }}" target="_blank"
                                            style="padding: 12px 28px; font-size: 15px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; border-radius: 6px; letter-spacing: 0.5px;">Activar
                                            mi cuenta</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Respaldo de enlace directo -->
                            <p style="font-size: 13px; color: #64748b; margin-top: 25px; line-height: 1.5;">
                                Si el botón anterior no funciona, copia y pega el siguiente enlace directamente en tu
                                navegador web:
                                <br>
                                <a href="{{ $url }}" style="color: #0284c7; word-break: break-all;">{{ $url }}</a>
                            </p>

                            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;">

                            <p style="font-size: 13px; color: #94a3b8; margin-bottom: 0;">
                                Si no solicitaste esta cuenta o consideras que se trata de un error, puedes ignorar este
                                mensaje de forma segura.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td
                            style="background-color: #f8fafc; padding: 25px 30px; border-top: 1px solid #e2e8f0; text-align: left; font-size: 13px; color: #64748b; line-height: 1.5;">
                            <p style="margin: 0; font-weight: bold; color: #334155;">Cordialmente,</p>
                            <p style="margin: 2px 0 20px 0;">Equipo Técnico de la Plataforma de Cultura, Turismo y
                                Patrimonio de Sogamoso</p>

                            <!-- Escudo y Logo -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                style="background-color: #1e293b; border-radius: 6px; overflow: hidden;">
                                <tr>
                                    <td align="center" style="padding: 8px 10px;">
                                        <a href="javascript:void(0);"
                                            style="text-decoration: none; cursor: default; display: block;">
                                            <img src="{{ $message->embed(public_path('escudoLogo.png')) }}"
                                                alt="Secretaría de Cultura, Turismo y Patrimonio - Sogamoso" width="420"
                                                style="width: 100%; max-width: 420px; height: auto; display: block; margin: 0 auto; border: 0;">
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
            </td>
        </tr>
    </table>
    </td>
    </tr>
    </table>
</body>

</html>