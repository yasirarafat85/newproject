<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AdminGuard;
use App\Services\AuditService;

final class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('admin.departments');

        return $this->view('admin/departments/index', [
            'departments' => QueryBuilder::table('departments')
                ->select(
                    'departments.id', 'departments.name', 'departments.is_active', 'departments.is_public',
                    'departments.is_default', 'departments.assignment_strategy', 'departments.sort_order',
                    'parent.name AS parent_name',
                    'agents.name AS manager_name',
                    'sla_plans.name AS sla_name'
                )
                ->leftJoin('departments AS parent', 'parent.id', '=', 'departments.parent_id')
                ->leftJoin('agents', 'agents.id', '=', 'departments.manager_id')
                ->leftJoin('sla_plans', 'sla_plans.id', '=', 'departments.sla_id')
                ->orderBy('departments.sort_order')
                ->orderBy('departments.name')
                ->get(),
            'ticketCounts' => $this->ticketCounts(),
            'agentCounts'  => $this->agentCounts(),
        ], 'layouts/agent');
    }

    public function createForm(Request $request): Response
    {
        $this->authorize('admin.departments');

        return $this->view('admin/departments/form', array_merge($this->formData(null), [
            'department' => null,
        ]), 'layouts/agent');
    }

    public function store(Request $request): Response
    {
        $this->authorize('admin.departments');

        Validator::make($request->all(), $this->rules(null), $this->labels())->validate();

        $id = QueryBuilder::table('departments')->insert($this->payload($request) + ['created_at' => now()]);

        $this->applyDefaultFlag($id, $request->boolean('is_default'));

        AuditService::log('department.created', 'department', $id, (string) $request->input('name'));
        flash('success', 'ডিপার্টমেন্ট যোগ করা হয়েছে।');

        return $this->redirect('/admin/departments');
    }

    public function editForm(Request $request): Response
    {
        $this->authorize('admin.departments');

        $department = $this->find($request->paramInt('id'));

        return $this->view('admin/departments/form', array_merge($this->formData((int) $department['id']), [
            'department' => $department,
        ]), 'layouts/agent');
    }

    public function update(Request $request): Response
    {
        $this->authorize('admin.departments');

        $department = $this->find($request->paramInt('id'));
        $id = (int) $department['id'];

        Validator::make($request->all(), $this->rules($id), $this->labels())->validate();

        // ডিফল্ট ডিপার্টমেন্ট কখনো নিষ্ক্রিয় থাকতে পারে না — নতুন টিকেট যাবে কোথায়?
        $isActive = $request->boolean('is_active');
        if ((int) $department['is_default'] === 1 && !$isActive) {
            flash('danger', 'ডিফল্ট ডিপার্টমেন্ট নিষ্ক্রিয় করা যায় না। আগে অন্য একটিকে ডিফল্ট করুন।');

            return $this->redirect('/admin/departments/' . $id . '/edit');
        }

        QueryBuilder::table('departments')->where('id', $id)
            ->update($this->payload($request) + ['updated_at' => now()]);

        $this->applyDefaultFlag($id, $request->boolean('is_default'));

        AuditService::log('department.updated', 'department', $id, (string) $request->input('name'));
        flash('success', 'ডিপার্টমেন্ট হালনাগাদ হয়েছে।');

        return $this->redirect('/admin/departments');
    }

    public function destroy(Request $request): Response
    {
        $this->authorize('admin.departments');

        $department = $this->find($request->paramInt('id'));
        $id = (int) $department['id'];

        if ($blocked = AdminGuard::blockDepartmentDeletion($id)) {
            flash('danger', $blocked);

            return $this->redirect('/admin/departments');
        }

        QueryBuilder::table('departments')->where('id', $id)->delete();

        AuditService::log('department.deleted', 'department', $id, (string) $department['name']);
        flash('success', 'ডিপার্টমেন্ট মুছে ফেলা হয়েছে।');

        return $this->redirect('/admin/departments');
    }

    // ---------- সহায়ক ----------

    private function find(int $id): array
    {
        $department = QueryBuilder::table('departments')->where('id', $id)->first();

        if ($department === null) {
            throw new HttpException(404, 'ডিপার্টমেন্টটি খুঁজে পাওয়া যায়নি।');
        }

        return $department;
    }

    private function payload(Request $request): array
    {
        return [
            'name'                => (string) $request->input('name'),
            'parent_id'           => $request->integer('parent_id') ?: null,
            'manager_id'          => $request->integer('manager_id') ?: null,
            'sla_id'              => $request->integer('sla_id') ?: null,
            'signature'           => (string) $request->raw('signature', '') ?: null,
            'auto_response'       => $request->boolean('auto_response') ? 1 : 0,
            'is_public'           => $request->boolean('is_public') ? 1 : 0,
            'is_active'           => $request->boolean('is_active') ? 1 : 0,
            'assignment_strategy' => $this->validStrategy((string) $request->input('assignment_strategy')),
            'sort_order'          => $request->integer('sort_order'),
        ];
    }

    private function rules(?int $ignoreId): array
    {
        return [
            'name'       => 'required|max:120|unique:departments,name' . ($ignoreId === null ? '' : ',' . $ignoreId),
            'parent_id'  => 'integer|exists:departments,id',
            'manager_id' => 'integer|exists:agents,id',
            'sla_id'     => 'integer|exists:sla_plans,id',
            'sort_order' => 'integer',
        ];
    }

    /**
     * ডিফল্ট ফ্ল্যাগ একবারে একটিতেই থাকে।
     * ফ্ল্যাগ তোলার চেষ্টা উপেক্ষা করি — কোনো ডিফল্ট না থাকলে নতুন টিকেট যাবে কোথায়?
     */
    private function applyDefaultFlag(int $id, bool $makeDefault): void
    {
        if (!$makeDefault) {
            return;
        }

        Database::transaction(static function () use ($id): void {
            QueryBuilder::table('departments')->where('is_default', 1)->update(['is_default' => 0]);
            QueryBuilder::table('departments')->where('id', $id)->update(['is_default' => 1, 'is_active' => 1]);
        });
    }

    private function formData(?int $excludeId): array
    {
        $parents = QueryBuilder::table('departments')->select('id', 'name')->orderBy('name');

        if ($excludeId !== null) {
            // নিজেকে নিজের প্যারেন্ট বানানো যাবে না
            $parents->where('id', '!=', $excludeId);
        }

        return [
            'parents'  => $parents->get(),
            'managers' => QueryBuilder::table('agents')
                ->select('id', 'name')
                ->where('status', 'active')->whereNull('deleted_at')
                ->orderBy('name')->get(),
            'slaPlans' => QueryBuilder::table('sla_plans')->select('id', 'name')->where('is_active', 1)->orderBy('name')->get(),
        ];
    }

    private function ticketCounts(): array
    {
        $rows = QueryBuilder::table('tickets')
            ->selectRaw('dept_id, COUNT(*) AS total')
            ->whereNull('deleted_at')
            ->groupBy('dept_id')
            ->get();

        return array_column($rows, 'total', 'dept_id');
    }

    private function agentCounts(): array
    {
        $rows = QueryBuilder::table('agent_departments')
            ->selectRaw('dept_id, COUNT(*) AS total')
            ->groupBy('dept_id')
            ->get();

        return array_column($rows, 'total', 'dept_id');
    }

    private function validStrategy(string $strategy): string
    {
        return in_array($strategy, ['manual', 'round_robin', 'least_load'], true) ? $strategy : 'manual';
    }

    private function labels(): array
    {
        return [
            'name' => 'নাম', 'parent_id' => 'প্যারেন্ট ডিপার্টমেন্ট',
            'manager_id' => 'ম্যানেজার', 'sla_id' => 'SLA প্ল্যান', 'sort_order' => 'ক্রম',
        ];
    }
}
