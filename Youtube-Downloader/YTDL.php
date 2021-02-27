<?php

//Source: https://github.com/Athlon1600/youtube-downloader



/*************************************************************
*
*  File Path: /src/BrowserClient.php
*
************************************************************/


//namespace Curl;

class BrowserClient extends Client
{
    // HTTP headers that uniquely identify this browser such as User-Agent
    protected $headers = array(
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Encoding' => 'gzip, deflate',
        'Accept-Language' => 'en-US,en;q=0.5',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:71.0) Gecko/20100101 Firefox/71.0'
    );

    protected $options = array(
        CURLOPT_ENCODING => '', // apparently curl will decode gzip automatically when this is empty
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15
    );

    protected static $_storage_dir;

    /** @var string Where the cookies are stored */
    protected $cookie_file;

    public function __construct()
    {
        parent::__construct();

        $cookie_file = join(DIRECTORY_SEPARATOR, [static::getStorageDirectory(), "BrowserClient"]);
        $this->setCookieFile($cookie_file);
    }

    protected function getStorageDirectory()
    {
        return static::$_storage_dir ? static::$_storage_dir : sys_get_temp_dir();
    }

    // TODO: make this apply across all previous browser sessions too
    public static function setStorageDirectory($path)
    {
        static::$_storage_dir = $path;
    }

    /**
     * Format ip:port or null to use direct connection
     * @param $proxy_server
     */
    public function setProxy($proxy_server)
    {
        $this->options[CURLOPT_PROXY] = $proxy_server;
    }

    public function getProxy()
    {
        return !empty($this->options[CURLOPT_PROXY]) ? $this->options[CURLOPT_PROXY] : null;
    }

    public function setCookieFile($cookie_file)
    {
        $this->cookie_file = $cookie_file;

        // read & write cookies
        $this->options[CURLOPT_COOKIEJAR] = $cookie_file;
        $this->options[CURLOPT_COOKIEFILE] = $cookie_file;
    }

    public function getCookieFile()
    {
        return $this->cookie_file;
    }

    // Manual alternative for: curl_getinfo($ch, CURLINFO_COOKIELIST));
    public function getCookies()
    {
        $contents = @file_get_contents($this->getCookieFile());
        return $contents;
    }

    public function setCookies($cookies)
    {
        return @file_put_contents($this->getCookieFile(), $cookies) !== false;
    }

    public function clearCookies()
    {
        @unlink($this->getCookieFile());
    }
}



/*************************************************************
*
*  File Path: /src/Client.php
*
************************************************************/


//namespace Curl;

class Client
{
    // Default HTTP headers
    protected $headers = array();

    // Default cURL options
    protected $options = array();

    public function __construct()
    {
        // do nothing
    }

    protected function buildUrl($uri, $params = array())
    {
        if ($params) {
            return $uri . '?' . http_build_query($params);
        }

        return $uri;
    }

    protected function getCombinedHeaders($headers)
    {
        $headers = $this->headers + $headers;

        array_walk($headers, function (&$item, $key) {
            $item = "{$key}: {$item}";
        });

        return array_values($headers);
    }

