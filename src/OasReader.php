<?php

declare(strict_types=1);

namespace app;

class OasReader
{
    public function getData(string $source): array
    {
        // get from url
        $parsedUrl = parse_url($source);

        if (isset($parsedUrl['scheme'])) {
            printInfo('Detecting url, fetching data...');

            $curl = curl_init($source);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($curl);

            if (curl_errno($curl)) {
                printError('Error:' . curl_error($curl));
            }

            curl_close($curl);

            return $this->toArray($data);
        }

        // get from file
        if (file_exists($source)) {
            printInfo('Detected file path, fetching data...');

            $data = file_get_contents($source);

            return $this->toArray($data);
        }

        return [];
    }

    protected function toArray(string $data): array
    {
        printInfo('Specs reading...');

        return json_decode($data, true) ?: yaml_parse($data);
    }
}
