<?php

namespace Bolt\Asset\Widget;

use Bolt\Asset\AssetSortTrait;
use Bolt\Asset\Injector;
use Bolt\Asset\QueueInterface;
use Bolt\Asset\Snippet\Snippet;
use Bolt\Asset\Target;
use Bolt\Common\Thrower;
use Bolt\Controller\Zone;
use Doctrine\Common\Cache\CacheProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Markup;

/**
 * Widget queue processor.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 * @author Bob den Otter <bob@twokings.nl>
 */
class Queue implements QueueInterface
{
    use AssetSortTrait;

    /** @var WidgetAssetInterface[] Queue with snippets of HTML to insert. */
    protected $queue = [];
    /** @var \Bolt\Asset\Injector */
    protected $injector;
    /** @var \Doctrine\Common\Cache\CacheProvider */
    protected $cache;
    /** @var Environment */
    protected $twig;

    /** @var bool */
    private $deferAdded;

    /**
     * Constructor.
     *
     * NOTE: Constructor type hint for Environment omitted for BC, add in v4
     *
     * @param Injector      $injector
     * @param CacheProvider $cache
     * @param Environment   $twig
     */
    public function __construct(Injector $injector, CacheProvider $cache, Environment $twig)
    {
        $this->injector = $injector;
        $this->cache = $cache;
        $this->twig = $twig;
    }

    /**
     * Add a widget to the queue.
     *
     * @param WidgetAssetInterface $widget
     */
    public function add(WidgetAssetInterface $widget)
    {
        $widget->setKey();
        $this->queue[$widget->getKey()] = $widget;
    }

    /**
     * Get a widget from the queue.
     *
     * @param string $key
     *
     * @return WidgetAssetInterface
     */
    public function get($key)
    {
        return $this->queue[$key];
    }

    /**
     * Get a rendered (and potentially cached) widget from the queue.
     *
     * @param string $key
     *
     * @return Markup|string
     */
    public function getRendered($key)
    {
        return $this->getHtml($this->queue[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = [];
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, Response $response)
    {
        /** @var WidgetAssetInterface $widget */
        foreach ($this->queue as $widget) {
            if ($widget->isDeferred()) {
                $this->addDeferredJavaScript($widget, $response);
            }
        }
    }

    /**
     * Get the queued widgets.
     *
     * @return WidgetAssetInterface[]
     */
    public function getQueue()
    {
        return $this->queue;
    }

    public function hasItemsInQueue($location, $zone = Zone::FRONTEND)
    {
        Deprecated::method(3.4, 'Queue::has');

        return $this->has($location, $zone);
    }

    /**
     * Get the number of queued widgets.
     *
     * @param string $location Location (e.g. 'dashboard_aside_top')
     * @param string $zone     Either Zone::FRONTEND or Zone::BACKEND
     *
     * @return bool
     */
    public function has($location, $zone = Zone::FRONTEND)
    {
        return (bool) $this->count($location, $zone);
    }

    public function countItemsInQueue($location, $zone = Zone::FRONTEND)
    {
        Deprecated::method(3.4, 'Queue::count');

        return $this->count($location, $zone);
    }

    /**
     * Get the number of queued widgets.
     *
     * @param string $location Location (e.g. 'dashboard_aside_top')
     * @param string $zone     Either Zone::FRONTEND or Zone::BACKEND
     *
     * @return int
     */
    public function count($location, $zone = Zone::FRONTEND)
    {
        $count = 0;

        foreach ($this->queue as $widget) {
            if ($widget->getZone() === $zone && $widget->getLocation() === $location) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Render a location's widget.
     *
     * @param string $location        Location (e.g. 'dashboard_aside_top')
     * @param string $zone            Either Zone::FRONTEND or Zone::BACKEND
     * @param string $wrapperTemplate Template file for wrapper
     *
     * @return string|null
     */
    public function render($location, $zone = Zone::FRONTEND, $wrapperTemplate = 'widgetwrapper.twig')
    {
        $widgets = [];

        /** @var WidgetAssetInterface $widget */
        foreach ($this->sort($this->queue) as $widget) {
            if ($widget->getZone() !== $zone || $widget->getLocation() !== $location) {
                continue;
            }
            $html = $widget->isDeferred() ? null : $this->getHtml($widget);
            $widgets[] = ['object' => $widget, 'html' => $html];
        }

        if (empty($widgets)) {
            return null;
        }

        return $this->twig->render($wrapperTemplate, ['location' => $location, 'widgets' => $widgets]);
    }

    /**
     * Get the HTML content from the widget.
     *
     * @param WidgetAssetInterface $widget
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getHtml(WidgetAssetInterface $widget)
    {
        $key = 'widget_' . $widget->getKey();
        if ($html = $this->cache->fetch($key)) {
            return $html;
        }

        $html = Thrower::call([$widget, '__toString']);

        if ($widget->getCacheDuration() !== null) {
            $this->cache->save($key, $html, $widget->getCacheDuration());
        }

        return $html;
    }

    /**
     * Insert a snippet of Javascript to fetch the actual widget's contents.
     *
     * @param WidgetAssetInterface $widget
     * @param Response             $response
     */
    protected function addDeferredJavaScript(WidgetAssetInterface $widget, Response $response)
    {
        if ($this->deferAdded) {
            return;
        }

        $javaScript = $this->twig->render('widgetjavascript.twig', ['widget' => $widget]);
        $snippet = Snippet::create()
            ->setCallback((string) $javaScript)
            ->setLocation(Target::AFTER_BODY_JS)
        ;

        $this->deferAdded = true;

        $this->injector->inject($snippet, Target::AFTER_BODY_JS, $response);
    }
}
