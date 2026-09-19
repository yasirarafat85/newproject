<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditService;

/**
 * হেল্প টপিক — গ্রাহকের ফর্মে "কী নিয়ে সমস্যা" ড্রপডাউন।
 *
 * এখানেই ঠিক হয় নতুন টিকেট কোন ডিপার্টমেন্টে, কোন প্রায়োরিটিতে ও
 * কোন SLA-তে যাবে, তাই রাউটিংয়ের প্রথম ধাপ আসলে এটিই।
 */
final class HelpTopicController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('admin.topics');

        return $this->view('admin/topics/index', [
            'topics' => QueryBuilder::table('help_topics')
                ->select(
                    'help_topics.id', 'help_topics.name', 'help_topics.is_public', 'help_topics.is_active',
                    'help_topics.sort_order',
                    'departments.name AS dept_name',
                    'priorities.name AS priority_name', 'priorities.color AS priority_color',
                    'sla_plans.name AS sla_name',
                    'agents.name AS agent_name',
                    'teams.name AS team_name'
                )
                ->leftJoin('departments', 'departments.id', '=', 'help_topics.dept_id')
                ->leftJoin('priorities', 'priorities.id', '=', 'help_topics.priority_id')
                ->leftJoin('sla_plans', 'sla_plans.id', '=', 'help_topics.sla_id')
                ->leftJoin('agents', 'agents.id', '=', 'help_topics.auto_assign_agent_id')
                ->leftJoin('teams', 'teams.id', '=', 'help_topics.auto_assign_team_id')
                ->orderBy('help_topics.sort_order')
                ->orderBy('help_topics.name')
                ->get(),
            'ticketCounts' => array_column(
                QueryBuilder::table('tickets')
                    ->selectRaw('topic_id, COUNT(*) AS total')
                    ->whereNotNull('topic_id')->whereNull('deleted_at')
                    ->groupBy('topic_id')->get(),
                'total',
                'topic_id'
            ),
        ], 'layouts/agent');
    }

    public function createForm(Request $request): Response
    {
        $this->authorize('admin.topics');

        return $this->view('admin/topics/form', array_merge($this->formData(null), ['topic' => null]), 'layouts/agent');
    }

    public function store(Request $request): Response
    {
        $this->authorize('admin.topics');

        Validator::make($request->all(), $this->rules(), $this->labels())->validate();

        $id = QueryBuilder::table('help_topics')->insert($this->payload($request) + ['created_at' => now()]);

        AuditService::log('topic.created', 'help_topic', $id, (string) $request->input('name'));
        flash('success', 'হেল্প টপিক যোগ করা হয়েছে।');

        return $this->redirect('/admin/topics');
    }

    public function editForm(Request $request): Response
    {
        $this->authorize('admin.topics');

        $topic = $this->find($request->paramInt('id'));

        return $this->view('admin/topics/form', array_merge(
            $this->formData((int) $topic['id']),
            ['topic' => $topic]
        ), 'layouts/agent');
    }

    public function update(Request $request): Response
    {
        $this->authorize('admin.topics');

        $topic = $this->find($request->paramInt('id'));
        $id = (int) $topic['id'];

        Validator::make($request->all(), $this->rules(), $this->labels())->validate();

        QueryBuilder::table('help_topics')->where('id', $id)->update($this->payload($request));

        AuditService::log('topic.updated', 'help_topic', $id, (string) $request->input('name'));
        flash('success', 'হেল্প টপিক হালনাগাদ হয়েছে।');

        return $this->redirect('/admin/topics');
    }

    public function destroy(Request $request): Response
    {
        $this->authorize('admin.topics');

        $topic = $this->find($request->paramInt('id'));
        $id = (int) $topic['id'];

        // topic_id SET NULL হয়ে যাবে, টিকেট হারাবে না
        $used = QueryBuilder::table('tickets')->where('topic_id', $id)->whereNull('deleted_at')->count();

        QueryBuilder::table('help_topics')->where('id', $id)->delete();

        AuditService::log('topic.deleted', 'help_topic', $id, (string) $topic['name']);
        flash('success', $used > 0
            ? "টপিক মুছে ফেলা হয়েছে। {$used}টি পুরনো টিকেটে আর টপিক থাকবে না।"
            : 'টপিক মুছে ফেলা হয়েছে।');

        return $this->redirect('/admin/topics');
    }

    // ---------- সহায়ক ----------

    private function find(int $id): array
    {
        $topic = QueryBuilder::table('help_topics')->where('id', $id)->first();

        if ($topic === null) {
            throw new HttpException(404, 'টপিকটি খুঁজে পাওয়া যায়নি।');
        }

        return $topic;
    }

    private function payload(Request $request): array
    {
        return [
            'name'                 => (string) $request->input('name'),
            'parent_id'            => $request->integer('parent_id') ?: null,
            'dept_id'              => $request->integer('dept_id') ?: null,
            'priority_id'          => $request->integer('priority_id') ?: null,
            'sla_id'               => $request->integer('sla_id') ?: null,
            'auto_assign_agent_id' => $request->integer('auto_assign_agent_id') ?: null,
            'auto_assign_team_id'  => $request->integer('auto_assign_team_id') ?: null,
            'is_public'            => $request->boolean('is_public') ? 1 : 0,
            'is_active'            => $request->boolean('is_active') ? 1 : 0,
            'sort_order'           => $request->integer('sort_order'),
        ];
    }

    private function rules(): array
    {
        return [
            'name'                 => 'required|max:160',
            'parent_id'            => 'integer|exists:help_topics,id',
            'dept_id'              => 'integer|exists:departments,id',
            'priority_id'          => 'integer|exists:priorities,id',
            'sla_id'               => 'integer|exists:sla_plans,id',
            'auto_assign_agent_id' => 'integer|exists:agents,id',
            'auto_assign_team_id'  => 'integer|exists:teams,id',
            'sort_order'           => 'integer',
        ];
    }

    private function formData(?int $excludeId): array
    {
        $parents = QueryBuilder::table('help_topics')->select('id', 'name')->orderBy('name');
        if ($excludeId !== null) {
            $parents->where('id', '!=', $excludeId);
        }

        return [
            'parents'     => $parents->get(),
            'departments' => QueryBuilder::table('departments')->select('id', 'name')->where('is_active', 1)->orderBy('sort_order')->get(),
            'priorities'  => QueryBuilder::table('priorities')->select('id', 'name')->orderBy('level')->get(),
            'slaPlans'    => QueryBuilder::table('sla_plans')->select('id', 'name')->where('is_active', 1)->orderBy('name')->get(),
            'agents'      => QueryBuilder::table('agents')->select('id', 'name')->where('status', 'active')->whereNull('deleted_at')->orderBy('name')->get(),
            'teams'       => QueryBuilder::table('teams')->select('id', 'name')->where('is_active', 1)->orderBy('name')->get(),
        ];
    }

    private function labels(): array
    {
        return [
            'name' => 'টপিকের নাম', 'parent_id' => 'প্যারেন্ট টপিক', 'dept_id' => 'ডিপার্টমেন্ট',
            'priority_id' => 'প্রায়োরিটি', 'sla_id' => 'SLA', 'auto_assign_agent_id' => 'নির্দিষ্ট এজেন্ট',
            'auto_assign_team_id' => 'নির্দিষ্ট টিম', 'sort_order' => 'ক্রম',
        ];
    }
}
