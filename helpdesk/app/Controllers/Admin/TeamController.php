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
use App\Services\AuditService;

/**
 * টিম — শিফট বা দক্ষতাভিত্তিক দল। টিকেট টিমের কিউতে গেলে যে আগে
 * claim করবে সে পাবে (P3-এর ট্রান্সফার সিস্টেমে ব্যবহৃত হবে)।
 */
final class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('admin.teams');

        return $this->view('admin/teams/index', [
            'teams' => QueryBuilder::table('teams')
                ->select('teams.id', 'teams.name', 'teams.is_active', 'teams.notify_lead', 'agents.name AS lead_name')
                ->leftJoin('agents', 'agents.id', '=', 'teams.lead_agent_id')
                ->orderBy('teams.name')
                ->get(),
            'memberCounts' => $this->memberCounts(),
            'ticketCounts' => $this->ticketCounts(),
        ], 'layouts/agent');
    }

    public function createForm(Request $request): Response
    {
        $this->authorize('admin.teams');

        return $this->view('admin/teams/form', array_merge($this->formData(), [
            'team'    => null,
            'members' => [],
        ]), 'layouts/agent');
    }

    public function store(Request $request): Response
    {
        $this->authorize('admin.teams');

        Validator::make($request->all(), $this->rules(null), $this->labels())->validate();

        $id = Database::transaction(function () use ($request): int {
            $id = QueryBuilder::table('teams')->insert($this->payload($request) + ['created_at' => now()]);
            $this->syncMembers($id, $request);

            return $id;
        });

        AuditService::log('team.created', 'team', $id, (string) $request->input('name'));
        flash('success', 'টিম তৈরি হয়েছে।');

        return $this->redirect('/admin/teams');
    }

    public function editForm(Request $request): Response
    {
        $this->authorize('admin.teams');

        $team = $this->find($request->paramInt('id'));

        return $this->view('admin/teams/form', array_merge($this->formData(), [
            'team'    => $team,
            'members' => array_map('intval', array_column(
                QueryBuilder::table('team_members')->select('agent_id')->where('team_id', (int) $team['id'])->get(),
                'agent_id'
            )),
        ]), 'layouts/agent');
    }

    public function update(Request $request): Response
    {
        $this->authorize('admin.teams');

        $team = $this->find($request->paramInt('id'));
        $id = (int) $team['id'];

        Validator::make($request->all(), $this->rules($id), $this->labels())->validate();

        Database::transaction(function () use ($id, $request): void {
            QueryBuilder::table('teams')->where('id', $id)->update($this->payload($request));
            $this->syncMembers($id, $request);
        });

        AuditService::log('team.updated', 'team', $id, (string) $request->input('name'));
        flash('success', 'টিম হালনাগাদ হয়েছে।');

        return $this->redirect('/admin/teams');
    }

    public function destroy(Request $request): Response
    {
        $this->authorize('admin.teams');

        $team = $this->find($request->paramInt('id'));
        $id = (int) $team['id'];

        // টিকেটের assigned_team_id SET NULL হয়ে যাবে, তাই ডেটা হারানোর ঝুঁকি নেই
        $assigned = QueryBuilder::table('tickets')->where('assigned_team_id', $id)->whereNull('deleted_at')->count();

        QueryBuilder::table('teams')->where('id', $id)->delete();

        AuditService::log('team.deleted', 'team', $id, (string) $team['name']);
        flash('success', $assigned > 0
            ? "টিম মুছে ফেলা হয়েছে। {$assigned}টি টিকেট এখন আনঅ্যাসাইনড।"
            : 'টিম মুছে ফেলা হয়েছে।');

        return $this->redirect('/admin/teams');
    }

    // ---------- সহায়ক ----------

    private function find(int $id): array
    {
        $team = QueryBuilder::table('teams')->where('id', $id)->first();

        if ($team === null) {
            throw new HttpException(404, 'টিমটি খুঁজে পাওয়া যায়নি।');
        }

        return $team;
    }

    private function payload(Request $request): array
    {
        return [
            'name'          => (string) $request->input('name'),
            'lead_agent_id' => $request->integer('lead_agent_id') ?: null,
            'notify_lead'   => $request->boolean('notify_lead') ? 1 : 0,
            'is_active'     => $request->boolean('is_active') ? 1 : 0,
        ];
    }

    private function rules(?int $ignoreId): array
    {
        return [
            'name'          => 'required|max:120|unique:teams,name' . ($ignoreId === null ? '' : ',' . $ignoreId),
            'lead_agent_id' => 'integer|exists:agents,id',
        ];
    }

    /** টিম লিড সবসময় সদস্য হিসেবে যোগ হন। */
    private function syncMembers(int $teamId, Request $request): void
    {
        $selected = array_map('intval', (array) $request->raw('members', []));

        $lead = $request->integer('lead_agent_id');
        if ($lead > 0 && !in_array($lead, $selected, true)) {
            $selected[] = $lead;
        }

        $valid = array_map('intval', array_column(
            QueryBuilder::table('agents')
                ->select('id')
                ->whereIn('id', $selected ?: [0])
                ->whereNull('deleted_at')
                ->get(),
            'id'
        ));

        QueryBuilder::table('team_members')->where('team_id', $teamId)->delete();

        foreach ($valid as $agentId) {
            QueryBuilder::table('team_members')->insert(['team_id' => $teamId, 'agent_id' => $agentId]);
        }
    }

    private function formData(): array
    {
        return [
            'agents' => QueryBuilder::table('agents')
                ->select('agents.id', 'agents.name', 'departments.name AS dept_name')
                ->leftJoin('departments', 'departments.id', '=', 'agents.primary_dept_id')
                ->where('agents.status', 'active')
                ->whereNull('agents.deleted_at')
                ->orderBy('agents.name')
                ->get(),
        ];
    }

    private function memberCounts(): array
    {
        return array_column(
            QueryBuilder::table('team_members')->selectRaw('team_id, COUNT(*) AS total')->groupBy('team_id')->get(),
            'total',
            'team_id'
        );
    }

    private function ticketCounts(): array
    {
        return array_column(
            QueryBuilder::table('tickets')
                ->selectRaw('assigned_team_id, COUNT(*) AS total')
                ->whereNotNull('assigned_team_id')
                ->whereNull('deleted_at')
                ->groupBy('assigned_team_id')
                ->get(),
            'total',
            'assigned_team_id'
        );
    }

    private function labels(): array
    {
        return ['name' => 'টিমের নাম', 'lead_agent_id' => 'টিম লিড'];
    }
}
