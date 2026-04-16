<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleDocumentFile;
use App\Support\VehicleDocumentFileDownloadResponder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VehicleDocumentFileSignedDownloadController extends Controller
{
    public function __invoke(Vehicle $vehicle, VehicleDocumentFile $file): StreamedResponse
    {
        return VehicleDocumentFileDownloadResponder::stream($vehicle, $file);
    }
}
