<?php

namespace Minhnhc\Util;

use ErrorException;

class ExeAsync
{
    private string $cmd;
    private string $cacheFile;

    private bool $windows;
    private int $startTime;
    private bool $finished = false;
    public function __construct($cmd) {
        $this->cmd = $cmd;
        $this->cacheFile = "cache-pipe-".uniqid();

        $this->windows = str_starts_with(php_uname(), "Windows");

    }

    public function hasFinished(): bool
    {
        if ($this->finished) {
            return true;
        }

        // Time out
        $time = time() - $this->startTime;
        if ($time > 60 * 5) {
            // 5 minute
//            try {
//                if (file_exists($this->cacheFile)) {
//                    unlink($this->cacheFile);
//                }
//            }catch (\Exception $e) {
//
//            }
            global $logger;
            $logger->debug("Timeout for $this->cmd");
            $this->finished = true;
            return true;
        }

        if ($this->windows) {
            if (!file_exists($this->cacheFile)) {
                $this->finished = true;
                return true;
            }

            set_error_handler(function($errNo, $errStr, $errFile, $errLine) {
                // error was suppressed with the @-operator
                if (0 === error_reporting()) {
                    return false;
                }
                throw new ErrorException($errStr, 0, $errNo, $errFile, $errLine);
            });

            try {
                $fp = fopen($this->cacheFile, "w");
                $this->finished = true;
                $ret = true;
                fclose($fp);
                unlink($this->cacheFile);
            } catch (\Exception $e) {
                $ret = false;
            }
            restore_error_handler();
            return $ret;
        }else{
            if (file_exists($this->cacheFile)) {
                sleep(1);
                unlink($this->cacheFile);
                $this->finished = true;
                return true;
            }
            return false;
        }

    }

    public function run() {
        if($this->cmd) {
            $this->startTime = time();
            global $logger;
            $logger->debug("Run command $this->cmd");
            if ($this->windows){
                pclose(popen("start /B ". $this->cmd ." > " .$this->cacheFile, "r"));
            }
            else {
                // exec($this->cmd . " > /dev/null &");
                // $logger->debug("{ ".$this->cmd." && echo finished > " . $this->cacheFile.";} > /dev/null 2>/dev/null &");
                exec("{ ".$this->cmd." && echo finished > ".$this->cacheFile.";} > /dev/null 2>/dev/null &");
            }

        }
    }

    public function __destruct()
    {
        if (file_exists($this->cacheFile) && $this->windows) {
            global $logger;
            $logger->debug("Delete cache {$this->cacheFile}");
            unlink($this->cacheFile);
        }
    }
}
