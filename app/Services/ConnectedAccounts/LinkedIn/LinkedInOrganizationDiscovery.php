<?php

declare(strict_types=1);

namespace App\Services\ConnectedAccounts\LinkedIn;

use App\Dto\ConnectedAccount\LinkedInOrganization;
use App\Dto\ConnectedAccount\LinkedInOrganizationDiscoveryResult;
use App\Enums\LinkedInOrganizationDiscoveryStatus;
use App\Services\Publishing\Connectors\LinkedInConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Discovers the LinkedIn Organizations (Pages) a member administers, so the
 * connect flow can offer them as connectable accounts. Uses the Community
 * Management ACL finder + Organization batch lookup. Failures are typed so
 * the caller can distinguish an empty successful result from an incomplete
 * discovery without exposing provider response details.
 */
class LinkedInOrganizationDiscovery
{
    private const string ACLS_URL = 'https://api.linkedin.com/rest/organizationAcls';

    private const string ORGANIZATIONS_URL = 'https://api.linkedin.com/rest/organizations';

    /** Safety cap on ACL pagination (20 orgs/page → 400 administered orgs). */
    private const int MAX_PAGES = 20;

    public function __construct(private readonly HttpFactory $http) {}

    public function discover(string $accessToken): LinkedInOrganizationDiscoveryResult
    {
        $urnResult = $this->adminOrganizationUrns($accessToken);

        if ($urnResult['status'] !== LinkedInOrganizationDiscoveryStatus::Succeeded) {
            return new LinkedInOrganizationDiscoveryResult($urnResult['status']);
        }

        if ($urnResult['urns'] === []) {
            return new LinkedInOrganizationDiscoveryResult(LinkedInOrganizationDiscoveryStatus::Succeeded);
        }

        return $this->resolveOrganizations($accessToken, $urnResult['urns']);
    }

    /**
     * @return array{status: LinkedInOrganizationDiscoveryStatus, urns: list<string>}
     */
    private function adminOrganizationUrns(string $accessToken): array
    {
        $urns = [];
        $start = 0;
        $page = 0;

        do {
            try {
                $response = $this->http
                    ->timeout(5)
                    ->connectTimeout(3)
                    ->withToken($accessToken)
                    ->withHeaders($this->headers())
                    ->acceptJson()
                    ->get(self::ACLS_URL, [
                        'q' => 'roleAssignee',
                        'role' => 'ADMINISTRATOR',
                        'state' => 'APPROVED',
                        'count' => 20,
                        'start' => $start,
                    ]);
            } catch (ConnectionException) {
                return ['status' => LinkedInOrganizationDiscoveryStatus::ConnectionFailed, 'urns' => []];
            }

            if ($response->failed()) {
                return ['status' => $this->statusForHttpCode($response->status()), 'urns' => []];
            }

            $elements = $response->json('elements');

            if (! is_array($elements)) {
                return ['status' => LinkedInOrganizationDiscoveryStatus::ProviderFailed, 'urns' => []];
            }

            foreach ($elements as $element) {
                if (! is_array($element)) {
                    return ['status' => LinkedInOrganizationDiscoveryStatus::ProviderFailed, 'urns' => []];
                }

                // Paginated responses use `organizationTarget`; non-paginated use `organization`.
                $urn = (string) ($element['organizationTarget'] ?? $element['organization'] ?? '');

                if ($urn !== '') {
                    $urns[] = $urn;
                }
            }

            $start += count($elements);
            // Hard page cap: this runs synchronously inside the OAuth callback, so
            // a misbehaving `next`-forever response must never spin indefinitely.
            $hasNextLink = $elements !== [] && $this->hasNextPage($response->json('paging.links', []));
            $page++;

            if ($page === self::MAX_PAGES && $hasNextLink) {
                return ['status' => LinkedInOrganizationDiscoveryStatus::ProviderFailed, 'urns' => []];
            }

            $hasNext = $hasNextLink;
        } while ($hasNext);

        return [
            'status' => LinkedInOrganizationDiscoveryStatus::Succeeded,
            'urns' => array_values(array_unique($urns)),
        ];
    }

    /**
     * @param  list<string>  $urns
     */
    private function resolveOrganizations(string $accessToken, array $urns): LinkedInOrganizationDiscoveryResult
    {
        $ids = array_map(fn (string $urn): string => $this->urnId($urn), $urns);

        try {
            $response = $this->http
                ->timeout(5)
                ->connectTimeout(3)
                ->withToken($accessToken)
                ->withHeaders($this->headers())
                ->acceptJson()
                ->get(self::ORGANIZATIONS_URL, ['ids' => 'List('.implode(',', $ids).')']);
        } catch (ConnectionException) {
            return new LinkedInOrganizationDiscoveryResult(LinkedInOrganizationDiscoveryStatus::ConnectionFailed);
        }

        if ($response->failed()) {
            return new LinkedInOrganizationDiscoveryResult($this->statusForHttpCode($response->status()));
        }

        $results = (array) $response->json('results', []);
        $statuses = (array) $response->json('statuses', []);
        $organizations = [];

        foreach ($ids as $id) {
            $status = (int) ($statuses[$id] ?? 200);

            if ($status !== 200) {
                return new LinkedInOrganizationDiscoveryResult($this->statusForHttpCode($status));
            }

            if (! isset($results[$id]) || ! is_array($results[$id])) {
                return new LinkedInOrganizationDiscoveryResult(LinkedInOrganizationDiscoveryStatus::ProviderFailed);
            }

            $result = (array) $results[$id];
            $organizations[] = new LinkedInOrganization(
                id: $id,
                urn: 'urn:li:organization:'.$id,
                name: (string) ($result['localizedName'] ?? $id),
                vanityName: (string) ($result['vanityName'] ?? $id),
            );
        }

        return new LinkedInOrganizationDiscoveryResult(
            LinkedInOrganizationDiscoveryStatus::Succeeded,
            $organizations,
        );
    }

    private function statusForHttpCode(int $status): LinkedInOrganizationDiscoveryStatus
    {
        return match ($status) {
            401 => LinkedInOrganizationDiscoveryStatus::AuthenticationFailed,
            403 => LinkedInOrganizationDiscoveryStatus::PermissionDenied,
            429 => LinkedInOrganizationDiscoveryStatus::RateLimited,
            default => LinkedInOrganizationDiscoveryStatus::ProviderFailed,
        };
    }

    private function hasNextPage(mixed $links): bool
    {
        foreach ((array) $links as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === 'next') {
                return true;
            }
        }

        return false;
    }

    private function urnId(string $urn): string
    {
        $segments = explode(':', $urn);

        return $segments[count($segments) - 1];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'LinkedIn-Version' => (string) config('services.linkedin-openid.api_version', LinkedInConnector::DEFAULT_VERSION),
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }
}
