<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\Module;
use Yii;

/**
 * Ayda API client. Passwords and tokens are not written to the log.
 */
class AydaClient
{
    private Module $module;

    public function __construct(?Module $module = null)
    {
        $this->module = $module ?: SecretStore::module();
    }

    public function configured(): bool
    {
        return $this->module->hasCredentials();
    }

    /**
     * @return array{token:string,expiry:string}
     */
    public function token(bool $force = false): array
    {
        if (!$force) {
            $cached = SecretStore::decrypt((string)$this->module->settings->get(Module::SETTING_TOKEN, ''));
            $expiry = (string)$this->module->settings->get(Module::SETTING_TOKEN_EXPIRY, '');
            if ($cached !== '' && $expiry !== '' && strtotime($expiry) > time() + 60) {
                return ['token' => $cached, 'expiry' => $expiry];
            }
        }

        $username = $this->module->getApiUsername();
        $password = $this->module->getApiPassword();
        if ($username === '' || $password === '') {
            throw new AydaException('Ayda API username and password are not set.');
        }

        $response = $this->raw('GET', '/Authorization/Token', null, [
            'Authorization: Basic ' . base64_encode($username . ':' . $password),
            'Accept: application/json',
        ]);
        $token = (string)($response['token'] ?? '');
        $expiry = (string)($response['expiry'] ?? '');
        if ($token === '') {
            throw new AydaException('Ayda did not return a token.');
        }
        $this->module->settings->set(Module::SETTING_TOKEN, SecretStore::encrypt($token));
        $this->module->settings->set(Module::SETTING_TOKEN_EXPIRY, $expiry);
        return ['token' => $token, 'expiry' => $expiry];
    }

