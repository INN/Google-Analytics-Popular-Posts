<?php

Class Analytic_Bridge_Service extends Google_Service_Analytics {
	
	public function timezone(&$report) {

		$profileInfo = $report->getProfileInfo();
		$profile = $this->management_profiles->get($profileInfo->accountId,$profileInfo->webPropertyId,$profileInfo->profileId);

		return $profile->timezone;

	}

}