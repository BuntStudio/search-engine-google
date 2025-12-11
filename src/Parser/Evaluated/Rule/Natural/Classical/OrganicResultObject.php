<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

class OrganicResultObject
{
    protected $title       = null;
    protected $description = null;
    protected $link        = null;
    protected $usedGotoDomainLink = false;

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
     */
    public function setLink($link)
    {
        $this->link = $link;
    }

    /**
     * @return bool
     */
    public function hasUsedGotoDomainLink()
    {
        return $this->usedGotoDomainLink;
    }

    /**
     * @param bool $usedGotoDomainLink
     */
    public function setUsedGotoDomainLink($usedGotoDomainLink)
    {
        $this->usedGotoDomainLink = $usedGotoDomainLink;
    }

}
