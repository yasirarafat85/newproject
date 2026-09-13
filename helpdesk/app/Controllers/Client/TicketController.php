<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Validator;
use App\Models\Ticket;
use App\Services\AttachmentService;
use App\Services\TicketService;
use RuntimeException;

/**
 * গ্রাহকের টিকেট — তৈরি, তালিকা, বিস্তারিত ও উত্তর।
 *
 * লগইন ছাড়াও টিকেট খোলা যায় (অ্যাডমিন চাইলে বন্ধ করতে পারেন); তখন
 * ইমেইল + টিকেট নম্বর দিয়ে অবস্থা দেখা যায়।
 */
final class TicketController extends Controller
{
    public function create(Request $request): Response
    {
        if (!Auth::isClient() && !setting('allow_guest_tickets', '1')) {
            flash('warning', 'টিকেট খুলতে আগে লগইন করুন।');

            return $this->redirect('/login');
        }

        return $this->view('client/tickets/create', [
            'topics'      => $this->publicTopics(),
            'selectedTopic' => $request->integer('topic'),
            'maxUpload'   => AttachmentService::humanLimit(),
        ], 'layouts/client');
    }

    public function store(Request $request): Response
    {
        $isGuest = !Auth::isClient();

        if ($isGuest && !setting('allow_guest_tickets', '1')) {
            throw new HttpException(403, 'অ্যাকাউন্ট ছাড়া টিকেট খোলা বন্ধ আছে।');
        }

        $rules = [
            'subject'  => 'required|max:250',
            'body'     => 'required|max:20000',
            'topic_id' => 'integer|exists:help_topics,id',
        ];
        $labels = ['subject' => 'বিষয়', 'body' => 'বিবরণ', 'topic_id' => 'বিষয়ের ধরন', 'name' => 'নাম', 'email' => 'ইমেইল'];

        if ($isGuest) {
            $rules['name'] = 'required|max:120';
            $rules['email'] = 'required|email|max:190';
        }

        Validator::make($request->all(), $rules, $labels)->validate();

        $userId = $isGuest
            ? $this->findOrCreateGuest((string) $request->input('name'), (string) $request->input('email'))
            : (int) Auth::clientId();

        try {
            $ticket = TicketService::create([
                'user_id'     => $userId,
                'subject'     => (string) $request->input('subject'),
                'body'        => (string) $request->raw('body'),
                'topic_id'    => $request->integer('topic_id') ?: null,
                'source'      => 'web',
                'ip'          => $request->ip(),
            ]);
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return back('/tickets/new');
        }

        $this->attachFiles($request, (int) $ticket['id'], $isGuest ? 'user' : 'user', $userId);

        flash('success', 'আপনার টিকেট তৈরি হয়েছে। টিকেট নম্বর: ' . $ticket['number']);

        if ($isGuest) {
            // গেস্টকে সরাসরি দেখানোর জন্য সেশনে অনুমতি রাখি, লিংক শেয়ার হলেও নিরাপদ
            Session::put('_guest_tickets', array_unique(array_merge(
                (array) Session::get('_guest_tickets', []),
                [$ticket['number']]
            )));
        }

        return $this->redirect('/tickets/' . $ticket['number']);
    }

    public function index(Request $request): Response
    {
        $page = max(1, $request->integer('page', 1));

        $tickets = Ticket::listQuery()
            ->where('tickets.user_id', (int) Auth::clientId())
            ->orderBy('tickets.last_message_at', 'DESC')
            ->orderBy('tickets.id', 'DESC')
            ->paginate($page, 15);

        return $this->view('client/tickets/index', [
            'tickets' => $tickets,
        ], 'layouts/client');
    }

    public function show(Request $request): Response
    {
        $ticket = $this->authorisedTicket($request->paramString('number'));

        return $this->view('client/tickets/show', [
            'ticket'      => $ticket,
            'threads'     => Ticket::threads((int) $ticket['id'], false),
            'attachments' => Ticket::attachmentsByThread((int) $ticket['id']),
            'canReply'    => TicketService::canReopen($ticket),
            'maxUpload'   => AttachmentService::humanLimit(),
        ], 'layouts/client');
    }

