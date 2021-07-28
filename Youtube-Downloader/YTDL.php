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

/*************************************************************
*
*  File Path: /src/ContentTypes.php
*
************************************************************/


//namespace CurlDownloader;

class ContentTypes
{
    // sourced from: https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
    public static $map = array(
        'application/andrew-inset' => 'ez',
        'application/applixware' => 'aw',
        'application/atom+xml' => 'atom',
        'application/atomcat+xml' => 'atomcat',
        'application/atomsvc+xml' => 'atomsvc',
        'application/ccxml+xml' => 'ccxml',
        'application/cdmi-capability' => 'cdmia',
        'application/cdmi-container' => 'cdmic',
        'application/cdmi-domain' => 'cdmid',
        'application/cdmi-object' => 'cdmio',
        'application/cdmi-queue' => 'cdmiq',
        'application/cu-seeme' => 'cu',
        'application/davmount+xml' => 'davmount',
        'application/docbook+xml' => 'dbk',
        'application/dssc+der' => 'dssc',
        'application/dssc+xml' => 'xdssc',
        'application/ecmascript' => 'ecma',
        'application/emma+xml' => 'emma',
        'application/epub+zip' => 'epub',
        'application/exi' => 'exi',
        'application/font-tdpfr' => 'pfr',
        'application/gml+xml' => 'gml',
        'application/gpx+xml' => 'gpx',
        'application/gxf' => 'gxf',
        'application/hyperstudio' => 'stk',
        'application/inkml+xml' => 'ink',
        'application/ipfix' => 'ipfix',
        'application/java-archive' => 'jar',
        'application/java-serialized-object' => 'ser',
        'application/java-vm' => 'class',
        'application/javascript' => 'js',
        'application/json' => 'json',
        'application/jsonml+json' => 'jsonml',
        'application/lost+xml' => 'lostxml',
        'application/mac-binhex40' => 'hqx',
        'application/mac-compactpro' => 'cpt',
        'application/mads+xml' => 'mads',
        'application/marc' => 'mrc',
        'application/marcxml+xml' => 'mrcx',
        'application/mathematica' => 'ma',
        'application/mathml+xml' => 'mathml',
        'application/mbox' => 'mbox',
        'application/mediaservercontrol+xml' => 'mscml',

        'application/metalink4+xml' => 'meta4',
        'application/mets+xml' => 'mets',
        'application/mods+xml' => 'mods',
        'application/mp21' => 'm21',
        'application/mp4' => 'mp4s',
        'application/msword' => 'doc',
        'application/mxf' => 'mxf',
        'application/octet-stream' => 'bin',
        'application/oda' => 'oda',
        'application/oebps-package+xml' => 'opf',
        'application/ogg' => 'ogx',
        'application/omdoc+xml' => 'omdoc',
        'application/onenote' => 'onetoc',
        'application/oxps' => 'oxps',
        'application/patch-ops-error+xml' => 'xer',
        'application/pdf' => 'pdf',
        'application/pgp-encrypted' => 'pgp',
        'application/pgp-signature' => 'asc',
        'application/pics-rules' => 'prf',
        'application/pkcs10' => 'p10',
        'application/pkcs7-mime' => 'p7m',
        'application/pkcs7-signature' => 'p7s',
        'application/pkcs8' => 'p8',
        'application/pkix-attr-cert' => 'ac',
        'application/pkix-cert' => 'cer',
        'application/pkix-crl' => 'crl',
        'application/pkix-pkipath' => 'pkipath',
        'application/pkixcmp' => 'pki',
        'application/pls+xml' => 'pls',
        'application/postscript' => 'ai',
        'application/prs.cww' => 'cww',
        'application/pskc+xml' => 'pskcxml',
        'application/rdf+xml' => 'rdf',
        'application/reginfo+xml' => 'rif',
        'application/relax-ng-compact-syntax' => 'rnc',
        'application/resource-lists+xml' => 'rl',
        'application/resource-lists-diff+xml' => 'rld',
        'application/rls-services+xml' => 'rs',
        'application/rpki-ghostbusters' => 'gbr',
        'application/rpki-manifest' => 'mft',
        'application/rpki-roa' => 'roa',
        'application/rsd+xml' => 'rsd',
        'application/rss+xml' => 'rss',
        'application/rtf' => 'rtf',
        'application/sbml+xml' => 'sbml',
        'application/scvp-cv-request' => 'scq',
        'application/scvp-cv-response' => 'scs',
        'application/scvp-vp-request' => 'spq',
        'application/scvp-vp-response' => 'spp',
        'application/sdp' => 'sdp',
        'application/set-payment-initiation' => 'setpay',
        'application/set-registration-initiation' => 'setreg',
        'application/shf+xml' => 'shf',
        'application/smil+xml' => 'smi',
        'application/sparql-query' => 'rq',
        'application/sparql-results+xml' => 'srx',
        'application/srgs' => 'gram',
        'application/srgs+xml' => 'grxml',
        'application/sru+xml' => 'sru',
        'application/ssdl+xml' => 'ssdl',
        'application/ssml+xml' => 'ssml',
        'application/tei+xml' => 'tei',
        'application/thraud+xml' => 'tfi',
        'application/timestamped-data' => 'tsd',
        'application/vnd.3gpp.pic-bw-large' => 'plb',
        'application/vnd.3gpp.pic-bw-small' => 'psb',
        'application/vnd.3gpp.pic-bw-var' => 'pvb',
        'application/vnd.3gpp2.tcap' => 'tcap',
        'application/vnd.3m.post-it-notes' => 'pwn',
        'application/vnd.accpac.simply.aso' => 'aso',
        'application/vnd.accpac.simply.imp' => 'imp',
        'application/vnd.acucobol' => 'acu',
        'application/vnd.acucorp' => 'atc',
        'application/vnd.adobe.air-application-installer-package+zip' => 'air',
        'application/vnd.adobe.formscentral.fcdt' => 'fcdt',
        'application/vnd.adobe.fxp' => 'fxp',
        'application/vnd.adobe.xdp+xml' => 'xdp',
        'application/vnd.adobe.xfdf' => 'xfdf',
        'application/vnd.ahead.space' => 'ahead',
        'application/vnd.airzip.filesecure.azf' => 'azf',
        'application/vnd.airzip.filesecure.azs' => 'azs',
        'application/vnd.amazon.ebook' => 'azw',
        'application/vnd.americandynamics.acc' => 'acc',
        'application/vnd.amiga.ami' => 'ami',
        'application/vnd.android.package-archive' => 'apk',
        'application/vnd.anser-web-certificate-issue-initiation' => 'cii',
        'application/vnd.anser-web-funds-transfer-initiation' => 'fti',
        'application/vnd.antix.game-component' => 'atx',
        'application/vnd.apple.installer+xml' => 'mpkg',
        'application/vnd.apple.mpegurl' => 'm3u8',
        'application/vnd.aristanetworks.swi' => 'swi',
        'application/vnd.astraea-software.iota' => 'iota',
        'application/vnd.audiograph' => 'aep',
        'application/vnd.blueice.multipass' => 'mpm',
        'application/vnd.bmi' => 'bmi',
        'application/vnd.businessobjects' => 'rep',
        'application/vnd.chemdraw+xml' => 'cdxml',
        'application/vnd.chipnuts.karaoke-mmd' => 'mmd',
        'application/vnd.cinderella' => 'cdy',
        'application/vnd.claymore' => 'cla',
        'application/vnd.cloanto.rp9' => 'rp9',
        'application/vnd.clonk.c4group' => 'c4g',
        'application/vnd.cluetrust.cartomobile-config' => 'c11amc',
        'application/vnd.cluetrust.cartomobile-config-pkg' => 'c11amz',
        'application/vnd.commonspace' => 'csp',
        'application/vnd.contact.cmsg' => 'cdbcmsg',
        'application/vnd.cosmocaller' => 'cmc',
        'application/vnd.crick.clicker' => 'clkx',
        'application/vnd.crick.clicker.keyboard' => 'clkk',
        'application/vnd.crick.clicker.palette' => 'clkp',
        'application/vnd.crick.clicker.template' => 'clkt',
        'application/vnd.crick.clicker.wordbank' => 'clkw',
        'application/vnd.criticaltools.wbs+xml' => 'wbs',
        'application/vnd.ctc-posml' => 'pml',
        'application/vnd.cups-ppd' => 'ppd',
        'application/vnd.curl.car' => 'car',
        'application/vnd.curl.pcurl' => 'pcurl',
        'application/vnd.dart' => 'dart',
        'application/vnd.data-vision.rdz' => 'rdz',
        'application/vnd.dece.data' => 'uvf',
        'application/vnd.dece.ttml+xml' => 'uvt',
        'application/vnd.dece.unspecified' => 'uvx',
        'application/vnd.dece.zip' => 'uvz',
        'application/vnd.denovo.fcselayout-link' => 'fe_launch',
        'application/vnd.dna' => 'dna',
        'application/vnd.dolby.mlp' => 'mlp',
        'application/vnd.dpgraph' => 'dpg',
        'application/vnd.dreamfactory' => 'dfac',
        'application/vnd.ds-keypoint' => 'kpxx',
        'application/vnd.dvb.ait' => 'ait',
        'application/vnd.dvb.service' => 'svc',
        'application/vnd.dynageo' => 'geo',
        'application/vnd.ecowin.chart' => 'mag',
        'application/vnd.enliven' => 'nml',
        'application/vnd.epson.esf' => 'esf',
        'application/vnd.epson.msf' => 'msf',
        'application/vnd.epson.quickanime' => 'qam',
        'application/vnd.epson.salt' => 'slt',
        'application/vnd.epson.ssf' => 'ssf',
        'application/vnd.eszigno3+xml' => 'es3',
        'application/vnd.ezpix-album' => 'ez2',
        'application/vnd.ezpix-package' => 'ez3',
        'application/vnd.fdf' => 'fdf',
        'application/vnd.fdsn.mseed' => 'mseed',
        'application/vnd.fdsn.seed' => 'seed',
        'application/vnd.flographit' => 'gph',
        'application/vnd.fluxtime.clip' => 'ftc',
        'application/vnd.framemaker' => 'fm',
        'application/vnd.frogans.fnc' => 'fnc',
        'application/vnd.frogans.ltf' => 'ltf',
        'application/vnd.fsc.weblaunch' => 'fsc',
        'application/vnd.fujitsu.oasys' => 'oas',
        'application/vnd.fujitsu.oasys2' => 'oa2',
        'application/vnd.fujitsu.oasys3' => 'oa3',
        'application/vnd.fujitsu.oasysgp' => 'fg5',
        'application/vnd.fujitsu.oasysprs' => 'bh2',
        'application/vnd.fujixerox.ddd' => 'ddd',
        'application/vnd.fujixerox.docuworks' => 'xdw',
        'application/vnd.fujixerox.docuworks.binder' => 'xbd',
        'application/vnd.fuzzysheet' => 'fzs',
        'application/vnd.genomatix.tuxedo' => 'txd',
        'application/vnd.geogebra.file' => 'ggb',
        'application/vnd.geogebra.tool' => 'ggt',
        'application/vnd.geometry-explorer' => 'gex',
        'application/vnd.geonext' => 'gxt',
        'application/vnd.geoplan' => 'g2w',
        'application/vnd.geospace' => 'g3w',
        'application/vnd.gmx' => 'gmx',
        'application/vnd.google-earth.kml+xml' => 'kml',
        'application/vnd.google-earth.kmz' => 'kmz',
        'application/vnd.grafeq' => 'gqf',
        'application/vnd.groove-account' => 'gac',
        'application/vnd.groove-help' => 'ghf',
        'application/vnd.groove-identity-message' => 'gim',
        'application/vnd.groove-injector' => 'grv',
        'application/vnd.groove-tool-message' => 'gtm',
        'application/vnd.groove-tool-template' => 'tpl',
        'application/vnd.groove-vcard' => 'vcg',
        'application/vnd.hal+xml' => 'hal',
        'application/vnd.handheld-entertainment+xml' => 'zmm',
        'application/vnd.hbci' => 'hbci',
        'application/vnd.hhe.lesson-player' => 'les',
        'application/vnd.hp-hpgl' => 'hpgl',
        'application/vnd.hp-hpid' => 'hpid',
        'application/vnd.hp-hps' => 'hps',
        'application/vnd.hp-jlyt' => 'jlt',
        'application/vnd.hp-pcl' => 'pcl',
        'application/vnd.hp-pclxl' => 'pclxl',
        'application/vnd.hydrostatix.sof-data' => 'sfd-hdstx',
        'application/vnd.ibm.minipay' => 'mpy',
        'application/vnd.ibm.modcap' => 'afp',
        'application/vnd.ibm.rights-management' => 'irm',
        'application/vnd.ibm.secure-container' => 'sc',
        'application/vnd.iccprofile' => 'icc',
        'application/vnd.igloader' => 'igl',
        'application/vnd.immervision-ivp' => 'ivp',
        'application/vnd.immervision-ivu' => 'ivu',
        'application/vnd.insors.igm' => 'igm',
        'application/vnd.intercon.formnet' => 'xpw',
        'application/vnd.intergeo' => 'i2g',
        'application/vnd.intu.qbo' => 'qbo',
        'application/vnd.intu.qfx' => 'qfx',
        'application/vnd.ipunplugged.rcprofile' => 'rcprofile',
        'application/vnd.irepository.package+xml' => 'irp',
        'application/vnd.is-xpr' => 'xpr',
        'application/vnd.isac.fcs' => 'fcs',
        'application/vnd.jam' => 'jam',
        'application/vnd.jcp.javame.midlet-rms' => 'rms',
        'application/vnd.jisp' => 'jisp',
        'application/vnd.joost.joda-archive' => 'joda',
        'application/vnd.kahootz' => 'ktz',
        'application/vnd.kde.karbon' => 'karbon',
        'application/vnd.kde.kchart' => 'chrt',
        'application/vnd.kde.kformula' => 'kfo',
        'application/vnd.kde.kivio' => 'flw',
        'application/vnd.kde.kontour' => 'kon',
        'application/vnd.kde.kpresenter' => 'kpr',
        'application/vnd.kde.kspread' => 'ksp',
        'application/vnd.kde.kword' => 'kwd',
        'application/vnd.kenameaapp' => 'htke',
        'application/vnd.kidspiration' => 'kia',
        'application/vnd.kinar' => 'kne',
        'application/vnd.koan' => 'skp',
        'application/vnd.kodak-descriptor' => 'sse',
        'application/vnd.las.las+xml' => 'lasxml',
        'application/vnd.llamagraphics.life-balance.desktop' => 'lbd',
        'application/vnd.llamagraphics.life-balance.exchange+xml' => 'lbe',
        'application/vnd.lotus-1-2-3' => '123',
        'application/vnd.lotus-approach' => 'apr',
        'application/vnd.lotus-freelance' => 'pre',
        'application/vnd.lotus-notes' => 'nsf',
        'application/vnd.lotus-organizer' => 'org',
        'application/vnd.lotus-screencam' => 'scm',
        'application/vnd.lotus-wordpro' => 'lwp',
        'application/vnd.macports.portpkg' => 'portpkg',
        'application/vnd.mcd' => 'mcd',
        'application/vnd.medcalcdata' => 'mc1',
        'application/vnd.mediastation.cdkey' => 'cdkey',
        'application/vnd.mfer' => 'mwf',
        'application/vnd.mfmp' => 'mfm',
        'application/vnd.micrografx.flo' => 'flo',
        'application/vnd.micrografx.igx' => 'igx',
        'application/vnd.mif' => 'mif',
        'application/vnd.mobius.daf' => 'daf',
        'application/vnd.mobius.dis' => 'dis',
        'application/vnd.mobius.mbk' => 'mbk',
        'application/vnd.mobius.mqy' => 'mqy',
        'application/vnd.mobius.msl' => 'msl',
        'application/vnd.mobius.plc' => 'plc',
        'application/vnd.mobius.txf' => 'txf',
        'application/vnd.mophun.application' => 'mpn',
        'application/vnd.mophun.certificate' => 'mpc',
        'application/vnd.mozilla.xul+xml' => 'xul',
        'application/vnd.ms-artgalry' => 'cil',
        'application/vnd.ms-cab-compressed' => 'cab',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.ms-excel.addin.macroenabled.12' => 'xlam',
        'application/vnd.ms-excel.sheet.binary.macroenabled.12' => 'xlsb',
        'application/vnd.ms-excel.sheet.macroenabled.12' => 'xlsm',
        'application/vnd.ms-excel.template.macroenabled.12' => 'xltm',
        'application/vnd.ms-fontobject' => 'eot',
        'application/vnd.ms-htmlhelp' => 'chm',
        'application/vnd.ms-ims' => 'ims',
        'application/vnd.ms-lrm' => 'lrm',
        'application/vnd.ms-officetheme' => 'thmx',
        'application/vnd.ms-pki.seccat' => 'cat',
        'application/vnd.ms-pki.stl' => 'stl',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.ms-powerpoint.addin.macroenabled.12' => 'ppam',
        'application/vnd.ms-powerpoint.presentation.macroenabled.12' => 'pptm',
        'application/vnd.ms-powerpoint.slide.macroenabled.12' => 'sldm',
        'application/vnd.ms-powerpoint.slideshow.macroenabled.12' => 'ppsm',
        'application/vnd.ms-powerpoint.template.macroenabled.12' => 'potm',
        'application/vnd.ms-project' => 'mpp',
        'application/vnd.ms-word.document.macroenabled.12' => 'docm',
        'application/vnd.ms-word.template.macroenabled.12' => 'dotm',
        'application/vnd.ms-works' => 'wps',
        'application/vnd.ms-wpl' => 'wpl',
        'application/vnd.ms-xpsdocument' => 'xps',
        'application/vnd.mseq' => 'mseq',
        'application/vnd.musician' => 'mus',
        'application/vnd.muvee.style' => 'msty',
        'application/vnd.mynfc' => 'taglet',
        'application/vnd.neurolanguage.nlu' => 'nlu',
        'application/vnd.nitf' => 'ntf',
        'application/vnd.noblenet-directory' => 'nnd',
        'application/vnd.noblenet-sealer' => 'nns',
        'application/vnd.noblenet-web' => 'nnw',
        'application/vnd.nokia.n-gage.data' => 'ngdat',
        'application/vnd.nokia.n-gage.symbian.install' => 'n-gage',
        'application/vnd.nokia.radio-preset' => 'rpst',
        'application/vnd.nokia.radio-presets' => 'rpss',
        'application/vnd.novadigm.edm' => 'edm',
        'application/vnd.novadigm.edx' => 'edx',
        'application/vnd.novadigm.ext' => 'ext',
        'application/vnd.oasis.opendocument.chart' => 'odc',
        'application/vnd.oasis.opendocument.chart-template' => 'otc',
        'application/vnd.oasis.opendocument.database' => 'odb',
        'application/vnd.oasis.opendocument.formula' => 'odf',
        'application/vnd.oasis.opendocument.formula-template' => 'odft',
        'application/vnd.oasis.opendocument.graphics' => 'odg',
        'application/vnd.oasis.opendocument.graphics-template' => 'otg',
        'application/vnd.oasis.opendocument.image' => 'odi',
        'application/vnd.oasis.opendocument.image-template' => 'oti',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        'application/vnd.oasis.opendocument.presentation-template' => 'otp',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.spreadsheet-template' => 'ots',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.text-master' => 'odm',
        'application/vnd.oasis.opendocument.text-template' => 'ott',
        'application/vnd.oasis.opendocument.text-web' => 'oth',
        'application/vnd.olpc-sugar' => 'xo',
        'application/vnd.oma.dd2+xml' => 'dd2',
        'application/vnd.openofficeorg.extension' => 'oxt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.presentationml.slide' => 'sldx',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'ppsx',
        'application/vnd.openxmlformats-officedocument.presentationml.template' => 'potx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'xltx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'dotx',
        'application/vnd.osgeo.mapguide.package' => 'mgp',
        'application/vnd.osgi.dp' => 'dp',
        'application/vnd.osgi.subsystem' => 'esa',
        'application/vnd.palm' => 'pdb',
        'application/vnd.pawaafile' => 'paw',
        'application/vnd.pg.format' => 'str',
        'application/vnd.pg.osasli' => 'ei6',
        'application/vnd.picsel' => 'efif',
        'application/vnd.pmi.widget' => 'wg',
        'application/vnd.pocketlearn' => 'plf',
        'application/vnd.powerbuilder6' => 'pbd',
        'application/vnd.previewsystems.box' => 'box',
        'application/vnd.proteus.magazine' => 'mgz',
        'application/vnd.publishare-delta-tree' => 'qps',
        'application/vnd.pvi.ptid1' => 'ptid',
        'application/vnd.quark.quarkxpress' => 'qxd',
        'application/vnd.realvnc.bed' => 'bed',
        'application/vnd.recordare.musicxml' => 'mxl',
        'application/vnd.recordare.musicxml+xml' => 'musicxml',
        'application/vnd.rig.cryptonote' => 'cryptonote',
        'application/vnd.rim.cod' => 'cod',
        'application/vnd.rn-realmedia' => 'rm',
        'application/vnd.rn-realmedia-vbr' => 'rmvb',
        'application/vnd.route66.link66+xml' => 'link66',
        'application/vnd.sailingtracker.track' => 'st',
        'application/vnd.seemail' => 'see',
        'application/vnd.sema' => 'sema',
        'application/vnd.semd' => 'semd',
        'application/vnd.semf' => 'semf',
        'application/vnd.shana.informed.formdata' => 'ifm',
        'application/vnd.shana.informed.formtemplate' => 'itp',
        'application/vnd.shana.informed.interchange' => 'iif',
        'application/vnd.shana.informed.package' => 'ipk',
        'application/vnd.simtech-mindmapper' => 'twd',
        'application/vnd.smaf' => 'mmf',
        'application/vnd.smart.teacher' => 'teacher',
        'application/vnd.solent.sdkm+xml' => 'sdkm',
        'application/vnd.spotfire.dxp' => 'dxp',
        'application/vnd.spotfire.sfs' => 'sfs',
        'application/vnd.stardivision.calc' => 'sdc',
        'application/vnd.stardivision.draw' => 'sda',
        'application/vnd.stardivision.impress' => 'sdd',
        'application/vnd.stardivision.math' => 'smf',
        'application/vnd.stardivision.writer' => 'sdw',
        'application/vnd.stardivision.writer-global' => 'sgl',
        'application/vnd.stepmania.package' => 'smzip',
        'application/vnd.stepmania.stepchart' => 'sm',
        'application/vnd.sun.xml.calc' => 'sxc',
        'application/vnd.sun.xml.calc.template' => 'stc',
        'application/vnd.sun.xml.draw' => 'sxd',
        'application/vnd.sun.xml.draw.template' => 'std',
        'application/vnd.sun.xml.impress' => 'sxi',
        'application/vnd.sun.xml.impress.template' => 'sti',
        'application/vnd.sun.xml.math' => 'sxm',
        'application/vnd.sun.xml.writer' => 'sxw',
        'application/vnd.sun.xml.writer.global' => 'sxg',
        'application/vnd.sun.xml.writer.template' => 'stw',
        'application/vnd.sus-calendar' => 'sus',
        'application/vnd.svd' => 'svd',
        'application/vnd.symbian.install' => 'sis',
        'application/vnd.syncml+xml' => 'xsm',
        'application/vnd.syncml.dm+wbxml' => 'bdm',
        'application/vnd.syncml.dm+xml' => 'xdm',
        'application/vnd.tao.intent-module-archive' => 'tao',
        'application/vnd.tcpdump.pcap' => 'pcap',
        'application/vnd.tmobile-livetv' => 'tmo',
        'application/vnd.trid.tpt' => 'tpt',
        'application/vnd.triscape.mxs' => 'mxs',
        'application/vnd.trueapp' => 'tra',
        'application/vnd.ufdl' => 'ufd',
        'application/vnd.uiq.theme' => 'utz',
        'application/vnd.umajin' => 'umj',
        'application/vnd.unity' => 'unityweb',
        'application/vnd.uoml+xml' => 'uoml',
        'application/vnd.vcx' => 'vcx',
        'application/vnd.visio' => 'vsd',
        'application/vnd.visionary' => 'vis',
        'application/vnd.vsf' => 'vsf',
        'application/vnd.wap.wbxml' => 'wbxml',
        'application/vnd.wap.wmlc' => 'wmlc',
        'application/vnd.wap.wmlscriptc' => 'wmlsc',
        'application/vnd.webturbo' => 'wtb',
        'application/vnd.wolfram.player' => 'nbp',
        'application/vnd.wordperfect' => 'wpd',
        'application/vnd.wqd' => 'wqd',
        'application/vnd.wt.stf' => 'stf',
        'application/vnd.xara' => 'xar',
        'application/vnd.xfdl' => 'xfdl',
        'application/vnd.yamaha.hv-dic' => 'hvd',
        'application/vnd.yamaha.hv-script' => 'hvs',
        'application/vnd.yamaha.hv-voice' => 'hvp',
        'application/vnd.yamaha.openscoreformat' => 'osf',
        'application/vnd.yamaha.openscoreformat.osfpvg+xml' => 'osfpvg',
        'application/vnd.yamaha.smaf-audio' => 'saf',
        'application/vnd.yamaha.smaf-phrase' => 'spf',
        'application/vnd.yellowriver-custom-menu' => 'cmp',
        'application/vnd.zul' => 'zir',
        'application/vnd.zzazz.deck+xml' => 'zaz',
        'application/voicexml+xml' => 'vxml',
        'application/widget' => 'wgt',
        'application/winhlp' => 'hlp',
        'application/wsdl+xml' => 'wsdl',
        'application/wspolicy+xml' => 'wspolicy',
        'application/x-7z-compressed' => '7z',
        'application/x-abiword' => 'abw',
        'application/x-ace-compressed' => 'ace',
        'application/x-apple-diskimage' => 'dmg',
        'application/x-authorware-bin' => 'aab',
        'application/x-authorware-map' => 'aam',
        'application/x-authorware-seg' => 'aas',
        'application/x-bcpio' => 'bcpio',
        'application/x-bittorrent' => 'torrent',
        'application/x-blorb' => 'blb',
        'application/x-bzip' => 'bz',
        'application/x-bzip2' => 'bz2',
        'application/x-cbr' => 'cbr',
        'application/x-cdlink' => 'vcd',
        'application/x-cfs-compressed' => 'cfs',
        'application/x-chat' => 'chat',
        'application/x-chess-pgn' => 'pgn',
        'application/x-conference' => 'nsc',
        'application/x-cpio' => 'cpio',
        'application/x-csh' => 'csh',
        'application/x-debian-package' => 'deb',
        'application/x-dgc-compressed' => 'dgc',
        'application/x-director' => 'dir',
        'application/x-doom' => 'wad',
        'application/x-dtbncx+xml' => 'ncx',
        'application/x-dtbook+xml' => 'dtb',
        'application/x-dtbresource+xml' => 'res',
        'application/x-dvi' => 'dvi',
        'application/x-envoy' => 'evy',
        'application/x-eva' => 'eva',
        'application/x-font-bdf' => 'bdf',
        'application/x-font-ghostscript' => 'gsf',
        'application/x-font-linux-psf' => 'psf',
        'application/x-font-pcf' => 'pcf',
        'application/x-font-snf' => 'snf',
        'application/x-font-type1' => 'pfa',
        'application/x-freearc' => 'arc',
        'application/x-futuresplash' => 'spl',
        'application/x-gca-compressed' => 'gca',
        'application/x-glulx' => 'ulx',
        'application/x-gnumeric' => 'gnumeric',
        'application/x-gramps-xml' => 'gramps',
        'application/x-gtar' => 'gtar',
        'application/x-hdf' => 'hdf',
        'application/x-install-instructions' => 'install',
        'application/x-iso9660-image' => 'iso',
        'application/x-java-jnlp-file' => 'jnlp',
        'application/x-latex' => 'latex',
        'application/x-lzh-compressed' => 'lzh',
        'application/x-mie' => 'mie',
        'application/x-mobipocket-ebook' => 'prc',
        'application/x-ms-application' => 'application',
        'application/x-ms-shortcut' => 'lnk',
        'application/x-ms-wmd' => 'wmd',
        'application/x-ms-wmz' => 'wmz',
        'application/x-ms-xbap' => 'xbap',
        'application/x-msaccess' => 'mdb',
        'application/x-msbinder' => 'obd',
        'application/x-mscardfile' => 'crd',
        'application/x-msclip' => 'clp',
        'application/x-msdownload' => 'exe',
        'application/x-msmediaview' => 'mvb',
        'application/x-msmetafile' => 'wmf',
        'application/x-msmoney' => 'mny',
        'application/x-mspublisher' => 'pub',
        'application/x-msschedule' => 'scd',
        'application/x-msterminal' => 'trm',
        'application/x-mswrite' => 'wri',
        'application/x-netcdf' => 'nc',
        'application/x-nzb' => 'nzb',
        'application/x-pkcs12' => 'p12',
        'application/x-pkcs7-certificates' => 'p7b',
        'application/x-pkcs7-certreqresp' => 'p7r',
        'application/x-rar-compressed' => 'rar',
        'application/x-research-info-systems' => 'ris',
        'application/x-sh' => 'sh',
        'application/x-shar' => 'shar',
        'application/x-shockwave-flash' => 'swf',
        'application/x-silverlight-app' => 'xap',
        'application/x-sql' => 'sql',
        'application/x-stuffit' => 'sit',
        'application/x-stuffitx' => 'sitx',
        'application/x-subrip' => 'srt',
        'application/x-sv4cpio' => 'sv4cpio',
        'application/x-sv4crc' => 'sv4crc',
        'application/x-t3vm-image' => 't3',
        'application/x-tads' => 'gam',
        'application/x-tar' => 'tar',
        'application/x-tcl' => 'tcl',
        'application/x-tex' => 'tex',
        'application/x-tex-tfm' => 'tfm',
        'application/x-texinfo' => 'texinfo',
        'application/x-tgif' => 'obj',
        'application/x-ustar' => 'ustar',
        'application/x-wais-source' => 'src',
        'application/x-x509-ca-cert' => 'der',
        'application/x-xfig' => 'fig',
        'application/x-xliff+xml' => 'xlf',
        'application/x-xpinstall' => 'xpi',
        'application/x-xz' => 'xz',
        'application/x-zmachine' => 'z1',
        'application/xaml+xml' => 'xaml',
        'application/xcap-diff+xml' => 'xdf',
        'application/xenc+xml' => 'xenc',
        'application/xhtml+xml' => 'xhtml',
        'application/xml' => 'xml',
        'application/xml-dtd' => 'dtd',
        'application/xop+xml' => 'xop',
        'application/xproc+xml' => 'xpl',
        'application/xslt+xml' => 'xslt',
        'application/xspf+xml' => 'xspf',
        'application/xv+xml' => 'mxml',
        'application/yang' => 'yang',
        'application/yin+xml' => 'yin',
        'application/zip' => 'zip',
        'audio/adpcm' => 'adp',
        'audio/basic' => 'au',
        'audio/midi' => 'mid',
        'audio/mp4' => 'm4a',
        'audio/mpeg' => 'mpga',
        'audio/ogg' => 'oga',
        'audio/s3m' => 's3m',
        'audio/silk' => 'sil',
        'audio/vnd.dece.audio' => 'uva',
        'audio/vnd.digital-winds' => 'eol',
        'audio/vnd.dra' => 'dra',
        'audio/vnd.dts' => 'dts',
        'audio/vnd.dts.hd' => 'dtshd',
        'audio/vnd.lucent.voice' => 'lvp',
        'audio/vnd.ms-playready.media.pya' => 'pya',
        'audio/vnd.nuera.ecelp4800' => 'ecelp4800',
        'audio/vnd.nuera.ecelp7470' => 'ecelp7470',
        'audio/vnd.nuera.ecelp9600' => 'ecelp9600',
        'audio/vnd.rip' => 'rip',
        'audio/webm' => 'weba',
        'audio/x-aac' => 'aac',
        'audio/x-aiff' => 'aif',
        'audio/x-caf' => 'caf',
        'audio/x-flac' => 'flac',
        'audio/x-matroska' => 'mka',
        'audio/x-mpegurl' => 'm3u',
        'audio/x-ms-wax' => 'wax',
        'audio/x-ms-wma' => 'wma',
        'audio/x-pn-realaudio' => 'ram',
        'audio/x-pn-realaudio-plugin' => 'rmp',
        'audio/x-wav' => 'wav',
        'audio/xm' => 'xm',
        'chemical/x-cdx' => 'cdx',
        'chemical/x-cif' => 'cif',
        'chemical/x-cmdf' => 'cmdf',
        'chemical/x-cml' => 'cml',
        'chemical/x-csml' => 'csml',
        'chemical/x-xyz' => 'xyz',
        'font/collection' => 'ttc',
        'font/otf' => 'otf',
        'font/ttf' => 'ttf',
        'font/woff' => 'woff',
        'font/woff2' => 'woff2',
        'image/bmp' => 'bmp',
        'image/cgm' => 'cgm',
        'image/g3fax' => 'g3',
        'image/gif' => 'gif',
        'image/ief' => 'ief',
        'image/jpeg' => 'jpeg',
        'image/ktx' => 'ktx',
        'image/png' => 'png',
        'image/prs.btif' => 'btif',
        'image/sgi' => 'sgi',
        'image/svg+xml' => 'svg',
        'image/tiff' => 'tiff',
        'image/vnd.adobe.photoshop' => 'psd',
        'image/vnd.dece.graphic' => 'uvi',
        'image/vnd.djvu' => 'djvu',
        'image/vnd.dvb.subtitle' => 'sub',
        'image/vnd.dwg' => 'dwg',
        'image/vnd.dxf' => 'dxf',
        'image/vnd.fastbidsheet' => 'fbs',
        'image/vnd.fpx' => 'fpx',
        'image/vnd.fst' => 'fst',
        'image/vnd.fujixerox.edmics-mmr' => 'mmr',
        'image/vnd.fujixerox.edmics-rlc' => 'rlc',
        'image/vnd.ms-modi' => 'mdi',
        'image/vnd.ms-photo' => 'wdp',
        'image/vnd.net-fpx' => 'npx',
        'image/vnd.wap.wbmp' => 'wbmp',
        'image/vnd.xiff' => 'xif',
        'image/webp' => 'webp',
        'image/x-3ds' => '3ds',
        'image/x-cmu-raster' => 'ras',
        'image/x-cmx' => 'cmx',
        'image/x-freehand' => 'fh',
        'image/x-icon' => 'ico',
        'image/x-mrsid-image' => 'sid',
        'image/x-pcx' => 'pcx',
        'image/x-pict' => 'pic',
        'image/x-portable-anymap' => 'pnm',
        'image/x-portable-bitmap' => 'pbm',
        'image/x-portable-graymap' => 'pgm',
        'image/x-portable-pixmap' => 'ppm',
        'image/x-rgb' => 'rgb',
        'image/x-tga' => 'tga',
        'image/x-xbitmap' => 'xbm',
        'image/x-xpixmap' => 'xpm',
        'image/x-xwindowdump' => 'xwd',
        'message/rfc822' => 'eml',
        'model/iges' => 'igs',
        'model/mesh' => 'msh',
        'model/vnd.collada+xml' => 'dae',
        'model/vnd.dwf' => 'dwf',
        'model/vnd.gdl' => 'gdl',
        'model/vnd.gtw' => 'gtw',
        'model/vnd.mts' => 'mts',
        'model/vnd.vtu' => 'vtu',
        'model/vrml' => 'wrl',
        'model/x3d+binary' => 'x3db',
        'model/x3d+vrml' => 'x3dv',
        'model/x3d+xml' => 'x3d',
        'text/cache-manifest' => 'appcache',
        'text/calendar' => 'ics',
        'text/css' => 'css',
        'text/csv' => 'csv',
        'text/html' => 'html',
        'text/n3' => 'n3',
        'text/plain' => 'txt',
        'text/prs.lines.tag' => 'dsc',
        'text/richtext' => 'rtx',
        'text/sgml' => 'sgml',
        'text/tab-separated-values' => 'tsv',
        'text/troff' => 't',
        'text/turtle' => 'ttl',
        'text/uri-list' => 'uri',
        'text/vcard' => 'vcard',
        'text/vnd.curl' => 'curl',
        'text/vnd.curl.dcurl' => 'dcurl',
        'text/vnd.curl.mcurl' => 'mcurl',
        'text/vnd.curl.scurl' => 'scurl',
        'text/vnd.dvb.subtitle' => 'sub',
        'text/vnd.fly' => 'fly',
        'text/vnd.fmi.flexstor' => 'flx',
        'text/vnd.graphviz' => 'gv',
        'text/vnd.in3d.3dml' => '3dml',
        'text/vnd.in3d.spot' => 'spot',
        'text/vnd.sun.j2me.app-descriptor' => 'jad',
        'text/vnd.wap.wml' => 'wml',
        'text/vnd.wap.wmlscript' => 'wmls',
        'text/x-asm' => 's',
        'text/x-c' => 'c',
        'text/x-fortran' => 'f',
        'text/x-java-source' => 'java',
        'text/x-nfo' => 'nfo',
        'text/x-opml' => 'opml',
        'text/x-pascal' => 'p',
        'text/x-setext' => 'etx',
        'text/x-sfv' => 'sfv',
        'text/x-uuencode' => 'uu',
        'text/x-vcalendar' => 'vcs',
        'text/x-vcard' => 'vcf',
        'video/3gpp' => '3gp',
        'video/3gpp2' => '3g2',
        'video/h261' => 'h261',
        'video/h263' => 'h263',
        'video/h264' => 'h264',
        'video/jpeg' => 'jpgv',
        'video/jpm' => 'jpm',
        'video/mj2' => 'mj2',
        'video/mp4' => 'mp4',
        'video/mpeg' => 'mpeg',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'qt',
        'video/vnd.dece.hd' => 'uvh',
        'video/vnd.dece.mobile' => 'uvm',
        'video/vnd.dece.pd' => 'uvp',
        'video/vnd.dece.sd' => 'uvs',
        'video/vnd.dece.video' => 'uvv',
        'video/vnd.dvb.file' => 'dvb',
        'video/vnd.fvt' => 'fvt',
        'video/vnd.mpegurl' => 'mxu',
        'video/vnd.ms-playready.media.pyv' => 'pyv',
        'video/vnd.uvvu.mp4' => 'uvu',
        'video/vnd.vivo' => 'viv',
        'video/webm' => 'webm',
        'video/x-f4v' => 'f4v',
        'video/x-fli' => 'fli',
        'video/x-flv' => 'flv',
        'video/x-m4v' => 'm4v',
        'video/x-matroska' => 'mkv',
        'video/x-mng' => 'mng',
        'video/x-ms-asf' => 'asf',
        'video/x-ms-vob' => 'vob',
        'video/x-ms-wm' => 'wm',
        'video/x-ms-wmv' => 'wmv',
        'video/x-ms-wmx' => 'wmx',
        'video/x-ms-wvx' => 'wvx',
        'video/x-msvideo' => 'avi',
        'video/x-sgi-movie' => 'movie',
        'video/x-smv' => 'smv',
        'x-conference/x-cooltalk' => 'ice'
    );

