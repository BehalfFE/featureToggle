<?php
/**
 * Behalf
 * User: Alex(Shurik) Pustilnik
 * Date: 8/24/15
 */
namespace FeatureToggle;

class FeatureToggle extends \CApplicationComponent {
	/**
	 * @var
	 */
	private $featureToggleUser;

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var FeatureToggleUserInfoInterface
     */
    public $userInfo;

    /**
     * @var Callable
     */
    public $userInfoCallable;

    /**
     * @var bool
     */
    public $defaultReturn = false;

    /**
     * @var \GuzzleHttp\Client
     */
    private $apiClient;

    /**
     * @var string
     */
    public $url;


    public $componentActive = true;

    /**
     * @var Object
     */
    private $user;

    private $featuresList;

	/**
	 *
	 */
    public function init() {
        parent::init();

        if(!$this->isComponentActive()) {
            return;
        }

        try {
            $this->userInfo = call_user_func( $this->userInfoCallable );

            $this->setUser( $this->userInfo );


            $this->featureToggleUser = new \LaunchDarkly\LDUser(
                $this->user->key,
                $this->user->secondary,
                $this->user->ip,
                $this->user->country,
                $this->user->email,
                $this->user->name,
                $this->user->avatar,
                $this->user->firstName,
                $this->user->lastName,
                $this->user->anonymous,
                array(
                    'type' => $this->user->type,
                    'parentCompanyId' => $this->user->parentId,
                    'referredAccountId' => $this->user->referredAccountId,
                    'channel' => $this->user->channel
                )
            );

            $this->apiClient = new \GuzzleHttp\Client(array(
                'base_url' => $this->url,
                'defaults' => array(
                    'headers' => array(
                        'Authorization' => "api_key {$this->apiKey}",
                        'Content-Type' => 'application/json',
                    ),
                    'timeout'         => 10,
                    'connect_timeout' => 10
                )
            ));

            if (app()->hasProperty('config') && app()->config->testing()) {
                app()->eventsManager->addEvent('afterRender', array($this, 'renderFeatureFlags'));
            }
            $this->log( $this->userInfo );
        } catch (\Exception $ex) {
            $this->componentActive = false;
            \Yii::log("Cannot initiate Feature Toggles: {$ex->getMessage()}", \CLogger::LEVEL_WARNING, 'system.featureToggle');
        }
    }

    public function isComponentActive()
    {
        if($this->componentActive && $this->checkFTStatusEnabled())
        {
            return true;
        }

        return false;
    }

	/**
	 * Get status from featureToggle if key is enable or disable:
	 * How to use: app()->featureToggle->isActive("my.key");
	 *
	 * @param string $featureKey
	 * @return bool
	 *
	 * DEMO: app()->featureToggle->isActive("my.key")
	 */
    public function isActive($featureKey) {
        // Main switch is off
        if (!$this->isComponentActive()){
            return $this->defaultReturn;
        }

        // Ensure featureList of the user is set
        $this->fetchUserFeaturesList();

        // Check if requested feature is enabled for the user
        if ( isset($this->featuresList[$featureKey]) ) {
            return $this->featuresList[$featureKey]['_value'] == true;
        }

        return $this->defaultReturn;
    }

    /**
     * Check Feature Toggle if enable or disable from url param query
     *
     * @return bool
     */
    private function checkFTStatusEnabled () {
        if (isset($_GET["ft_status"]) && $_GET["ft_status"] == "0") {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function flags(){
        if (!$this->isComponentActive()) {
            return array();
        }

        $response = $this->apiClient->get('features');
        $flags = $response->json();

        $flagKeys = array();
        foreach($flags['items'] as $flag){
            $flagKeys[] = $flag['key'];
        }

        return $flagKeys;
    }

    /**
     * @return array
     */
    public function flagStates(){
        $flags = $this->flags();

        $flagStates = array();
        foreach($flags as $flag){
            $flagStates[$flag] = $this->isActive($flag);
        }

        return $flagStates;
    }

    /**
     * registers the javascript code for the feature toggle
     */
    public function registerScript(){
        \Yii::app()->clientScript->registerScript('featureToggle', $this->clientScript(), \CClientScript::POS_END);
    }

    /**
     * @return string
     */
    protected function clientScript(){
        $flags = json_encode( $this->flagStates() );
        return "
            require([
                'lib/featureToggle'
            ], function(ft){
                var flags = {$flags};
                ft.init(flags);
            });
        ";
    }

    /**
     * log feature flags and their state
     */
    protected function log( FeatureToggleUserInfoInterface $userInfo ){
        $logText = $userInfo->getFTUserKey() . ': ' . json_encode($this->flagStates());
        \Yii::log($logText, \CLogger::LEVEL_INFO, 'system.featureToggle');
    }

    /**
     * @param CEvent $event
     */
    public function renderFeatureFlags(\CEvent $event){
        $flags = json_encode($this->flagStates());
        $output =  "<div class=\"feature-flags hidden\">{$flags}</div>";
        $event->params['output'] = (isset($event->params['output'])) ? $event->params['output'] . $output : $output;
    }

    private function setUser( FeatureToggleUserInfoInterface $userInfo ){
        $this->user = new \stdClass();

        $this->user->key = $userInfo->getFTUserKey();
        $this->user->secondary = $userInfo->getFTUserSecondary();
        $this->user->ip = $userInfo->getFTUserIp();
        $this->user->country = $userInfo->getFTUserCountry();
        $this->user->email = $userInfo->getFTUserEmail();
        $this->user->name = $userInfo->getFTUserName();
        $this->user->avatar = $userInfo->getFTUserAvatar();
        $this->user->firstName = $userInfo->getFTUserFirstName();
        $this->user->lastName = $userInfo->getFTUserLastName();
        $this->user->anonymous = $userInfo->getFTUserAnonymous();
        $this->user->parentId = $userInfo->getFTUserParentId();
        $this->user->type = $userInfo->getFTUserType();
        $this->user->referredAccountId = $userInfo->getFTUserReferredAccountId();
        $this->user->channel = $userInfo->getFTUserChannel();

    }

    /**
     * Retrieve feature list and each feature state corresponding to the current user.
     */
    private function fetchUserFeaturesList(){
        if ( is_array($this->featuresList) && count($this->featuresList) > 0 ) {
            return;
        }

        try {
            $key = $this->featureToggleUser->getKey();

            $response = $this->apiClient->get("/api/users/$key/features");
            $json_response = $response->json();
            $this->featuresList = isset( $json_response['items'] ) ? $json_response['items'] : array();

        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            error_log("LDClient::toggle received HTTP status code $code, using default");
        }
    }


}
