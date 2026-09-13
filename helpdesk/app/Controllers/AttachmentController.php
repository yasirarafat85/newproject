<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Ticket;
use App\Services\AttachmentService;

/**
 * অ্যাটাচমেন্ট ডাউনলোড।
 *
 * ফাইল ওয়েব রুটের বাইরে থাকে, তাই প্রতিটি ডাউনলোডেই অনুমতি যাচাই হয়।
 * URL-এ ক্রমিক id নয়, uuid ব্যবহার করা হয় যাতে অনুমান করে অন্যের
 * ফাইল চাওয়া না যায়।
 */
final class AttachmentController extends Controller
{
    public function download(Request $request): Response
    {
        $attachment = QueryBuilder::table('attachments')
            ->where('uuid', $request->paramString('uuid'))
            ->first();

        if ($attachment === null) {
            throw new HttpException(404, 'ফাইলটি খুঁজে পাওয়া যায়নি।');
        }

        $ticket = Ticket::findById((int) $attachment['ticket_id']);
        if ($ticket === null || !$this->mayAccess($ticket, $attachment)) {
            throw new HttpException(403, 'এই ফাইলটি দেখার অনুমতি আপনার নেই।');
        }

        $path = AttachmentService::absolutePath($attachment);
        if (!is_file($path)) {
            throw new HttpException(404, 'ফাইলটি সার্ভারে আর নেই।');
        }

        return Response::download($path, (string) $attachment['original_name'], (string) $attachment['mime_type']);
    }

    private function mayAccess(array $ticket, array $attachment): bool
    {
        if (Auth::isAgent()) {
            return Auth::canSeeTicket($ticket);
        }

        // ইন্টার্নাল নোটের সংযুক্তি গ্রাহক কখনো দেখবেন না
        if ($attachment['thread_id'] !== null) {
            $internal = QueryBuilder::table('ticket_threads')
                ->where('id', (int) $attachment['thread_id'])
                ->value('is_internal');

            if ((int) $internal === 1) {
                return false;
            }
        }

        if (Auth::isClient() && (int) $ticket['user_id'] === (int) Auth::clientId()) {
            return true;
        }

        return in_array($ticket['number'], (array) Session::get('_guest_tickets', []), true);
    }
}
