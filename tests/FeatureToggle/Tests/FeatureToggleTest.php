<?php

class FeatureToggleTest extends CTestCase {

    /*
     * @var FeatureToggle
     */
    private $FeatureToggle;


    public function testClassInit(){

        $this->FeatureToggle = new \FeatureToggle\FeatureToggle;


        $this->assertInstanceOf('FeatureToggle\FeatureToggle', $this->FeatureToggle);
    }

    public function testClassInitFail(){


        $this->FeatureToggle = new \FeatureToggle\FeatureToggle;

        $this->assertInstanceOf('FeatureToggle\FeatureToggle', $this->FeatureToggle);
    }



}