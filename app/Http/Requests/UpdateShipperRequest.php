<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Shipper;
use App\Support\ShipperGeoValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateShipperRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipper = $this->route('shipper');

        return $shipper instanceof Shipper && $this->user()->can('update', $shipper);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            ShipperGeoValidator::assertHierarchy($v);
        });
    }
}
