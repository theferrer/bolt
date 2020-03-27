<?php

namespace Bolt\EventListener;

use Bolt\Logger\FlashBagAttachableInterface;
use Bolt\Request\ProfilerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 * @author Carson Full <carsonfull@gmail.com>
 */
class FlashLoggerListener implements EventSubscriberInterface
{
    use ProfilerAwareTrait;

    /** @var FlashBagAttachableInterface */
    protected $flashLogger;
    /** @var bool */
    protected $debug;

    /**
     * Constructor.
     *
     * @param FlashBagAttachableInterface $flashLogger
     * @param bool                        $debug
     */
    public function __construct(FlashBagAttachableInterface $flashLogger, $debug)
    {
        $this->flashLogger = $flashLogger;
        $this->debug = $debug;
    }

    /**
     * Resume the session if it has been started previously or debugging is enabled.
     *
     * @param GetResponseEvent $event
     */
    public function onRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $isProfilerRoute = $this->isProfilerRequest($request);
        if (!$isProfilerRoute && ($this->debug || $request->hasPreviousSession()) && !$session->isStarted()) {
            $this->attachFlashBag($session);
        }
    }

    /**
     * Attach session's flash bag to flash logger if it is started.
     *
     * @param GetResponseEvent|FilterResponseEvent $event
     */
    public function onEvent($event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if ($session && $session->isStarted()) {
            $this->attachFlashBag($session);
        }
    }

    protected function attachFlashBag(SessionInterface $session)
    {
        if (!$this->flashLogger->isFlashBagAttached() && $session instanceof Session) {
            $this->flashLogger->attachFlashBag($session->getFlashBag());
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST  => [
                ['onRequest', 127], // Right after Session is set in Request
                // For Session started in kernel.request events
                ['onEvent', -1024],
            ],
            KernelEvents::RESPONSE => [
                // For Session started in controller
                ['onEvent', 1000],
                // For Session started in kernel.response events
                ['onEvent', -1000], // Before StreamedResponseListener (Same as SaveSessionListener)
            ],
        ];
    }
}
