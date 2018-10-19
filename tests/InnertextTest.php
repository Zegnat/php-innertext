<?php

declare(strict_types=1);

namespace Zegnat\Innertext;

use Masterminds\HTML5;
use PHPUnit\Framework\TestCase;

class InnertextTest extends TestCase
{
    /**
     * @dataProvider dataFiles
     */
    public function testPlaintextOutput(string $htmlFile, string $expectedOutput)
    {
        $dom = new HTML5(['disable_html_ns' => true]);
        $dom = $dom->loadHTMLFile($htmlFile);
        $test = $dom->getElementById('innertexttest');
        $parser = new Innertext();
        $this->assertSame(\file_get_contents($expectedOutput), $parser->innerText($test));
    }

    public function dataFiles(): array
    {
        $names = [];
        $tests = [];
        foreach (\scandir(__DIR__.\DIRECTORY_SEPARATOR.'files') as $file) {
            $name = \pathinfo($file, PATHINFO_FILENAME);
            if ('' !== $name && '.' !== $name[0]) {
                $names[] = \pathinfo($file, PATHINFO_FILENAME);
            }
        }
        foreach (\array_unique($names) as $name) {
            $tests[$name] = [
                \realpath(__DIR__.\DIRECTORY_SEPARATOR.'files'.\DIRECTORY_SEPARATOR.$name.'.html'),
                \realpath(__DIR__.\DIRECTORY_SEPARATOR.'files'.\DIRECTORY_SEPARATOR.$name.'.txt'),
            ];
        }

        return $tests;
    }
}
