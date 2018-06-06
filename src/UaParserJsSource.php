<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2018, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Ua\UserAgent;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Finder\Finder;

class UaParserJsSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'ua-parser-js';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $agent) {
            yield (string) UserAgent::fromUseragent($agent);
        }
    }

    /**
     * @return iterable|array[]
     */
    public function getProperties(): iterable
    {
        $path = 'node_modules/ua-parser-js/test';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        $jsonParser = new JsonParser();

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            try {
                $provider = $jsonParser->parse(
                    $file->getContents(),
                    JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                );
            } catch (ParsingException $e) {
                $this->logger->error(
                    new \Exception(sprintf('file %s contains invalid json.', $file->getPathname()), 0, $e)
                );
                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            $providerName = $file->getFilename();
            $base = [
                'browser' => [
                    'name'    => null,
                    'version' => null,
                ],
                'platform' => [
                    'name'    => null,
                    'version' => null,
                ],
                'device' => [
                    'name'     => null,
                    'brand'    => null,
                    'type'     => null,
                    'ismobile' => null,
                ],
                'engine' => [
                    'name'    => null,
                    'version' => null,
                ],
            ];

            foreach ($provider as $data) {
                $agent = trim($data['ua']);

                if (empty($agent)) {
                    continue;
                }

                if (!isset($agents[$agent])) {
                    $agents[$agent] = $base;
                }

                switch ($providerName) {
                    case 'browser-test.json':
                        $agents[$agent]['browser']['name']    = $data['expect']['name']    === 'undefined' ? '' : $data['expect']['name'];
                        $agents[$agent]['browser']['version'] = $data['expect']['version'] === 'undefined' ? '' : $data['expect']['version'];

                        break;
                    case 'device-test.json':
                        $agents[$agent]['device']['name']  = $data['expect']['model']  === 'undefined' ? '' : $data['expect']['model'];
                        $agents[$agent]['device']['brand'] = $data['expect']['vendor'] === 'undefined' ? '' : $data['expect']['vendor'];
                        $agents[$agent]['device']['type']  = $data['expect']['type']   === 'undefined' ? '' : $data['expect']['type'];

                        break;
                    case 'os-test.json':
                        $agents[$agent]['platform']['name']    = $data['expect']['name']    === 'undefined' ? '' : $data['expect']['name'];
                        $agents[$agent]['platform']['version'] = $data['expect']['version'] === 'undefined' ? '' : $data['expect']['version'];

                        break;
                    // Skipping cpu-test.json because we don't look at CPU data, which is all that file tests against
                    // Skipping engine-test.json because we don't look at Engine data // @todo: fix
                    // Skipping mediaplayer-test.json because it seems that this file isn't used in this project's actual tests (see test.js)
                }
            }
        }

        foreach ($agents as $agent => $test) {
            yield (string) UserAgent::fromUseragent($agent) => $test;
        }
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'node_modules/ua-parser-js/test';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        $jsonParser = new JsonParser();

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            try {
                $provider = $jsonParser->parse(
                    $file->getContents(),
                    JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                );
            } catch (ParsingException $e) {
                $this->logger->error(
                    new \Exception(sprintf('file %s contains invalid json.', $file->getPathname()), 0, $e)
                );
                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            foreach ($provider as $data) {
                $agent = trim($data['ua']);

                if (empty($agent)) {
                    continue;
                }

                yield $agent;
            }
        }
    }
}
