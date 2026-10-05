<?php

use App\Models\RoomMaterial;

function roomMaterialWith(int $required, int $issued): RoomMaterial
{
    $roomMaterial = new RoomMaterial;
    $roomMaterial->setRawAttributes([
        'required_quantity' => $required,
        'issued_quantity' => $issued,
    ], true);

    return $roomMaterial;
}

test('issued more than required gives zero outstanding, never negative', function () {
    expect(roomMaterialWith(3000, 4000)->outstandingQuantity())->toBe(0);
});

test('empty stock leaves the whole outstanding amount short', function () {
    $roomMaterial = roomMaterialWith(5000, 1000);

    expect($roomMaterial->outstandingQuantity())->toBe(4000);
    expect($roomMaterial->shortageQuantity(0))->toBe(4000);
    expect($roomMaterial->isShort(0))->toBeTrue();
});

test('stock above the outstanding amount means no shortage', function () {
    $roomMaterial = roomMaterialWith(5000, 1000);

    expect($roomMaterial->shortageQuantity(9000))->toBe(0);
    expect($roomMaterial->isShort(9000))->toBeFalse();
});

test('stock exactly equal to the outstanding amount means no shortage', function () {
    $roomMaterial = roomMaterialWith(5000, 1000);

    expect($roomMaterial->shortageQuantity(4000))->toBe(0);
    expect($roomMaterial->isShort(4000))->toBeFalse();
});
