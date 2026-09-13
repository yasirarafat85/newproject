<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\QueryBuilder;

/**
 * activity_log-এ অডিট এন্ট্রি। কে, কী, কখন, কোন IP —
 * সংবেদনশীল প্রতিটি কাজের জন্য।
 */
final class AuditService
{
    public static function log(
        string $action,
        ?string $objectType = null,
        ?int $objectId = null,
        ?string $description = null,
        array $meta = [],
    ): void {
        [$actorType, $actorId] = self::actor();

        QueryBuilder::table('activity_log')->insert([
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'action'      => $action,
            'object_type' => $objectType,
            'object_id'   => $objectId,
            'description' => $description === null ? null : mb_substr($description, 0, 500),
            'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'created_at'  => now(),
        ]);
    }

    /** @return array{0:string,1:?int} */
    private static function actor(): array
    {
        if (Auth::isAgent()) {
            return ['agent', Auth::agentId()];
        }

        if (Auth::isClient()) {
            return ['user', Auth::clientId()];
        }

        return ['system', null];
    }
}
