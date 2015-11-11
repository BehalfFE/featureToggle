<?php
/**
 * Created by PhpStorm.
 * User: yairbudic
 * Date: 09/11/2015
 * Time: 6:20 PM
 */

namespace FeatureToggle;

interface FeatureToggleUserInfoInterface
{
    public function getFTUserKey();

    public function getFTUserSecondary();

    public function getFTUserIp();

    public function getFTUserCountry();

    public function getFTUserEmail();

    public function getFTUserName();

    public function getFTUserAvatar();

    public function getFTUserFirstName();

    public function getFTUserLastName();

    public function getFTUserAnonymous();

    public function getFTUserParentId();

    public function getFTUserType();

    public function getFTUserReferredAccountId();

    public function getFTUserChannel();
}