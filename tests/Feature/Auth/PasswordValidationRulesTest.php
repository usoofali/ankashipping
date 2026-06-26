<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

test('default password rules permit at least 8 characters', function () {
    $validator = Validator::make(
        ['password' => '12345678'],
        ['password' => [Password::default()]]
    );

    expect($validator->passes())->toBeTrue();
});

test('default password rules reject less than 8 characters', function () {
    $validator = Validator::make(
        ['password' => '1234567'],
        ['password' => [Password::default()]]
    );

    expect($validator->fails())->toBeTrue();
});
