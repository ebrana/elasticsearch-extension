<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Bridges\Tracy;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;

final readonly class PlaygroundRequestHandler
{
    private const string GENERATE_PATH = '/_tracy/elasticsearch/playground/generate';
    private const string EXECUTE_PATH = '/_tracy/elasticsearch/playground/execute';
    private const string SESSION_SECTION = 'elasticsearch-playground';
    private const string SESSION_TOKEN = 'csrf-token';

    public function __construct(
        private Request $httpRequest,
        private Response $httpResponse,
        private Session $session,
        private PlaygroundService $playgroundService,
    ) {
    }

    /**
     * @throws \Random\RandomException
     * @throws \JsonException
     */
    public function handle(): void
    {
        $path = $this->getRequestPath();
        if ($path !== self::GENERATE_PATH && $path !== self::EXECUTE_PATH) {
            return;
        }

        if (strtoupper($this->httpRequest->getMethod()) !== 'POST') {
            $this->respond(['error' => 'Method not allowed.'], 405);
        }

        $token = (string) $this->httpRequest->getPost('_token', '');
        if (!hash_equals($this->getCsrfToken(), $token)) {
            $this->respond(['error' => 'Invalid CSRF token.'], 403);
        }

        $query = trim((string) $this->httpRequest->getPost('query', ''));
        if ($query === '') {
            $this->respond(['error' => 'Query is required.'], 400);
        }

        $index = $this->httpRequest->getPost('index');
        $operation = trim((string) $this->httpRequest->getPost('operation', 'search'));

        try {
            $data = $path === self::EXECUTE_PATH
                ? $this->playgroundService->execute($query, is_string($index) ? $index : null, $operation)
                : $this->playgroundService->generatePhp($query);
        } catch (\Throwable $e) {
            $this->respond(['error' => $e->getMessage()], 400);
        }

        $this->respond($data, 200);
    }

    /**
     * @return string[]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getAllowedIndices(): array
    {
        return $this->playgroundService->getAllowedIndices();
    }

    public function getGenerateUrl(): string
    {
        return $this->buildUrl(self::GENERATE_PATH);
    }

    public function getExecuteUrl(): string
    {
        return $this->buildUrl(self::EXECUTE_PATH);
    }

    /**
     * @throws \Random\RandomException
     */
    public function getCsrfToken(): string
    {
        $section = $this->session->getSection(self::SESSION_SECTION);
        $token = $section->get(self::SESSION_TOKEN);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $section->set(self::SESSION_TOKEN, $token);
        }

        return $token;
    }

    private function buildUrl(string $path): string
    {
        return rtrim($this->httpRequest->getUrl()->getBasePath(), '/') . $path;
    }

    private function getRequestPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $basePath = rtrim($this->httpRequest->getUrl()->getBasePath(), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws \JsonException
     */
    private function respond(array $payload, int $code): never
    {
        $this->httpResponse->setCode($code);
        $this->httpResponse->setContentType('application/json', 'utf-8');
        echo (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
