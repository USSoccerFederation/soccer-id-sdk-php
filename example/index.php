<?php
    include 'init.php';

    $profile = null;
    $session = getUssfAuth()?->getSession();
    if( $session !== null ){
        // Note: It's not recommend that you really live pull this kind of data every page load; you should probably cache it.
        // This is only here as an example indicating that you can access a user's profile outside of the login callback.
        $profile = getUssfAuth()
                ?->identity()
                ?->getProfile($session->accessToken) ?? null;
    }
?>
<html>
    <head></head>
    <body>
        <h1 style="text-align: center;">Soccer ID Example App</h1>
        <div style="text-align: center;">
        <?php if($session !== null) :?>
            You are logged in as <?php echo htmlspecialchars($session->user['email'] ?? $session->user['sub']); ?> &nbsp; | &nbsp;
            <a href="/logout.php">Log out</a>

            <h2 style="margin-top: 4em;">Raw session</h2>
            <div style="text-align: left">
                <?php dump($session); ?>
            </div>

            <h2 style="margin-top: 2em;">Profile</h2>
            <strong>Name:</strong> <?php echo htmlspecialchars($profile?->first_name ?? 'firstname not set'); ?>
            <?php echo htmlspecialchars($profile->last_name ?? 'lastname not set'); ?><br />

            <strong>DOB:</strong> <?php echo htmlspecialchars($profile?->date_of_birth ?? 'DOB not set'); ?>

            <h2 style="margin-top: 2em;">Raw Profile</h2>
            <div style="text-align: left">
                <?php dump($profile); ?>
            </div>

        <?php else:?>
            <a href="/login.php">Log in via U.S. Soccer</a>
        <?php endif;?>
        </div>
    </body>
</html>
