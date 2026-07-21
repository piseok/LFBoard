<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function download(int $id): BinaryFileResponse
    {
        $media = MediaFile::query()->findOrFail($id);

        $absolutePath = public_path($media->file_path);

        abort_unless(is_file($absolutePath), 404);

        $media->increment('download_count');

        return response()->download($absolutePath, $media->original_name);
    }
}
