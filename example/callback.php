<?php

use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;

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

$session = getUssfAuth()->callback(function (Auth0Session $session, ?object $profile) {
    // $session will contain information related to Auth0, such as name, email, access token, etc.
    // $profile will contain additional information about the user. Data shape is configurable.
    //
    // This is where you can sync the user's info with your database.
    dump($session, $profile);
});