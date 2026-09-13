<?php
declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Models\Ticket;
use App\Services\AttachmentService;
use App\Services\TicketService;
use RuntimeException;

/**
 * এজেন্টের টিকেট কিউ ও টিকেট ভিউ।
 *
 * দৃশ্যমানতার নিয়ম Ticket::applyVisibility()-তে একবারই লেখা; প্রতিটি
 * অ্যাকশন আলাদা করে টিকেটটির উপর অনুমতিও যাচাই করে।
 */
final class TicketController extends Controller
{
    /** URL-এ যে সাজানোর কলামগুলো অনুমোদিত — হোয়াইটলিস্ট। */
    private const SORTABLE = [
        'created'  => 'tickets.created_at',
        'updated'  => 'tickets.updated_at',
        'due'      => 'tickets.due_at',
        'priority' => 'priorities.level',
        'number'   => 'tickets.number',
    ];

    public function index(Request $request): Response
    {
        $filter = (string) $request->query('filter', 'open');
        $search = trim((string) $request->query('q', ''));
        $deptId = $request->integer('dept');
        $statusId = $request->integer('status');
        $sort = (string) $request->query('sort', 'updated');
        $direction = $request->query('dir') === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->integer('page', 1));

        $query = Ticket::applyVisibility(Ticket::listQuery());
        $this->applyFilter($query, $filter);

        if ($deptId > 0) {
            $query->where('tickets.dept_id', $deptId);
        }

        if ($statusId > 0) {
            $query->where('tickets.status_id', $statusId);
        }

        if ($search !== '') {
            // নম্বর, বিষয় ও গ্রাহকের নাম/ইমেইল — একটিই সার্চ বাক্স
            $term = '%' . $search . '%';
            $query->whereGroup(static function (QueryBuilder $sub) use ($term): void {
                $sub->where('tickets.number', 'LIKE', $term)
                    ->orWhere('tickets.subject', 'LIKE', $term)
                    ->orWhere('users.name', 'LIKE', $term)
                    ->orWhere('users.email', 'LIKE', $term);
            });
        }

        $query->orderBy(self::SORTABLE[$sort] ?? self::SORTABLE['updated'], $direction);

