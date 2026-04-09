<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ImportTemplateController extends Controller
{
    public function geo(string $entity): BinaryFileResponse
    {
        $allowed = ['countries', 'states', 'cities', 'ports', 'shippers'];

        abort_unless(in_array($entity, $allowed, true), Response::HTTP_NOT_FOUND);

        $path = resource_path("import-templates/{$entity}.sample.csv");

        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return response()->download(
            $path,
            "{$entity}.sample.csv",
            ['Content-Type' => 'text/csv']
        );
    }
}
