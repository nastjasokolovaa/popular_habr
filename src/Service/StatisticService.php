<?php

namespace App\Service;

use JsonException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutput;

class StatisticService
{
    private const DURATION = 'duration';
    private const COUNT = 'count';

    /** @throws JsonException */
    public function getByPath(string $path) {
        if (!file_exists($path)) {
            die("Error: file {$path} is not found.\n");
        }

        $fp = fopen($path, 'rt');
        if (!$fp) {
            die("Error: unable to open file: {$path}");
        }

        $buf = '';
        $inString = false;
        $escape = false;
        $jsonStart = -1;

        $result = [];
        while (!feof($fp)) {
            $buf .= fread($fp, 4096);
            $bufLen = strlen($buf);
            for ($i = 0; $i < $bufLen; $i++) {
                $char = $buf[$i];
                if ($inString) {
                    if ($escape) {
                        $escape = false;
                    } elseif ($char === '\\') {
                        $escape = true;
                    } elseif ($char === '"') {
                        $inString = false;
                    }
                    continue;
                }

                if ($char === '"') {
                    $inString = true;
                    continue;
                }

                if ($jsonStart <= -1) {
                    if ($char === '{') {
                        $jsonStart = $i;
                    } else {
                        die("Corrupted file near: {$buf}");
                    }
                } else {
                    if ($char === '}') {
                        $jsonStr = substr($buf, $jsonStart, $i - $jsonStart + 1);
                        $this->appendToTable($result, $jsonStr);

                        $buf = substr($buf, $i + 1);
                        $i = -1;
                        $jsonStart = -1;
                        $bufLen = strlen($buf);
                    }
                }
            }
        }
        fclose($fp);
        $this->printTable($this->filterTopURLs($result));
    }

    /** @throws JsonException */
    private function appendToTable(array &$resultTable, string $jsonStr): void
    {
        $row = json_decode($jsonStr, flags: JSON_THROW_ON_ERROR);
        $date = date('Y-m-d', $row->time);
        $url = $this->cleanURL($row->url);

        if (!isset($resultTable[$date][$url])) {
            $resultTable[$date][$url] = [
                self::DURATION => 0.0,
                self::COUNT => 0,
            ];
        }
        $oldDuration = $resultTable[$date][$url][self::DURATION];
        $oldTimes = $resultTable[$date][$url][self::COUNT];
        $resultTable[$date][$url][self::DURATION] = (($oldDuration * $oldTimes) + $row->duration) / ($oldTimes + 1);
        $resultTable[$date][$url][self::COUNT]++;
    }

    private function cleanURL(string $url): string
    {
        $parsedURL = parse_url($url);
        // Assume that the port is ommitted.
        return $parsedURL['scheme'] . '://' . $parsedURL['host'] . ($parsedURL['path'] ?? '');
    }

    private function filterTopURLs(array $data): array
    {
        foreach ($data as &$urls) {
            uasort($urls, function ($a, $b) {
                return $b[self::COUNT] <=> $a[self::COUNT];
            });

            $urls = array_slice($urls, 0, 3, true);
        }
        unset($urls);

        return $data;
    }

    private function printTable(array $data): void
    {
        $table = new Table(new ConsoleOutput());
        $table->setHeaders(['Date', 'URL', 'Count', 'Duration']);

        foreach ($data as $date => $urls) {
            $firstRow = true;

            foreach ($urls as $url => $info) {
                $table->addRow([
                    $firstRow ? $date : '',
                    $url,
                    $info[self::COUNT],
                    round($info[self::DURATION], 1),
                ]);
                $firstRow = false;
            }
            $table->addRow(new TableSeparator());
        }
        $table->render();
    }
}
