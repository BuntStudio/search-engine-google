<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

class OrganicResultObject
{
    protected $title       = null;
    protected $description = null;
    protected $link        = null;

    /**
     * @return null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param null $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param null $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return null
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @param null $link
     * @param string $onlyRemoveSrsltidForDomain
     */
    public function setLink($link, string $onlyRemoveSrsltidForDomain = '')
    {
        $this->link = \SM_Rank_Service::getUrlFromGoogleTranslate(\Utils::removeParamFromUrl($link));
        if(
            $onlyRemoveSrsltidForDomain &&
            \Utils::wwwhost($onlyRemoveSrsltidForDomain) != \Utils::wwwhost(\SM_Rank_Service::getUrlFromGoogleTranslate($link))
        ) {
            // if $onlyRemoveSrsltidForDomain present, only remove srsltid for specified domain
            $this->link = \SM_Rank_Service::getUrlFromGoogleTranslate($link);
        }
    }

}
