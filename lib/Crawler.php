<?php

/**
 * Class Crawler
 */
class Crawler {

    /**
     * @var
     */
    public $savePath;
    /**
     * @var mixed
     */
    public $domain;

    /**
     * @var array
     */
    private $urlsStack = array();
    /**
     * @var array
     */
    private $crawledUrls = array();

    /**
     * @var
     */
    private $pause;
    /**
     * @var
     */
    private $currentUrl;
    /**
     * @var
     */
    private $currentFileType;
    /**
     * @var
     */
    private $totalBytes;
    /**
     * @var string
     */
    private $_mode;

    /**
     * @param $url
     * @param $savePath
     * @param string $mode
     */
    public function __construct($url, $savePath, $mode = 'all'){
        $this->_mode = $mode;
        $this->homeUrl = $url;
        $this->domain = parse_url($url, PHP_URL_HOST);
        $this->schema = parse_url($url, PHP_URL_SCHEME);
        $this->urlParams = parse_url($url, PHP_URL_QUERY);
        $this->savePath = $savePath;
        $this->urlsStack[] = $url;
    }

    /**
     *
     */
    public function crawl(){
        while(!empty($this->urlsStack)){
            $this->get($this->getFromUrlsStack());
        }
        echo "Total Files: ".count($this->crawledUrls);
        echo "Total Size: ".round($this->totalBytes / 1024 / 1024, 2)." MB";
    }

    /**
     * @param $url
     * @param bool $currentUrlPath
     * @return string
     */
    private function createAbsoluteUrl($url, $currentUrlPath = false){
        if(!$currentUrlPath){
            $currentUrlPathArray = parse_url($this->currentUrl);
            if($currentUrlPathArray && isset($currentUrlPathArray['host'])){
                $currentUrlPath = $currentUrlPathArray['scheme'].'://'.$currentUrlPathArray['host'].$currentUrlPathArray['path'];
            } elseif($currentUrlPathArray && !isset($currentUrlPathArray['host'])) {
                $currentUrlPath = $currentUrlPathArray['path'];
            } else {
                $currentUrlPath = $this->currentUrl;
            }

            if(preg_match('/(?:.)*\/((?:.)*?\.(?:.)*)$/sim', parse_url($currentUrlPath, PHP_URL_PATH), $filename)){
                $currentUrlPath = str_replace($filename[1], '', $currentUrlPath);
            }
        }

        /*if ($urlArray = parse_url($url)) {
            if(isset($urlArray['host'])){
                $url = $urlArray['scheme'].'://'.$urlArray['host'].$urlArray['path'];
            } else {
                $url = $urlArray['path'];
            }

        }*/

        if($this->isAbsolutePath($url)){
            $url = $this->schema.'://'.$this->domain.$url.$this->urlParams;
        } elseif($this->isRelativePath($url)) {
            if($this->urlParams){
                $url = $currentUrlPath.$url.'?'.$this->urlParams;
            } else {
                $url = $currentUrlPath.$url;
            }
        }
        return $url;
    }

    /**
     * @return mixed
     */
    private function getFromUrlsStack(){
        $url = array_shift($this->urlsStack);
        $this->addToCrawledUrls($url);
        return $url;
    }

    /**
     * @param $url
     */
    public function addToUrlsStack($url){
        $this->urlsStack[] = $this->createAbsoluteUrl($url);
    }

    /**
     * @param $url
     */
    public function addToCrawledUrls($url){
        $this->crawledUrls[] = $url;
    }