    public function request($method, $uri, $params = array(), $headers = array(), $curl_options = array())
    {
        $ch = curl_init();

        if ($method == 'GET') {
            curl_setopt($ch, CURLOPT_URL, $this->buildUrl($uri, $params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $uri);

            $post_data = is_array($params) ? http_build_query($params) : $params;

            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getCombinedHeaders($headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // last chance to override
        curl_setopt_array($ch, is_array($curl_options) ? ($curl_options + $this->options) : $this->options);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        $response = new Response();
        $response->status = $info ? $info['http_code'] : 0;
        $response->body = $result;
        $response->error = curl_error($ch);
        $response->info = new CurlInfo($info);

        curl_close($ch);

        return $response;
    }

    public function get($uri, $params = array(), $headers = array())
    {
        return $this->request('GET', $uri, $params, $headers);
    }

    public function post($uri, $params = array(), $headers = array())
    {
        return $this->request('POST', $uri, $params, $headers);
    }
}



/*************************************************************
*
*  File Path: /src/CurlInfo.php
*
************************************************************/


//namespace Curl;

/** @noinspection SpellCheckingInspection */

/**
 * @property-read string $url
 * @property-read string $content_type
 * @property-read int $http_code
 * @property-read int $header_size
 * @property-read int $request_size
 * @property-read int $filetime
 * @property-read int $ssl_verify_result
 * @property-read int $redirect_count
 *
 * @property-read double $total_time
 * @property-read double $namelookup_time
 * @property-read double $connect_time
 * @property-read double $pretransfer_time
 *
 * @property-read int $size_upload
 * @property-read int $size_download
 *
 * @property-read int $speed_download
 * @property-read int $speed_upload
 *
 * @property-read int $download_content_length
 * @property-read int $upload_content_length
 *
 * @property-read double $starttransfer_time
 * @property-read double $redirect_time
 *
 * @property-read array $certinfo
 *
 * @property-read string $primary_ip
 * @property-read int $primary_port
 *
 * @property-read string $local_ip
 * @property-read int $local_port
 *
 * @property-read string $redirect_url
 */
class CurlInfo implements \ArrayAccess
{
    protected $info;

    public function __construct($info)
    {
        $this->info = $info;
    }

    public function toArray()
    {
        return $this->info;
    }

    public function __get($prop)
    {
        return $this->offsetGet($prop);
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->info);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->info[$offset];
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        // READONLY
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        // READONLY
    }
}


/*************************************************************
*
*  File Path: /src/Response.php
*
************************************************************/


//namespace Curl;

// https://github.com/php-fig/http-message/blob/master/src/ResponseInterface.php#L11
class Response
{
    public $status;
    public $body;

    // usually empty
    public $error;

    /** @var CurlInfo */
    public $info;
}


// *******************************************************************************************************************************************************

/*************************************************************
*
*  File Path: /src/Browser.php
*
************************************************************/


//namespace YouTube;

//use Curl\BrowserClient;
//use YouTube\Utils\Utils;

class Browser extends BrowserClient
{
    public function setUserAgent($agent)
    {
        $this->headers['User-Agent'] = $agent;
    }

    public function getUserAgent()
    {
        return Utils::arrayGet($this->headers, 'User-Agent');
    }

    public function followRedirects($enabled)
    {
        $this->options[CURLOPT_FOLLOWLOCATION] = $enabled ? 1 : 0;
        return $this;
    }

    public function cachedGet($url)
    {
        $cache_path = sprintf('%s/%s', static::getStorageDirectory(), $this->getCacheKey($url));

        if (file_exists($cache_path)) {

            // unserialize could fail on empty file
            $str = file_get_contents($cache_path);
            return unserialize($str);
        }

        $response = $this->get($url);

        // cache only if successful
        if (empty($response->error)) {
            file_put_contents($cache_path, serialize($response));
        }

        return $response;
    }

    protected function getCacheKey($url)
    {
        return md5($url) . '_v3';
    }
}


/*************************************************************
*
*  File Path: /src/DownloadOptions.php
*
************************************************************/


//namespace YouTube;

//use YouTube\Models\SplitStream;
//use YouTube\Models\StreamFormat;
//use YouTube\Models\VideoDetails;
//use YouTube\Utils\Utils;

class DownloadOptions
{
    /** @var StreamFormat[] $formats */
    private $formats;

    /** @var VideoDetails|null */
    private $info;

    public function __construct($formats, $info = null)
    {
        $this->formats = $formats;
        $this->info = $info;
    }

    /**
     * @return StreamFormat[]
     */
    public function getAllFormats()
    {
        return $this->formats;
    }

    /**
     * @return VideoDetails|null
     */
    public function getInfo()
    {
        return $this->info;
    }

    // Will not include Videos with Audio
    public function getVideoFormats()
    {
        return Utils::arrayFilterReset($this->getAllFormats(), function ($format) {
            /** @var $format StreamFormat */
            return strpos($format->mimeType, 'video') === 0 && empty($format->audioQuality);
        });
    }

    public function getAudioFormats()
    {
        return Utils::arrayFilterReset($this->getAllFormats(), function ($format) {
            /** @var $format StreamFormat */
            return strpos($format->mimeType, 'audio') === 0;
        });
    }

    public function getCombinedFormats()
    {
        return Utils::arrayFilterReset($this->getAllFormats(), function ($format) {
            /** @var $format StreamFormat */
            return strpos($format->mimeType, 'video') === 0 && !empty($format->audioQuality);
        });
    }

    /**
     * @return StreamFormat|null
     */
    public function getFirstCombinedFormat()
    {
        $combined = $this->getCombinedFormats();
        return count($combined) ? $combined[0] : null;
    }

    protected function getLowToHighVideoFormats()
    {
        $copy = array_values($this->getVideoFormats());

        usort($copy, function ($a, $b) {

            /** @var StreamFormat $a */
            /** @var StreamFormat $b */

            return $a->height - $b->height;
        });

        return $copy;
    }

    protected function getLowToHighAudioFormats()
    {
        $copy = array_values($this->getAudioFormats());

        // just assume higher filesize => higher quality...
        usort($copy, function ($a, $b) {

            /** @var StreamFormat $a */
            /** @var StreamFormat $b */

            return $a->contentLength - $b->contentLength;
        });

        return $copy;
    }

    // Combined using: ffmpeg -i video.mp4 -i audio.mp3 output.mp4
    public function getSplitFormats($quality = null)
    {
        // sort formats by quality in desc, and high = first, medium = middle, low = last
        $videos = $this->getLowToHighVideoFormats();
        $audio = $this->getLowToHighAudioFormats();

        if ($quality == 'high' || $quality == 'best') {

            return new SplitStream([
                'video' => $videos[count($videos) - 1],
                'audio' => $audio[count($audio) - 1]
            ]);

        } else if ($quality == 'low' || $quality == 'worst') {

            return new SplitStream([
                'video' => $videos[0],
                'audio' => $audio[0]
            ]);
        }

        // something in between!
        return new SplitStream([
            'video' => $videos[floor(count($videos) / 2)],
            'audio' => $audio[floor(count($audio) / 2)]
        ]);
    }
}


/*************************************************************
*
*  File Path: /src/Exception/TooManyRequestsException.php
*
************************************************************/


//namespace YouTube\Exception;

//use YouTube\Responses\WatchVideoPage;

class TooManyRequestsException extends YouTubeException
{
    protected $page;

    public function __construct(WatchVideoPage $page)
    {
        parent::__construct(get_class($this), 429, null);

        $this->page = $page;
    }

    /**
     * @return WatchVideoPage
     */
    public function getPage()
    {
        return $this->page;
    }
}


/*************************************************************
*
*  File Path: /src/Exception/VideoPlayerNotFoundException.php
*
************************************************************/


//namespace YouTube\Exception;

class VideoPlayerNotFoundException extends YouTubeException
{

}


/*************************************************************
*
*  File Path: /src/Exception/YouTubeException.php
*
************************************************************/


//namespace YouTube\Exception;

class YouTubeException extends \Exception
{

}


/*************************************************************
*
*  File Path: /src/Models/AbstractModel.php
*
************************************************************/


//namespace YouTube\Models;

abstract class AbstractModel
{
    public function __construct($array)
    {
        if (is_array($array)) {
            $this->fillFromArray($array);
        }
    }

    public static function fromArray($array)
    {
        return new static($array);
    }

    private function fillFromArray($array)
    {
        foreach ($array as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }
}


/*************************************************************
*
*  File Path: /src/Models/SplitStream.php
*
************************************************************/


//namespace YouTube\Models;

class SplitStream extends AbstractModel
{
    /** @var StreamFormat */
    public $video;
    /** @var StreamFormat */
    public $audio;
}


/*************************************************************
*
*  File Path: /src/Models/StreamFormat.php
*
************************************************************/


//namespace YouTube\Models;

class StreamFormat extends AbstractModel
{
    public $itag;
    public $mimeType;
    public $width;
    public $height;
    public $contentLength;
    public $quality;
    public $qualityLabel;
    public $audioQuality;
    public $audioSampleRate;
    public $url;
    public $signatureCipher;

    public function getCleanMimeType()
    {
        return trim(preg_replace('/;.*/', '', $this->mimeType));
    }
}


/*************************************************************
*
*  File Path: /src/Models/VideoDetails.php
*
************************************************************/


//namespace YouTube\Models;

//use YouTube\Utils\Utils;

class VideoDetails
{
    protected $videoDetails = array();

    private function __construct($videoDetails)
    {
        $this->videoDetails = $videoDetails;
    }

    /**
     * From `videoDetails` array that appears inside JSON on /watch or /get_video_info pages
     * @param $array
     * @return static
     */
    public static function fromPlayerResponseArray($array)
    {
        return new static(Utils::arrayGet($array, 'videoDetails'));
    }

    public function getId()
    {
        return Utils::arrayGet($this->videoDetails, 'videoId');
    }

    public function getTitle()
    {
        return Utils::arrayGet($this->videoDetails, 'title');
    }

    public function getKeywords()
    {
        return Utils::arrayGet($this->videoDetails, 'keywords');
    }

    public function getShortDescription()
    {
        return Utils::arrayGet($this->videoDetails, 'shortDescription');
    }

    public function getViewCount()
    {
        return Utils::arrayGet($this->videoDetails, 'viewCount');
    }
}


/*************************************************************
*
*  File Path: /src/Responses/GetVideoInfo.php
*
************************************************************/


//namespace YouTube\Responses;

//use YouTube\Utils\Utils;

class GetVideoInfo extends HttpResponse
{
    public function getJson()
    {
        return Utils::parseQueryString($this->getResponseBody());
    }

    public function isError()
    {
        return Utils::arrayGet($this->getJson(), 'errorcode') !== null;
    }

    /**
     * About same as `player_response` that appears on video pages.
     * @return array
     */
    public function getPlayerResponse()
    {
        $playerResponse = Utils::arrayGet($this->getJson(), 'player_response');
        return json_decode($playerResponse, true);
    }
}


/*************************************************************
*
*  File Path: /src/Responses/HttpResponse.php
*
************************************************************/


//namespace YouTube\Responses;

//use Curl\Response;

abstract class HttpResponse
{
    /**
     * @var Response
     */
    private $response;

    // Will become null if response contents cannot be decoded from JSON
    private $json;

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->json = json_decode($response->body, true);
    }

    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return string|null
     */
    public function getResponseBody()
    {
        return $this->response->body;
    }

    /**
     * @return array|null
     */
    public function getJson()
    {
        return $this->json;
    }

    public function isStatusOkay()
    {
        return $this->getResponse()->status == 200;
    }
}



/*************************************************************
*
*  File Path: /src/Responses/VideoPlayerJs.php
*
************************************************************/


//namespace YouTube\Responses;

class VideoPlayerJs extends HttpResponse
{
}


/*************************************************************
*
*  File Path: /src/Responses/WatchVideoPage.php
*
************************************************************/


//namespace YouTube\Responses;

//use YouTube\Utils\Utils;

class WatchVideoPage extends HttpResponse
{
    public function isTooManyRequests()
    {
        return
            strpos($this->getResponseBody(), 'We have been receiving a large volume of requests') !== false ||
            strpos($this->getResponseBody(), 'systems have detected unusual traffic') !== false ||
            strpos($this->getResponseBody(), '/recaptcha/') !== false;
    }

    public function hasPlayableVideo()
    {
        $playerResponse = $this->getPlayerResponse();
        $playabilityStatus = Utils::arrayGet($playerResponse, 'playabilityStatus.status');

        return $this->getResponse()->status == 200 && $playabilityStatus == 'OK';
    }

    /**
     * Look for a player script URL. E.g:
     * <script src="//s.ytimg.com/yts/jsbin/player-fr_FR-vflHVjlC5/base.js" name="player/base"></script>
     *
     * @return string|null
     */
    public function getPlayerScriptUrl()
    {
        // check what player version that video is using
        if (preg_match('@<script\s*src="([^"]+player[^"]+js)@', $this->getResponseBody(), $matches)) {
            return Utils::relativeToAbsoluteUrl($matches[1], 'https://www.youtube.com');
        }

        return null;
    }

    public function getPlayerResponse()
    {
        // $re = '/ytplayer.config\s*=\s*([^\n]+});ytplayer/i';
        // $re = '/player_response":"(.*?)\"}};/';
        $re = '/ytInitialPlayerResponse\s*=\s*({.+?})\s*;/i';

        if (preg_match($re, $this->getResponseBody(), $matches)) {
            $match = $matches[1];
            return json_decode($match, true);
        }

        return array();
    }
}


/*************************************************************
*
*  File Path: /src/SignatureDecoder.php
*
************************************************************/


//namespace YouTube;

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

        } else if (preg_match('@(?:\b|[^a-zA-Z0-9$])([a-zA-Z0-9$]{2})\s*=\s*function\(\s*a\s*\)\s*{\s*a\s*=\s*a\.split\(\s*""\s*\)@is', $js_code, $matches)) {
            return preg_quote($matches[1]);
        }

