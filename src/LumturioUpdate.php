<?php

declare(strict_types=1);

namespace LumturioJira;

use RuntimeException;
use SimpleXMLElement;
use stdClass;

class LumturioUpdate
{
    public function __construct(protected stdClass $data)
    {
    }

    public function getShortName(): string
    {
        return \trim($this->data->short_name);
    }

    public function getSecureVersion(): ?string
    {
        $releases = $this->getDrupalUpdates()->releases->release;

        if (!$releases instanceof SimpleXMLElement) {
            return null;
        }

        foreach ($releases as $release) {
            if (\count($release->terms) === 0 || !$release->terms[0] instanceof SimpleXMLElement) {
                continue;
            }

            foreach ($release->terms[0] as $term) {
                if (
                    ($term->name->__toString() === 'Release type') &&
                    ($term->value->__toString() === 'Security update')
                ) {
                    return (string) $release->version;
                }
            }
        }

        return null;
    }

    protected function getMajorVersion(): string
    {
        $majorVersion = \preg_filter('/^([0-9]+)\..*/', '\1', $this->data->current_version);

        if (!\is_string($majorVersion)) {
            throw new RuntimeException('Cound not parse current major version.');
        }

        return $majorVersion;
    }

    protected function getDrupalUpdates(): SimpleXMLElement
    {
        $url = "https://updates.drupal.org/release-history/{$this->getShortName()}/{$this->getMajorVersion()}.x";

        $curl = \curl_init();
        \curl_setopt($curl, \CURLOPT_URL, $url);
        \curl_setopt($curl, \CURLOPT_VERBOSE, false);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, 2);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, 2);
        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, 1);

        $result = \curl_exec($curl);

        if (!\is_string($result)) {
            throw new RuntimeException('Could not get data from updates.drupal.org.');
        }

        $updates = \simplexml_load_string($result);

        if (!$updates instanceof SimpleXMLElement) {
            throw new RuntimeException('Could not parse XML data from updates.drupal.org.');
        }

        return $updates;
    }
}