        return $this->view('agent/tickets/index', [
            'tickets'     => $query->paginate($page, (int) setting('default_page_size', '25')),
            'filter'      => $filter,
            'search'      => $search,
            'sort'        => $sort,
            'direction'   => strtolower($direction),
            'deptId'      => $deptId,
            'statusId'    => $statusId,
            'departments' => $this->visibleDepartments(),
            'statuses'    => QueryBuilder::table('statuses')->orderBy('sort_order')->get(),
            'counts'      => $this->filterCounts(),
            'queryString' => array_filter([
                'filter' => $filter, 'q' => $search, 'dept' => $deptId ?: null,
                'status' => $statusId ?: null, 'sort' => $sort, 'dir' => strtolower($direction),
            ]),
        ], 'layouts/agent');
    }

    public function show(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));

        return $this->view('agent/tickets/show', [
            'ticket'      => $ticket,
            'threads'     => Ticket::threads((int) $ticket['id'], true),
            'attachments' => Ticket::attachmentsByThread((int) $ticket['id']),
            'transfers'   => Ticket::transfers((int) $ticket['id']),
            'statuses'    => QueryBuilder::table('statuses')->orderBy('sort_order')->get(),
            'priorities'  => QueryBuilder::table('priorities')->orderBy('level')->get(),
            'canned'      => $this->cannedFor((int) $ticket['dept_id']),
            'userTickets' => $this->otherTicketsOf((int) $ticket['user_id'], (int) $ticket['id']),
            'maxUpload'   => AttachmentService::humanLimit(),
        ], 'layouts/agent');
    }

    public function reply(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $this->authorize('ticket.reply', $ticket);

        Validator::make($request->all(), ['body' => 'required|max:50000'], ['body' => 'উত্তর'])->validate();

        $statusId = $request->integer('status_id') ?: null;
        if ($statusId !== null && !QueryBuilder::table('statuses')->where('id', $statusId)->exists()) {
            $statusId = null;
        }

        try {
            $full = Ticket::findById((int) $ticket['id']);
            $threadId = TicketService::agentReply($full, (int) Auth::agentId(), (string) $request->raw('body'), $statusId);
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return back('/agent/tickets/' . $ticket['id']);
        }

        $this->attachFiles($request, (int) $ticket['id'], $threadId);
        flash('success', 'উত্তর পাঠানো হয়েছে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function note(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $this->authorize('ticket.note', $ticket);

        Validator::make($request->all(), ['body' => 'required|max:50000'], ['body' => 'নোট'])->validate();

        try {
            $threadId = TicketService::addThread((int) $ticket['id'], [
                'type'     => 'note',
                'agent_id' => (int) Auth::agentId(),
                'body'     => (string) $request->raw('body'),
                'is_html'  => true,
            ]);
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return back('/agent/tickets/' . $ticket['id']);
        }

        $this->attachFiles($request, (int) $ticket['id'], $threadId);
        flash('success', 'ইন্টার্নাল নোট যোগ হয়েছে — গ্রাহক এটি দেখবেন না।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function claim(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));

        // নিজে নেওয়ার জন্য assign অনুমতি লাগে না — উত্তর দিতে পারলেই যথেষ্ট
        $this->authorize('ticket.reply', $ticket);

        TicketService::claim((int) $ticket['id'], (int) Auth::agentId());
        flash('success', 'টিকেটটি এখন আপনার দায়িত্বে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function changeStatus(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));

        $statusId = $request->integer('status_id');
        $status = QueryBuilder::table('statuses')->where('id', $statusId)->first();

        if ($status === null) {
            throw new HttpException(422, 'এই স্ট্যাটাসটি নেই।');
        }

        $permission = in_array($status['state'], ['resolved', 'closed'], true) ? 'ticket.close' : 'ticket.edit';
        if ($status['state'] === 'open' && in_array($ticket['status_state'], ['resolved', 'closed'], true)) {
            $permission = 'ticket.reopen';
        }
        $this->authorize($permission, $ticket);

        TicketService::setStatus((int) $ticket['id'], $statusId, (int) Auth::agentId());
        flash('success', 'স্ট্যাটাস পরিবর্তন হয়েছে: ' . $status['name']);

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function changePriority(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $this->authorize('ticket.change_priority', $ticket);

        $priorityId = $request->integer('priority_id');
        $priority = QueryBuilder::table('priorities')->where('id', $priorityId)->first();

        if ($priority === null) {
            throw new HttpException(422, 'এই প্রায়োরিটিটি নেই।');
        }

        QueryBuilder::table('tickets')->where('id', (int) $ticket['id'])
            ->update(['priority_id' => $priorityId, 'updated_at' => now()]);

        TicketService::addSystemNote((int) $ticket['id'], 'প্রায়োরিটি বদলে "' . $priority['name'] . '" করা হয়েছে।', (int) Auth::agentId());
        flash('success', 'প্রায়োরিটি হালনাগাদ হয়েছে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function createForm(Request $request): Response
    {
        $this->authorize('ticket.create');

        return $this->view('agent/tickets/create', [
            'departments' => $this->visibleDepartments(),
            'priorities'  => QueryBuilder::table('priorities')->orderBy('level')->get(),
            'topics'      => QueryBuilder::table('help_topics')->where('is_active', 1)->orderBy('sort_order')->get(),
        ], 'layouts/agent');
    }

    public function store(Request $request): Response
    {
        $this->authorize('ticket.create');

        Validator::make($request->all(), [
            'name'        => 'required|max:120',
            'email'       => 'required|email|max:190',
            'subject'     => 'required|max:250',
            'body'        => 'required|max:50000',
            'dept_id'     => 'required|integer|exists:departments,id',
            'priority_id' => 'integer|exists:priorities,id',
            'topic_id'    => 'integer|exists:help_topics,id',
        ], [
            'name' => 'গ্রাহকের নাম', 'email' => 'গ্রাহকের ইমেইল', 'subject' => 'বিষয়',
            'body' => 'বিবরণ', 'dept_id' => 'ডিপার্টমেন্ট',
        ])->validate();

        $email = mb_strtolower((string) $request->input('email'));
        $user = QueryBuilder::table('users')->where('email', $email)->whereNull('deleted_at')->first();

        $userId = $user !== null ? (int) $user['id'] : QueryBuilder::table('users')->insert([
            'uuid'       => Str::uuid(),
            'name'       => (string) $request->input('name'),
            'email'      => $email,
            'status'     => 'active',
            'created_at' => now(),
        ]);

        $ticket = TicketService::create([
            'user_id'             => $userId,
            'subject'             => (string) $request->input('subject'),
            'body'                => (string) $request->raw('body'),
            'dept_id'             => $request->integer('dept_id'),
            'priority_id'         => $request->integer('priority_id') ?: null,
            'topic_id'            => $request->integer('topic_id') ?: null,
            'source'              => 'agent',
            'created_by_agent_id' => (int) Auth::agentId(),
            'ip'                  => $request->ip(),
        ]);

        $this->attachFiles($request, (int) $ticket['id'], null);
        flash('success', 'টিকেট তৈরি হয়েছে: #' . $ticket['number']);

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    // ---------- সহায়ক ----------

    private function visibleTicket(int $id): array
    {
        $ticket = Ticket::detail($id);
        if ($ticket === null) {
            throw new HttpException(404, 'টিকেটটি খুঁজে পাওয়া যায়নি।');
        }

        if (!Auth::canSeeTicket($ticket)) {
            throw new HttpException(403, 'এই টিকেটটি দেখার অনুমতি আপনার নেই।');
        }

        return $ticket;
    }

    private function applyFilter(QueryBuilder $query, string $filter): void
    {
        $agentId = (int) Auth::agentId();

        match ($filter) {
            'mine'       => $query->where('tickets.assigned_agent_id', $agentId)
                                  ->whereIn('statuses.state', ['open', 'paused']),
            'unassigned' => $query->whereNull('tickets.assigned_agent_id')
                                  ->whereNull('tickets.assigned_team_id')
                                  ->whereIn('statuses.state', ['open']),
            'overdue'    => $query->where('tickets.due_at', '<', now())
                                  ->whereIn('statuses.state', ['open', 'paused']),
            'pending'    => $query->whereIn('statuses.state', ['paused']),
            'answered'   => $query->where('tickets.is_answered', 1)
                                  ->whereIn('statuses.state', ['open', 'paused']),
            'closed'     => $query->whereIn('statuses.state', ['resolved', 'closed']),
            'all'        => $query,
            default      => $query->whereIn('statuses.state', ['open', 'paused']),
        };
    }

    /** সাইড ট্যাবের সংখ্যা — প্রতিটি ফিল্টারে কয়টি টিকেট আছে। */
    private function filterCounts(): array
    {
        $counts = [];

        foreach (['open', 'mine', 'unassigned', 'overdue', 'pending', 'closed'] as $filter) {
            $query = Ticket::applyVisibility(Ticket::listQuery());
            $this->applyFilter($query, $filter);
            $counts[$filter] = $query->count('tickets.id');
        }

        return $counts;
    }

    private function visibleDepartments(): array
    {
        $query = QueryBuilder::table('departments')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('sort_order');

        if (!Auth::isAdmin() && !Auth::hasPermission('ticket.view_all')) {
            $query->whereIn('id', Auth::departmentIds());
        }

        return $query->get();
    }

    private function cannedFor(int $deptId): array
    {
        return QueryBuilder::table('canned_responses')
            ->select('id', 'title', 'body_html')
            ->where('is_active', 1)
            ->whereGroup(static function (QueryBuilder $sub) use ($deptId): void {
                $sub->whereNull('dept_id')->orWhere('dept_id', $deptId);
            })
            ->orderBy('title')
            ->get();
    }

    private function otherTicketsOf(int $userId, int $excludeId): array
    {
        return Ticket::listQuery()
            ->where('tickets.user_id', $userId)
            ->where('tickets.id', '!=', $excludeId)
            ->orderBy('tickets.created_at', 'DESC')
            ->limit(5)
            ->get();
    }

    private function attachFiles(Request $request, int $ticketId, ?int $threadId): void
    {
        $files = $request->fileList('attachments');
        if ($files === []) {
            return;
        }

        $threadId ??= (int) QueryBuilder::table('ticket_threads')
            ->where('ticket_id', $ticketId)
            ->orderBy('id', 'DESC')
            ->value('id');

        $result = AttachmentService::storeMany($files, $ticketId, $threadId, 'agent', (int) Auth::agentId());

        if ($result['errors'] !== []) {
            flash('warning', 'কিছু ফাইল যোগ করা যায়নি — ' . implode(' · ', $result['errors']));
        }
    }
}
