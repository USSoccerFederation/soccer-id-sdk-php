<?php

require_once "../vendor/autoload.php";
include 'init.php';

/*
 * This is where the user is redirected when returning from Auth0
 * Universal Login. If the callback attempt is successful, the user
 * can be considered authenticated.
 *
 * This would be a good time to:
 * - Check if the user exists in your database; creating them if necessary
 * - Synchronize profile information (update name, email, etc.)
 * - Set any cookies/session needed to keep the user logged into your app
 */

$auth = getAuthInstance();
$session = $auth->callback();

dump($session);