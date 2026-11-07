<?php

use App\Services\MobilePhoneNumber;
use App\Services\SwalaSmsService;
use Illuminate\Support\Facades\Http;

test('mobile phone formats normalize consistently for storage and SMS', function (string $input, string $expected) {
    expect(app(MobilePhoneNumber::class)->normalize($input))->toBe($expected)
        ->and(app(SwalaSmsService::class)->normalizePhone($input))->toBe(substr($expected, 1));
})->with([
    'local Tanzania' => ['0712345678', '+255712345678'],
    'local without trunk prefix' => ['712345678', '+255712345678'],
    'E.164 Tanzania' => ['+255712345678', '+255712345678'],
    'digits Tanzania' => ['255712345678', '+255712345678'],
    'international access prefix' => ['00255712345678', '+255712345678'],
    'formatted Tanzania' => ['+255 (712) 345-678', '+255712345678'],
    'international UK' => ['+447911123456', '+447911123456'],
    'international UK access prefix' => ['00447911123456', '+447911123456'],
    'international US digits' => ['12025550123', '+12025550123'],
]);

test('invalid or unsuitable phone numbers are rejected', function (string $input) {
    app(MobilePhoneNumber::class)->normalize($input);
})->with([
    'empty' => [''],
    'incomplete' => ['0712345'],
    'text mixed with digits' => ['call 0712345678'],
    'extension' => ['0712345678 ext 123'],
    'unknown country code' => ['+999712345678'],
    'Tanzania landline' => ['+255222123456'],
    'invalid Tanzania prefix' => ['+255112345678'],
])->throws(RuntimeException::class, 'The mobile phone number is invalid.');

test('the bulk gateway rejects invalid recipients before an HTTP request', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response([], 202)]);

    try {
        app(SwalaSmsService::class)->sendBulk(
            [['to' => '+255222123456']],
            'Example',
            'Example',
            '0a6fb6f9-206c-47ca-a3a1-de895cc0e7e9',
        );
        test()->fail('An invalid mobile recipient should have been rejected.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('The mobile phone number is invalid.');
    }

    Http::assertNothingSent();
});
