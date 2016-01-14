<?php

class AssetDirectory {
  
    private $_dir;
    private $_language;
    
    public $ipa;
    public $plist;
    public $profile;
    public $apk;
    public $json;
    public $note;
    
    /**
    These directories won't be searched when traversing
    down the directory structure. If the user accesses
    directly these will still function as normal.
    */
    private $ignored_subdirectories = array("support", "thinned");
    
    
    public function __construct(Directory $dir, $language) {
        $this->_dir = $dir;
        $this->_language = $language;
        $this->parseDirectoryContents();
    }  
    
    private function parseDirectoryContents() {
        // iOS
        $this->ipa        = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_IOS_IPA));
        $this->plist      = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_IOS_PLIST));
        $this->profile    = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_IOS_PROFILE));
    
        // Android
        $this->apk        = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_ANDROID_APK));
        $this->json       = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_ANDROID_JSON));
    
        $this->note = '';
        // Common
        if ($this->_language) {
            $this->note   = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_COMMON_NOTES . '.' . $this->_language));
        }
        if (!$this->note) {
            $this->note   = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_COMMON_NOTES));   // the default language file should not have a language extension, so if en is default, never creaete a .html.en file!
        }
        $this->icon       = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_COMMON_ICON));
        $this->mandatory  = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_VERSION_MANDATORY));    // this file defines if the version is mandatory
        $this->restrict   = @array_shift(glob($this->_dir->path . '/*' . AppUpdater::FILE_VERSION_RESTRICT));    // this file defines the teams allowed to access this version
    }
    
    
    public function getApplicationVersions($platform) {
        $files = array();
        
        $allVersions = array();
        
        if ((!$this->ipa || !$this->plist) && 
            (!$this->apk || !$this->json)) {
            // check if any are available in a subdirectory
            $subDirs = array();
            $directoryIterator = new RecursiveDirectoryIterator($this->_dir->path, FilesystemIterator::SKIP_DOTS);
            $objects = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);
            foreach($objects as $object) {
                if ($object->isDir()) {
                    $path = str_replace($this->_dir->path, "", $object->getPathname());
                    
                    $components = explode("/", ltrim($path, "/"));
                    $subdir = $components[0];
                    if (!in_array($subdir, $this->ignored_subdirectories)) {
                        array_push($subDirs, $path);
                    }
                }
            }
        
            // Sort the files and display
            usort($subDirs, function($a, $b) {
                return version_compare($a, $b, '<');
            });
            
            if (count($subDirs) > 0) {
                foreach ($subDirs as $subDir) {
          
                    $subDirectory = dir($this->_dir->path . $subDir);
                    $subAssetDir = new AssetDirectory($subDirectory, $this->_language);
                    
                    if ($subAssetDir->ipa && $subAssetDir->plist && (!$platform || $platform == AppUpdater::PLATFORM_IOS)) {
                        $version = array();
                        $version[AppUpdater::FILE_IOS_IPA] = $subAssetDir->ipa;
                        $version[AppUpdater::FILE_IOS_PLIST] = $subAssetDir->plist;
                        $version[AppUpdater::FILE_COMMON_NOTES] = $subAssetDir->note;
                        $version[AppUpdater::FILE_VERSION_RESTRICT] = $subAssetDir->restrict;
                        $version[AppUpdater::FILE_VERSION_MANDATORY] = $subAssetDir->mandatory;
                        $version[AppUpdater::INDEX_DEVICES] = $this->devicesForIPA($subAssetDir->ipa);
                        $version[AppUpdater::INDEX_THINNEDAPPS] = $this->thinnedAssets($subDirectory);
                        
                        // if this is a restricted version, check if the UDID is provided and allowed
                        if ($subAssetDir->restrict && !$this->checkProtectedVersion($subAssetDir->restrict)) {
                            continue;
                        }
                        
                        $version[AppUpdater::FILE_COMMON_ICON] = $subAssetDir->icon;
                        $allVersions[$subDir] = $version;
                    } else if ($subAssetDir->apk && $subAssetDir->json && (!$platform || $platform == AppUpdater::PLATFORM_ANDROID)) {
                        $version = array();
                        $version[AppUpdater::FILE_ANDROID_APK] = $subAssetDir->apk;
                        $version[AppUpdater::FILE_ANDROID_JSON] = $subAssetDir->json;
                        $version[AppUpdater::FILE_COMMON_NOTES] = $subAssetDir->note;
                        
                        // Is this a build for a specific android platform?
                        $matches = array();
                        if (preg_match("/android\/[0-9\.\/]*(?P<platform>[\w]+)/i", $subAssetDir->_dir->path, $matches)) {
                            $version[AppUpdater::ANDROID_SUBPLATFORM] = ucfirst($matches["platform"]);
                        }

                        $version[AppUpdater::FILE_COMMON_ICON] = $subAssetDir->icon;
                        $allVersions[$subDir] = $version;
                    }
                }
        
                if (count($allVersions) > 0) {
                    $files[AppUpdater::VERSIONS_SPECIFIC_DATA] = $allVersions;
                    $files[AppUpdater::VERSIONS_COMMON_DATA][AppUpdater::FILE_IOS_PROFILE] = $subAssetDir->profile;
                }
            }
        } else {
            $version = array();
            if ($this->ipa && $this->plist) {
                $version[AppUpdater::FILE_IOS_IPA] = $this->ipa;
                $version[AppUpdater::FILE_IOS_PLIST] = $this->plist;
                $version[AppUpdater::FILE_COMMON_NOTES] = $this->note;
                $version[AppUpdater::FILE_COMMON_ICON] = $this->icon;
                $version[AppUpdater::INDEX_DEVICES] = $this->devicesForIPA($this->ipa);
                $version[AppUpdater::INDEX_THINNEDAPPS] = $this->thinnedAssets($this->_dir);
                
                $allVersions[] = $version;
                $files[AppUpdater::VERSIONS_SPECIFIC_DATA] = $allVersions;
                $files[AppUpdater::VERSIONS_COMMON_DATA][AppUpdater::FILE_IOS_PROFILE] = $this->profile;
            } else if ($this->apk && $this->json) {
                $version[AppUpdater::FILE_ANDROID_APK] = $this->apk;
                $version[AppUpdater::FILE_ANDROID_JSON] = $this->json;
                $version[AppUpdater::FILE_COMMON_NOTES] = $this->note;
                $version[AppUpdater::FILE_COMMON_ICON] = $this->icon;
                $allVersions[] = $version;
                $files[AppUpdater::VERSIONS_SPECIFIC_DATA] = $allVersions;
            }
        }
        
        return $files;
    }
    
    private function devicesForIPA($ipa) {
        $file = new SplFileObject($ipa);
        $ipa = new IPA($file);
        if ($ipa->provisionsAllDevices()) {
            return AppUpdater::PROVISIONED_ALL_DEVICES;
        }
        else {
            return $ipa->provisionedDevices();
        }
    }
    
    private function thinnedAssets(Directory $directory) {
        $thinnedPath = dir($directory->path . "/thinned");
        
        $assets = array();
        
        if ($thinnedPath) {
            $directoryIterator = new RecursiveDirectoryIterator($thinnedPath->path, FilesystemIterator::SKIP_DOTS);
            $objects = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);
            foreach($objects as $object) {
                $info = pathinfo($object->getFilename());
                $name = str_replace("_", " ", strstr($info['filename'], "iP"));

                $assets[trim($name)] = $object->getSize();
            }
        }
        return $assets;
    }
    
    protected function checkProtectedVersion($restrict) {
        $allowedTeams = array();
        foreach (@file($restrict) as $line) {
            if (preg_match('/^\s*#/', $line)) continue;
            $items = array_filter(array_map('trim', explode(',', $line)));
            $allowedTeams = array_merge($allowedTeams, $items);
        }
        
        if (!$allowedTeams) return true;
        
        $udid = Router::arg(AppUpdater::PARAM_2_UDID);
        if (!$udid) return false;
        
        $users = AppUpdater::parseUserList();
        if (isset($users[$udid])) {
            return count(array_intersect($users[$udid]['teams'], $allowedTeams)) > 0;
        }
        
        return false;
    }
    
}

?>