    public static function getExtension($content_type, $default = null)
    {
        $content_type = static::cleanContentType($content_type);

        if (array_key_exists($content_type, static::$map)) {
            return static::$map[$content_type];
        }

        return $default;
    }

    /**
     * Will translate: "text/html; charset=UTF-8" into just "text/html"
     * @param $content_type
     * @return string
     */
    public static function cleanContentType($content_type)
    {
        return trim(preg_replace('/;.*/', '', $content_type));
    }
}


/*************************************************************
*
*  File Path: /src/CurlDownloader.php
*
************************************************************/


//namespace CurlDownloader;

//use Curl\Client;

class CurlDownloader
{
    /** @var Client */
    private $client;

    // Timeout after 10 minutes.
    protected $max_timeout = 600;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    protected function createTempFile()
    {
        return tempnam(sys_get_temp_dir(), uniqid());
    }

    protected function getPathFromUrl($url)
    {
        return parse_url($url, PHP_URL_PATH);
    }

    protected function getFilenameFromUrl($url)
    {
        // equivalent to: pathinfo with PATHINFO_FILENAME
        return basename($this->getPathFromUrl($url));
    }

    protected function getExtensionFromUrl($url)
    {
        return pathinfo($this->getFilenameFromUrl($url), PATHINFO_EXTENSION);
    }