    /**
     * @param $url
     * @return bool
     */
    public function get($url){
        $this->currentUrl = $url;
        echo "Crawling $url".PHP_EOL;
        $contentType = get_headers($url, 1)["Content-Type"];
        if(is_array($contentType)){
            return false;
        }
        $this->currentFileType = strtolower($contentType);
        $content = @file_get_contents($url);
        if(!$content){
            return;
        }
        if(strpos($this->currentFileType, 'text/html') !== false){
            $content = Sunra\PhpSimple\HtmlDomParser::str_get_html($content);
            foreach($content->find('a') as $a){
                if($this->checkUrl($a->href)){
                    $this->addToUrlsStack($a->href);
                }
            }
            foreach($content->find('link[rel=stylesheet]') as $css){
                if($this->checkUrl($css->href)){
                    $this->addToUrlsStack($css->href);
                }
            }
            foreach($content->find('link[rel=stylesheet/less]') as $less){
                if($this->checkUrl($less->href)){
                    $this->addToUrlsStack($less->href);
                }
            }
            foreach($content->find('script[src]') as $js){
                if($this->checkUrl($js->src)){
                    $this->addToUrlsStack($js->src);
                }
            }
            foreach($content->find('img') as $img){
                if($this->checkUrl($img->src)){
                    $this->addToUrlsStack($img->src);
                }
            }
        }

        if(strpos($this->currentFileType, 'text/css') !== false){
            if(preg_match_all('/url\((?:"|\')?(.*?)(?:"|\')?\)/sim', $content, $files)){
                foreach($files[1] as $file){
                    if($this->checkUrl($file)){
                        $this->addToUrlsStack($file);
                    }
                }
            };

        }

        $this->save($content, $url);
        $this->pause();
    }

    /**
     * @param $content
     * @param $url
     */
    public function save($content, $url){
        $path  = parse_url($url,PHP_URL_PATH);

        if(preg_match('/(?:.)*\/((?:.)*?\.(?:.)*)$/sim', $path, $filename)){
            $filename = $filename[1];
            $path = dirname($path);
        } else {
            $filename = 'index.html';
        }

        $target = $this->savePath.'/'.$this->domain.$path;
        if(!is_dir($target)){
            mkdir($target, 0777, true);
        }
        if(strpos($target, '/', strlen($target) - 1) === strlen($target) - 1){
            $newFile = $target.$filename;
        } else {
            $newFile = $target.'/'.$filename;
        }
        /*echo "Filename: $path".PHP_EOL;
        echo "Path: $path".PHP_EOL;*/
        $this->totalBytes += file_put_contents($newFile, $content);
        // @todo: realpath
        echo "Saved to: $newFile".PHP_EOL;
        echo "----------------------------".PHP_EOL;
    }

    /**
     *
     */
    private function pause(){
        if($this->pause){
            $timeout = $this->pause;
            while($timeout !== 0){
                echo "$timeout...";
                sleep(1);
                $timeout--;
                if($timeout === 0){
                    echo PHP_EOL;
                }
            }
        }
    }

    /**
     * @param $url
     * @return bool
     */
    private function checkUrl($url){

        if(strpos($url, '#') === 0 || (strpos($url, 'mailto:') === 0) || (strpos($url, 'javascript:') === 0)){
            return false;
        }


        if(in_array($this->createAbsoluteUrl($url), $this->urlsStack) || in_array($this->createAbsoluteUrl($url), $this->crawledUrls)){
            return false;
        }

        $domainPattern = "|http(s)*://$this->domain/(.)*|sim";
        if(strpos($url, 'http') === 0 && !preg_match($domainPattern, $url)){
            return false;
        }

        if($this->_mode == 'single' && (pathinfo($url, PATHINFO_EXTENSION) == '' || pathinfo($url, PATHINFO_EXTENSION) == 'html' || pathinfo($url, PATHINFO_EXTENSION) == 'php')){
            return false;
        }

        return true;
    }

    /**
     * @param $url
     * @return bool
     */
    private function isAbsolutePath($url){
        return strpos($url, '/' === 0) && strpos($url, '//' === 0);
    }

    /**
     * @param $url
     * @return bool
     */
    private function isRelativePath($url){
        return !preg_match('/^http(s)*/sim', $url);
    }
}