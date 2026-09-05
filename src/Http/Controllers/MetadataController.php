<?php

declare(strict_types=1);

namespace Laravarc\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laravarc\Core\Metadata\Exceptions\MetadataArtifactNotFoundException;
use Laravarc\Core\Metadata\MetadataService;

final class MetadataController
{
    public function __invoke(MetadataService $metadataService): JsonResponse
    {
        try {
            return response()->json($metadataService->artifact()->toArray());
        } catch (MetadataArtifactNotFoundException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