    /**
     * @param $url
     * @param $destination
     * @return \Curl\Response
     */
    public function download($url, $destination)
    {
        $handler = new HeaderHandler();

        // Will download file to temp for now
        $temp_filename = $this->createTempFile();

        $handle = fopen($temp_filename, 'w+');

        $response = $this->client->request('GET', $url, [], [], [
            CURLOPT_FILE => $handle,
            CURLOPT_HEADERFUNCTION => $handler->callback(),
            CURLOPT_TIMEOUT => $this->max_timeout
        ]);

        // TODO: refactor this whole filename logic into its own class
        if ($response->info->http_code === 200) {
            $filename = $handler->getContentDispositionFilename();

            if (empty($filename)) {
                $url = $response->info->url;

                $filename = $this->getFilenameFromUrl($url);

                $extension_from_url = $this->getExtensionFromUrl($url);
                $extension_from_content_type = ContentTypes::getExtension($handler->getContentType());

                // E.g: https://www.google.com/
                if (empty($filename)) {
                    $filename = 'index.' . ($extension_from_content_type ? $extension_from_content_type : 'html');
                } else {

                    // in case filename in url is like `videoplayback` with `content-type: video/mp4`
                    if (empty($extension_from_url) && $extension_from_content_type) {
                        $filename = ($filename . '.' . $extension_from_content_type);
                    }
                }
            }

            $save_to = call_user_func($destination, $filename);

            rename($temp_filename, $save_to);
        }

        @fclose($handle);
        @unlink($temp_filename);

        return $response;
    }
}



