<?php

//Source: https://github.com/Athlon1600/youtube-downloader

namespace YouTube;

class Browser
{
    protected $storage_dir;
    protected $cookie_file;

    protected $user_agent = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0';

    public function __construct()
    {
        $filename = 'youtube_downloader_cookies.txt';

        $this->storage_dir = sys_get_temp_dir();
        $this->cookie_file = join(DIRECTORY_SEPARATOR, [sys_get_temp_dir(), $filename]);
    }

    public function getCookieFile()
    {
        return $this->cookie_file;
    }

    public function get($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

        //curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function getCached($url)
    {
        $cache_path = sprintf('%s/%s', $this->storage_dir, $this->getCacheKey($url));

        if (file_exists($cache_path)) {

            // unserialize could fail on empty file
            $str = file_get_contents($cache_path);
            return unserialize($str);
        }

        $response = $this->get($url);

        // must not fail
        if ($response) {
            file_put_contents($cache_path, serialize($response));
            return $response;
        }

        return null;
    }

    public function head($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        return http_parse_headers($result);
    }

    // useful for checking for: 429 Too Many Requests
    public function getStatus($url)
    {

    }

    protected function getCacheKey($url)
    {
        return md5($url);
    }
}

class Parser
{
    private $itag_info = array(
        5 => "FLV 400x240",
        6 => "FLV 450x240",
        13 => "3GP Mobile",
        17 => "3GP 144p",
        18 => "MP4 360p",
        22 => "MP4 720p (HD)",
        34 => "FLV 360p",
        35 => "FLV 480p",
        36 => "3GP 240p",
        37 => "MP4 1080",
        38 => "MP4 3072p",
        43 => "WebM 360p",
        44 => "WebM 480p",
        45 => "WebM 720p",
        46 => "WebM 1080p",
        59 => "MP4 480p",
        78 => "MP4 480p",
        82 => "MP4 360p 3D",
        83 => "MP4 480p 3D",
        84 => "MP4 720p 3D",
        85 => "MP4 1080p 3D",
        91 => "MP4 144p",
        92 => "MP4 240p HLS",
        93 => "MP4 360p HLS",
        94 => "MP4 480p HLS",
        95 => "MP4 720p HLS",
        96 => "MP4 1080p HLS",
        100 => "WebM 360p 3D",
        101 => "WebM 480p 3D",
        102 => "WebM 720p 3D",
        120 => "WebM 720p 3D",
        127 => "TS Dash Audio 96kbps",
        128 => "TS Dash Audio 128kbps"
    );

    public function parseItagInfo($itag)
    {
        if (isset($this->itag_detailed[$itag])) {
            return $this->itag_detailed[$itag];
        }

        return 'Unknown';
        // return isset($this->itag_info[$itag]) ? $this->itag_info[$itag] : 'Unknown';
    }

