<?php
declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * হোয়াইটলিস্ট-ভিত্তিক HTML স্যানিটাইজার।
 *
 * গ্রাহক ও এজেন্ট দুজনেই রিচ-টেক্সট পাঠাতে পারেন, আর ইমেইল থেকেও
 * যেকোনো HTML আসতে পারে — তাই সংরক্ষণের আগে সবকিছু এখান দিয়ে যায়।
 *
 * নীতি: যা স্পষ্টভাবে অনুমোদিত নয়, তা বাদ। ট্যাগ বাদ গেলেও ভেতরের
 * লেখা থেকে যায় (script/style ছাড়া, সেগুলো পুরোপুরি মুছে যায়)।
 *
 * Composer থাকলে HTMLPurifier-এ বদলানো যাবে; ইন্টারফেস একই।
 */
final class Sanitizer
{
    /** @var array<string, string[]> ট্যাগ => অনুমোদিত অ্যাট্রিবিউট */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'strike' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'span' => [], 'div' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** এই ট্যাগগুলো ভেতরের লেখাসহ সম্পূর্ণ মুছে যায়। */
    private const STRIP_ENTIRELY = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta', 'base', 'svg', 'math'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        // মেটা চার্সেট ছাড়া libxml বাইটগুলোকে ISO-8859-1 ধরে নেয় আর বাংলা ভেঙে যায়
        $wrapped = '<?xml encoding="UTF-8"?><div id="hd-root">' . $html . '</div>';

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            // পার্স করা না গেলে নিরাপদ পথ: সব ট্যাগ ফেলে দিই
            return nl2br(e(strip_tags($html)));
        }

        $root = $document->getElementById('hd-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::cleanNode($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    /** প্লেইন টেক্সটকে নিরাপদ HTML-এ — গ্রাহকের সরল টেক্সটএরিয়ার জন্য। */
    public static function fromPlainText(string $text): string
    {
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $html .= '<p>' . nl2br(e($paragraph), false) . '</p>';
        }

        return $html;
    }

    private static function cleanNode(DOMNode $node): void
    {
        // পেছন থেকে ঘুরি, কারণ চলার পথে সন্তান সরানো হতে পারে
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child === null) {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;   // টেক্সট নোড — saveHTML নিজেই এস্কেপ করবে
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::STRIP_ENTIRELY, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!isset(self::ALLOWED[$tag])) {
                self::cleanNode($child);
                self::unwrap($child);   // ট্যাগ বাদ, ভেতরের লেখা থাকুক
                continue;
            }

            self::cleanAttributes($child, $tag);
            self::cleanNode($child);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
            $attribute = $element->attributes->item($i);
            if ($attribute === null) {
                continue;
            }

            $name = strtolower($attribute->nodeName);

            // on* হ্যান্ডলার ও style সবসময় বাদ — style দিয়ে ক্লিকজ্যাকিং সম্ভব
            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if (($name === 'href' || $name === 'src') && !self::isSafeUrl($attribute->nodeValue ?? '', $name)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            // বাইরের লিংক নতুন ট্যাবে, কিন্তু window.opener ছাড়া
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }

        if ($tag === 'a' && !$element->hasAttribute('href')) {
            self::unwrap($element);
        }
    }

    private static function isSafeUrl(string $url, string $attribute): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        // কন্ট্রোল ক্যারেক্টার দিয়ে "java\0script:" ধাঁচের ফাঁকি ঠেকাই
        $normalised = strtolower(preg_replace('/[\x00-\x20]/', '', $url) ?? '');

        foreach (['javascript:', 'vbscript:', 'file:', 'about:'] as $scheme) {
            if (str_starts_with($normalised, $scheme)) {
                return false;
            }
        }

        if (str_starts_with($normalised, 'data:')) {
            // ইমেইলের ইনলাইন ছবি কাজে লাগে; data: দিয়ে SVG/HTML চালানো যাবে না
            return $attribute === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $url) === 1;
        }

        return str_starts_with($normalised, 'http://')
            || str_starts_with($normalised, 'https://')
            || str_starts_with($normalised, 'mailto:')
            || str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || preg_match('#^[a-z0-9._-]+(/|$)#i', $url) === 1;   // আপেক্ষিক পথ
    }

    /** ট্যাগ সরিয়ে তার সন্তানদের সরাসরি প্যারেন্টে বসায়। */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
