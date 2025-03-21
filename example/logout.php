<?php

include 'init.php';

/*
 * Remember to destroy their session/cookies and whatever
 * else you need to do to log the user out of the app *before*
 * calling `logout()` against the `UssfAuth` instance.
 */

session_destroy();
getUssfAuth()->logout();