<?php

use App\Models\Vehicle;
use App\Support\GatepassPinNormalizer;

test('vehicle can save gatepass_pin longer than 11 characters', function () {
    $vehicle = Vehicle::factory()->create();
    $pin = 'ACA6';

    $vehicle->update([
        'gatepass_pin' => $pin,
    ]);

    expect($vehicle->fresh()->gatepass_pin)->toBe('ACA6');
});

test('gatepass_pin_normalizer strips common prefixes like Pick up PIN:', function () {
    expect(GatepassPinNormalizer::normalize('Pick up PIN: ACA6'))->toBe('ACA6');
    expect(GatepassPinNormalizer::normalize('pick up pin ACA6'))->toBe('ACA6');
    expect(GatepassPinNormalizer::normalize('PIN: 123456'))->toBe('123456');
    expect(GatepassPinNormalizer::normalize('Gate Pass PIN - GP-9988'))->toBe('GP-9988');
    expect(GatepassPinNormalizer::normalize('ACA6'))->toBe('ACA6');
});

test('gatepass_pin_normalizer validates PIN format correctly', function () {
    expect(GatepassPinNormalizer::isValidFormat('ACA6'))->toBeTrue();
    expect(GatepassPinNormalizer::isValidFormat('123456'))->toBeTrue();
    expect(GatepassPinNormalizer::isValidFormat('GP-9988'))->toBeTrue();
    expect(GatepassPinNormalizer::isValidFormat('ACA6 EXTRA DETAILS'))->toBeFalse();
    expect(GatepassPinNormalizer::isValidFormat('This is not a pin code at all'))->toBeFalse();
});