/*************************************************************
*
*  File Path: /src/HeaderHandler.php
*
************************************************************/


//namespace CurlDownloader;

class HeaderHandler
{
    protected $headers = array();

    /** @var callable */
    protected $callback;

    // Thanks Drupal!
    // const REQUEST_HEADER_FILENAME_REGEX = '@\\bfilename(?<star>\\*?)=\\"(?<filename>.+)\\"@';
    const REQUEST_HEADER_FILENAME_REGEX = '/filename\s*=\s*["\']*(?<filename>[^"\']+)/';

    public function callback()
    {
        $oThis = $this;

        $headers = array();
        $first_line_sent = false;

        return function ($ch, $data) use ($oThis, &$first_line_sent, &$headers) {
            $line = trim($data);

            if ($first_line_sent == false) {
                $first_line_sent = true;
            } elseif ($line === '') {
                $oThis->sendHeaders();
            } else {

                $parts = explode(':', $line, 2);

                // Despite that headers may be retrieved case-insensitively, the original case MUST be preserved by the implementation
                // Non-conforming HTTP applications may depend on a certain case,
                // so it is useful for a user to be able to dictate the case of the HTTP headers when creating a request or response.

                // TODO:
                // Multiple message-header fields with the same field-name may be present in a message
                // if and only if the entire field-value for that header field is defined as a comma-separated list
                $oThis->headers[trim($parts[0])] = isset($parts[1]) ? trim($parts[1]) : null;
            }

            return strlen($data);
        };
    }