    private $itag_detailed = array(
        5 => 'flv, 240p, video/audio',
        6 => 'flv, 270p, video/audio',
        13 => '3gp, video/audio',
        17 => '3gp, 144p, video/audio',
        18 => 'mp4, 360p, video/audio',
        22 => 'mp4, 720p, video/audio',
        34 => 'flv, 360p, video/audio',
        35 => 'flv, 480p, video/audio',
        36 => '3gp, video/audio',
        37 => 'mp4, 1080p, video/audio',
        38 => 'mp4, 3072p, video/audio',
        43 => 'webm, 360p, video/audio',
        44 => 'webm, 480p, video/audio',
        45 => 'webm, 720p, video/audio',
        46 => 'webm, 1080p, video/audio',
        59 => 'mp4, 480p, video/audio',
        78 => 'mp4, 480p, video/audio',
        82 => 'mp4, 360p, video/audio',
        83 => 'mp4, 480p, video/audio',
        84 => 'mp4, 720p, video/audio',
        85 => 'mp4, 1080p, video/audio',
        100 => 'webm, 360p, video/audio',
        101 => 'webm, 480p, video/audio',
        102 => 'webm, 720p, video/audio',
        91 => 'mp4, 144p, video/audio',
        92 => 'mp4, 240p, video/audio',
        93 => 'mp4, 360p, video/audio',
        94 => 'mp4, 480p, video/audio',
        95 => 'mp4, 720p, video/audio',
        96 => 'mp4, 1080p, video/audio',
        132 => 'mp4, 240p, video/audio',
        151 => 'mp4, 72p, video/audio',
        133 => 'mp4, 240p, video',
        134 => 'mp4, 360p, video',
        135 => 'mp4, 480p, video',
        136 => 'mp4, 720p, video',
        137 => 'mp4, 1080p, video',
        138 => 'mp4, video',
        160 => 'mp4, 144p, video',
        212 => 'mp4, 480p, video',
        264 => 'mp4, 1440p, video',
        298 => 'mp4, 720p, video',
        299 => 'mp4, 1080p, video',
        266 => 'mp4, 2160p, video',
        139 => 'm4a, audio',
        140 => 'm4a, audio',
        141 => 'm4a, audio',
        256 => 'm4a, audio',
        258 => 'm4a, audio',
        325 => 'm4a, audio',
        328 => 'm4a, audio',
        167 => 'webm, 360p, video',
        168 => 'webm, 480p, video',
        169 => 'webm, 720p, video',
        170 => 'webm, 1080p, video',
        218 => 'webm, 480p, video',
        219 => 'webm, 480p, video',
        278 => 'webm, 144p, video',
        242 => 'webm, 240p, video',
        243 => 'webm, 360p, video',
        244 => 'webm, 480p, video',
        245 => 'webm, 480p, video',
        246 => 'webm, 480p, video',
        247 => 'webm, 720p, video',
        248 => 'webm, 1080p, video',
        271 => 'webm, 1440p, video',
        272 => 'webm, 2160p, video',
        302 => 'webm, 720p, video',
        303 => 'webm, 1080p, video',
        308 => 'webm, 1440p, video',
        313 => 'webm, 2160p, video',
        315 => 'webm, 2160p, video',
        171 => 'webm, audio',
        172 => 'webm, audio',
        249 => 'webm, audio',
        250 => 'webm, audio',
        251 => 'webm, audio',
    );
}

class SignatureDecoder
{
    /**
     * Throws both \Exception and \Error
     * https://www.php.net/manual/en/language.errors.php7.php
     *
     * @param $signature
     * @param $js_code
     * @return string
     */
    public function decode($signature, $js_code)
    {
        $func_name = $this->parseFunctionName($js_code);

        // PHP instructions
        $instructions = (array)$this->parseFunctionCode($func_name, $js_code);

        foreach ($instructions as $opt) {

            $command = $opt[0];
            $value = $opt[1];

            if ($command == 'swap') {

                $temp = $signature[0];
                $signature[0] = $signature[$value % strlen($signature)];
                $signature[$value] = $temp;

            } elseif ($command == 'splice') {
                $signature = substr($signature, $value);
            } elseif ($command == 'reverse') {
                $signature = strrev($signature);
            }
        }

        return trim($signature);
    }

    public function parseFunctionName($js_code)
    {
        if (preg_match('@,\s*encodeURIComponent\((\w{2})@is', $js_code, $matches)) {
            $func_name = $matches[1];
            $func_name = preg_quote($func_name);

            return $func_name;

        } else if (preg_match('@\b([a-zA-Z0-9$]{2})\s*=\s*function\(\s*a\s*\)\s*{\s*a\s*=\s*a\.split\(\s*""\s*\)@is', $js_code, $matches)) {
            return preg_quote($matches[1]);
        }

        return null;
    }

