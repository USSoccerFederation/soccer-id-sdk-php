<?php

include 'init.php';


/*
* When the user chooses to log in via the U.S. Soccer Auth0 Universal Login,
* they simply need to be redirected to the Universal Login landing page.
* Doing so is as simple as calling `login()` on the `UssfAuth` instance.
*
* Once they have completed the auth challenge, they will be directed
* to your configured callback endpoint for a code exchange.
*
* You should call this when the user chooses to log in via U.S. Soccer Auth.
*/

getUssfAuth()->login();