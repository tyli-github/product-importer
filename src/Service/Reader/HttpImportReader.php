<?php

declare(strict_types=1);

namespace App\Service\Reader;

use App\Entity\ImportJob;
use App\Interface\ImportReaderInterface;
use App\Service\ImportSourceDetector;
use Generator;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class HttpImportReader implements ImportReaderInterface
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function supports(string $source): bool
    {
        return ImportSourceDetector::isHttpUrl($source) === true;
    }

    /**
     * @return Generator<int, array<string, string>>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function read(string $source): Generator
    {
        $response = $this->httpClient->request('GET', $source);
        $data = $response->toArray();

        // Support flat array or wrapped: {"products": [...]}
        $rows = array_is_list($data) ? $data : (array_values(array_filter($data, 'is_array'))[0] ?? []);

        if ($rows === []) {
            throw new RuntimeException(sprintf('No product rows found in response from: %s', $source));
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeRow($row);

            // convert all values to strings: not ideal or correct but "good enough" as an example import for now
            $stringified = array_map(
                static fn($value): string => is_scalar($value) ? (string) $value : (is_array($value) ? json_encode($value) : ''),
                $normalized
            );

            yield ($index + 1) => $stringified;
        }
    }

    public function getSourceType(): string
    {
        return ImportJob::SOURCE_TYPE_HTTP;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row['title']) && !isset($row['name'])) {
            $row['name'] = $row['title'];
            unset($row['title']);
        }

        return $row;
    }
}
