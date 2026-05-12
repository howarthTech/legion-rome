<?php
/**
 * Contact form handler — POST /_form/contact.php
 *
 * Receives a submission from /contact/, validates it, rate-limits by IP,
 * routes to the appropriate post officer based on the "subject" dropdown,
 * and sends the email via Resend. On success redirects to /thanks/.
 */
declare(strict_types=1);
require_once __DIR__ . '/_shared.php';

rl_require_post();
rl_check_honeypot('website');
rl_rate_limit('contact');

$name    = rl_required('name', 200);
$email   = rl_required_email('email');
$phone   = rl_optional('phone', 50);
$subject = rl_required('subject', 50);
$message = rl_required('message', 5000);

// Map dropdown value to the officer who should receive it. The mapping lives
// in code (not the form HTML) so a hostile submitter can't redirect a message
// to an arbitrary address.
$routing = [
    'commander'  => ['romepost5@gmail.com',     'Commander Albert Hollis'],
    'sr-vice'    => ['grulo65@gmail.com',       'Sr Vice Commander George Sifuentes'],
    'historian'  => ['wjadams42@gmail.com',     'Post Historian Will Adams'],
    'auxiliary'  => ['romepost5@gmail.com',     'Auxiliary (relayed via Commander)'],
    'sal'        => ['romepost5@gmail.com',     'SAL (relayed via Commander)'],
    'general'    => ['romepost5@gmail.com',     'Post 5 General Inquiry'],
];
if (!isset($routing[$subject])) {
    rl_fail(400, "Invalid recipient");
}
[$toAddress, $recipientLabel] = $routing[$subject];

$body = <<<EOT
A message was submitted from the contact form at romelegion.org.

Sent to: $recipientLabel
From:    $name <$email>
Phone:   $phone

Message:
--------
$message

(Submitted from IP: {$_SERVER['REMOTE_ADDR']} at
 {$_SERVER['REQUEST_TIME_FLOAT']} UTC. Reply directly to this email to respond
 to the submitter.)
EOT;

rl_send_email(
    $toAddress,
    "[Post 5 Web] {$name} — {$subject}",
    $body,
    $email
);

rl_success_redirect('/thanks/');
