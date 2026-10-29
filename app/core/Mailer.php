<?php
class Mailer
{
    public static function sendPasswordOtp(string $to, string $otp): void
    {
        $host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $port = (int) (getenv('SMTP_PORT') ?: 587);
        $username = getenv('SMTP_USERNAME') ?: 'farmbot9217@gmail.com';
        $password = getenv('SMTP_PASSWORD') ?: 'nemm pyej xdeb kbes';
        $from = getenv('SMTP_FROM_EMAIL') ?: $username;
        $fromName = getenv('SMTP_FROM_NAME') ?: APP_NAME;
        $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');

        if ($host === '' || $username === '' || $password === '' || $from === '') {
            throw new RuntimeException('Email is not configured. Set the SMTP environment variables first.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException('Could not connect to the email server: ' . $errstr);
        }
        stream_set_timeout($socket, 15);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO cafecore.local', [250]);
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Could not establish secure email connection.');
                }
                self::command($socket, 'EHLO cafecore.local', [250]);
            }
            self::command($socket, 'AUTH LOGIN', [334]);
            self::command($socket, base64_encode($username), [334]);
            self::command($socket, base64_encode($password), [235]);
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $subject = 'Your ' . APP_NAME . ' password reset code';
            $body = "Hello,\r\n\r\nWe received a request to reset your " . APP_NAME . " password.\r\n\r\nYour one-time password (OTP) is: {$otp}\r\n\r\nThis code expires in 10 minutes and can only be used once. If you did not request this, you can safely ignore this email.\r\n\r\n— " . APP_NAME;
            $headers = [
                'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Date: ' . date(DATE_RFC2822),
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $message = preg_replace('/\r?\n/', "\r\n", $message);
            $message = preg_replace('/\r\n\./', "\r\n..", $message);
            fwrite($socket, $message . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private static function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expected);
    }

    /** @param resource $socket */
    private static function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Email server rejected the request (SMTP ' . $code . ').');
        }
        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
