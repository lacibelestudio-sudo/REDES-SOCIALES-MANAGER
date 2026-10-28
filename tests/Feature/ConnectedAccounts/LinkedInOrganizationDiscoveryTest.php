<?php

use App\Enums\LinkedInOrganizationDiscoveryStatus;
use App\Services\ConnectedAccounts\LinkedIn\LinkedInOrganizationDiscovery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('discovers administered organizations and resolves their names', function () {
    Http::fake([
        'https://api.linkedin.com/rest/organizationAcls*' => Http::response([
            'elements' => [
                ['role' => 'ADMINISTRATOR', 'state' => 'APPROVED', 'organizationTarget' => 'urn:li:organization:2414183'],
                ['role' => 'ADMINISTRATOR', 'state' => 'APPROVED', 'organization' => 'urn:li:organization:79988552'],
            ],
            'paging' => ['start' => 0, 'count' => 10, 'links' => []],
        ]),
        'https://api.linkedin.com/rest/organizations*' => Http::response([
            'results' => [
                '2414183' => ['id' => 2414183, 'localizedName' => 'Acme Inc', 'vanityName' => 'acme'],
                '79988552' => ['id' => 79988552, 'localizedName' => 'Demo Co', 'vanityName' => 'democo'],
            ],
            'statuses' => ['2414183' => 200, '79988552' => 200],
        ]),
    ]);

    $result = app(LinkedInOrganizationDiscovery::class)->discover('tok');

    expect($result->status)->toBe(LinkedInOrganizationDiscoveryStatus::Succeeded)
        ->and($result->organizations)->toHaveCount(2)
        ->and($result->organizations[0]->urn)->toBe('urn:li:organization:2414183')
        ->and($result->organizations[0]->id)->toBe('2414183')
        ->and($result->organizations[0]->name)->toBe('Acme Inc')
        ->and($result->organizations[0]->vanityName)->toBe('acme');
});

test('distinguishes successful discovery without organizations from a failed lookup', function () {
    Http::fake(['https://api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => []])]);

    $result = app(LinkedInOrganizationDiscovery::class)->discover('tok');

    expect($result->status)->toBe(LinkedInOrganizationDiscoveryStatus::Succeeded)
        ->and($result->organizations)->toBe([]);
});

test('reports organization acl failures by safe category', function (int $status, LinkedInOrganizationDiscoveryStatus $expected): void {
    Http::fake(['https://api.linkedin.com/rest/organizationAcls*' => Http::response([], $status)]);

    $result = app(LinkedInOrganizationDiscovery::class)->discover('tok');

    expect($result->status)->toBe($expected)
        ->and($result->organizations)->toBe([]);
})->with([
    'expired token' => [401, LinkedInOrganizationDiscoveryStatus::AuthenticationFailed],
    'missing permission' => [403, LinkedInOrganizationDiscoveryStatus::PermissionDenied],
    'rate limited' => [429, LinkedInOrganizationDiscoveryStatus::RateLimited],
    'provider error' => [503, LinkedInOrganizationDiscoveryStatus::ProviderFailed],
]);

test('reports connection failures separately from a successful empty discovery', function () {
    Http::fake(fn () => throw new ConnectionException('offline'));

    $result = app(LinkedInOrganizationDiscovery::class)->discover('tok');

    expect($result->status)->toBe(LinkedInOrganizationDiscoveryStatus::ConnectionFailed)
        ->and($result->organizations)->toBe([]);
});

test('reports failures resolving organization details instead of presenting no pages', function () {
    Http::fake([
        'https://api.linkedin.com/rest/organizationAcls*' => Http::response([
            'elements' => [['organizationTarget' => 'urn:li:organization:2414183']],
        ]),
        'https://api.linkedin.com/rest/organizations*' => Http::response([
            'results' => [
                '2414183' => ['id' => 2414183, 'localizedName' => 'Acme Inc', 'vanityName' => 'acme'],
            ],
            'statuses' => ['2414183' => 403],
        ]),
    ]);

    $result = app(LinkedInOrganizationDiscovery::class)->discover('tok');

    expect($result->status)->toBe(LinkedInOrganizationDiscoveryStatus::PermissionDenied)
        ->and($result->organizations)->toBe([]);
});
