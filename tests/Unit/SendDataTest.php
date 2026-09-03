<?php

use App\DTOs\SendData;
use Carbon\CarbonImmutable;

it('includes the id in the attribute array when present', function () {
    $validTo = CarbonImmutable::now()->addDay();

    $data = new SendData(
        userId: 1,
        message: 'secret',
        name: 'My Send',
        validTo: $validTo,
        id: '01900000-0000-7000-8000-000000000001',
    );

    expect($data->toArray())->toBe([
        'id' => '01900000-0000-7000-8000-000000000001',
        'user_id' => 1,
        'message' => 'secret',
        'name' => 'My Send',
        'valid_to' => $validTo,
    ]);
});

it('omits the id from the attribute array when null', function () {
    $validTo = CarbonImmutable::now()->addDay();

    $data = new SendData(
        userId: 7,
        message: 'secret',
        name: 'My Send',
        validTo: $validTo,
    );

    expect($data->toArray())
        ->not->toHaveKey('id')
        ->toBe([
            'user_id' => 7,
            'message' => 'secret',
            'name' => 'My Send',
            'valid_to' => $validTo,
        ]);
});

it('cannot modify properties after construction', function () {
    $data = new SendData(
        userId: 1,
        message: 'secret',
        name: 'My Send',
        validTo: CarbonImmutable::now()->addDay(),
    );

    $data->message = 'changed';
})->throws(Error::class);
