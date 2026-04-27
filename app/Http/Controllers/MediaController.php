<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function serve(Request $request, string $path): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $segments = explode('/', $path);
        abort_if(count($segments) < 3 || $segments[0] !== 'content-packs', 404);
        $packId = (int) $segments[1];

        $pack = ContentPack::withTrashed()->findOrFail($packId);
        $allowed = $pack->user_id === $user->id
            || ContentPackPurchase::where('user_id', $user->id)
                ->where('content_pack_id', $packId)
                ->exists();
        abort_if(! $allowed, 403);

        return response('', 200, [
            'X-Accel-Redirect' => '/private-media/' . $path,
            'Content-Type'     => mime_content_type(
                storage_path('app/private/' . $path)
            ),
        ]);
    }
}
