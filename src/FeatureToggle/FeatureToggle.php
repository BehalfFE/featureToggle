<?php
/**
 * Behalf
 * User: Alex(Shurik) Pustilnik
 * Date: 8/24/15
 */
namespace FeatureToggle;

use LaunchDarkly\LDClient;

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
     * @var string
     */
    public $url;


    public $componentActive = true;

    /**
     * @var Object
     */
    private $user;

    /**
     * @var LDClient
     */
    private $client;

    /**
     * @var array
     */
     public $cacheServer;



    public function init() {
        parent::init();

        if(!$this->isComponentActive()) {
            return;
        }

        try {
            $this->userInfo = call_user_func( $this->userInfoCallable );

            $this->setUser( $this->userInfo );

            $memcacheServerName = $this->cacheServer['host'];
            $memcacheServerPort = $this->cacheServer['port'];

            if($memcacheServerName && $memcacheServerPort) {
                $memcached = new \Memcached();
                $memcached->addServer($memcacheServerName, $memcacheServerPort);
                $cacheDriver = new \Doctrine\Common\Cache\MemcachedCache();
                $cacheDriver->setMemcached($memcached);
                $cacheStorage = new \Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage($cacheDriver);
            }
            else{
                $cacheStorage =  new \Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage(
                                   new \Doctrine\Common\Cache\FilesystemCache('/tmp/')
                                 );
            }

            $this->client = new \LaunchDarkly\LDClient($this->apiKey, array("cache" => $cacheStorage));


            $this->featureToggleUser = (new \LaunchDarkly\LDUserBuilder($this->user->key))
                ->secondary($this->user->secondary)
                ->ip($this->user->ip)
                ->country($this->user->country)
                ->email($this->user->email)
                ->name($this->user->name)
                ->avatar($this->user->avatar)
                ->firstName($this->user->firstName)
                ->lastName($this->user->lastName)
                ->anonymous($this->user->anonymous)
                ->custom(array(
                    'type' => $this->user->type,
                    'parentCompanyId' => $this->user->parentId,
                    'referredAccountId' => $this->user->referredAccountId,
                    'channel' => $this->user->channel
                ))
                ->build();
         } catch (\Exception $ex) {
            $this->componentActive = false;
            \Yii::log("Cannot initiate Feature Toggles: {$ex->getMessage()}", \CLogger::LEVEL_WARNING, 'system.featureToggle');
        }
    }

    public function isComponentActive()
    {
        if($this->componentActive)
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

        if( function_exists('info') ){
            $userId=$this->userInfo->getFTUserKey();
            $output = "[featureKey={$featureKey}] [userId={$userId}]";
            info($output);
        }

        return $this->client->toggle($featureKey, $this->featureToggleUser, $this->defaultReturn);
    }

    /**
     * @return array
     */
    public function flags(){
        if (!$this->isComponentActive()) {
            return array();
        }

        $apiClient = new \GuzzleHttp\Client(array(
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

        $response = $apiClient->get('features');
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
        return array();
    }

    /**
     * registers the javascript code for the feature toggle
     */
    public function registerScript(){}

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


}