    // convert JS code for signature decipher to PHP code
    public function parseFunctionCode($func_name, $player_htmlz)
    {
        // extract code block from that function
        // single quote in case function name contains $dollar sign
        // xm=function(a){a=a.split("");wm.zO(a,47);wm.vY(a,1);wm.z9(a,68);wm.zO(a,21);wm.z9(a,34);wm.zO(a,16);wm.z9(a,41);return a.join("")};
        if (preg_match('/' . $func_name . '=function\([a-z]+\){(.*?)}/', $player_htmlz, $matches)) {

            $js_code = $matches[1];

            // extract all relevant statements within that block
            // wm.vY(a,1);
            if (preg_match_all('/([a-z0-9]{2})\.([a-z0-9]{2})\([^,]+,(\d+)\)/i', $js_code, $matches) != false) {

                // must be identical
                $obj_list = $matches[1];

                //
                $func_list = $matches[2];

                // extract javascript code for each one of those statement functions
                preg_match_all('/(' . implode('|', $func_list) . '):function(.*?)\}/m', $player_htmlz, $matches2, PREG_SET_ORDER);

                $functions = array();

                // translate each function according to its use
                foreach ($matches2 as $m) {

                    if (strpos($m[2], 'splice') !== false) {
                        $functions[$m[1]] = 'splice';
                    } elseif (strpos($m[2], 'a.length') !== false) {
                        $functions[$m[1]] = 'swap';
                    } elseif (strpos($m[2], 'reverse') !== false) {
                        $functions[$m[1]] = 'reverse';
                    }
                }

                // FINAL STEP! convert it all to instructions set
                $instructions = array();

                foreach ($matches[2] as $index => $name) {
                    $instructions[] = array($functions[$name], $matches[3][$index]);
                }

                return $instructions;
            }
        }

        return null;
    }
}

class YouTubeDownloader
{
    protected $client;

    /** @var string */
    protected $error;

    function __construct()
    {
        $this->client = new Browser();
    }

    public function getLastError()
    {
        return $this->error;
    }

    // accepts either raw HTML or url
    // <script src="//s.ytimg.com/yts/jsbin/player-fr_FR-vflHVjlC5/base.js" name="player/base"></script>
    public function getPlayerUrl($video_html)
    {
        $player_url = null;

        // check what player version that video is using
        if (preg_match('@<script\s*src="([^"]+player[^"]+js)@', $video_html, $matches)) {
            $player_url = $matches[1];

            // relative protocol?
            if (strpos($player_url, '//') === 0) {
                $player_url = 'http://' . substr($player_url, 2);
            } elseif (strpos($player_url, '/') === 0) {
                // relative path?
                $player_url = 'http://www.youtube.com' . $player_url;
            }
        }

        return $player_url;
    }

    public function getPlayerCode($player_url)
    {
        $contents = $this->client->getCached($player_url);
        return $contents;
    }

    // extract youtube video_id from any piece of text
    public function extractVideoId($str)
    {
        if (preg_match('/[a-z0-9_-]{11}/i', $str, $matches)) {
            return $matches[0];
        }

        return false;
    }

    /**
     * @param array $links
     * @param string $selector mp4, 360, etc...
     * @return array
     */
    private function selectFirst($links, $selector)
    {
        $result = array();
        $formats = preg_split('/\s*,\s*/', $selector);

        $name = ($links['name'] ?? "video");
        unset($links['name']);

        // has to be in this order
        foreach ($formats as $f) {

            foreach ($links as $l) {

                if (stripos($l['format'], $f) !== false || $f == 'any') {
                    $result[] = $l;
                }
            }
        }

        $result['name'] = $name;

        return $result;
    }

    public function getVideoInfo($url)
    {
        // $this->client->get("https://www.youtube.com/get_video_info?el=embedded&eurl=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3D" . urlencode($video_id) . "&video_id={$video_id}");
    }

    public function getPageHtml($url)
    {
        $video_id = $this->extractVideoId($url);
        return $this->client->get("https://www.youtube.com/watch?v={$video_id}");
    }

    public function getPlayerResponse($page_html)
    {
        if (preg_match('/player_response":"(.*?)","/', $page_html, $matches)) {
            $match = stripslashes($matches[1]);

            $ret = json_decode($match, true);
            return $ret;
        }

        return null;
    }