    public function clearToken(): void
    {
        $this->module->settings->set(Module::SETTING_TOKEN, '');
        $this->module->settings->set(Module::SETTING_TOKEN_EXPIRY, '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function wallets(?string $currency = null): array
    {
        $query = ['Page' => 1, 'PageSize' => 50];
        if ($currency) {
            $query['CurrencyCode'] = strtoupper($currency);
        }
        $page = $this->send('GET', '/Wallets', null, $query);
        return $this->items($page);
    }

    public function walletBalance(string $currency): ?float
    {
        foreach ($this->wallets($currency) as $wallet) {
            if (strtoupper((string)($wallet['currencyCode'] ?? '')) === strtoupper($currency) && isset($wallet['balance'])) {
                return (float)$wallet['balance'];
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createProject(string $name, string $currency, string $reference, string $description): array
    {
        return $this->send('POST', '/Projects', [
            'name' => mb_substr($name, 0, 128),
            'reference' => mb_substr($reference, 0, 36),
            'description' => mb_substr($description, 0, 2000),
            'currencyCode' => strtoupper($currency),
        ]);
    }

    /**
     * Ayda can create a project and still return an error. Match the exact name and currency.
     *
     * @return array<string, mixed>|null
     */
    public function findProject(string $name, string $currency): ?array
    {
        $page = $this->send('GET', '/Projects', null, [
            'Name' => $name,
            'CurrencyCode' => strtoupper($currency),
            'Page' => 1,
            'PageSize' => 50,
        ]);
        foreach ($this->items($page) as $project) {
            if (strcasecmp((string)($project['name'] ?? ''), $name) !== 0) {
                continue;
            }
            if (strtoupper((string)($project['currencyCode'] ?? '')) !== strtoupper($currency)) {
                continue;
            }
            return $project;
        }
        return null;
    }

    /**
     * @param float[] $amounts
     * @return array{feeAmount:float,taxAmount:float,totalAmount:float}
     */
    public function fees(string $projectId, array $amounts): array
    {
        $response = $this->send('POST', '/Projects/' . rawurlencode($projectId) . '/Fees', [
            'incentives' => array_values(array_map('floatval', $amounts)),
        ]);
        return [
            'feeAmount' => (float)($response['feeAmount'] ?? 0),
            'taxAmount' => (float)($response['taxAmount'] ?? 0),
            'totalAmount' => (float)($response['totalAmount'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function authorise(array $payload): array
    {
        return $this->send('POST', '/Incentives/Authorise', $payload);
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     * @return array<string, mixed>
     */
    public function authoriseBatch(array $payloads): array
    {
        return $this->send('POST', '/Incentives/Authorise/Batch', $payloads);
    }

    /**
     * @return array<string, mixed>
     */
    public function batch(string $id): array
    {
        return $this->send('GET', '/Batches/' . rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function incentive(string $id): array
    {
        return $this->send('GET', '/Incentives/' . rawurlencode($id));
    }

    public function approve(string $id, string $reason): void
    {
        $this->send('PUT', '/Incentives/' . rawurlencode($id) . '/Approve', [
            'reason' => mb_substr($reason, 0, 128),
        ]);
    }

    public function cancel(string $id, string $reason): void
    {
        $this->send('PUT', '/Incentives/' . rawurlencode($id) . '/Cancel', [
            'reason' => mb_substr($reason, 0, 128),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function participantsByEmail(string $email): array
    {
        $page = $this->send('GET', '/Participants', null, [
            'Email' => $email,
            'Page' => 1,
            'PageSize' => 10,
        ]);
        return $this->items($page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectParticipants(string $projectId, string $email): array
    {
        $page = $this->send('GET', '/ProjectParticipants', null, [
            'Email' => $email,
            'ProjectId' => $projectId,
            'Page' => 1,
            'PageSize' => 10,
        ]);
        return $this->items($page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectParticipantIncentives(string $projectParticipantId): array
    {
        $response = $this->send('GET', '/ProjectParticipants/' . rawurlencode($projectParticipantId) . '/Incentives');
        return $this->items($response);
    }

    public function forgetParticipant(string $id): void
    {
        $this->send('PUT', '/Participants/' . rawurlencode($id) . '/Forget');
    }

    public function forgetProjectParticipant(string $id): void
    {
        $this->send('PUT', '/ProjectParticipants/' . rawurlencode($id) . '/Forget');
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|array<int, array<string, mixed>>|null $body
     * @return array<string, mixed>|array<int, mixed>
     */
    private function send(string $method, string $path, $body = null, array $query = [], bool $retried = false)
    {
        $token = $this->token();
        try {
            return $this->raw($method, $path, $body, [
                'Authorization: Bearer ' . $token['token'],
                'Accept: application/json',
                'Content-Type: application/json',
            ], $query);
        } catch (AydaException $e) {
            if ($e->status === 401 && !$retried) {
                $this->clearToken();
                $this->token(true);
                return $this->send($method, $path, $body, $query, true);
            }
            throw $e;
        }
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|array<int, mixed>|null $body
     * @return array<string, mixed>|array<int, mixed>
     */
    private function raw(string $method, string $path, $body, array $headers, array $query = []): array
    {
        $url = $this->module->getApiBase() . $path;
        if ($query !== []) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new AydaException('Could not start the Ayda request.');
        }
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            Yii::warning('Ayda request failed: ' . $method . ' ' . $path . ' ' . $error, 'thiscovery-incentives');
            throw new AydaException('Ayda could not be reached.');
        }
        if ($status === 204 || trim((string)$raw) === '') {
            if ($status >= 400) {
                throw new AydaException($this->failureMessage($status, ''), $status);
            }
            return [];
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            if ($status >= 400) {
                throw new AydaException($this->failureMessage($status, (string)$raw), $status);
            }
            throw new AydaException('Ayda returned a response that could not be read.', $status);
        }
        if ($status >= 400) {
            Yii::warning('Ayda ' . $status . ' ' . $method . ' ' . $path . ' ' . $this->failureMessage($status, $decoded), 'thiscovery-incentives');
            throw new AydaException($this->failureMessage($status, $decoded), $status);
        }
        return $decoded;
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function failureMessage(int $status, $body): string
    {
        $parts = [];
        if (is_array($body)) {
            foreach (['detail', 'title'] as $key) {
                if (!empty($body[$key]) && is_string($body[$key])) {
                    $parts[] = $body[$key];
                }
            }
            if (!empty($body['errors']) && is_array($body['errors'])) {
                foreach ($body['errors'] as $messages) {
                    foreach ((array)$messages as $message) {
                        if (is_string($message) && $message !== '') {
                            $parts[] = $message;
                        }
                    }
                }
            }
        } elseif (is_string($body) && trim($body) !== '') {
            $parts[] = trim($body);
        }
        if (is_array($body) && array_is_list($body)) {
            foreach ($body as $item) {
                if (is_string($item) && $item !== '') {
                    $parts[] = $item;
                } elseif (is_array($item)) {
                    foreach (['detail', 'title', 'message', 'errorMessage'] as $key) {
                        if (!empty($item[$key]) && is_string($item[$key])) {
                            $parts[] = $item[$key];
                        }
                    }
                }
            }
        }
        $detail = trim(implode(' ', array_unique($parts)));
        if ($detail === '' && is_array($body)) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
            if (is_string($encoded) && $encoded !== '' && $encoded !== '[]' && $encoded !== '{}') {
                $detail = $encoded;
            }
        }
        if ($detail === '') {
            return 'Ayda rejected the request (' . $status . ').';
        }
        return 'Ayda rejected the request (' . $status . '): ' . mb_substr($detail, 0, 500);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function items(array $payload): array
    {
        if ($payload === []) {
            return [];
        }
        if (array_is_list($payload)) {
            return $payload;
        }
        foreach (['items', 'results', 'data', 'value'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }
        return [$payload];
    }
}
