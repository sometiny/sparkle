<?php


namespace Sparkle\Traits;


use Sparkle\Application;

trait ApplicationOwner
{
    private ?Application $application = null;

    /**
     * @return Application|null
     */
    public function getApplication(): ?Application
    {
        return $this->application;
    }

    /**
     * @param Application|null $application
     */
    public function setApplication(?Application $application): void
    {
        $this->application = $application;
    }

}
