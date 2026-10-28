<?php

use App\Enums\InstagramAccountType;

test('normalizes provider account types without guessing unknown values', function (mixed $providerValue, InstagramAccountType $expected): void {
    expect(InstagramAccountType::fromProvider($providerValue))->toBe($expected);
})->with([
    'Business' => ['BUSINESS', InstagramAccountType::Business],
    'Creator' => ['MEDIA_CREATOR', InstagramAccountType::Creator],
    'missing stays unknown' => [null, InstagramAccountType::Unknown],
    'unrecognized stays unknown' => ['PERSONAL', InstagramAccountType::Unknown],
]);