    protected function sendHeaders()
    {
        if (is_callable($this->callback)) {
            call_user_func($this->callback, $this);
        }
    }

    /**
     * @param callable $callback
     */
    public function onHeadersReceived($callback)
    {
        $this->callback = $callback;
    }

    // While header names are not case-sensitive, getHeaders() will preserve the exact case in which headers were originally specified.
    public function getHeaders()
    {
        return $this->headers;
    }

    public function getContentDispositionFilename()
    {
        $normalized = array_change_key_case($this->headers, CASE_LOWER);
        $header = isset($normalized['content-disposition']) ? $normalized['content-disposition'] : null;

        if ($header && preg_match(static::REQUEST_HEADER_FILENAME_REGEX, $header, $matches)) {
            return $matches['filename'];
        }

        return null;
    }

    public function getContentType()
    {
        $normalized = array_change_key_case($this->headers, CASE_LOWER);
        return isset($normalized['content-type']) ? $normalized['content-type'] : null;
    }
}


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

    public function consentCookies()
    {
        $response = $this->get('https://www.youtube.com/');
        $current_url = $response->info->url;

        // must be missing that special cookie
        if (strpos($current_url, 'consent.youtube.com') !== false) {

            $field_names = ['gl', 'm', 'pc', 'continue', 'ca', 'x', 'v', 't', 'hl', 'src', 'uxe'];

            $form_data = [];

            foreach ($field_names as $name) {
                $value = Utils::getInputValueByName($response->body, $name);
                $form_data[$name] = htmlspecialchars_decode($value);
            }

            // this will set that cookie that we need to never see that message again
            $this->post('https://consent.youtube.com/s', $form_data);
        }
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

    public static function getInputValueByName($html, $name)
    {
        if (preg_match("/name=(['\"]){$name}\\1[^>]+value=(['\"])(.*?)\\2/is", $html, $matches)) {
            return $matches[3];
        }

        return null;
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
                'html5' => 1,
                'video_id' => $video_id,
                'eurl' => 'https://youtube.googleapis.com/v/' . $video_id,
                'el' => 'embedded', // or detailpage. default: embedded, will fail if video is not embeddable
                'c' => 'TVHTML5',
                'cver' => '6.20180913'
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
