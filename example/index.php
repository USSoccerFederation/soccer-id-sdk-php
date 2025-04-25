<?php
    include 'init.php';

    $profile = null;
    if( isset($_SESSION['auth0AccessToken']) ){
        // Note: It's not recommend that you really live pull this kind of data every page load; you should probably cache it.
        // This is only here as an example indicating that you can access a user's profile outside of the login callback.
        $profile = getUssfAuth()->identity()->getProfile($_SESSION['auth0AccessToken']) ?? null;
    }
?>
<html>
    <head></head>
    <body>
        <h1 style="text-align: center;">Soccer ID Example App</h1>
        <div style="text-align: center;">
        <?php if($_SESSION['logged_in'] ?? false) :?>
            You are logged in as <?php echo htmlspecialchars($_SESSION['username']); ?> &nbsp; | &nbsp;
            <a href="/logout.php">Log out</a>

            <h2 style="margin-top: 4em;">Profile</h2>
            <strong>Name:</strong> <?php echo htmlspecialchars($profile?->user_metadata->profile->firstName ?? ''); ?>
            <?php echo htmlspecialchars($profile->user_metadata->profile->lastName ?? ''); ?><br />

            <strong>DOB:</strong> <?php echo htmlspecialchars($profile?->user_metadata->profile->birthDate ?? ''); ?>
        <?php else:?>
            <a href="/login.php">Log in via U.S. Soccer</a>
        <?php endif;?>
        </div>
    </body>
</html>