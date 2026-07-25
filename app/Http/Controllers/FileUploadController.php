<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateFileDownloadLinkAction;
use App\DTOs\CreateFileDownloadLinkData;
use App\DTOs\CreateFileUploadLinkData;
use App\Http\Requests\CreateFileDownloadLinkRequest;
use App\Http\Requests\CreateFileUploadLinkRequest;
use App\Services\Interfaces\R2UploadServiceInterface;
use Illuminate\Http\JsonResponse;

class FileUploadController extends Controller
{
    public function __construct(
        protected R2UploadServiceInterface $uploads,
        protected CreateFileDownloadLinkAction $createDownloadLink,
    ) {}

    public function store(CreateFileUploadLinkRequest $request): JsonResponse
    {
        $link = $this->uploads->createUploadLink(
            CreateFileUploadLinkData::from($request->user(), $request->validated()),
        );

        return response()->json($link->toArray(), 201);
    }

    public function download(CreateFileDownloadLinkRequest $request): JsonResponse
    {
        $link = $this->createDownloadLink->execute(
            CreateFileDownloadLinkData::from($request->user(), $request->validated()),
        );

        return response()->json($link->toArray());
    }
}
