<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\Str;
use RuntimeException;

/**
 * টিকেটে অতিরিক্ত ব্যক্তি (CC) — যাঁরা মূল রিকোয়েস্টার নন, কিন্তু
 * অগ্রগতি জানতে চান।
 *
 * কোলাবোরেটররা গ্রাহকের দৃষ্টিকোণ থেকেই টিকেট দেখেন — ইন্টার্নাল নোট
 * তাঁদের কাছে কখনো যায় না।
 */
final class CollaboratorService
{
    public static function add(array $ticket, string $email, string $name, ?int $byAgentId): int
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('ইমেইল ঠিকানাটি সঠিক নয়।');
        }

        $ticketId = (int) $ticket['id'];
        $user = QueryBuilder::table('users')->where('email', $email)->whereNull('deleted_at')->first();

        if ($user !== null && (int) $user['id'] === (int) $ticket['user_id']) {
            throw new RuntimeException('ইনিই টিকেটের মূল গ্রাহক — আলাদা করে যোগ করার দরকার নেই।');
        }

        $userId = $user !== null ? (int) $user['id'] : QueryBuilder::table('users')->insert([
            'uuid'       => Str::uuid(),
            'name'       => trim($name) !== '' ? trim($name) : explode('@', $email)[0],
            'email'      => $email,
            'status'     => 'active',
            'created_at' => now(),
        ]);

        $existing = QueryBuilder::table('ticket_collaborators')
            ->where('ticket_id', $ticketId)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            if ((int) $existing['is_active'] === 1) {
                throw new RuntimeException('ইনি ইতিমধ্যে এই টিকেটে যুক্ত আছেন।');
            }

            // আগে সরানো হয়েছিল — আবার সক্রিয় করি, ইতিহাস নষ্ট না করে
            QueryBuilder::table('ticket_collaborators')
                ->where('id', (int) $existing['id'])
                ->update(['is_active' => 1, 'added_by_agent_id' => $byAgentId]);

            $collaboratorId = (int) $existing['id'];
        } else {
            $collaboratorId = QueryBuilder::table('ticket_collaborators')->insert([
                'ticket_id'         => $ticketId,
                'user_id'           => $userId,
                'type'              => 'cc',
                'added_by_agent_id' => $byAgentId,
                'is_active'         => 1,
                'created_at'        => now(),
            ]);
        }

        $displayName = QueryBuilder::table('users')->where('id', $userId)->value('name');
        TicketService::addSystemNote($ticketId, sprintf('%s (%s) কে অনুলিপিতে যুক্ত করা হয়েছে', (string) $displayName, $email), $byAgentId);
        AuditService::log('ticket.collaborator_added', 'ticket', $ticketId, $email);

        return $collaboratorId;
    }

    public static function remove(array $ticket, int $collaboratorId, ?int $byAgentId): void
    {
        $ticketId = (int) $ticket['id'];

        $row = QueryBuilder::table('ticket_collaborators')
            ->select('ticket_collaborators.id', 'users.name', 'users.email')
            ->join('users', 'users.id', '=', 'ticket_collaborators.user_id')
            ->where('ticket_collaborators.id', $collaboratorId)
            ->where('ticket_collaborators.ticket_id', $ticketId)
            ->first();

        if ($row === null) {
            throw new RuntimeException('এই কোলাবোরেটরকে পাওয়া যায়নি।');
        }

        // মুছে না ফেলে নিষ্ক্রিয় করি — কে কখন যুক্ত ছিলেন তার ইতিহাস থাকুক
        QueryBuilder::table('ticket_collaborators')->where('id', $collaboratorId)->update(['is_active' => 0]);

        TicketService::addSystemNote($ticketId, sprintf('%s কে অনুলিপি থেকে সরানো হয়েছে', (string) $row['name']), $byAgentId);
        AuditService::log('ticket.collaborator_removed', 'ticket', $ticketId, (string) $row['email']);
    }

    /** @return array<int, array> সক্রিয় কোলাবোরেটর */
    public static function listFor(int $ticketId): array
    {
        return QueryBuilder::table('ticket_collaborators')
            ->select('ticket_collaborators.id', 'ticket_collaborators.type', 'ticket_collaborators.created_at',
                'users.name', 'users.email')
            ->join('users', 'users.id', '=', 'ticket_collaborators.user_id')
            ->where('ticket_collaborators.ticket_id', $ticketId)
            ->where('ticket_collaborators.is_active', 1)
            ->orderBy('ticket_collaborators.created_at')
            ->get();
    }

    /** গ্রাহককে পাঠানো উত্তরে যে ঠিকানাগুলো অনুলিপিতে যাবে (P4)। */
    public static function ccAddresses(int $ticketId): array
    {
        return array_column(self::listFor($ticketId), 'email');
    }
}
