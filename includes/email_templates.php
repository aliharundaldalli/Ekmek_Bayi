<?php
/**
 * E-posta Şablonları
 * 
 * Bu dosya sistem genelinde kullanılan standart e-posta şablonunu içerir.
 */

/**
 * Standart HTML E-posta Şablonu Oluşturur
 * 
 * @param string $title E-posta başlığı (Header kısmında görünür)
 * @param string $content E-posta içeriği (HTML olabilir)
 * @param string|null $buttonText Buton metni (Opsiyonel)
 * @param string|null $buttonUrl Buton linki (Opsiyonel)
 * @return string Oluşturulan HTML şablonu
 */
function getStandardEmailTemplate($title, $content, $buttonText = null, $buttonUrl = null) {
    // Site ayarlarını al (Logo ve Site Başlığı için)
    global $settings;
    
    $siteTitle = htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş Sistemi');
    // Logo HTML removed as per request
    $logoHtml = '';

    // Buton HTML
    $buttonHtml = '';
    if ($buttonText && $buttonUrl) {
        $buttonHtml = '
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top: 25px; margin-bottom: 25px;">
            <tr>
                <td align="center" bgcolor="#4e73df" style="border-radius: 6px;">
                    <a href="' . $buttonUrl . '" target="_blank" style="font-family: \'Poppins\', Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; border-radius: 6px; padding: 12px 24px; display: inline-block; border: 1px solid #4e73df;">
                        ' . htmlspecialchars($buttonText) . '
                    </a>
                </td>
            </tr>
        </table>';
    }

    // Şablon
    return '
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap");
            body { margin: 0; padding: 0; font-family: "Poppins", Arial, sans-serif; background-color: #f8f9fc; color: #5a5c69; -webkit-font-smoothing: antialiased; }
            table { border-collapse: collapse; width: 100%; }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-top: 20px; margin-bottom: 20px; }
            .email-header { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); padding: 30px 20px; text-align: center; color: #ffffff; }
            .email-header h1 { margin: 0; font-size: 24px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
            .email-body { padding: 40px 30px; line-height: 1.6; font-size: 15px; color: #333333; }
            .email-footer { background-color: #f1f3f9; padding: 20px; text-align: center; font-size: 12px; color: #858796; border-top: 1px solid #e3e6f0; }
            .email-footer a { color: #4e73df; text-decoration: none; }
            h2 { color: #4e73df; font-size: 20px; margin-top: 0; margin-bottom: 15px; border-bottom: 2px solid #f1f3f9; padding-bottom: 10px; }
            p { margin-bottom: 15px; }
            strong { color: #2e59d9; }
            .info-box { background-color: #f8f9fc; border-left: 4px solid #4e73df; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            .warning-box { background-color: #fff3cd; border-left: 4px solid #f6c23e; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #856404; }
            .success-box { background-color: #d4edda; border-left: 4px solid #1cc88a; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #155724; }
            
            /* Responsive */
            @media only screen and (max-width: 600px) {
                .email-body { padding: 20px; }
                .email-header { padding: 20px; }
                .email-header h1 { font-size: 20px; }
            }
        </style>
    </head>
    <body>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <div class="email-container">
                        <!-- Header -->
                        <div class="email-header">
                            ' . $logoHtml . '
                            <h1>' . htmlspecialchars($title) . '</h1>
                        </div>
                        
                        <!-- Body -->
                        <div class="email-body">
                            ' . $content . '
                            ' . $buttonHtml . '
                        </div>
                        
                        <!-- Footer -->
                        <div class="email-footer">
                            <p>&copy; ' . date('Y') . ' ' . $siteTitle . '. Tüm hakları saklıdır.</p>
                            <p>Bu e-posta otomatik olarak gönderilmiştir, lütfen cevaplamayınız.</p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';
}
?>
