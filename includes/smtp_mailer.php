<?php
declare(strict_types=1);

require_once __DIR__ . '/mail_config.php';

/**
 * Send a plain-text email via a raw SMTP socket (no external libraries).
 * Supports SSL (port 465) and STARTTLS (port 587).
 */
function smtpSendMail(string $toEmail, string $toName, string $subject, string $textBody): bool
{
    $cfg = mailConfig();

    $host      = (string)$cfg['smtp_host'];
    $port      = (int)$cfg['smtp_port'];
    $enc       = strtolower((string)$cfg['smtp_encryption']);
    $user      = (string)$cfg['smtp_username'];
    $pass      = (string)$cfg['smtp_password'];
    $fromEmail = (string)$cfg['from_email'];
    $fromName  = (string)$cfg['from_name'];

    if ($user === '' || $pass === '' || $fromEmail === '') {
        error_log('SMTP not configured: APP_SMTP_USERNAME, APP_SMTP_PASSWORD, and APP_MAIL_FROM must all be set.');
        return false;
    }

    // --- Connect ----------------------------------------------------------------
    $remote = ($enc === 'ssl')
        ? "ssl://{$host}:{$port}"
        : "tcp://{$host}:{$port}";

    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if ($fp === false) {
        error_log("SMTP connect failed [{$errno}]: {$errstr}");
        return false;
    }
    stream_set_timeout($fp, 15);

    // --- Helpers ----------------------------------------------------------------

    /**
     * Read a complete (possibly multi-line) SMTP response.
     * Gmail's EHLO reply is always multi-line (250-PIPELINING, 250-SIZE, …, 250 AUTH …).
     * We keep reading until we hit a line whose 4th character is a space (not a dash).
     */
    $read = static function () use ($fp): string {
        $out = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false || $line === '') {
                break;
            }
            $out .= $line;
            // A line like "250 OK" signals end; "250-KEYWORD" means more lines follow.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };

    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = static function (string $resp, array $codes): bool {
        // Match the first 3-digit code in the response.
        if (!preg_match('/^(\d{3})/m', $resp, $m)) {
            return false;
        }
        return in_array((int)$m[1], $codes, true);
    };

    // --- SMTP handshake ---------------------------------------------------------

    $resp = $read();
    if (!$expect($resp, [220])) {
        error_log('SMTP greeting failed: ' . trim($resp));
        fclose($fp);
        return false;
    }

    $write('EHLO localhost');
    $resp = $read();
    if (!$expect($resp, [250])) {
        // Fall back to HELO
        $write('HELO localhost');
        $resp = $read();
        if (!$expect($resp, [250])) {
            error_log('SMTP EHLO/HELO failed: ' . trim($resp));
            fclose($fp);
            return false;
        }
    }

    // STARTTLS upgrade (port 587 / encryption = tls)
    if ($enc === 'tls') {
        $write('STARTTLS');
        $resp = $read();
        if (!$expect($resp, [220])) {
            error_log('SMTP STARTTLS failed: ' . trim($resp));
            fclose($fp);
            return false;
        }

        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP TLS upgrade (stream_socket_enable_crypto) failed.');
            fclose($fp);
            return false;
        }

        // Re-handshake after TLS upgrade
        $write('EHLO localhost');
        $resp = $read();
        if (!$expect($resp, [250])) {
            error_log('SMTP EHLO after STARTTLS failed: ' . trim($resp));
            fclose($fp);
            return false;
        }
    }

    // --- AUTH LOGIN -------------------------------------------------------------

    $write('AUTH LOGIN');
    $resp = $read();
    if (!$expect($resp, [334])) {
        error_log('SMTP AUTH LOGIN not accepted: ' . trim($resp));
        fclose($fp);
        return false;
    }

    $write(base64_encode($user));
    $resp = $read();
    if (!$expect($resp, [334])) {
        error_log('SMTP username rejected: ' . trim($resp));
        fclose($fp);
        return false;
    }

    $write(base64_encode($pass));
    $resp = $read();
    if (!$expect($resp, [235])) {
        error_log('SMTP authentication failed (wrong password or App Password not used): ' . trim($resp));
        fclose($fp);
        return false;
    }

    // --- Envelope ---------------------------------------------------------------

    if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
        error_log('Invalid MAIL FROM address: ' . $fromEmail);
        fclose($fp);
        return false;
    }

    $fromHeader = $fromName !== ''
        ? sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail)
        : $fromEmail;

    $toHeader = $toName !== ''
        ? sprintf('"%s" <%s>', addcslashes($toName, '"\\'), $toEmail)
        : $toEmail;

    $write("MAIL FROM:<{$fromEmail}>");
    $resp = $read();
    if (!$expect($resp, [250])) {
        error_log('SMTP MAIL FROM failed: ' . trim($resp));
        fclose($fp);
        return false;
    }

    $write("RCPT TO:<{$toEmail}>");
    $resp = $read();
    if (!$expect($resp, [250, 251])) {
        error_log('SMTP RCPT TO failed: ' . trim($resp));
        fclose($fp);
        return false;
    }

    // --- Message body -----------------------------------------------------------

    $write('DATA');
    $resp = $read();
    if (!$expect($resp, [354])) {
        error_log('SMTP DATA command failed: ' . trim($resp));
        fclose($fp);
        return false;
    }

    // Normalise line endings and dot-stuff (RFC 5321 §4.5.2)
    $normalised = str_replace(["\r\n", "\r", "\n"], "\r\n", $textBody);
    $dotStuffed = preg_replace('/^\./m', '..', $normalised);

    $headers   = [];
    $headers[] = 'From: '                     . $fromHeader;
    $headers[] = 'To: '                       . $toHeader;
    $headers[] = 'Subject: '                  . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'Date: '                     . gmdate('D, d M Y H:i:s') . ' +0000';
    $headers[] = 'Message-ID: <'              . bin2hex(random_bytes(16)) . '@' . $host . '>';

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $dotStuffed . "\r\n.";
    fwrite($fp, $message . "\r\n");

    $resp = $read();
    if (!$expect($resp, [250])) {
        error_log('SMTP message send failed: ' . trim($resp));
        fclose($fp);
        return false;
    }

    $write('QUIT');
    fclose($fp);
    return true;
}

/**
 * Send the password-reset email to a specific user's address.
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink): bool
{
    $subject = 'NDMU Facility Booking System – Password Reset';

    $body  = "Hello {$toName},\r\n\r\n";
    $body .= "We received a request to reset the password for your account.\r\n\r\n";
    $body .= "Click the link below to set a new password (valid for 1 hour):\r\n";
    $body .= "{$resetLink}\r\n\r\n";
    $body .= "If you did not request a password reset, you can safely ignore this\r\n";
    $body .= "email — your password will not be changed.\r\n\r\n";
    $body .= "— Notre Dame of Marbel University Facility Booking System\r\n";

    return smtpSendMail($toEmail, $toName, $subject, $body);
}
