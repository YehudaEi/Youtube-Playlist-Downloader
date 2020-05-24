<?php

/***********************************************
 * 
 * PHP-Youtube Downloader
 * 
 * Owner: Yehuda Eisenberg.
 * 
 * Mail: Yehuda.telegram@gmail.com
 * 
 * Link: https://yehudae.ga
 * 
 * Telegram: @YehudaEisenberg
 * 
 * GitHub: https://github.com/YehudaEi
 *
 * License: MIT - אסור לעשות שימוש ציבורי, חובה להשאיר קרדיט ליוצר
 * 
************************************************/

error_reporting(0);

function setPath($scriptName = "YouTubeDownloader.video", $parm = ""){
    $scriptName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '_', $scriptName);
    $scriptPath = '/YouTube/'.$scriptName;
    $url = parse_url($_SERVER['REQUEST_URI']);
    
    if(urldecode($url['path']) !== urldecode($scriptPath)){
        header("Location: {$scriptPath}{$parm}");
        die();
    }
}

if(!isset($_GET['url'])){
    setPath();
    print '<html><head><title>PHP Youtube</title>'.
        '<script>function watch(){if(url=document.getElementById("url").value,null!==document.getElementById("video")){var e=document.getElementById("video");e.parentNode.removeChild(e);watch()}else{var t=document.createElement("video");t.setAttribute("src","?url="+url),t.setAttribute("id","video"),t.setAttribute("controls","controls"),t.setAttribute("autoplay","autoplay"),document.getElementById("content").appendChild(t)}}</script>'.
        '</head><body><div style="visibility: hidden;"></body></div><center id="content"><h1>PHP Youtube Downloader</h1><input type="text" style="width:250" placeholder="Here is the link to YouTube :)" id="url"><br><br><button onclick="watch()">watch</button><br><br></center></body></html>';
    die();
}else{
    if (preg_match('/[a-z0-9_-]{11,13}/i', $_GET['url'], $matches)) {
        $id = $matches[0];
    }
    if(isset($id) && !empty($id)){
        $file = 'logs' . '/' . date('Y-m-d') . '.log';
        $write = str_pad($_SERVER['REMOTE_ADDR'] . ', ' , 25) . date('d/M/Y - H:i:s') . ', ' . 'YouTube: '.$id . "\r\n";
        file_put_contents($file, $write, FILE_APPEND);
        
        require_once('YTDL.php');
        
        $youtube = new \YouTube\YouTubeDownloader();
        $links = $youtube->getDownloadLinks("https://www.youtube.com/watch?v=".$id, "mp4");

        if (count($links) == 1) {
            setPath();
            die("no links..");
        }
        else{
            setPath($links['name'] . ".mp4", "?url=https://www.youtube.com/watch?v=".$id);
        }
        $url = $links[0]['url'];
        
        $streamer = new \YouTube\YoutubeStreamer();
        $streamer->stream($url);
    }
    else{
        setPath();
        die("'id' not found!");
    }
}