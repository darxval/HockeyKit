<?php

require("OrganisationView.php");

class Renderer {
    
    private $_router;
    private $_appUpdater;
    private $_superView;
    private $_content;
    private $_downloadURL;
    private $_prompt;
    
    public function __construct(AppUpdater $appUpdater, Router $router) {
        $this->_appUpdater = $appUpdater;
        $this->_router = $router;
        $this->_superview = new view("superview.html");
        $this->_content = new view();
    }
    
    public function setDevice($device) {

        $applications = array_filter($this->_appUpdater->applications, function ($item) use ($device) {
            switch($device) {
                case Device::iOS:
                    return $item[AppUpdater::INDEX_PLATFORM] == AppUpdater::APP_PLATFORM_IOS;
                
                case Device::Android:
                    return $item[AppUpdater::INDEX_PLATFORM] == AppUpdater::APP_PLATFORM_ANDROID;
                
                case Device::Desktop:
                    return true;
            }
        });
        
        if (count($applications) == 0) {
            $this->_content = NO_APPS_AVAILABLE_MESSAGE;
        }
        
        if (isset($this->_router->arguments["org"])) {
	        $this->_content = new OrganisationView($this->_appUpdater->applications, $this->_router, $device);
        	return;
        }
        
        
        $shouldUseTable = (count($applications) > 1);
				
        foreach($applications as $app) {
            if (isset($app[AppUpdater::INDEX_SUBTITLE]) && $app[AppUpdater::INDEX_SUBTITLE]) {
                $version = $app[AppUpdater::INDEX_SUBTITLE] . " (" . $app[AppUpdater::INDEX_VERSION] . ")";
            } else {
                $version = $app[AppUpdater::INDEX_VERSION];
            }
			
            $fragment = $shouldUseTable ? "appstable.html" : "app.html";
        
			
            $image = $app[AppUpdater::INDEX_IMAGE];
            $title = $app[AppUpdater::INDEX_APP];
		
            // Configure the view for app information.
            $content = new view($fragment);
            $content->replaceAll(array(
                "image"     => $image,
                "title"     => $title,
                "version"   => $version
            ));
            
            if ($shouldUseTable) {
                $this->_prompt = "Click on the icons below to visit the download page for each app.";
                $platform = isset($app[AppUpdater::ANDROID_SUBPLATFORM]) ? $app[AppUpdater::INDEX_PLATFORM] . " (" . $app[AppUpdater::ANDROID_SUBPLATFORM] . ")" : $app[AppUpdater::INDEX_PLATFORM];
                $content->replace("platform", $platform);
                $content->replace("download_link", "/apps/" . dirname($app['path']));
                $this->_content->append($content);
                continue;
            }
						
            $this->_prompt = $this->promptForPlatform($app[AppUpdater::INDEX_PLATFORM]);
            
            $content->replace("size", round($app[AppUpdater::INDEX_APPSIZE] / 1024 / 1024, 1) . " MB");
            $content->replace("utc_created_time", $app[AppUpdater::INDEX_DATE]);
				
            // Add required buttons to the page.
            $buttons = new view();
            if (isset($app[AppUpdater::INDEX_PROFILE]) && $app[AppUpdater::INDEX_PROFILE]) {
                $button = new view("button.html");
                $button->replaceAll(array(
                    "text"  => "Download Profile",
                    "url"   => $this->_router->baseURL . "api/2/apps/" . $app[AppUpdater::INDEX_DIR] . "?format=mobileprovision"
                ));
                $buttons->append($button);
            }

            // Add download buttons if required.
            if ($app[AppUpdater::INDEX_PLATFORM] == AppUpdater::APP_PLATFORM_IOS ||
                $app[AppUpdater::INDEX_PLATFORM] == AppUpdater::APP_PLATFORM_ANDROID) {
                $button = new view("button.html");
                
                if ($app[AppUpdater::INDEX_PLATFORM] == AppUpdater::APP_PLATFORM_IOS && $device == Device::iOS) {
                    $this->_downloadURL = "itms-services://?action=download-manifest&url=" . urlencode($this->_router->baseURL . "api/2/apps/" . $app[AppUpdater::INDEX_DIR] . "?format=plist");
                }
                else {
                    $this->_downloadURL = $this->_router->baseURL . $app['path'];
                }

                $button->replaceAll(array(
                    "text"  => "Download Application",
                    "url"   => $this->_downloadURL
                ));
                $buttons->append($button);
            }
            
            $content->replace("buttons", $buttons);
            
            if ($app[AppUpdater::INDEX_NOTES]) {
                $releaseNotes = new view("releasenotes.html");
                $releaseNotes->replace("notes", $app[AppUpdater::INDEX_NOTES]);
                $content->replace("releasenotes", $releaseNotes);
            }
            else {
                $content->replace("releasenotes", new view());
            }
            
            $devices = null;
            if (isset($app[AppUpdater::INDEX_DEVICES]) && $device == Device::Desktop) {
                $devices = new view("devices.html");
                
                $message = null;
                $device_list = null;
                if ($app[AppUpdater::INDEX_DEVICES] == AppUpdater::PROVISIONED_ALL_DEVICES) {
                    $message = ENTERPRISE_DEVICES_MESSAGE;
                }
                else {
                    $message = PROVISIONED_DEVICES_MESSAGE;
                    $device_row = new view('device_row.html');
                    foreach ($app[AppUpdater::INDEX_DEVICES] as $device) {
                        $device_list .= $device_row->replace('uuid', $device);
                        $device_row->reset();
                    }
                }
                
                $devices->replaceAll(array(
                    "message" => $message,
                    "device_list" => $device_list
                ));
            }
            
            $content->replace("devices", $devices);
            
            $this->_content->append($content);
        }
    }
    
    private function version() {
        $versionPath = dirname(__FILE__) . "/version.php";
        if (file_exists($versionPath)) {
            require_once($versionPath);
            return GitVersion::COMMIT . " (" . GitVersion::DATE . ")";
        }
        return "";
    }
    
    private function promptForPlatform($platform) {
        $prompt = "";
        if ($platform == AppUpdater::APP_PLATFORM_IOS) {
            $prompt = new view("prompt_ios.html");
        }
        else if ($platform == AppUpdater::APP_PLATFORM_ANDROID) {
            $prompt = new view("prompt_android.html");
        }
        return $prompt;
    }
    
    public function __toString(){
        return $this->_superview->replaceAll(array(
            "content"       => $this->_content, 
            "baseurl"       => $this->_router->baseURL,
            "downloadURL"   => $this->_downloadURL,
            "prompt"        => $this->_prompt,
            "copyright"     => COPYRIGHT_FOOTER,
            "version"       => $this->version()
        ))->get();
    }
}

?>