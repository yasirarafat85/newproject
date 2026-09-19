<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;

/**
 * অডিট লগ — কে, কী, কখন, কোন IP থেকে।
 * শুধু পড়া যায়; এখান থেকে কিছু মোছা বা বদলানো যায় না।
 */
final class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('admin.logs');

        $action = trim((string) $request->query('action', ''));
        $actorType = (string) $request->query('actor', '');
        $search = trim((string) $request->query('q', ''));

        $query = QueryBuilder::table('activity_log')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC');

        if ($action !== '') {
            $query->where('action', $action);
        }

        if (in_array($actorType, ['agent', 'user', 'system'], true)) {
            $query->where('actor_type', $actorType);
        }

        if ($search !== '') {
            $query->where('description', 'LIKE', '%' . $search . '%');
        }

        $logs = $query->paginate(max(1, $request->integer('page', 1)), 50);

        return $this->view('admin/logs/index', [
            'logs'       => $logs,
            'actions'    => $this->distinctActions(),
            'action'     => $action,
            'actorType'  => $actorType,
            'search'     => $search,
            'actorNames' => $this->actorNames($logs->items),
        ], 'layouts/agent');
    }

    private function distinctActions(): array
    {
        return array_column(
            QueryBuilder::table('activity_log')
                ->selectRaw('DISTINCT action')
                ->orderBy('action')
                ->get(),
            'action'
        );
    }

    /**
     * লগের প্রতিটি সারিতে আলাদা কোয়েরি না চালিয়ে একবারেই নাম আনি।
     *
     * @return array{agent: array<int,string>, user: array<int,string>}
     */
    private function actorNames(array $rows): array
    {
        $agentIds = [];
        $userIds = [];

        foreach ($rows as $row) {
            if ($row['actor_id'] === null) {
                continue;
            }
            if ($row['actor_type'] === 'agent') {
                $agentIds[] = (int) $row['actor_id'];
            } elseif ($row['actor_type'] === 'user') {
                $userIds[] = (int) $row['actor_id'];
            }
        }

        return [
            'agent' => $agentIds === [] ? [] : array_column(
                QueryBuilder::table('agents')->select('id', 'name')->whereIn('id', array_unique($agentIds))->get(),
                'name',
                'id'
            ),
            'user' => $userIds === [] ? [] : array_column(
                QueryBuilder::table('users')->select('id', 'name')->whereIn('id', array_unique($userIds))->get(),
                'name',
                'id'
            ),
        ];
    }
}
