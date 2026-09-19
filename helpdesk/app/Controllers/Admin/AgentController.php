<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Hash;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Services\AdminGuard;
use App\Services\AuditService;

final class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('admin.agents');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $query = QueryBuilder::table('agents')
            ->select(
                'agents.id', 'agents.name', 'agents.username', 'agents.email',
                'agents.is_admin', 'agents.is_available', 'agents.status', 'agents.last_login_at',
                'roles.name AS role_name',
                'departments.name AS dept_name'
            )
            ->join('roles', 'roles.id', '=', 'agents.role_id')
            ->leftJoin('departments', 'departments.id', '=', 'agents.primary_dept_id')
            ->whereNull('agents.deleted_at')
            ->orderBy('agents.name');

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->whereGroup(static function (QueryBuilder $sub) use ($term): void {
                $sub->where('agents.name', 'LIKE', $term)
                    ->orWhere('agents.username', 'LIKE', $term)
                    ->orWhere('agents.email', 'LIKE', $term);
            });
        }

        if (in_array($status, ['active', 'locked', 'disabled'], true)) {
            $query->where('agents.status', $status);
        }

        return $this->view('admin/agents/index', [
            'agents' => $query->paginate(max(1, $request->integer('page', 1)), 25),
            'search' => $search,
            'status' => $status,
        ], 'layouts/agent');
    }

    public function createForm(Request $request): Response
    {
        $this->authorize('admin.agents');

        return $this->view('admin/agents/form', array_merge($this->formData(), [
            'agent'       => null,
            'memberships' => [],
        ]), 'layouts/agent');
    }

    public function store(Request $request): Response
    {
        $this->authorize('admin.agents');

        Validator::make($request->all(), [
            'name'     => 'required|max:120',
            'username' => 'required|min:3|max:60|unique:agents,username',
            'email'    => 'required|email|max:190|unique:agents,email',
            'password' => 'required|min:8|max:255|confirmed',
            'role_id'  => 'required|integer|exists:roles,id',
            'primary_dept_id' => 'integer|exists:departments,id',
            'max_open_tickets' => 'integer',
        ], $this->labels())->validate();

        $agentId = Database::transaction(function () use ($request): int {
            $id = QueryBuilder::table('agents')->insert([
                'uuid'                => Str::uuid(),
                'name'                => (string) $request->input('name'),
                'username'            => (string) $request->input('username'),
                'email'               => mb_strtolower((string) $request->input('email')),
                'password_hash'       => Hash::make((string) $request->raw('password')),
                'mobile'              => (string) $request->input('mobile') ?: null,
                'signature'           => (string) $request->raw('signature', '') ?: null,
                'role_id'             => $request->integer('role_id'),
                'primary_dept_id'     => $request->integer('primary_dept_id') ?: null,
                'is_admin'            => $request->boolean('is_admin') ? 1 : 0,
                'is_available'        => $request->boolean('is_available') ? 1 : 0,
                'max_open_tickets'    => $request->integer('max_open_tickets') ?: null,
                'status'              => $this->validStatus((string) $request->input('status')),
                'password_changed_at' => now(),
                'created_at'          => now(),
            ]);

            $this->syncDepartments($id, $request);

            return $id;
        });

        AuditService::log('agent.created', 'agent', $agentId, (string) $request->input('name'));
        flash('success', 'এজেন্ট যোগ করা হয়েছে।');

        return $this->redirect('/admin/agents');
    }

    public function editForm(Request $request): Response
    {
        $this->authorize('admin.agents');

        $agent = $this->findAgent($request->paramInt('id'));

        return $this->view('admin/agents/form', array_merge($this->formData(), [
            'agent'       => $agent,
            'memberships' => $this->membershipMap((int) $agent['id']),
        ]), 'layouts/agent');
    }

    public function update(Request $request): Response
    {
        $this->authorize('admin.agents');

        $agent = $this->findAgent($request->paramInt('id'));
        $agentId = (int) $agent['id'];

        $rules = [
            'name'     => 'required|max:120',
            'username' => 'required|min:3|max:60|unique:agents,username,' . $agentId,
            'email'    => 'required|email|max:190|unique:agents,email,' . $agentId,
            'role_id'  => 'required|integer|exists:roles,id',
            'primary_dept_id'  => 'integer|exists:departments,id',
            'max_open_tickets' => 'integer',
        ];

        // পাসওয়ার্ড ফাঁকা রাখলে আগেরটিই থাকে
        if ($request->filled('password')) {
            $rules['password'] = 'min:8|max:255|confirmed';
        }

        Validator::make($request->all(), $rules, $this->labels())->validate();

        $isAdmin = $request->boolean('is_admin');
        $status = $this->validStatus((string) $request->input('status'));

        if ($blocked = AdminGuard::blockAdminFlagChange($agentId, $isAdmin)) {
            flash('danger', $blocked);

            return $this->redirect('/admin/agents/' . $agentId . '/edit');
        }

        if ($status !== 'active' && ($blocked = AdminGuard::blockDeactivation($agentId))) {
            flash('danger', $blocked);

            return $this->redirect('/admin/agents/' . $agentId . '/edit');
        }

        $update = [
            'name'             => (string) $request->input('name'),
            'username'         => (string) $request->input('username'),
            'email'            => mb_strtolower((string) $request->input('email')),
            'mobile'           => (string) $request->input('mobile') ?: null,
            'signature'        => (string) $request->raw('signature', '') ?: null,
            'role_id'          => $request->integer('role_id'),
            'primary_dept_id'  => $request->integer('primary_dept_id') ?: null,
            'is_admin'         => $isAdmin ? 1 : 0,
            'is_available'     => $request->boolean('is_available') ? 1 : 0,
            'max_open_tickets' => $request->integer('max_open_tickets') ?: null,
            'status'           => $status,
            'updated_at'       => now(),
        ];

        if ($request->filled('password')) {
            $update['password_hash'] = Hash::make((string) $request->raw('password'));
            $update['password_changed_at'] = now();
        }

        Database::transaction(function () use ($agentId, $update, $request): void {
            QueryBuilder::table('agents')->where('id', $agentId)->update($update);
            $this->syncDepartments($agentId, $request);
        });

        AuditService::log('agent.updated', 'agent', $agentId, $update['name']);
        flash('success', 'এজেন্টের তথ্য হালনাগাদ হয়েছে।');

        return $this->redirect('/admin/agents');
    }

    public function destroy(Request $request): Response
    {
        $this->authorize('admin.agents');

        $agent = $this->findAgent($request->paramInt('id'));
        $agentId = (int) $agent['id'];

        if ($blocked = AdminGuard::blockDeactivation($agentId)) {
            flash('danger', $blocked);

            return $this->redirect('/admin/agents');
        }

        // সফট ডিলিট — টিকেটের ইতিহাসে নামটি থেকে যাক
        QueryBuilder::table('agents')->where('id', $agentId)->update([
            'status'     => 'disabled',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        AuditService::log('agent.deleted', 'agent', $agentId, (string) $agent['name']);
        flash('success', 'এজেন্টকে সরিয়ে দেওয়া হয়েছে। টিকেটের ইতিহাসে নামটি থেকে যাবে।');

        return $this->redirect('/admin/agents');
    }

    // ---------- সহায়ক ----------

    private function findAgent(int $id): array
    {
        $agent = QueryBuilder::table('agents')->where('id', $id)->whereNull('deleted_at')->first();

        if ($agent === null) {
            throw new HttpException(404, 'এজেন্টকে খুঁজে পাওয়া যায়নি।');
        }

        return $agent;
    }

    private function formData(): array
    {
        return [
            'roles'       => QueryBuilder::table('roles')->orderBy('name')->get(),
            'departments' => QueryBuilder::table('departments')->where('is_active', 1)->orderBy('sort_order')->get(),
            'superAdmins' => AdminGuard::superAdminCount(),
        ];
    }

    /** @return array<int, array{member:bool, manager:bool}> */
    private function membershipMap(int $agentId): array
    {
        $map = [];

        foreach (QueryBuilder::table('agent_departments')->where('agent_id', $agentId)->get() as $row) {
            $map[(int) $row['dept_id']] = [
                'member'  => true,
                'manager' => (int) $row['is_manager'] === 1,
            ];
        }

        return $map;
    }

    /**
     * ডিপার্টমেন্ট সদস্যপদ নতুন করে বসায়।
     *
     * প্রাইমারি ডিপার্টমেন্ট সবসময় সদস্যপদে যোগ হয় — নইলে এজেন্ট নিজের
     * ডিপার্টমেন্টের টিকেটই দেখতে পাবেন না।
     */
    private function syncDepartments(int $agentId, Request $request): void
    {
        $selected = array_map('intval', (array) $request->raw('departments', []));
        $managers = array_map('intval', (array) $request->raw('managers', []));

        $primary = $request->integer('primary_dept_id');
        if ($primary > 0 && !in_array($primary, $selected, true)) {
            $selected[] = $primary;
        }

        $valid = array_column(
            QueryBuilder::table('departments')->select('id')->whereIn('id', $selected ?: [0])->get(),
            'id'
        );
        $valid = array_map('intval', $valid);

        QueryBuilder::table('agent_departments')->where('agent_id', $agentId)->delete();

        foreach ($valid as $deptId) {
            QueryBuilder::table('agent_departments')->insert([
                'agent_id'       => $agentId,
                'dept_id'        => $deptId,
                'is_manager'     => in_array($deptId, $managers, true) ? 1 : 0,
                'alerts_enabled' => 1,
            ]);
        }
    }

    private function validStatus(string $status): string
    {
        return in_array($status, ['active', 'locked', 'disabled'], true) ? $status : 'active';
    }

    private function labels(): array
    {
        return [
            'name' => 'নাম', 'username' => 'ইউজারনেম', 'email' => 'ইমেইল',
            'password' => 'পাসওয়ার্ড', 'role_id' => 'রোল',
            'primary_dept_id' => 'প্রধান ডিপার্টমেন্ট', 'max_open_tickets' => 'সর্বোচ্চ খোলা টিকেট',
        ];
    }
}
