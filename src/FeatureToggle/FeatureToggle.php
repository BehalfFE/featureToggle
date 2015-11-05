<?php
/**
 * Behalf
 * User: Alex(Shurik) Pustilnik
 * Date: 8/24/15
 */
 namespace FeatureToggle;

class FeatureToggle extends \ZApplicationComponent {

	/**
	 * @var
	 */
	private $featureToggleClient;

	/**
	 * @var
	 */
	private $featureToggleUser;

	/**
	 * @var string
	 */
	public $apiKey;

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

	/**
	 * @var bool
	 */
	public $featureToggleStatusDisable;

    private $user;

    private $parentCompany;

	/**
	 *
	 */
	public function init() {
		parent::init();

		$this->featureToggleStatusDisable = $this->checkFTStatus();

        try {


            $this->featureToggleClient = new \LaunchDarkly\LDClient($this->apiKey, array(
                'timeout' => 10,
                'connect_timeout' => 10
            ));

            $this->setUser();
            $this->setCompany();

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
                    'parentCompanyName' => $this->parentCompany->name,
                    'parentCompanyEmail' => $this->parentCompany->email,
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
                    )
                )
            ));

            if (app()->config->testing()) {
                app()->eventsManager->addEvent('afterRender', array($this, 'renderFeatureFlags'));
            }
            $this->log();
        } catch (\Exception $ex) {
            $this->featureToggleStatusDisable = true;
            \Yii::log("Cannot initiate Feature Toggles: {$ex->getMessage()}", CLogger::LEVEL_WARNING, 'system.featureToggle');
        }
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
		if ($this->featureToggleStatusDisable){
			return $this->defaultReturn;
		}
		return $this->featureToggleClient->toggle($featureKey, $this->featureToggleUser, $this->defaultReturn);
	}

	/**
	 * Check Feature Toggle if enable or disable from url param query
	 *
	 * @return bool
	 */
	private function checkFTStatus () {
		return isset($_GET["ft_status"]) && $_GET["ft_status"] == "0";
	}
    /**
     * @return array
     */
    public function flags(){
        if ($this->featureToggleStatusDisable) {
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
        $flags = json_encode(app()->featureToggle->flagStates());
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
    protected function log(){
        $logText = app()->user->companyId() . ': ' . json_encode($this->flagStates());
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

    private function setUser(){
        $this->user = new \stdClass();

        $company = new \Company();
        $company->fetch();

        $user = new \User();
        $user->fetch();

        $this->user->key = $company->id;
        $this->user->secondary = null;
        $this->user->ip = \Yii::app()->request->getUserHostAddress();
        $this->user->country = null;
        $this->user->email = $user->email;
        $this->user->name = $company->name;
        $this->user->avatar = null;
        $this->user->firstName = $user->firstName;
        $this->user->lastName = $user->lastName;
        $this->user->anonymous = null;
        $this->user->parentId = $company->parentId;
        $this->user->type = $company->type["name"];
        $this->user->referredAccountId = $company->referringCompanyId;
        $this->user->channel = $company->channel;
    }

    private function setCompany(){
        $this->parentCompany = new \stdClass();

        $this->parentCompany->name  = null;
        $this->parentCompany->email = null;

        $user = new \User(false);
        $company = new \Company(false);

        if ( $this->user->parentId ){
            $company->setId( $this->user->parentId );
            $company->fetch();

            if ( $company ) {
                $this->parentCompany->name  = $company->name;

                $userArray = $user->getUserById($company->userId);
                if ( $user ) {
                    $this->parentCompany->email = $userArray['email'];
                }
            }
        }
    }
}
