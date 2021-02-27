<?php

define('YOUTUBE_API_KEY', '');

function downloadVideo($id, $dirName){
    if(!is_dir($dirName) && !file_exists($dirName))
        mkdir($dirName);
        
    if(isset($id) && !empty($id)){
        sleep(10);

        $youtube = new YouTubeDownloader();
        $links = $youtube->getDownloadLinks("https://www.youtube.com/watch?v=".$id, "mp4");
        $link = $links->getFirstCombinedFormat();

        if (!$link) {
            die("no links..");
        }
        
        return copy($link->url, $dirName.'/'.$links->getInfo()->getTitle().'.mp4');
    }
}

function getPlaylistVideos($playlistId){
    if(empty(YOUTUBE_API_KEY)){
        die('ERROR: YOUTUBE_API_KEY is empty!');
    }

    $videos = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=' . $playlistId . '&key=' . YOUTUBE_API_KEY), true);

    $result = array();
    if (!empty($videos['items'])){
        foreach ($videos['items'] as $video){
            $result[] = array('title' => $video['snippet']['title'], 'id' => $video['snippet']['resourceId']['videoId']);
        }

        return $result;
    }

    return array();
}

function downloadListVideos($id, $playlistName, $mail){
    $videos = getPlaylistVideos($id);
    
    foreach ($videos as $video){
        $success = downloadVideo($video['id'], $playlistName);
        if(!isset($success) || !$success){
            var_dump($video['title']);
            file_put_contents($playlistName . '/err.log', $video['title']."\n\n", FILE_APPEND);
        }
    }
    
    if(is_dir($playlistName)){
        $zip = new Zipper();
        $resZip = $zip->create($playlistName.".zip", $playlistName);
        
        echo $resZip ? 'Zip Created!' : 'Zip not Created!';
        
        $resDel = del($playlistName);
        
        echo $resDel ? 'Dir Deleted!' : 'Dir not Deleted!';
        
        if($resZip){
            $path = $_SERVER['REQUEST_SCHEME'] ."://" . $_SERVER['SERVER_NAME'] . str_replace(basename($_SERVER['SCRIPT_FILENAME']), "", $_SERVER['SCRIPT_NAME']);
            
            $filePath = $path . rawurlencode($playlistName) . ".zip";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: PlaylistDownloader@" . $_SERVER['SERVER_NAME'] . "\r\n";
            $subject = "Your playlist " . $playlistName . " ready to download";
            $body = "<h1>Your playlist " . $playlistName . " ready to download<br><br><a href='" . $filePath . "'>Click here</a><h1>";
            
            mail($mail, $subject, $body, $headers);
        }
    }
    else{
        die('No Video...');
    }
}

function del($path){
    if (is_link($path)) {
        return unlink($path);
    } elseif (is_dir($path)) {
        $objects = scandir($path);
        $ok = true;
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!del($path . '/' . $file)) {
                        $ok = false;
                    }
                }
            }
        }
        return ($ok) ? rmdir($path) : false;
    } elseif (is_file($path)) {
        return unlink($path);
    }
    return false;
}

class Zipper{
    private $zip;

    public function __construct()
    {
        $this->zip = new ZipArchive();
    }

    public function create($filename, $files)
    {
        $res = $this->zip->open($filename, ZipArchive::CREATE);
        if ($res !== true) {
            return false;
        }
        if (is_array($files)) {
            foreach ($files as $f) {
                if (!$this->addFileOrDir($f)) {
                    $this->zip->close();
                    return false;
                }
            }
            $this->zip->close();
            return true;
        } else {
            if ($this->addFileOrDir($files)) {
                $this->zip->close();
                return true;
            }
            return false;
        }
    }

    private function addFileOrDir($filename)
    {
        if (is_file($filename)) {
            return $this->zip->addFile($filename);
        } elseif (is_dir($filename)) {
            return $this->addDir($filename);
        }
        return false;
    }

    private function addDir($path)
    {
        if (!$this->zip->addEmptyDir($path)) {
            return false;
        }
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($path . '/' . $file)) {
                        if (!$this->addDir($path . '/' . $file)) {
                            return false;
                        }
                    } elseif (is_file($path . '/' . $file)) {
                        if (!$this->zip->addFile($path . '/' . $file)) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
}