<?php

use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;

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

    // Set the user's session cookies, or whatever else your app needs to do to log in a user.
    // Ensure that you are
    session_set_cookie_params([
        'lifetime' => 3600, // Cookie expires in 1 hour
        'path' => '/',
        'domain' => '', // Defaults to the current domain
        'secure' => false, // Should typically be `true`, but we'll allow HTTP for testing purposes
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Strict' // Restrict cross-site requests
    ]);

    session_start();
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $session->user['email'];

    // Direct the user into your application; don't keep them on callback
    header("Location: /index.php");
});