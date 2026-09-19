<?php
declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Ticket;
use App\Services\CollaboratorService;
use App\Services\ExternalForwardService;
use App\Services\TransferService;
use RuntimeException;

/**
 * টিকেট হস্তান্তর, বাইরে ফরওয়ার্ড ও অনুলিপি (CC)।
 *
 * প্রতিটি অ্যাকশন আগে টিকেটটি দেখার অধিকার, তারপর নির্দিষ্ট কাজের
 * অনুমতি যাচাই করে — দুটো আলাদা প্রশ্ন।
 */
final class TransferController extends Controller
{
    public function transfer(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $target = (string) $request->input('target');

        $reason = mb_substr((string) $request->input('reason', ''), 0, 250);
        $byAgentId = (int) Auth::agentId();

        try {
            match ($target) {
                'department' => $this->toDepartment($ticket, $request, $byAgentId, $reason),
                'agent'      => $this->toAgent($ticket, $request, $byAgentId, $reason),
                'team'       => $this->toTeam($ticket, $request, $byAgentId, $reason),
                'release'    => $this->release($ticket, $byAgentId, $reason),
                default      => throw new RuntimeException('হস্তান্তরের ধরনটি বোঝা গেল না।'),
            };
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return $this->redirect('/agent/tickets/' . $ticket['id']);
        }

        flash('success', 'টিকেটটি স্থানান্তর করা হয়েছে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    private function toDepartment(array $ticket, Request $request, int $byAgentId, string $reason): void
    {
        $this->authorize('ticket.transfer', $ticket);

        $slaAction = (string) $request->input('sla_action', TransferService::SLA_KEEP);
        if (!in_array($slaAction, [TransferService::SLA_KEEP, TransferService::SLA_RESET, TransferService::SLA_EXTEND], true)) {
            $slaAction = TransferService::SLA_KEEP;
        }

        TransferService::toDepartment($ticket, $request->integer('dept_id'), $byAgentId, $reason, $slaAction);
    }

    private function toAgent(array $ticket, Request $request, int $byAgentId, string $reason): void
    {
        $this->authorize('ticket.assign', $ticket);
        TransferService::toAgent($ticket, $request->integer('agent_id'), $byAgentId, $reason);
    }

    private function toTeam(array $ticket, Request $request, int $byAgentId, string $reason): void
    {
        $this->authorize('ticket.assign', $ticket);
        TransferService::toTeam($ticket, $request->integer('team_id'), $byAgentId, $reason);
    }

    private function release(array $ticket, int $byAgentId, string $reason): void
    {
        // নিজের টিকেট ছেড়ে দিতে assign অনুমতি লাগে না; অন্যেরটা ছাড়তে লাগে
        if ((int) ($ticket['assigned_agent_id'] ?? 0) !== $byAgentId) {
            $this->authorize('ticket.assign', $ticket);
        } else {
            $this->authorize('ticket.reply', $ticket);
        }

        TransferService::release($ticket, $byAgentId, $reason);
    }

    public function forward(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $this->authorize('ticket.forward_external', $ticket);

        Validator::make($request->all(), [
            'to_email' => 'required|email|max:190',
            'body'     => 'required|max:20000',
        ], ['to_email' => 'প্রাপকের ইমেইল', 'body' => 'বার্তা'])->validate();

        $cc = array_filter(array_map('trim', explode(',', (string) $request->input('cc_emails', ''))));

        try {
            ExternalForwardService::forward(
                $ticket,
                (int) Auth::agentId(),
                (string) $request->input('to_email'),
                (string) $request->raw('body'),
                $cc,
                $request->boolean('include_history'),
                $request->boolean('include_attachments'),
            );
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return $this->redirect('/agent/tickets/' . $ticket['id']);
        }

        flash('success', 'ফরওয়ার্ডটি পাঠানোর জন্য কিউতে রাখা হয়েছে। উত্তর এলে তা এই টিকেটে ইন্টার্নাল নোট হিসেবে যুক্ত হবে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function addCollaborator(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $this->authorize('ticket.edit', $ticket);

        Validator::make($request->all(), [
            'email' => 'required|email|max:190',
            'name'  => 'max:120',
        ], ['email' => 'ইমেইল', 'name' => 'নাম'])->validate();

        try {
            CollaboratorService::add(
                $ticket,
                (string) $request->input('email'),
                (string) $request->input('name', ''),
                (int) Auth::agentId()
            );
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return $this->redirect('/agent/tickets/' . $ticket['id']);
        }

        flash('success', 'অনুলিপিতে যুক্ত করা হয়েছে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

    public function removeCollaborator(Request $request): Response
    {
        $ticket = $this->visibleTicket($request->paramInt('id'));
        $this->authorize('ticket.edit', $ticket);

        try {
            CollaboratorService::remove($ticket, $request->paramInt('collaborator'), (int) Auth::agentId());
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());

            return $this->redirect('/agent/tickets/' . $ticket['id']);
        }

        flash('success', 'অনুলিপি থেকে সরানো হয়েছে।');

        return $this->redirect('/agent/tickets/' . $ticket['id']);
    }

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
}
