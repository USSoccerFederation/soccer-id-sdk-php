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

$session = getUssfAuth()->callback(
    function (Auth0Session $session, ?object $profile) {
        // $session will contain information related to Auth0, such as name, email, access token, etc.
        // $profile will contain additional information about the user. Data shape is configurable.

        /*
         * This is where you can sync the user's info with your database.
         * Example:
         *
         * if ($profile === null) {
         *    DB::table('users')
         *        ->where('email', $session->user['email'])
         *        ->update([
         *            'first_name' => $profile->user_metadata->profile->firstName,
         *            'last_name' => $profile->user_metadata->profile->lastName
         *        ]);
         * }
         */

        // Set the user's session cookies, or whatever else your app needs to do to log in a user.
        // Example only; not secure
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $session->user['email'];
        $_SESSION['auth0AccessToken'] = $session->accessToken;


        // You may return an array of key-value pairs in order to perform a profile update
        // to USSF's Identity Service
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
    },
    // You may pass an array of profile keys to selectively pull only the given profile params.
    // This will affect the shape of the `$profile` passed to the closure.
    [
        'user_metadata.profile.firstName',
        'user_metadata.profile.lastName',
        'user_metadata.profile.birthDate'
    ]
);

// Direct the user into your application; don't keep them on callback
header("Location: /index.php");