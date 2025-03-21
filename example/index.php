<?php
    include 'init.php';
?>
<html>
    <head></head>
    <body>
        <h1 style="text-align: center;">Soccer ID Example App</h1>
        <div style="text-align: center;">
        <?php if($_SESSION['logged_in'] ?? false) :?>
            You are logged in as <?php echo htmlspecialchars($_SESSION['username']); ?> &nbsp; | &nbsp;
            <a href="/logout.php">Log out</a>
        <?php else:?>
            <a href="/login.php">Log in via USSF</a>
        <?php endif;?>
        </div>
    </body>
</html>