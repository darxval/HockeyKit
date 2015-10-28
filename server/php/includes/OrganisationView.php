<?php
	
	class OrganisationView extends view {
		
		private $_applications;
		private $_device;
		private $_router;
		
		function __construct($applications, $router, $device) {
			parent::__construct("orglist.html");
			
			$this->_applications = $applications;
			$this->_device = $device;
			$this->_router = $router;
			
			$this->generateViews();
		}
		
		
		private function generateViews() {
			
			$releases = new view();

			foreach($this->_applications["RCDirectories"] as $type => $releaseCandidates) {
				
				$group = $this->groupForAppCluster($releaseCandidates);
				
				$group->replaceAll(array(
					"title" => $type,
					"panel-type" => "success"
				));
				$releases->append($group);
			}
			
			$this->replace("release-candidates", $releases);
			
			
			$snapshots = new view();
			
			foreach($this->_applications["SnapshotDirectories"] as $type => $releaseCandidates) {
				
				$group = $this->groupForAppCluster($releaseCandidates);
				
				$group->replaceAll(array(
					"title" => $type,
					"panel-type" => "warning"
				));
				$snapshots->append($group);
			}
			
			$this->replace("snapshots", $snapshots);
			
			$group = $this->appsForCluster($this->_applications["Uncategorised"], false);
			$this->replace("uncategorised", $group);
		}
		
		
		private function groupForAppCluster($appCluster) {
			
			$group = new view("orggroup.html");

			$apps = $this->appsForCluster($appCluster);
			
			$showMoreStyle = count($appCluster) > 1 ? "" : "display:none";
			$group->replaceAll(array("apps" => $apps, "showolder-style" => $showMoreStyle));
			
			return $group;
		}
		
		private function appsForCluster($appCluster, $hideOlderApps = true) {
			$apps = new view();
			
			// Loop over apps
			foreach($appCluster as $index => $candidate) {
				
				$release = new view("orgapp.html");
				
				$real_path = $candidate["dir"] . $candidate["directory_path"];
				
				if ($candidate[AppUpdater::INDEX_PLATFORM] == AppUpdater::APP_PLATFORM_IOS && $this->_device == Device::iOS) {
                    $url = "itms-services://?action=download-manifest&url=" . urlencode($this->_router->baseURL . "api/2/apps/" . $real_path . "?format=plist");
                }
                else {
                    $url = $this->_router->baseURL . $candidate['path'];
                }
				
				$release->replaceAll(array(
					"name"	=> $candidate["app"],
					"icon"  => $candidate[AppUpdater::INDEX_IMAGE],
					"version" => $candidate[AppUpdater::INDEX_VERSION],
					"size" => round($candidate[AppUpdater::INDEX_APPSIZE] / 1024 / 1024, 1) . " MB",
					"updated" => $candidate[AppUpdater::INDEX_DATE],
					"url"	=> $url,
					"style" => ($index == 0 || !$hideOlderApps) ? "" : "display:none",
					"devices" => count($candidate[AppUpdater::INDEX_DEVICES]) . " devices",
					"info-url"	=> "/apps/" . $real_path
				));
				
				if ($hideOlderApps) {
					$release->replace('feature-name', '');
				} else {
					$release->replace('feature-name', " <span class='label label-info'>" . $candidate["directory_path"] . "</span>");
				}
				
				$apps->append($release);
			}
			
			return $apps;
		}
	}