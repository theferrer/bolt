<?php

namespace Bolt\Tests\Twig\Runtime;

use Bolt\Legacy\Content;
use Bolt\Tests\BoltUnitTest;
use Bolt\Twig\Runtime\HtmlRuntime;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class to test Bolt\Twig\Runtime\HtmlRuntime.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class HtmlRuntimeTest extends BoltUnitTest
{
    public function testDecorateTT()
    {
        $handler = $this->getHtmlRuntime();

        $result = $handler->decorateTT('Lorem `ipsum` dolor.');
        $this->assertSame('Lorem <tt>ipsum</tt> dolor.', $result);
    }

    public function testEditable()
    {
        $app = $this->getApp();
        $handler = $this->getHtmlRuntime();
        $content = new Content($app);
        $content->setValues([
            'id'          => 42,
            'contenttype' => ['slug' => 'snail'],
        ]);

        $result = $handler->editable('<blink>Drop Bear Warning!</blink>', $content, 'paddock');
        $this->assertSame('<div class="Bolt-editable" data-id="42" data-contenttype="" data-field="paddock"><blink>Drop Bear Warning!</blink></div>', $result);
    }

    public function testMarkdown()
    {
        $handler = $this->getHtmlRuntime();

        $markdown = <<<MARKDOWN
# Episode IV
## A New Hope
It is a period of refactor war.
* BPFL
MARKDOWN;

        $html = <<< HTML
<h1>Episode IV</h1>
<h2>A New Hope</h2>
<p>It is a period of refactor war.</p>
<ul>
<li>BPFL</li>
</ul>
HTML;
        $result = $handler->markdown($markdown);
        $this->assertSame($html, $result);
    }

    public function testMenuMain()
    {
        $app = $this->getApp();
        $request = Request::createFromGlobals();
        $app['request'] = $request;
        $app['request_stack']->push($request);

        $handler = $this->getHtmlRuntime();

        $result = $handler->menu($app['twig'], 'main', 'partials/_sub_menu.twig', ['kitten' => 'fluffy']);
        $this->assertRegExp('#<li class="index-1 first">#', $result);
    }

    public function testShy()
    {
        $handler = $this->getHtmlRuntime();

        $result = $handler->shy('SomePeopleSayTheyAreShyOtherPeopleSayTheyAreNotWhatDoYouSay');
        $this->assertSame('SomePeople&shy;SayTheyAre&shy;ShyOtherPe&shy;opleSayThe&shy;yAreNotWha&shy;tDoYouSay', $result);
    }

    /**
     * @return HtmlRuntime
     */
    protected function getHtmlRuntime()
    {
        $app = $this->getApp();

        return new HtmlRuntime(
            $app['config'],
            $app['markdown'],
            $app['menu'],
            $app['storage']
        );
    }
}