    public function reply(Request $request): Response
    {
        $ticket = $this->authorisedTicket($request->paramString('number'));

        Validator::make($request->all(), ['body' => 'required|max:20000'], ['body' => 'বার্তা'])->validate();

        if (!TicketService::canReopen($ticket)) {
            flash('warning', 'এই টিকেটটি বন্ধ হয়ে গেছে। নতুন একটি টিকেট খুলুন।');

            return $this->redirect('/tickets/' . $ticket['number']);
        }

        $full = Ticket::findById((int) $ticket['id']);
        $threadId = TicketService::clientReply($full, (int) $full['user_id'], (string) $request->raw('body'), $request->ip());

        $this->attachFiles($request, (int) $ticket['id'], 'user', (int) $full['user_id'], $threadId);

        flash('success', 'আপনার উত্তর যোগ হয়েছে।');

        return $this->redirect('/tickets/' . $ticket['number']);
    }

    /** গেস্টের জন্য: ইমেইল + টিকেট নম্বর দিয়ে অবস্থা দেখা। */
    public function checkForm(Request $request): Response
    {
        return $this->view('client/tickets/check', [], 'layouts/client');
    }

    public function check(Request $request): Response
    {
        Validator::make($request->all(), [
            'number' => 'required|max:20',
            'email'  => 'required|email|max:190',
        ], ['number' => 'টিকেট নম্বর', 'email' => 'ইমেইল'])->validate();

        $number = trim((string) $request->input('number'), " \t#");
        $email = mb_strtolower((string) $request->input('email'));

        $ticket = QueryBuilder::table('tickets')
            ->select('tickets.number')
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->where('tickets.number', $number)
            ->where('users.email', $email)
            ->whereNull('tickets.deleted_at')
            ->first();

        if ($ticket === null) {
            // কোনটি ভুল তা বলি না — নম্বর অনুমান করে তথ্য বের করা ঠেকাতে
            flash('danger', 'এই টিকেট নম্বর ও ইমেইলের সঙ্গে মেলে এমন কিছু পাওয়া যায়নি।');

            return back('/tickets/check');
        }

        Session::put('_guest_tickets', array_unique(array_merge(
            (array) Session::get('_guest_tickets', []),
            [$ticket['number']]
        )));

        return $this->redirect('/tickets/' . $ticket['number']);
    }

    // ---------- সহায়ক ----------

    /**
     * টিকেটটি এই দর্শকের দেখার অধিকার আছে কি না।
     * লগইন করা গ্রাহক → নিজের টিকেট। গেস্ট → এই সেশনে যাচাই হওয়া টিকেট।
     */
    private function authorisedTicket(string $number): array
    {
        $ticket = Ticket::findByNumber($number);
        if ($ticket === null) {
            throw new HttpException(404, 'টিকেটটি খুঁজে পাওয়া যায়নি।');
        }

        if (Auth::isClient() && (int) $ticket['user_id'] === (int) Auth::clientId()) {
            return Ticket::detail((int) $ticket['id']);
        }

        $allowed = (array) Session::get('_guest_tickets', []);
        if (in_array($ticket['number'], $allowed, true)) {
            return Ticket::detail((int) $ticket['id']);
        }

        throw new HttpException(403, 'এই টিকেটটি দেখার অনুমতি আপনার নেই।');
    }

    private function findOrCreateGuest(string $name, string $email): int
    {
        $email = mb_strtolower($email);
        $existing = QueryBuilder::table('users')->where('email', $email)->whereNull('deleted_at')->first();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return QueryBuilder::table('users')->insert([
            'uuid'       => Str::uuid(),
            'org_id'     => AuthController::matchOrganisation($email),
            'name'       => $name,
            'email'      => $email,
            'status'     => 'active',
            'created_at' => now(),
        ]);
    }

    private function attachFiles(Request $request, int $ticketId, string $uploaderType, int $uploaderId, ?int $threadId = null): void
    {
        $files = $request->fileList('attachments');
        if ($files === []) {
            return;
        }

        $threadId ??= (int) QueryBuilder::table('ticket_threads')
            ->where('ticket_id', $ticketId)
            ->orderBy('id', 'DESC')
            ->value('id');

        $result = AttachmentService::storeMany($files, $ticketId, $threadId, $uploaderType, $uploaderId);

        if ($result['errors'] !== []) {
            flash('warning', 'কিছু ফাইল যোগ করা যায়নি — ' . implode(' · ', $result['errors']));
        }
    }

    private function publicTopics(): array
    {
        return QueryBuilder::table('help_topics')
            ->select('id', 'name')
            ->where('is_public', 1)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();
    }
}
