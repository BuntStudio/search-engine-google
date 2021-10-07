<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule;

use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile\MobileV1;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile\MobileV2;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile\MobileV3;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile\MobileV4;

class AbstractRuleMobile
{
    protected $rulesForParsing;

    protected function generateRules()
    {
        return [
            new MobileV1(),
            new MobileV2(),
            new MobileV3(),
            new MobileV4()
        ];
    }

    public function getRules()
    {
        if (null == $this->rulesForParsing) {
            $this->rulesForParsing = $this->generateRules();
        }

        return $this->rulesForParsing;
    }
}
