<?php

class Renderer {
    
    private $_router;
    private $_appUpdater;
    private $_superView;
    private $_content;
    private $_downloadURL;
    
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
        
        foreach($applications as $app) {
            if (isset($app[AppUpdater::INDEX_SUBTITLE]) && $app[AppUpdater::INDEX_SUBTITLE]) {
                $version = $app[AppUpdater::INDEX_SUBTITLE] . " (" . $app[AppUpdater::INDEX_VERSION] . ")";
            } else {
                $version = $app[AppUpdater::INDEX_VERSION];
            }
        
            // Configure the view for app information.
            $content = new view("app.html");
            $content->replaceAll(array(
                "image"     => $app[AppUpdater::INDEX_IMAGE],
                "title"     => $app[AppUpdater::INDEX_APP],
                "version"   => $version,
                "size"      => round($app[AppUpdater::INDEX_APPSIZE] / 1024 / 1024, 1) . " MB",
                "released"  => date('m/d/Y H:i:s', $app[AppUpdater::INDEX_DATE])
            ));
            
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
                $this->_downloadURL = $this->_router->baseURL . $app['path'];
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
            
            $this->_content->append($content);
        }
    }
    
    
    public function __toString(){
        return $this->_superview->replaceAll(array(
            "content"       => $this->_content, 
            "baseurl"       => $this->_router->baseURL,
            "downloadURL"   => $this->_downloadURL,
            "copyright"     => COPYRIGHT_FOOTER
        ))->get();
    }
}

?>