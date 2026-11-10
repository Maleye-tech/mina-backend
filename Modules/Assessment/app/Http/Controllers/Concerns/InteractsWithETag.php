<?php

namespace Modules\Assessment\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réponses conditionnelles avec ETag pour les contenus stables.
 *
 * Une requête présentant un If-None-Match identique reçoit un 304 sans
 * payload — utile aux clients sur réseau instable.
 */
trait InteractsWithETag
{
    /**
     * ETag fort calculé sur la payload sérialisée : toute modification du
     * contenu produit une nouvelle valeur.
     *
     * @param  array<string, mixed>  $payload
     */
    private function etagFor(array $payload): string
    {
        return '"'.md5((string) json_encode($payload)).'"';
    }

    /**
     * Répondre 304 quand le client présente un ETag identique, sinon la payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function respondWithETag(array $payload, Request $request): Response
    {
        $etag = $this->etagFor($payload);

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304, ['ETag' => $etag]);
        }

        return response()->json($payload, 200, ['ETag' => $etag]);
    }
}
