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

/**
 * রোল ও পারমিশন ম্যাট্রিক্স।
 *
 * Super Admin রোলের পারমিশন বদলানো যায় না — নইলে সিস্টেমে এমন কেউ
 * থাকবে না যিনি সব কাজ করতে পারেন।
 */
final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('admin.roles');

        return $this->view('admin/roles/index', [
            'roles'           => QueryBuilder::table('roles')->orderBy('is_system', 'DESC')->orderBy('name')->get(),
            'permissionCount' => $this->permissionCounts(),
            'agentCount'      => $this->agentCounts(),
            'totalPermissions' => QueryBuilder::table('permissions')->count(),
        ], 'layouts/agent');
    }

    public function createForm(Request $request): Response
    {
        $this->authorize('admin.roles');

        return $this->view('admin/roles/form', [
            'role'        => null,
            'groups'      => $this->groupedPermissions(),
            'granted'     => [],
            'locked'      => false,
        ], 'layouts/agent');
    }

    public function store(Request $request): Response
    {
        $this->authorize('admin.roles');

        Validator::make($request->all(), [
            'name'        => 'required|max:80|unique:roles,name',
            'description' => 'max:255',
        ], ['name' => 'রোলের নাম', 'description' => 'বিবরণ'])->validate();

        $id = Database::transaction(function () use ($request): int {
            $id = QueryBuilder::table('roles')->insert([
                'name'        => (string) $request->input('name'),
                'description' => (string) $request->input('description') ?: null,
                'is_system'   => 0,
                'created_at'  => now(),
            ]);

            $this->syncPermissions($id, $request);

            return $id;
        });

        AuditService::log('role.created', 'role', $id, (string) $request->input('name'));
        flash('success', 'রোল তৈরি হয়েছে।');

        return $this->redirect('/admin/roles');
    }

    public function editForm(Request $request): Response
    {
        $this->authorize('admin.roles');

        $role = $this->find($request->paramInt('id'));

        return $this->view('admin/roles/form', [
            'role'    => $role,
            'groups'  => $this->groupedPermissions(),
            'granted' => $this->grantedCodes((int) $role['id']),
            'locked'  => AdminGuard::isProtectedRole($role),
        ], 'layouts/agent');
    }

    public function update(Request $request): Response
    {
        $this->authorize('admin.roles');

        $role = $this->find($request->paramInt('id'));
        $id = (int) $role['id'];

        Validator::make($request->all(), [
            'name'        => 'required|max:80|unique:roles,name,' . $id,
            'description' => 'max:255',
        ], ['name' => 'রোলের নাম', 'description' => 'বিবরণ'])->validate();

        Database::transaction(function () use ($id, $role, $request): void {
            QueryBuilder::table('roles')->where('id', $id)->update([
                'name'        => (string) $request->input('name'),
                'description' => (string) $request->input('description') ?: null,
                'updated_at'  => now(),
            ]);

            if (!AdminGuard::isProtectedRole($role)) {
                $this->syncPermissions($id, $request);
            }
        });

        AuditService::log('role.updated', 'role', $id, (string) $request->input('name'));
        flash('success', AdminGuard::isProtectedRole($role)
            ? 'রোলের নাম হালনাগাদ হয়েছে। Super Admin-এর পারমিশন সুরক্ষিত, বদলানো হয়নি।'
            : 'রোল ও পারমিশন হালনাগাদ হয়েছে।');

        return $this->redirect('/admin/roles');
    }

    public function destroy(Request $request): Response
    {
        $this->authorize('admin.roles');

        $role = $this->find($request->paramInt('id'));
        $id = (int) $role['id'];

        if ($blocked = AdminGuard::blockRoleDeletion($id)) {
            flash('danger', $blocked);

            return $this->redirect('/admin/roles');
        }

        QueryBuilder::table('roles')->where('id', $id)->delete();

        AuditService::log('role.deleted', 'role', $id, (string) $role['name']);
        flash('success', 'রোল মুছে ফেলা হয়েছে।');

        return $this->redirect('/admin/roles');
    }

    // ---------- সহায়ক ----------

    private function find(int $id): array
    {
        $role = QueryBuilder::table('roles')->where('id', $id)->first();

        if ($role === null) {
            throw new HttpException(404, 'রোলটি খুঁজে পাওয়া যায়নি।');
        }

        return $role;
    }

    /** পারমিশনগুলো গ্রুপ অনুযায়ী সাজানো, config-এর লেবেলসহ। */
    private function groupedPermissions(): array
    {
        $labels = config('permissions.groups', []);
        $grouped = [];

        foreach (QueryBuilder::table('permissions')->orderBy('id')->get() as $permission) {
            $group = (string) $permission['group_name'];
            $grouped[$group]['label'] = $labels[$group]['label'] ?? $group;
            $grouped[$group]['items'][] = $permission;
        }

        return $grouped;
    }

    private function grantedCodes(int $roleId): array
    {
        return array_column(
            QueryBuilder::table('permissions')
                ->select('permissions.code')
                ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('role_permissions.role_id', $roleId)
                ->get(),
            'code'
        );
    }

    private function syncPermissions(int $roleId, Request $request): void
    {
        $codes = array_filter((array) $request->raw('permissions', []), 'is_string');

        $ids = array_map('intval', array_column(
            QueryBuilder::table('permissions')->select('id')->whereIn('code', $codes ?: [''])->get(),
            'id'
        ));

        QueryBuilder::table('role_permissions')->where('role_id', $roleId)->delete();

        foreach ($ids as $permissionId) {
            QueryBuilder::table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function permissionCounts(): array
    {
        return array_column(
            QueryBuilder::table('role_permissions')->selectRaw('role_id, COUNT(*) AS total')->groupBy('role_id')->get(),
            'total',
            'role_id'
        );
    }

    private function agentCounts(): array
    {
        return array_column(
            QueryBuilder::table('agents')
                ->selectRaw('role_id, COUNT(*) AS total')
                ->whereNull('deleted_at')
                ->groupBy('role_id')
                ->get(),
            'total',
            'role_id'
        );
    }
}
