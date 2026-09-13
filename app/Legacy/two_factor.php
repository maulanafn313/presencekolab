<?php

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function generateGoogleAuthenticatorSecret()
{
    if (! class_exists('\Sonata\GoogleAuthenticator\GoogleAuthenticator')) {
        return null;
    }
    $g = new GoogleAuthenticator;

    return $g->generateSecret();
}

function getGoogleAuthenticatorQRCode($secret, $email, $issuer = 'Sistem Presensi')
{
    if (! class_exists('\Sonata\GoogleAuthenticator\GoogleQrUrl')) {
        return null;
    }
    try {
        // Generate QR code URL for Google Authenticator
        // Format: otpauth://totp/ISSUER:EMAIL?secret=SECRET&issuer=ISSUER
        $qrContent = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($issuer),
            urlencode($email),
            urlencode($secret),
            urlencode($issuer)
        );

        // Use Google Charts API to generate QR code image
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($qrContent);

        return $qrUrl;
    } catch (Exception $e) {
        error_log('Error generating QR code: '.$e->getMessage());

        return null;
    }
}

function verifyGoogleAuthenticatorOTP($secret, $code)
{
    if (! class_exists('\Sonata\GoogleAuthenticator\GoogleAuthenticator')) {
        return false;
    }
    if (empty($secret) || empty($code)) {
        return false;
    }
    $g = new GoogleAuthenticator;

    return $g->checkCode($secret, $code);
}

// Email Helper Functions
function sendPasswordResetEmail($email, $resetToken)
{
    try {
        // Build reset URL - handle both localhost and production
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath = dirname($scriptName);

        // Clean up base path - remove trailing slash and normalize
        $basePath = rtrim($basePath, '/');
        if ($basePath === '.') {
            $basePath = '';
        }
        if (! empty($basePath) && $basePath !== '/') {
            $basePath = '/'.ltrim($basePath, '/');
        }

        $resetUrl = $protocol.'://'.$host.$basePath.'/index.php?page=verify-otp&token='.urlencode($resetToken);

        $subject = 'Reset Password - Sistem Presensi';

        // Professional email template
        $htmlBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0; text-align: center; background-color: #ffffff;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 40px 20px 40px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: bold;">Reset Password</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px; background-color: #ffffff;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">Halo,</p>
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">Kami menerima permintaan untuk mereset password akun Anda di Sistem Presensi Berbasis Wajah.</p>
                            <p style="margin: 0 0 30px 0; color: #333333; font-size: 16px; line-height: 1.6;">Untuk melanjutkan proses reset password, silakan verifikasi dengan kode OTP dari Google Authenticator Anda terlebih dahulu.</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="'.htmlspecialchars($resetUrl).'" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">Verifikasi OTP</a>
                            </div>
                            <p style="margin: 30px 0 10px 0; color: #666666; font-size: 14px; line-height: 1.6;">Atau salin link berikut ke browser Anda:</p>
                            <p style="margin: 0 0 30px 0; color: #667eea; font-size: 14px; word-break: break-all; line-height: 1.6;">'.htmlspecialchars($resetUrl).'</p>
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 30px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;"><strong>Penting:</strong> Link ini akan kedaluwarsa dalam 1 jam. Jika Anda tidak meminta reset password, abaikan email ini.</p>
                            </div>
                            <p style="margin: 30px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">Terima kasih,<br><strong>Tim Sistem Presensi</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0; color: #6c757d; font-size: 12px;">&copy; '.date('Y').' Sistem Presensi Berbasis Wajah. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        $textBody = "Reset Password\n\n";
        $textBody .= "Kami menerima permintaan untuk mereset password akun Anda.\n\n";
        $textBody .= "Untuk melanjutkan, silakan verifikasi dengan kode OTP dari Google Authenticator Anda:\n";
        $textBody .= $resetUrl."\n\n";
        $textBody .= "Link ini akan kedaluwarsa dalam 1 jam.\n\n";
        $textBody .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
        $textBody .= "Terima kasih,\nTim Sistem Presensi";

        $headers = 'MIME-Version: 1.0'."\r\n";
        $headers .= 'Content-type:text/html;charset=UTF-8'."\r\n";
        $headers .= 'From: Sistem Presensi <noreply@presensi.local>'."\r\n";
        $headers .= 'Reply-To: noreply@presensi.local'."\r\n";

        // Try to send email
        $result = @mail($email, $subject, $htmlBody, $headers);

        // Log email attempt
        error_log("Password reset email sent to: $email, URL: $resetUrl, Result: ".($result ? 'SUCCESS' : 'FAILED'));

        // For development/testing: if mail() fails, log but don't fail completely
        // In production, you should configure SMTP properly
        if (! $result) {
            error_log("Warning: mail() function returned false for $email. Check PHP mail configuration.");
            // For development: we'll still allow the reset to proceed
            // In production, you should configure SMTP properly or use PHPMailer
        }

        return $result;
    } catch (Exception $e) {
        error_log('Error in sendPasswordResetEmail: '.$e->getMessage());

        return false;
    }
}

// FaceNet Integration Functions
