<?php
declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $agentId = (int) Auth::agentId();
        $scope = $this->visibleDepartmentIds();

        return $this->view('agent/dashboard', [
            'stats'        => $this->stats($agentId, $scope),
            'myTickets'    => $this->myRecentTickets($agentId),
            'unassigned'   => $this->unassignedTickets($scope),
            'weeklyVolume' => $this->weeklyVolume($scope),
        ], 'layouts/agent');
    }

    /**
     * এজেন্ট যে ডিপার্টমেন্টগুলোর টিকেট দেখতে পারেন।
     * null মানে সীমা নেই (অ্যাডমিন বা ticket.view_all)।
     */
    private function visibleDepartmentIds(): ?array
    {
        if (Auth::isAdmin() || Auth::hasPermission('ticket.view_all')) {
            return null;
        }

        return Auth::departmentIds();
    }

    private function applyScope(QueryBuilder $query, ?array $scope): QueryBuilder
    {
        if ($scope !== null) {
            $query->whereIn('tickets.dept_id', $scope);
        }

        return $query;
    }

    private function stats(int $agentId, ?array $scope): array
    {
        $open = $this->applyScope(
            QueryBuilder::table('tickets')
                ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
                ->whereIn('statuses.state', ['open'])
                ->whereNull('tickets.deleted_at'),
            $scope
        )->count();

        $pending = $this->applyScope(
            QueryBuilder::table('tickets')
                ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
                ->whereIn('statuses.state', ['paused'])
                ->whereNull('tickets.deleted_at'),
            $scope
        )->count();

        $overdue = $this->applyScope(
            QueryBuilder::table('tickets')
                ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
                ->whereIn('statuses.state', ['open', 'paused'])
                ->where('tickets.due_at', '<', now())
                ->whereNull('tickets.deleted_at'),
            $scope
        )->count();

        $mine = QueryBuilder::table('tickets')
            ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
            ->where('tickets.assigned_agent_id', $agentId)
            ->whereIn('statuses.state', ['open', 'paused'])
            ->whereNull('tickets.deleted_at')
            ->count();

        $unassigned = $this->applyScope(
            QueryBuilder::table('tickets')
                ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
                ->whereNull('tickets.assigned_agent_id')
                ->whereNull('tickets.assigned_team_id')
                ->whereIn('statuses.state', ['open'])
                ->whereNull('tickets.deleted_at'),
            $scope
        )->count();

        $resolvedToday = $this->applyScope(
            QueryBuilder::table('tickets')
                ->where('tickets.closed_at', '>=', date('Y-m-d 00:00:00'))
                ->whereNull('tickets.deleted_at'),
            $scope
        )->count();

        return compact('open', 'pending', 'overdue', 'mine', 'unassigned', 'resolvedToday');
    }

    private function myRecentTickets(int $agentId): array
    {
        return QueryBuilder::table('tickets')
            ->select(
                'tickets.id', 'tickets.number', 'tickets.subject', 'tickets.due_at',
                'tickets.last_message_at', 'tickets.created_at', 'tickets.is_answered',
                'users.name AS user_name',
                'statuses.name AS status_name', 'statuses.color AS status_color', 'statuses.state AS status_state',
                'priorities.name AS priority_name', 'priorities.color AS priority_color'
            )
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
            ->join('priorities', 'priorities.id', '=', 'tickets.priority_id')
            ->where('tickets.assigned_agent_id', $agentId)
            ->whereIn('statuses.state', ['open', 'paused'])
            ->whereNull('tickets.deleted_at')
            ->orderBy('priorities.level', 'DESC')
            ->orderBy('tickets.due_at', 'ASC')
            ->limit(8)
            ->get();
    }

    private function unassignedTickets(?array $scope): array
    {
        $query = QueryBuilder::table('tickets')
            ->select(
                'tickets.id', 'tickets.number', 'tickets.subject', 'tickets.created_at',
                'users.name AS user_name',
                'departments.name AS dept_name',
                'priorities.name AS priority_name', 'priorities.color AS priority_color'
            )
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->join('departments', 'departments.id', '=', 'tickets.dept_id')
            ->join('priorities', 'priorities.id', '=', 'tickets.priority_id')
            ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
            ->whereNull('tickets.assigned_agent_id')
            ->whereNull('tickets.assigned_team_id')
            ->whereIn('statuses.state', ['open'])
            ->whereNull('tickets.deleted_at')
            ->orderBy('tickets.created_at', 'ASC')
            ->limit(8);

        return $this->applyScope($query, $scope)->get();
    }

    /** গত ৭ দিনের দৈনিক টিকেট সংখ্যা — ড্যাশবোর্ডের চার্টে। */
    private function weeklyVolume(?array $scope): array
    {
        $bindings = [date('Y-m-d', strtotime('-6 days'))];
        $scopeSql = '';

        if ($scope !== null) {
            if ($scope === []) {
                return array_fill_keys(self::lastSevenDays(), 0);
            }
            $scopeSql = ' AND dept_id IN (' . implode(',', array_fill(0, count($scope), '?')) . ')';
            $bindings = array_merge($bindings, $scope);
        }

        $rows = Database::select(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM tickets
             WHERE created_at >= ? AND deleted_at IS NULL' . $scopeSql . '
             GROUP BY DATE(created_at)',
            $bindings
        );

        $byDay = array_column($rows, 'total', 'day');
        $result = [];
        foreach (self::lastSevenDays() as $day) {
            $result[$day] = (int) ($byDay[$day] ?? 0);
        }

        return $result;
    }

    /** @return string[] */
    private static function lastSevenDays(): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[] = date('Y-m-d', strtotime("-{$i} days"));
        }

        return $days;
    }
}
