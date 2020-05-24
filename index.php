<?php

require_once('simpleHtmlDom.php');
require_once('Youtube-Downloader/YTDL.php');
require_once('YTPDL.php');

ignore_user_abort(true);
set_time_limit(0);

if(isset($_POST['submit'])){
    $email = filter_var($_POST['mail'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid mail!');
    }
    
    $pName = htmlspecialchars($_POST['name']);
    if (mb_strlen($pName) > 30) {
        die('Playlist name is too long!');
    }
    
    if (preg_match('/[a-z0-9_-]{11,40}/i', $_POST['PID'], $matches)) {
        $link = "https://www.youtube.com/playlist?list=" . $matches[0];
    }
    else{
        die('Playlist ID not found!');
    }
    
    downloadListVideos($link, $pName, $email);
}
else{

echo <<<HTML
<html>

<head>
	<title>PHP Youtube</title>
</head>

<body align="center">
	<div id="content">
	    <form method="post">
    		<h1>PHP Youtube Playlist Downloader</h1>
    		<input type="text" style="width:180" placeholder="Playlist ID (only id)" name="PID">
    		<br>
    		<br>
    		<input type="text" style="width:180" placeholder="Playlist name" name="name">
    		<br>
    		<br>
    		<input type="text" style="width:180" placeholder="Your mail" name="mail">
    		<br>
    		<br>
    		<input type="submit" value="הורד" name="submit">
		</form>
	</div>
</body>

</html>
HTML;

}