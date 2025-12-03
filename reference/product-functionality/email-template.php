<?php
function get_email_template($content) {
    ob_start();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <style>
		.emailbtn:hover {
			background:#21273a;
		}
		</style>
    </head>
    <body style="font-family: Helvetica, sans-serif; -webkit-font-smoothing: antialiased; font-size: 16px; line-height: 1.3; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; background-color: #f4f5f6; margin: 0; padding: 0;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #f4f5f6; width: 100%;">
            <tr>
                <td align="center" style="vertical-align: top; padding: 24px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; max-width: 600px;">
                        <tr>
                            <td style="background-color: #ffffff; border-radius: 5px; padding: 24px;">
                                <?php echo $content; ?>
                            </td>
                        </tr>
                    </table>
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; max-width: 600px;">
                        <tr>
                            <td align="center" style="padding-top: 24px; color: #9a9ea6; font-size: 14px;">
                                <a href="https://eridehero.com" style="color: #9a9ea6; text-decoration: underline;"><img src="https://eridehero.com/wp-content/uploads/2021/09/logo.png" alt="ERideHero logo" style="width:145px" /></a>
                            </td>
                        </tr>
						<tr>
                            <td align="center" style="padding-top: 17px; color: #9a9ea6; font-size: 18px;">
                                The consumer-first, data-driven guide to micromobility
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    return ob_get_clean();
}