        return null;
    }

    // convert JS code for signature decipher to PHP code
    public function parseFunctionCode($func_name, $player_html)
    {
        // extract code block from that function
        // single quote in case function name contains $dollar sign
        // xm=function(a){a=a.split("");wm.zO(a,47);wm.vY(a,1);wm.z9(a,68);wm.zO(a,21);wm.z9(a,34);wm.zO(a,16);wm.z9(a,41);return a.join("")};
        if (preg_match('/' . $func_name . '=function\([a-z]+\){(.*?)}/', $player_html, $matches)) {

            $js_code = $matches[1];

            // extract all relevant statements within that block
            // wm.vY(a,1);
            if (preg_match_all('/([a-z0-9$]{2})\.([a-z0-9]{2})\([^,]+,(\d+)\)/i', $js_code, $matches) != false) {

                // wm
                $obj_list = $matches[1];

                // vY
                $func_list = $matches[2];

                // extract javascript code for each one of those statement functions
                preg_match_all('/(' . implode('|', $func_list) . '):function(.*?)\}/m', $player_html, $matches2, PREG_SET_ORDER);

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



/*************************************************************
*
*  File Path: /src/Utils/ITagUtils.php
*
************************************************************/


//namespace YouTube\Utils;

class ITagUtils
{
    public static function downloadFormats()
    {
        $data = file_get_contents("https://raw.githubusercontent.com/ytdl-org/youtube-dl/master/youtube_dl/extractor/youtube.py");

        // https://github.com/ytdl-org/youtube-dl/blob/master/youtube_dl/extractor/youtube.py#L429
        if (preg_match('/_formats = ({(.*?)})\s*_/s', $data, $matches)) {

            $json = $matches[1];

            // only "double" quotes are valid in JSON
            $json = str_replace("'", "\"", $json);

            // remove comments
            $json = preg_replace('/\s*#(.*)/', '', $json);

            // remove comma from last JSON item
            $json = preg_replace('/,\s*}/', '}', $json);

            return json_decode($json, true);
        }

        return array();
    }

    public static function transformFormats($formats)
    {
        $results = [];

        foreach ($formats as $itag => $format) {

            $temp = [];

            if (!empty($format['ext'])) {
                $temp[] = $format['ext'];
            }

            if (!empty($format['vcodec'])) {
                $temp[] = 'video';
            }

            if (!empty($format['height'])) {
                $temp[] = $format['height'] . 'p';
            }

            if (!empty($format['acodec']) && $format['acodec'] !== 'none') {
                $temp[] = 'audio';
            }

            $results[$itag] = implode(', ', $temp);
        }

        return $results;
    }

    public static function parseItagInfo($itag)
    {
        if (isset(static::$itag_detailed[$itag])) {
            return static::$itag_detailed[$itag];
        }

        return 'Unknown';
    }

    // itag info does not change frequently, that is why we cache it here as a plain static array
    private static $itag_detailed = array(
        5 => 'flv, video, 240p, audio',
        6 => 'flv, video, 270p, audio',
        13 => '3gp, video, audio',
        17 => '3gp, video, 144p, audio',
        18 => 'mp4, video, 360p, audio',
        22 => 'mp4, video, 720p, audio',
        34 => 'flv, video, 360p, audio',
        35 => 'flv, video, 480p, audio',
        36 => '3gp, video, audio',
        37 => 'mp4, video, 1080p, audio',
        38 => 'mp4, video, 3072p, audio',
        43 => 'webm, video, 360p, audio',
        44 => 'webm, video, 480p, audio',
        45 => 'webm, video, 720p, audio',
        46 => 'webm, video, 1080p, audio',
        59 => 'mp4, video, 480p, audio',
        78 => 'mp4, video, 480p, audio',
        82 => 'mp4, video, 360p, audio',
        83 => 'mp4, video, 480p, audio',
        84 => 'mp4, video, 720p, audio',
        85 => 'mp4, video, 1080p, audio',
        100 => 'webm, video, 360p, audio',
        101 => 'webm, video, 480p, audio',
        102 => 'webm, video, 720p, audio',
        91 => 'mp4, video, 144p, audio',
        92 => 'mp4, video, 240p, audio',
        93 => 'mp4, video, 360p, audio',
        94 => 'mp4, video, 480p, audio',
        95 => 'mp4, video, 720p, audio',
        96 => 'mp4, video, 1080p, audio',
        132 => 'mp4, video, 240p, audio',
        151 => 'mp4, video, 72p, audio',
        133 => 'mp4, video, 240p',
        134 => 'mp4, video, 360p',
        135 => 'mp4, video, 480p',
        136 => 'mp4, video, 720p',
        137 => 'mp4, video, 1080p',
        138 => 'mp4, video',
        160 => 'mp4, video, 144p',
        212 => 'mp4, video, 480p',
        264 => 'mp4, video, 1440p',
        298 => 'mp4, video, 720p',
        299 => 'mp4, video, 1080p',
        266 => 'mp4, video, 2160p',
        139 => 'm4a, audio',
        140 => 'm4a, audio',
        141 => 'm4a, audio',
        256 => 'm4a, audio',
        258 => 'm4a, audio',
        325 => 'm4a, audio',
        328 => 'm4a, audio',
        167 => 'webm, video, 360p',
        168 => 'webm, video, 480p',
        169 => 'webm, video, 720p',
        170 => 'webm, video, 1080p',
        218 => 'webm, video, 480p',
        219 => 'webm, video, 480p',
        278 => 'webm, video, 144p',
        242 => 'webm, video, 240p',
        243 => 'webm, video, 360p',
        244 => 'webm, video, 480p',
        245 => 'webm, video, 480p',
        246 => 'webm, video, 480p',
        247 => 'webm, video, 720p',
        248 => 'webm, video, 1080p',
        271 => 'webm, video, 1440p',
        272 => 'webm, video, 2160p',
        302 => 'webm, video, 720p',
        303 => 'webm, video, 1080p',
        308 => 'webm, video, 1440p',
        313 => 'webm, video, 2160p',
        315 => 'webm, video, 2160p',
        171 => 'webm, audio',
        172 => 'webm, audio',
        249 => 'webm, audio',
        250 => 'webm, audio',
        251 => 'webm, audio',
        394 => 'video',
        395 => 'video',
        396 => 'video',
        397 => 'video',
    );
}


/*************************************************************
*
*  File Path: /src/Utils/SerializationUtils.php
*
************************************************************/


//namespace YouTube\Utils;

//use YouTube\Models\StreamFormat;
//use YouTube\DownloadOptions;

class SerializationUtils
{
    public static function optionsToArray(DownloadOptions $downloadOptions)
    {
        return array_map(function (StreamFormat $link) {
            return $link->toArray();
        }, $downloadOptions->getAllFormats());
    }

    public static function optionsFromArray($array)
    {
        $links = array();

        foreach ($array as $item) {
            $links[] = new StreamFormat($item);
        }

        return new DownloadOptions($links);
    }

    public static function optionsFromFile($path)
    {
        $contents = @file_get_contents($path);

        if ($contents) {
            $json = json_decode($contents, true);

            if ($json) {
                return self::optionsFromArray($json);
            }
        }

        return null;
    }
}


/*************************************************************
*
*  File Path: /src/Utils/Utils.php
*
************************************************************/


//namespace YouTube\Utils;

class Utils
{
    /**
     * Extract youtube video_id from any piece of text
     * @param $str
     * @return string
     */
    public static function extractVideoId($str)
    {
        if (preg_match('/[a-z0-9_-]{11}/i', $str, $matches)) {
            return $matches[0];
        }

        return false;
    }

    public static function arrayGet($array, $key, $default = null)
    {
        foreach (explode('.', $key) as $segment) {

            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                $array = $default;
                break;
            }
        }

        return $array;
    }

    public static function arrayFilterReset($array, $callback)
    {
        return array_values(array_filter($array, $callback));
    }

    /**
     * @param $string
     * @return mixed
     */
    public static function parseQueryString($string)
    {
        $result = null;
        parse_str($string, $result);
        return $result;
    }

    public static function relativeToAbsoluteUrl($url, $domain)
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $scheme = $scheme ? $scheme : 'http';

        // relative protocol?
        if (strpos($url, '//') === 0) {
            return $scheme . '://' . substr($url, 2);
        } elseif (strpos($url, '/') === 0) {
            // relative path?
            return $domain . $url;
        }

        return $url;
    }
}


/*************************************************************
*
*  File Path: /src/YouTubeDownloader.php
*
************************************************************/


//namespace YouTube;

//use YouTube\Models\StreamFormat;
//use YouTube\Exception\TooManyRequestsException;
//use YouTube\Exception\VideoPlayerNotFoundException;
//use YouTube\Exception\YouTubeException;
//use YouTube\Models\VideoDetails;
//use YouTube\Responses\GetVideoInfo;
//use YouTube\Responses\VideoPlayerJs;
//use YouTube\Responses\WatchVideoPage;
//use YouTube\Utils\Utils;

class YouTubeDownloader
{
    protected $client;

    function __construct()
    {
        $this->client = new Browser();
    }

    public function getBrowser()
    {
        return $this->client;
    }

    /**
     * @param $query
     * @return array
     */
    public function getSearchSuggestions($query)
    {
        $query = rawurlencode($query);

        $response = $this->client->get('http://suggestqueries.google.com/complete/search?client=firefox&ds=yt&q=' . $query);
        $json = json_decode($response->body, true);

        if (is_array($json) && count($json) >= 2) {
            return $json[1];
        }

        return [];
    }

    public function getVideoInfo($video_id)
    {
        $video_id = Utils::extractVideoId($video_id);

        $response = $this->client->get("https://www.youtube.com/get_video_info?" . http_build_query([
                'video_id' => $video_id,
                'eurl' => 'https://youtube.googleapis.com/v/' . $video_id,
                'el' => 'embedded' // or detailpage. default: embedded, will fail if video is not embeddable
            ]));

        return new GetVideoInfo($response);
    }

    public function getPage($url)
    {
        $video_id = Utils::extractVideoId($url);

        // exact params as used by youtube-dl... must be there for a reason
        $response = $this->client->get("https://www.youtube.com/watch?" . http_build_query([
                'v' => $video_id,
                'gl' => 'US',
                'hl' => 'en',
                'has_verified' => 1,
                'bpctr' => 9999999999
            ]));

        return new WatchVideoPage($response);
    }

    /**
     * To parse the links for the video we need two things:
     * contents of `player_response` JSON object that appears on video pages
     * contents of player.js script file that's included inside video pages
     *
     * @param array $player_response
     * @param VideoPlayerJs $player
     * @return array
     */
    public function parseLinksFromPlayerResponse($player_response, VideoPlayerJs $player)
    {
        $js_code = $player->getResponseBody();

        $formats = Utils::arrayGet($player_response, 'streamingData.formats', []);

        // video only or audio only streams
        $adaptiveFormats = Utils::arrayGet($player_response, 'streamingData.adaptiveFormats', []);

        $formats_combined = array_merge($formats, $adaptiveFormats);

        // final response
        $return = array();

        foreach ($formats_combined as $format) {

            // appear as either "cipher" or "signatureCipher"
            $cipher = Utils::arrayGet($format, 'cipher', Utils::arrayGet($format, 'signatureCipher', ''));

            // some videos do not need to be decrypted!
            if (isset($format['url'])) {
                $return[] = new StreamFormat($format);
                continue;
            }

            $cipherArray = Utils::parseQueryString($cipher);

            $url = Utils::arrayGet($cipherArray, 'url');
            $sp = Utils::arrayGet($cipherArray, 'sp'); // used to be 'sig'
            $signature = Utils::arrayGet($cipherArray, 's');

            $decoded_signature = (new SignatureDecoder())->decode($signature, $js_code);

            $decoded_url = $url . '&' . $sp . '=' . $decoded_signature;

            $streamUrl = new StreamFormat($format);
            $streamUrl->url = $decoded_url;

            $return[] = $streamUrl;
        }

        return $return;
    }

    /**
     * @param $video_id
     * @param array $options
     * @return DownloadOptions
     * @throws TooManyRequestsException
     * @throws YouTubeException
     */
    public function getDownloadLinks($video_id, $options = array())
    {
        $page = $this->getPage($video_id);

        if ($page->isTooManyRequests()) {
            throw new TooManyRequestsException($page);
        } else if (!$page->isStatusOkay()) {
            throw new YouTubeException('Video not found');
        }

        // get JSON encoded parameters that appear on video pages
        $player_response = $page->getPlayerResponse();

        // it may ask you to "Sign in to confirm your age"
        // we can bypass that by querying /get_video_info
        if (!$page->hasPlayableVideo()) {
            $player_response = $this->getVideoInfo($video_id)->getPlayerResponse();
        }

        if (empty($player_response)) {
            throw new VideoPlayerNotFoundException();
        }

        // get player.js location that holds signature function
        $player_url = $page->getPlayerScriptUrl();
        $response = $this->getBrowser()->cachedGet($player_url);
        $player = new VideoPlayerJs($response);

        $links = $this->parseLinksFromPlayerResponse($player_response, $player);

        // since we already have that information anyways...
        $info = VideoDetails::fromPlayerResponseArray($player_response);

        return new DownloadOptions($links, $info);
    }
}



/*************************************************************
*
*  File Path: /src/YouTubeStreamer.php
*
************************************************************/


//namespace YouTube;

class YouTubeStreamer
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

            // if Forbidden or Not Found -> those are "valid" statuses too
            if ($status_code == 200 || $status_code == 206 || $status_code == 403 || $status_code == 404) {
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
