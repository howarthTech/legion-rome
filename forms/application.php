<?php
/**
 * Membership application handler — POST /_form/application.php
 *
 * Receives a submission from /membership/apply/, validates it, rate-limits,
 * and forwards the application to the post Adjutant (Rick Hunt) with the
 * Commander on cc. On success redirects to /thanks/.
 *
 * Note: this only sends an EMAIL of the application. The applicant still
 * needs to mail in their DD214 and dues check separately — the form's help
 * text reminds them.
 */
declare(strict_types=1);
require_once __DIR__ . '/_shared.php';

rl_require_post();
rl_check_honeypot('website');
rl_rate_limit('application');

$type      = rl_required('application_type', 20);
$first     = rl_required('first_name', 100);
$middle    = rl_optional('middle_initial', 1);
$last      = rl_required('last_name', 100);
$email     = rl_required_email('email');
$phone     = rl_optional('phone', 50);
$dob       = rl_optional('date_of_birth', 20);
$address   = rl_optional('address', 200);
$city      = rl_optional('city', 100);
$state     = rl_optional('state', 2);
$zip       = rl_optional('zip', 10);
$branch    = rl_required('branch', 30);
$era       = rl_optional('era', 200);
$former    = rl_optional('former_post', 200);
$membId    = rl_optional('membership_id', 50);
$notes     = rl_optional('notes', 5000);

if (!in_array($type, ['new', 'renewal', 'transfer'], true)) {
    rl_fail(400, "Invalid application type");
}

$displayType = match ($type) {
    'new'      => 'New Membership',
    'renewal'  => 'Renewal',
    'transfer' => 'Transfer to Post 5',
};

$middleDisplay = $middle ? "$middle. " : '';

$body = <<<EOT
A membership application was submitted on romelegion.org.

Type:    $displayType
Name:    $first {$middleDisplay}$last
Email:   $email
Phone:   $phone
DOB:     $dob
Address: $address
         $city, $state $zip

Branch:  $branch
Era:     $era

Former Department / Post (transfers only): $former
Existing membership ID (renewals/transfers): $membId

Notes from applicant:
---------------------
$notes

(Reply directly to this email to contact the applicant.
 Reminder: applicant must mail DD214 and \$55 dues check to
 P.O. Box 945, Rome, GA 30162 to complete membership.)
EOT;

// Send to the Adjutant, cc Commander.
rl_send_email(
    ['adjutant@romelegion.org', 'romepost5@gmail.com'],  // TODO: confirm if Rick Hunt has an email; for now route to the Commander
    "[Post 5 Web] $displayType — $first $last",
    $body,
    $email
);

rl_success_redirect('/thanks/');
