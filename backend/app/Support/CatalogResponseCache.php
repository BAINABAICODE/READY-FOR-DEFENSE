<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogResponseCache
{
    private const STORE = 'file';

    private const TTL_SECONDS = 3600;

    /**
     * @template T
     *
     * @param  Closure(): T  $resolver
     * @return T
     */
    public static function remember(Request $request, string $name, Closure $resolver): mixed
    {
        $params = $request->query();
        ksort($params);
        $suffix = $params === [] ? 'all' : md5((string) json_encode($params));

        return Cache::store(self::STORE)->remember(
            "catalog.{$name}.{$suffix}",
            self::TTL_SECONDS,
            $resolver,
        );
    }
}
