<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activa tu cuenta</title>
</head>

<body
    style="margin: 0; padding: 0; background-color: #f3ede1; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased; color: #1A1A1A;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
        style="background-color: #f3ede1; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                    style="max-width: 600px; background-color: #FFFFFF; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(96, 21, 15, 0.12); border: 1px solid #e5d9c0;">

                    <!-- Filete dorado institucional -->
                    <tr>
                        <td style="background-color: #D4A84B; font-size: 0; line-height: 0; height: 5px;">&nbsp;</td>
                    </tr>

                    <!-- Cabecera vino tinto -->
                    <tr>
                        <td align="center"
                            style="background: linear-gradient(135deg, #6b1d1d 0%, #60150F 60%, #4a100b 100%); background-color: #60150F; padding: 28px 30px 22px 30px;">
                            <p
                                style="margin: 0 0 4px 0; font-size: 21px; font-weight: bold; color: #FFFFFF; letter-spacing: 0.3px;">
                                Turismo Sogamoso</p>
                            <p style="margin: 0; font-size: 12px; letter-spacing: 2px; color: #e1bd45;">SECRETARÍA DE
                                CULTURA, TURISMO Y PATRIMONIO</p>
                        </td>
                    </tr>

                    <!-- Cuerpo del mensaje -->
                    <tr>
                        <td style="padding: 36px 32px 12px 32px; line-height: 1.65; font-size: 15px; color: #1A1A1A;">
                            <p style="margin-top: 0; font-size: 19px; font-weight: bold; color: #60150F;">Hola,
                                {{ $nombre }}
                            </p>

                            <p style="margin-bottom: 16px;">
                                La <strong>Secretaría de Cultura, Turismo y Patrimonio de Sogamoso</strong> ha creado
                                una cuenta de administrador para ti en la plataforma oficial de
                                <strong>Turismo Sogamoso</strong>.
                            </p>

                            <p style="margin-bottom: 20px;">
                                Para empezar a usarla, define tu contraseña de acceso:
                            </p>

                            <!-- Pasos -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                style="background-color: #faf6ee; border: 1px solid #e5d9c0; border-radius: 8px; margin-bottom: 8px;">
                                <tr>
                                    <td style="padding: 16px 18px; font-size: 14px; color: #1A1A1A; line-height: 1.7;">
                                        <span
                                            style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; background-color: #60150F; color: #FFFFFF; font-size: 12px; font-weight: bold;">1</span>
                                        &nbsp;Pulsa el botón <strong>Activar mi cuenta</strong>.<br>
                                        <span
                                            style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; background-color: #60150F; color: #FFFFFF; font-size: 12px; font-weight: bold;">2</span>
                                        &nbsp;Crea tu contraseña (mínimo 8 caracteres) y confírmala.
                                    </td>
                                </tr>
                            </table>

                            <!-- Vigencia -->
                            <p align="center" style="margin: 20px 0 4px 0;">
                                <span
                                    style="display: inline-block; background-color: #e1bd45; color: #60150F; font-size: 13px; font-weight: bold; padding: 6px 16px; border-radius: 20px;">Enlace
                                    de un solo uso · válido por 24 horas</span>
                            </p>

                            <!-- Botón CTA -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                style="margin: 24px auto;">
                                <tr>
                                    <td align="center" style="background-color: #60150F; border-radius: 8px;">
                                        <a href="{{ $url }}" target="_blank"
                                            style="padding: 14px 34px; font-size: 15px; font-weight: bold; color: #FFFFFF; text-decoration: none; display: inline-block; border-radius: 8px; letter-spacing: 0.5px;">Activar
                                            mi cuenta</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Respaldo de enlace directo -->
                            <p style="font-size: 13px; color: #5b5b5b; margin-top: 22px; line-height: 1.5;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:
                                <br>
                                <a href="{{ $url }}" style="color: #0D6B4C; word-break: break-all;">{{ $url }}</a>
                            </p>

                            <hr style="border: 0; border-top: 1px solid #e5d9c0; margin: 28px 0 16px 0;">

                            <p style="font-size: 13px; color: #7a7a7a; margin-bottom: 0;">
                                Si no solicitaste esta cuenta o crees que se trata de un error, puedes ignorar este
                                mensaje de forma segura.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie institucional -->
                    <tr>
                        <td
                            style="background-color: #4a100b; padding: 24px 32px; text-align: left; font-size: 13px; color: #f3ede1; line-height: 1.6;">
                            <p style="margin: 0; font-weight: bold; color: #FFFFFF;">Cordialmente,</p>
                            <p style="margin: 2px 0 0 0;">Equipo Técnico de la Plataforma de Cultura, Turismo y
                                Patrimonio de Sogamoso</p>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #e1bd45;">Alcaldía de Sogamoso —
                                Ciudad del Sol, Pueblo de Acero</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
