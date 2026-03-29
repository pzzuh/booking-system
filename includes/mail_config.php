<?php
declare(strict_types=1);

function mailConfig(): array
{
    $smtpUsername = 'Kentbelenson0218@gmail.com';        // <-- your Gmail address
    $fromEmail    = 'Kentbelenson0218@gmail.com';        // <-- same Gmail address
    $fromName     = 'Notre Dame of Marbel University Facility Booking System';

    return [
        'smtp_host'       => 'smtp.gmail.com',
        'smtp_port'       => 587,
        'smtp_encryption' => 'tls',
        'smtp_username'   => $smtpUsername,
        'smtp_password'   => 'yloa kurh niow grjt', // <-- your Gmail App Password
        'from_email'      => $fromEmail,
        'from_name'       => $fromName,
    ];
}