    // redirector.googlevideo.com
    //$url = preg_replace('@(\/\/)[^\.]+(\.googlevideo\.com)@', '$1redirector$2', $url);
    public function parsePlayerResponse($player_response, $js_code)
    {
        $parser = new Parser();

        try {
            $formats = $player_response['streamingData']['formats'];
            $adaptiveFormats = $player_response['streamingData']['adaptiveFormats'];

            if (!is_array($formats)) {
                $formats = array();
            }

            if (!is_array($adaptiveFormats)) {
                $adaptiveFormats = array();
            }

            $formats_combined = array_merge($formats, $adaptiveFormats);

            // final response
            $return = array();

            foreach ($formats_combined as $item) {
                $cipher = isset($item['cipher']) ? $item['cipher'] : '';
                $itag = $item['itag'];

                // some videos do not need to be decrypted!
                if (isset($item['url'])) {

                    $return[] = array(
                        'url' => $item['url'],
                        'itag' => $itag,
                        'format' => $parser->parseItagInfo($itag)
                    );

                    continue;
                }

                parse_str($cipher, $result);

                $url = $result['url'];
                $sp = $result['sp']; // typically 'sig'
                $signature = $result['s'];

                $decoded_signature = (new SignatureDecoder())->decode($signature, $js_code);

                // redirector.googlevideo.com
                //$url = preg_replace('@(\/\/)[^\.]+(\.googlevideo\.com)@', '$1redirector$2', $url);
                $return[] = array(
                    'url' => $url . '&' . $sp . '=' . $decoded_signature,
                    'itag' => $itag,
                    'format' => $parser->parseItagInfo($itag)
                );
            }

            return $return;

        } catch (\Exception $exception) {
            // do nothing
        } catch (\Throwable $throwable) {
            // do nothing
        }

        return null;
    }

    public function getDownloadLinks($video_id, $selector = false)
    {
        $this->error = null;

        $page_html = $this->getPageHtml($video_id);

        if (strpos($page_html, 'We have been receiving a large volume of requests') !== false ||
            strpos($page_html, 'systems have detected unusual traffic') !== false) {

            $this->error = 'HTTP 429: Too many requests.';

            return array();
        }

        // get JSON encoded parameters that appear on video pages
        $json = $this->getPlayerResponse($page_html);
        $name = $json['videoDetails']['title'] ?? "video";

        // get player.js location that holds signature function
        $url = $this->getPlayerUrl($page_html);
        $js = $this->getPlayerCode($url);

        $result = $this->parsePlayerResponse($json, $js);

        // if error happens
        if (!is_array($result)) {
            return array();
        }

        $result['name'] = $name;

        // do we want all links or just select few?
        if ($selector) {
            return $this->selectFirst($result, $selector);
        }

        return $result;
    }
}

class YoutubeStreamer
{
    // 4096
    protected $buffer_size = 256 * 1024;

    protected $headers = array();
    protected $headers_sent = false;

    protected $debug = false;

    protected function sendHeader($header)
    {
        if ($this->debug) {
            var_dump($header);
        } else {
            header($header);
        }
    }

    public function headerCallback($ch, $data)
    {
        // this should be first line
        if (preg_match('/HTTP\/[\d.]+\s*(\d+)/', $data, $matches)) {
            $status_code = $matches[1];

            if ($status_code == 200 || $status_code == 206) {
                $this->headers_sent = true;
                $this->sendHeader(rtrim($data));
            }

        } else {

            // only headers we wish to forward back to the client
            $forward = array('content-type', 'content-length', 'accept-ranges', 'content-range');

            $parts = explode(':', $data, 2);

            if ($this->headers_sent && count($parts) == 2 && in_array(trim(strtolower($parts[0])), $forward)) {
                $this->sendHeader(rtrim($data));
            }
        }

        return strlen($data);
    }

    public function bodyCallback($ch, $data)
    {
        if (true) {
            echo $data;
            flush();
        }

        return strlen($data);
    }

    public function stream($url)
    {
        $ch = curl_init();

        $headers = array();
        $headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0';

        if (isset($_SERVER['HTTP_RANGE'])) {
            $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
        }

        // otherwise you get weird "OpenSSL SSL_read: No error"
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_BUFFERSIZE, $this->buffer_size);
        curl_setopt($ch, CURLOPT_URL, $url);

        //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // we deal with this ourselves
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$this, 'headerCallback']);

        // if response is empty - this never gets called
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'bodyCallback']);

        $ret = curl_exec($ch);

        // TODO: $this->logError($ch);
        $error = ($ret === false) ? sprintf('curl error: %s, num: %s', curl_error($ch), curl_errno($ch)) : null;

        curl_close($ch);

        // if we are still here by now, then all must be okay
        return true;
    }
}
